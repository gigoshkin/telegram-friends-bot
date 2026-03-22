<?php

namespace App\Telegram\Command;

use App\Telegram\Handler\ExportsMenuHandler;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeAllPrivateChats;

class ExportsCommand extends Command
{
    protected string $command = 'exports';
    protected ?string $description = 'Manage your uploaded chat exports';
    protected array $scopes = [BotCommandScopeAllPrivateChats::class];

    public function handle(Nutgram $bot): void
    {
        if ($bot->chat()->type !== ChatType::PRIVATE) {
            $bot->sendMessage('Manage your exports in private chat.');
            return;
        }

        $bot->getContainer()->get(ExportsMenuHandler::class)($bot);
    }
}