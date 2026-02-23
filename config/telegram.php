<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Conversation\AddBotConversation;

use App\Telegram\Exception\QueryTooOldException;
use SergiX44\Nutgram\Conversations\Conversation;
use App\Telegram\Command\StartCommand;
use SergiX44\Nutgram\Nutgram;

Conversation::refreshOnDeserialize();

$bot->registerApiException(QueryTooOldException::class);

$bot->registerCommand(StartCommand::class);

$bot->onCallbackQueryData('add_bot', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    $bot->deleteMessage($bot->chatId(), $bot->messageId());
    AddBotConversation::begin($bot);
});
