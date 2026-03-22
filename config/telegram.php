<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Command\BotsCommand;
use App\Telegram\Command\StartCommand;
use App\Telegram\Conversation\AddBotConversation;
use App\Telegram\Conversation\BotConfigConversation;
use App\Telegram\Exception\QueryTooOldException;
use App\Telegram\Handler\BotDebugToggleHandler;
use App\Telegram\Handler\BotDeleteConfirmHandler;
use App\Telegram\Handler\BotDeleteHandler;
use App\Telegram\Handler\BotDetailHandler;
use App\Telegram\Handler\BotsMenuHandler;
use App\Telegram\Handler\SelectTargetHandler;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Conversations\Conversation;
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
$bot->registerCommand(BotsCommand::class);

$bot->onCallbackQueryData('add_bot', function (Nutgram $bot) {
    $bot->answerCallbackQuery();
    AddBotConversation::begin($bot);
});

$bot->onCallbackQueryData('bots_menu', BotsMenuHandler::class);
$bot->onCallbackQueryData('bot_menu:.*', BotDetailHandler::class);
$bot->onCallbackQueryData('bot_delete:.*', BotDeleteHandler::class);
$bot->onCallbackQueryData('bot_delete_confirm:.*', BotDeleteConfirmHandler::class);
$bot->onCallbackQueryData('bot_config:.*', BotConfigConversation::class);
$bot->onCallbackQueryData('bot_debug_toggle:.*', BotDebugToggleHandler::class);

$bot->onCallbackQueryData('select_target:.*', SelectTargetHandler::class);
