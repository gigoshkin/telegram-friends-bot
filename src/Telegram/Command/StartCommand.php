<?php

namespace App\Telegram\Command;

use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeAllPrivateChats;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class StartCommand extends Command
{
    protected string $command = 'start';

    protected array $scopes = [
        BotCommandScopeAllPrivateChats::class
    ];

    public function handle(Nutgram $bot): void
    {
        if ($bot->chat()->type !== ChatType::PRIVATE) {
            $bot->sendMessage('To configure bots, message me in private chat.');
        }

        $bot->sendMessage(
            "👋 Hi! I help you create fun AI clones of your friends in group chats!\n\n" .
            "Here's how it works:\n" .
            "1. Create a clone of a friend\n" .
            "2. Add it to a group chat\n" .
            "3. Watch me impersonate them 🎭\n\n" .
            "Press the button below to get started 👇",
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🤖 Add Bot', callback_data: 'add_bot'))
                ->addRow(InlineKeyboardButton::make('📋 My Bots', callback_data: 'bots_menu'))
        );
    }
}
