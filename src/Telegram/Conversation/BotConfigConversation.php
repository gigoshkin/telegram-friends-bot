<?php

namespace App\Telegram\Conversation;

use App\Entity\Bot;
use App\Telegram\Handler\BotDetailHandler;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

class BotConfigConversation extends Conversation
{
    protected ?int    $botId = null;
    protected ?string $field = null;

    private static array $fields = [
        'responseProbability' => [
            'label'  => 'Response probability',
            'prompt' => 'Enter response probability (0–100):',
            'min'    => 0,
            'max'    => 100,
        ],
        'minSimilarity' => [
            'label'  => 'Min similarity (trigram)',
            'prompt' => 'Enter minimum trigram similarity (0–100):',
            'min'    => 0,
            'max'    => 100,
        ],
        'directResponseProbability' => [
            'label'  => 'Direct reply probability',
            'prompt' => 'Enter probability of replying directly to the message vs sending to chat (0–100):',
            'min'    => 0,
            'max'    => 100,
        ],
        'sequentialWeight' => [
            'label'  => 'Sequential pair weight',
            'prompt' => 'Enter probability of picking a sequential pair over a direct reply pair (0–100):',
            'min'    => 0,
            'max'    => 100,
        ],
        'matchLimit' => [
            'label'  => 'Match limit',
            'prompt' => 'Enter number of top candidates to consider (1–50):',
            'min'    => 1,
            'max'    => 50,
            'raw'    => true,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BotDetailHandler $detailHandler,
    ) {
    }

    public function start(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        // callback data format: bot_config:{botId}:{field}
        $parts = explode(':', $bot->callbackQuery()?->data ?? '', 3);
        if (count($parts) !== 3) {
            $this->end();
            return;
        }

        [, $botId, $field] = $parts;

        /** @var Bot|null $entity */
        $entity = $this->em->getRepository(Bot::class)->find((int) $botId);
        if (!$entity || !isset(self::$fields[$field])
            || $entity->getOwner()->getTelegramId() !== (string)$bot->userId()
        ) {
            $this->end();
            return;
        }

        $this->botId = (int) $botId;
        $this->field = $field;

        $meta    = self::$fields[$field];
        $isRaw   = $meta['raw'] ?? false;
        $current = $isRaw ? $this->currentRaw($entity, $field) : $this->currentPct($entity, $field);
        $suffix  = $isRaw ? '' : '%';

        $bot->sendMessage(
            "{$meta['prompt']}\nCurrent value: <b>{$current}{$suffix}</b>",
            parse_mode: 'HTML',
        );

        $this->next('receiveValue');
    }

    public function receiveValue(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if (!is_numeric($text)) {
            $bot->sendMessage('Please send a number (e.g. 75).');
            return;
        }

        $value = (float) $text;
        $meta  = self::$fields[$this->field];

        if ($value < $meta['min'] || $value > $meta['max']) {
            $bot->sendMessage("Value must be between {$meta['min']} and {$meta['max']}.");
            return;
        }

        /** @var Bot|null $entity */
        $entity = $this->em->getRepository(Bot::class)->find($this->botId);
        if (!$entity) {
            $bot->sendMessage('Bot not found.');
            $this->end();
            return;
        }

        $isRaw = $meta['raw'] ?? false;

        match ($this->field) {
            'responseProbability'       => $entity->setResponseProbability($value / 100),
            'minSimilarity'             => $entity->setMinSimilarity($value / 100),
            'directResponseProbability' => $entity->setDirectResponseProbability($value / 100),
            'sequentialWeight'          => $entity->setSequentialWeight($value / 100),
            'matchLimit'                => $entity->setMatchLimit((int) $value),
        };

        $this->em->flush();

        $suffix  = $isRaw ? '' : '%';
        $bot->sendMessage(
            "✅ {$meta['label']} updated to <b>{$value}{$suffix}</b>",
            parse_mode: 'HTML',
            reply_markup: $this->detailHandler->buildKeyboard($entity),
        );

        $this->end();
    }

    private function currentPct(Bot $entity, string $field): int
    {
        return match ($field) {
            'responseProbability'       => (int) round($entity->getResponseProbability() * 100),
            'minSimilarity'             => (int) round($entity->getMinSimilarity() * 100),
            'directResponseProbability' => (int) round($entity->getDirectResponseProbability() * 100),
            'sequentialWeight'          => (int) round($entity->getSequentialWeight() * 100),
            default                     => 0,
        };
    }

    private function currentRaw(Bot $entity, string $field): int
    {
        return match ($field) {
            'matchLimit' => $entity->getMatchLimit(),
            default      => 0,
        };
    }
}
