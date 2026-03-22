<?php

namespace App\MessageHandler;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Message\ProcessChatExportMessage;
use App\Repository\ChatMessageRepository;
use App\Service\ChatImporter\ChatImporterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Throwable;

#[AsMessageHandler]
final readonly class ProcessChatExportMessageHandler
{
    public function __construct(
        private ChatImporterInterface  $chatImporter,
        private ChatMessageRepository  $chatMessageRepository,
        private EntityManagerInterface $em,
        private Nutgram                $nutgram,
        private LoggerInterface        $logger,
    ) {
    }

    public function __invoke(ProcessChatExportMessage $message): void
    {
        $bot = $this->em->getRepository(Bot::class)->find($message->getBotId());
        if (!$bot) {
            throw new UnrecoverableMessageHandlingException(
                sprintf('Bot #%d not found', $message->getBotId())
            );
        }

        $file = $this->em->getRepository(ChatExportFile::class)->find($message->getChatExportFileId());
        if (!$file) {
            $bot->setIsBeingTrained(false);
            $bot->setChatExportFile(null);
            $this->em->flush();
            throw new UnrecoverableMessageHandlingException(
                sprintf('ChatExportFile #%d not found', $message->getChatExportFileId())
            );
        }

        try {
            if (!$file->isImported()) {
                $this->chatImporter->import($file);
            }

            // Re-fetch file after import (EM was cleared inside the importer)
            $file = $this->em->find(ChatExportFile::class, $message->getChatExportFileId());
            $bot  = $this->em->find(Bot::class, $message->getBotId());

            $participants = $this->chatMessageRepository->findDistinctParticipants($file);

            if (empty($participants)) {
                $bot->setIsBeingTrained(false);
                $this->em->flush();
                $this->notifyUser($bot, "❌ No text messages found in the export. Please try a different file.");
                return;
            }

            $this->sendParticipantSelectionKeyboard($bot, $participants);

        } catch (Throwable $e) {
            $this->logger->error('Failed to process chat export', [
                'bot_id'             => $message->getBotId(),
                'chat_export_file_id' => $message->getChatExportFileId(),
                'error'              => $e->getMessage(),
                'trace'              => $e->getTraceAsString(),
            ]);

            $this->cleanupAfterFailure($message, $e);
        }
    }

    private function cleanupAfterFailure(ProcessChatExportMessage $message, Throwable $cause): void
    {
        try {
            $conn = $this->em->getConnection();

            // Roll back any open transaction left by a failed flush/query
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }

            // Detach everything so we start fresh
            $this->em->clear();

            // Delete any partially imported rows via DBAL — safe even if ORM state is dirty
            $conn->executeStatement(
                'DELETE FROM chat_message WHERE chat_export_file_id = ?',
                [$message->getChatExportFileId()]
            );

            // Reset bot state so the user can start over
            $bot = $this->em->find(Bot::class, $message->getBotId());
            if ($bot) {
                $bot->setIsBeingTrained(false);
                $bot->setChatExportFile(null);
                $this->em->flush();

                $this->notifyUser(
                    $bot,
                    "❌ Something went wrong while processing your chat export.\n\n" .
                    "Please try again — send /start to begin a new setup."
                );
            }
        } catch (Throwable $cleanupException) {
            // Cleanup itself failed — at minimum log both errors
            $this->logger->error('Cleanup after failed import also failed', [
                'original_error' => $cause->getMessage(),
                'cleanup_error'  => $cleanupException->getMessage(),
                'bot_id'         => $message->getBotId(),
            ]);
        }
    }

    /**
     * @param array<int, array{fromId: string, sender: string, messageCount: int}> $participants
     */
    private function sendParticipantSelectionKeyboard(Bot $bot, array $participants): void
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($participants as $participant) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    $this->participantLabel($participant),
                    callback_data: 'select_target:' . $bot->getId() . ':' . $participant['fromId']
                )
            );
        }

        $this->notifyUser(
            $bot,
            "✅ Import done! Now choose who the bot should imitate 👇",
            $keyboard
        );
    }

    /** @param array{fromId: string, sender: string, messageCount: int} $participant */
    private function participantLabel(array $participant): string
    {
        $shortId = substr(ltrim($participant['fromId'], 'user'), -4);
        $count   = number_format((int) $participant['messageCount'], 0, '.', ' ');
        return "{$participant['sender']} · {$count} msgs · #{$shortId}";
    }

    private function notifyUser(Bot $bot, string $text, ?InlineKeyboardMarkup $keyboard = null): void
    {
        try {
            $this->nutgram->sendMessage(
                $text,
                chat_id: $bot->getOwner()->getTelegramId(),
                reply_markup: $keyboard,
            );
        } catch (Throwable $e) {
            $this->logger->warning('Failed to notify user', [
                'bot_id' => $bot->getId(),
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
