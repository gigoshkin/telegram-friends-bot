<?php

namespace App\Repository;

use App\Entity\ChatExportFile;
use App\Entity\ChatMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * Returns one entry per unique from_id in the export, with their display name.
     * When someone changed their name over time, we take the most recent one (MAX).
     *
     * @return array<int, array{fromId: string, sender: string}>
     */
    public function findDistinctParticipants(ChatExportFile $file): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.fromId, MAX(m.sender) AS sender, COUNT(m.id) AS messageCount')
            ->where('m.chatExportFile = :file')
            ->andWhere('m.fromId IS NOT NULL')
            ->groupBy('m.fromId')
            ->orderBy('sender', 'ASC')
            ->setParameter('file', $file)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns the best-matching (trigger_text, reply_text) pairs for an incoming message,
     * ranked by pg_trgm trigram similarity directly in the database.
     * Returns up to $limit results so the caller can pick one at random.
     *
     * @return array<int, array{trigger_text: string, reply_text: string}>
     */
    public function findBestReplyPairs(
        ChatExportFile $file,
        string $targetFromId,
        string $incomingText,
        int $limit = 5,
        float $minSimilarity = 0.0,
    ): array {
        $sql = <<<SQL
            SELECT
                original.text AS trigger_text,
                reply.text    AS reply_text
            FROM chat_message reply
            JOIN chat_message original
                ON  original.telegram_message_id    = reply.reply_to_telegram_message_id
                AND original.chat_export_file_id    = :file_id
            WHERE reply.chat_export_file_id          = :file_id
              AND reply.from_id                      = :from_id
              AND reply.text                         IS NOT NULL
              AND original.text                      IS NOT NULL
              AND reply.reply_to_telegram_message_id IS NOT NULL
              AND similarity(lower(original.text), lower(:incoming)) >= :min_similarity
            ORDER BY similarity(lower(original.text), lower(:incoming)) DESC
            LIMIT :limit
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, [
                'file_id'        => $file->getId(),
                'from_id'        => $targetFromId,
                'incoming'       => $incomingText,
                'limit'          => $limit,
                'min_similarity' => $minSimilarity,
            ])
            ->fetchAllAssociative();
    }
}