<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use App\Service\BotInfoService;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

readonly class BotDeleteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private BotInfoService $botInfoService,
    ) {
    }

    public function __invoke(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        $data  = $bot->callbackQuery()?->data ?? '';
        $botId = (int) explode(':', $data, 2)[1];

        /** @var Bot|null $entity */
        $entity = $this->em->getRepository(Bot::class)->find($botId);

        if (!$entity || $entity->getOwner()->getTelegramId() !== (string)$bot->userId()) {
            $bot->editMessageText('Bot not found.');
            return;
        }

        $name = $this->botInfoService->getDisplayName($entity);

        $bot->editMessageText(
            "⚠️ Delete <b>{$name}</b>?\n\nThis cannot be undone.",
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('✅ Yes, delete', callback_data: "bot_delete_confirm:{$botId}"),
                InlineKeyboardButton::make('✖ Cancel', callback_data: "bot_menu:{$botId}"),
            ),
            parse_mode: 'HTML',
        );
    }
}
