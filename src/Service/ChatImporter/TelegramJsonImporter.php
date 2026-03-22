<?php

namespace App\Service\ChatImporter;

use App\Entity\ChatExportFile;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Psr\Log\LoggerInterface;

class TelegramJsonImporter implements ChatImporterInterface
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {
    }

    public function import(ChatExportFile $file): void
    {
        $path = $file->getPath();

        if (!file_exists($path)) {
            throw new \RuntimeException("Chat export file not found: {$path}");
        }

        $chatName = $this->extractChatName($path);
        $conn     = $this->em->getConnection();
        $fileId   = $file->getId();
        $count    = 0;
        $batch    = [];

        foreach (Items::fromFile($path, ['pointer' => '/messages', 'decoder' => new ExtJsonDecoder(true)]) as $message) {
            if (!is_array($message) || ($message['type'] ?? '') !== 'message') {
                continue;
            }

            $fromId = $message['from_id'] ?? null;
            $sender = $message['from'] ?? null;
            if (empty($fromId) || empty($sender) || !str_starts_with((string) $fromId, 'user')) {
                continue;
            }

            $telegramId = $message['id'] ?? null;
            if (empty($telegramId)) {
                continue;
            }

            $batch[] = [
                'telegram_message_id'          => (int) $telegramId,
                'from_id'                      => (string) $fromId,
                'sender'                       => (string) $sender,
                'text'                         => $this->extractText($message['text'] ?? null),
                'sent_at'                      => $this->parseDate($message['date_unixtime'] ?? $message['date'] ?? null),
                'reply_to_telegram_message_id' => isset($message['reply_to_message_id'])
                    ? (int) $message['reply_to_message_id']
                    : null,
            ];

            $count++;

            if (count($batch) >= self::BATCH_SIZE) {
                $this->bulkInsert($conn, $batch, $fileId);
                $batch = [];
                $this->logger->debug("Imported {$count} messages from export #{$fileId}");
            }
        }

        if (!empty($batch)) {
            $this->bulkInsert($conn, $batch, $fileId);
        }

        $file->setIsImported(true);
        if ($chatName !== null) {
            $file->setChatName($chatName);
        }
        $this->em->flush();

        $this->logger->info("Chat export #{$fileId} imported: {$count} messages");
    }

    /**
     * @param array<int, array{telegram_message_id: int, sender: string, text: ?string, sent_at: ?\DateTimeImmutable, reply_to_telegram_message_id: ?int}> $rows
     */
    private function bulkInsert(Connection $conn, array $rows, int $fileId): void
    {
        $placeholders = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?, ?, ?, ?)'));

        $sql = "INSERT INTO chat_message
                    (chat_export_file_id, telegram_message_id, from_id, sender, text, sent_at, reply_to_telegram_message_id)
                VALUES {$placeholders}";

        $params = [];
        foreach ($rows as $row) {
            $params[] = $fileId;
            $params[] = $row['telegram_message_id'];
            $params[] = $row['from_id'];
            $params[] = $row['sender'];
            $params[] = $row['text'];
            $params[] = $row['sent_at']?->format('Y-m-d H:i:s');
            $params[] = $row['reply_to_telegram_message_id'];
        }

        $conn->executeStatement($sql, $params);
    }

    private function extractChatName(string $path): ?string
    {
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return null;
        }
        $header = fread($fp, 4096);
        fclose($fp);

        if (preg_match('/"name"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $header, $m)) {
            return json_decode('"' . $m[1] . '"');
        }

        return null;
    }

    private function extractText(mixed $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        if (is_string($text)) {
            return $text;
        }

        if (is_array($text)) {
            $parts = [];
            foreach ($text as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                } elseif (is_array($part) && isset($part['text'])) {
                    $parts[] = (string) $part['text'];
                }
            }
            $result = implode('', $parts);
            return $result !== '' ? $result : null;
        }

        return null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (new \DateTimeImmutable())->setTimestamp((int) $value);
        }

        if (is_string($value)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
            return $dt !== false ? $dt : null;
        }

        return null;
    }
}
