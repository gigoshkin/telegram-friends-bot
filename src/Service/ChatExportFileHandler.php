<?php

namespace App\Service;

use App\Entity\ChatExportFile;
use App\Entity\User;
use App\Exception\Service\ChatExportFileHandler\FileNotFoundException;
use App\Exception\Service\ChatExportFileHandler\InvalidMimeTypeException;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Media\Document;
use SergiX44\Nutgram\Telegram\Types\Media\File;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

readonly class ChatExportFileHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private Nutgram                $bot,
        #[Autowire('%chat_exports_dir%')]
        private string                 $exportsDir,
        private LoggerInterface        $logger
    )
    {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws FileNotFoundException
     * @throws InvalidMimeTypeException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function saveFile(User $user, Document $document): ChatExportFile
    {
        if ($document->mime_type !== 'application/json') {
            throw new InvalidMimeTypeException('Only JSON files are supported.');
        }

        $file = $this->bot->getFile($document->file_id);
        if (!$file) {
            throw new FileNotFoundException('File not found. ID: ' . $document->file_id);
        }

        $path = $this->exportsDir . '/' . $file->file_id . '.json';

        if (!mkdir($concurrentDirectory = $this->exportsDir, 0750, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException('Could not create exports directory.');
        }

        if (!$this->tryDownloadFile($file, $path)) {
            throw new \RuntimeException('Could not download file.');
        }

        $entity = new ChatExportFile();
        $entity->setOwner($user);
        $entity->setPath($path);
        $entity->setTelegramFileId($file->file_id);

        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    private function tryDownloadFile(File $file, string $path): bool
    {
        try {
            $res = $this->bot->downloadFile($file, $path);
            return (bool) $res;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to download file from Telegram', [
                'file_id' => $file->file_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

}
