<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Service\TelegramUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;

readonly class ExportDeleteConfirmHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private TelegramUserProvider   $userProvider,
        private LoggerInterface        $logger,
        private ExportsMenuHandler     $exportsMenuHandler,
    )
    {
    }

    public function __invoke(Nutgram $bot): void
    {
        $bot->answerCallbackQuery(text: '✅ Export deleted.');

        $data = $bot->callbackQuery()?->data ?? '';
        $exportId = (int)explode(':', $data, 2)[1];

        $export = $this->em->find(ChatExportFile::class, $exportId);
        $user = $this->userProvider->getOrCreate($bot->userId());

        if (!$export || $export->getOwner()?->getId() !== $user->getId()) {
            $bot->editMessageText('Export not found.');
            return;
        }

        // Detach and deactivate all bots using this export
        $bots = $this->em->getRepository(Bot::class)->findBy(['chatExportFile' => $export]);
        foreach ($bots as $affectedBot) {
            $affectedBot->setChatExportFile(null);
            $affectedBot->setIsTrained(false);
        }

        // Delete file from disk
        $path = $export->getPath();
        if ($path && file_exists($path)) {
            if (!unlink($path)) {
                $this->logger->warning('Could not delete chat export file', ['path' => $path]);
            }
        }

        $this->em->remove($export);
        $this->em->flush();

        ($this->exportsMenuHandler)($bot);
    }
}