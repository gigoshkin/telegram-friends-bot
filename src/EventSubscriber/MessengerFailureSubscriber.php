<?php

namespace App\EventSubscriber;

use App\Entity\Bot;
use App\Message\ProcessChatExportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

readonly class MessengerFailureSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private EntityManagerInterface $em,
        private Nutgram                $nutgram,
        private LoggerInterface        $logger
    )
    {
    }

    public function onWorkerMessageFailedEvent(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if ($message instanceof ProcessChatExportMessage && !$event->willRetry()) {
            $this->handleTrainingFailure($message);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailedEvent',
        ];
    }

    private function handleTrainingFailure(ProcessChatExportMessage $message): void
    {
        $botId = $message->getBotId();
        $bot = $this->em->getRepository(Bot::class)->find($botId);
        if (!$bot) {
            return;
        }

        $bot->setIsBeingTrained(false);
        $this->em->flush();

        try {
            $this->nutgram->sendMessage(
                "❌ Training failed after multiple attempts. Please try again or contact support.",
                chat_id: $bot->getOwner()?->getTelegramId()
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to notify user about permanent training failure', [
                'bot_id' => $bot->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
