<?php

namespace App\Telegram\Handler;

use App\Repository\BotRepository;
use App\Service\BotInfoService;
use App\Service\TelegramUserProvider;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

readonly class BotsMenuHandler
{
    public function __construct(
        private TelegramUserProvider $userProvider,
        private BotRepository        $botRepository,
        private BotInfoService       $botInfoService,
    )
    {
    }

    public function __invoke(Nutgram $bot): void
    {
        if ($bot->callbackQuery()) {
            $bot->answerCallbackQuery();
        }

        $user = $this->userProvider->getOrCreate($bot->userId());
        $bots = $this->botRepository->findByOwner($user);

        if (empty($bots)) {
            $keyboard = InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('🤖 Add Bot', callback_data: 'add_bot'),
            );
            $this->reply($bot, "You don't have any bots yet.", $keyboard);
            return;
        }

        $keyboard = InlineKeyboardMarkup::make();
        foreach ($bots as $entity) {
            $label = $this->botInfoService->getDisplayName($entity) . ' · #' . $entity->getId();
            $keyboard->addRow(
                InlineKeyboardButton::make($label, callback_data: 'bot_menu:' . $entity->getId()),
            );
        }
        $keyboard->addRow(InlineKeyboardButton::make('➕ Add new bot', callback_data: 'add_bot'));

        $this->reply($bot, 'Your bots:', $keyboard);
    }

    private function reply(Nutgram $bot, string $text, InlineKeyboardMarkup $keyboard): void
    {
        if ($bot->callbackQuery()) {
            $bot->editMessageText($text, reply_markup: $keyboard);
        } else {
            $bot->sendMessage($text, reply_markup: $keyboard);
        }
    }
}
