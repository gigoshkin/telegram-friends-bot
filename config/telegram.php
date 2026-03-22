<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Conversation\AddBotConversation;
use App\Telegram\Exception\QueryTooOldException;
use App\Telegram\Handler\SelectTargetHandler;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Conversations\Conversation;
use App\Telegram\Command\StartCommand;
use SergiX44\Nutgram\Nutgram;

Conversation::refreshOnDeserialize();

$bot->onException(function (Nutgram $bot, Throwable $e) {
    $logger = $bot->getContainer()?->get(LoggerInterface::class);
    if ($logger) {
        $logger->error('Nutgram unhandled exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'update_id' => $bot->update()?->updateId ?? 'unknown',
            'class' => get_class($e),
            'previous' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
        ]);
    } else {
        error_log("Nutgram exception: " . $e->__toString());
    }
});

$bot->registerApiException(QueryTooOldException::class);

$bot->registerCommand(StartCommand::class);

$bot->onCallbackQueryData('add_bot', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    AddBotConversation::begin($bot);
});

$bot->onCallbackQueryData('select_target:.*', SelectTargetHandler::class);
