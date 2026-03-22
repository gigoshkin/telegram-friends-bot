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
            'label'   => 'Response probability',
            'prompt'  => 'Enter response probability (0–100):',
            'unit'    => '%',
            'min'     => 0,
            'max'     => 100,
        ],
        'minSimilarity' => [
            'label'   => 'Min similarity (trigram)',
            'prompt'  => 'Enter minimum trigram similarity (0–100):',
            'unit'    => '%',
            'min'     => 0,
            'max'     => 100,
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
        $current = $this->currentPct($entity, $field);

        $bot->sendMessage(
            "{$meta['prompt']}\nCurrent value: <b>{$current}%</b>",
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

        match ($this->field) {
            'responseProbability' => $entity->setResponseProbability($value / 100),
            'minSimilarity'       => $entity->setMinSimilarity($value / 100),
        };

        $this->em->flush();

        $bot->sendMessage(
            "✅ {$meta['label']} updated to <b>{$value}%</b>",
            parse_mode: 'HTML',
            reply_markup: $this->detailHandler->buildKeyboard($entity),
        );

        $this->end();
    }

    private function currentPct(Bot $entity, string $field): int
    {
        return match ($field) {
            'responseProbability' => (int) round($entity->getResponseProbability() * 100),
            'minSimilarity'       => (int) round($entity->getMinSimilarity() * 100),
            default               => 0,
        };
    }
}
