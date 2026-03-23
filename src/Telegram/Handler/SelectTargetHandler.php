<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use App\Entity\ChatMessage;
use App\Repository\ChatMessageRepository;
use App\Service\BotTrainer\BotTrainerInterface;
use App\Service\BotWebhookRegistrar;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

readonly class SelectTargetHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private BotTrainerInterface    $trainer,
        private BotWebhookRegistrar    $webhookRegistrar,
    )
    {
    }

    public function __invoke(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        $data = $bot->callbackQuery()?->data ?? '';
        // format: select_target:{botId}:{fromId}
        $parts = explode(':', $data, 3);
        if (count($parts) !== 3) {
            return;
        }

        [, $botId, $fromId] = $parts;

        /** @var Bot|null $entity */
        $entity = $this->em->getRepository(Bot::class)->find((int)$botId);
        if (!$entity) {
            return;
        }

        $entity->setTargetFromId($fromId);
        $entity->setIsBeingTrained(false);
        $this->em->flush();

        $this->trainer->train($entity);

        $this->webhookRegistrar->register($entity);

        $entity->setIsTrained(true);
        $this->em->flush();

        /** @var ChatMessageRepository $repo */
        $repo = $this->em->getRepository(ChatMessage::class);
        $senderName = $repo->findSenderName($entity->getChatExportFile(), $fromId) ?? $fromId;

        $bot->editMessageText(
            "✅ Done\\! Your bot is now imitating *{$senderName}*\\.\n\nAdd it to a group chat and watch it go\\! 🎭",
            parse_mode: ParseMode::MARKDOWN,
        );
    }
}
