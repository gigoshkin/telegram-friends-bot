<?php

namespace App\Telegram\Command;

use App\Telegram\Handler\BotsMenuHandler;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeAllPrivateChats;

class BotsCommand extends Command
{
    protected string $command = 'bots';

    protected array $scopes = [BotCommandScopeAllPrivateChats::class];

    public function handle(Nutgram $bot): void
    {
        if ($bot->chat()->type !== ChatType::PRIVATE) {
            $bot->sendMessage('Manage your bots in private chat.');
            return;
        }

        $handler = $bot->getContainer()->get(BotsMenuHandler::class);
        $handler($bot);
    }
}
