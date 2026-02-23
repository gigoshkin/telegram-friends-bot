<?php

namespace App\MessageHandler;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Message\ProcessChatExportMessage;
use App\Service\BotTrainer\BotTrainerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Throwable;

#[AsMessageHandler]
final readonly class ProcessChatExportMessageHandler
{
    public function __construct(
        private BotTrainerInterface    $botTrainer,
        private EntityManagerInterface $em,
        private Nutgram                $nutgram,
        private LoggerInterface        $logger
    )
    {
    }

    public function __invoke(ProcessChatExportMessage $message): void
    {
        $botId = $message->getBotId();
        $bot = $this->em->getRepository(Bot::class)->find($botId);

        if (!$bot) {
            $bot->setIsBeingTrained(false);
            $this->em->flush();
            throw new UnrecoverableMessageHandlingException(sprintf('Bot with id %d not found', $botId));
        }

        $chatExportFileId = $message->getChatExportFileId();
        $file = $this->em->getRepository(ChatExportFile::class)->find($chatExportFileId);
        if (!$file) {
            $bot->setIsBeingTrained(false);
            $this->em->flush();
            throw new UnrecoverableMessageHandlingException(sprintf('Telegram file not found ID: %s', $chatExportFileId));
        }

        $this->botTrainer->train($bot, $file);

        $bot->setIsBeingTrained(false);
        $bot->setIsTrained(true);
        $this->em->flush();

        $this->notifyUser($bot, "✅ Your bot is trained and ready to use! Add it to a group chat to get started.");
    }

    private function notifyUser(Bot $bot, string $message): void
    {
        try {
            $this->nutgram->sendMessage(
                $message,
                chat_id: $bot->getOwner()?->getTelegramId()
            );
        } catch (Throwable $e) {
            $this->logger->warning('Failed to notify user about training completion', [
                'bot_id' => $bot->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

}
