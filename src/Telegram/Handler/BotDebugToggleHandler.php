<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;

readonly class BotDebugToggleHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private BotDetailHandler $detailHandler,
    ) {
    }

    public function __invoke(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        $data  = $bot->callbackQuery()?->data ?? '';
        $botId = (int) explode(':', $data, 2)[1];

        /** @var Bot|null $entity */
        $entity = $this->em->getRepository(Bot::class)->find($botId);

        if (!$entity || $entity->getOwner()->getTelegramId() !== (string) $bot->userId()) {
            $bot->editMessageText('Bot not found.');
            return;
        }

        $entity->setDebugMode(!$entity->isDebugMode());
        $this->em->flush();

        $bot->editMessageText(
            $this->detailHandler->buildText($entity),
            reply_markup: $this->detailHandler->buildKeyboard($entity),
            parse_mode: 'HTML',
        );
    }
}