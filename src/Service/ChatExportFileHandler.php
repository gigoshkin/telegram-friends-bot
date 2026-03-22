<?php

namespace App\Service;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Entity\ChatExportUploadLink;
use App\Entity\User;
use App\Exception\Service\ChatExportFileHandler\FileNotFoundException;
use App\Exception\Service\ChatExportFileHandler\FileTooBigException;
use App\Exception\Service\ChatExportFileHandler\InvalidMimeTypeException;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Media\Document;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class ChatExportFileHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private Nutgram                $bot,
        #[Autowire('%chat_exports_dir%')]
        private string                 $exportsDir,
        private LoggerInterface        $logger,
        private UrlGeneratorInterface  $urlGenerator,
    )
    {
    }

    /**
     * Handle Telegram document upload
     *
     * @throws FileNotFoundException
     * @throws InvalidMimeTypeException
     * @throws FileTooBigException
     */
    public function handleTelegramUpload(User $user, Document $document): ChatExportFile
    {
        if ($document->mime_type !== 'application/json') {
            throw new InvalidMimeTypeException('Only JSON files are supported.');
        }

        try {
            $file = $this->bot->getFile($document->file_id);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'file is too big') !== false) {
                throw new FileTooBigException('File is too big (over 20MB). Please use the web upload link.');
            }
            throw new FileNotFoundException('Could not retrieve file from Telegram.');
        }

        if (!$file) {
            throw new FileNotFoundException('File not found on Telegram servers.');
        }

        $this->ensureDirectoryExists();

        $filename = $file->file_id . '.json';
        $path = $this->exportsDir . '/' . $filename;

        try {
            $this->bot->downloadFile($file, $path);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to download file from Telegram', [
                'file_id' => $file->file_id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Could not download file from Telegram.');
        }

        if (!$this->validateJsonFile($path)) {
            throw new InvalidMimeTypeException('Invalid JSON file.');
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
     * Handle web upload via UploadedFile
     *
     * @throws InvalidMimeTypeException
     */
    public function handleWebUpload(User $user, UploadedFile $file): ChatExportFile
    {
        $allowedMimes = ['application/json', 'text/plain'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new InvalidMimeTypeException('Only JSON files are supported.');
        }

        $this->ensureDirectoryExists();

        $filename = sprintf(
            '%d_%s.json',
            $user->getId(),
            bin2hex(random_bytes(8))
        );

        $path = $this->exportsDir . '/' . $filename;

        try {
            $file->move($this->exportsDir, $filename);
        } catch (\Exception $e) {
            $this->logger->error('Failed to move uploaded file', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Could not save uploaded file.');
        }

        if (!$this->validateJsonFile($path)) {
            @unlink($path);
            throw new InvalidMimeTypeException('Invalid JSON file.');
        }

        $entity = new ChatExportFile();
        $entity->setOwner($user);
        $entity->setPath($path);
        $entity->setTelegramFileId(null);

        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    public function generateUploadLink(Bot $bot): string
    {
        $token = bin2hex(random_bytes(32));

        $link = new ChatExportUploadLink();
        $link->setBot($bot);
        $link->setToken($token);

        $this->em->persist($link);
        $this->em->flush();

        return $this->urlGenerator->generate(
            'app_chat_export_upload',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->exportsDir) && !mkdir($concurrentDirectory = $this->exportsDir, 0750, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf(
                'Could not create exports directory: %s',
                $this->exportsDir
            ));
        }
    }

    private function validateJsonFile(string $path): bool
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException('Could not read file.');
        }

        return json_validate($content);
    }
}
