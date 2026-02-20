<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use SergiX44\Nutgram\Nutgram;

use SergiX44\Nutgram\Conversations\Conversation;
use App\Telegram\Command\StartCommand;

Conversation::refreshOnDeserialize();

$bot->registerCommand( StartCommand::class );