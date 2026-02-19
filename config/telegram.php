<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use SergiX44\Nutgram\Nutgram;

use SergiX44\Nutgram\Conversations\Conversation;

Conversation::refreshOnDeserialize();

$bot->onCommand('start', function (Nutgram $bot) {
    return $bot->sendMessage('Hello, world!');
})->description('The start command!');