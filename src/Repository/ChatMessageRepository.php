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

    public function findSenderName(?\App\Entity\ChatExportFile $file, string $fromId): ?string
    {
        if ($file === null) {
            return null;
        }

        return $this->createQueryBuilder('m')
            ->select('m.sender')
            ->where('m.chatExportFile = :file')
            ->andWhere('m.fromId = :fromId')
            ->setParameter('file', $file)
            ->setParameter('fromId', $fromId)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
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
        string         $targetFromId,
        string         $incomingText,
        int            $limit = 5,
        float          $minSimilarity = 0.0,
        float          $ftsWeight = 0.0,
    ): array
    {
        $trgmWeight = 1.0 - $ftsWeight;
        $sql = <<<SQL
            SELECT
                original.text AS trigger_text,
                reply.text    AS reply_text,
                (
                    :trgm_weight * similarity(lower(regexp_replace(original.text, '@\w+\s*', '', 'g')), lower(:incoming))
                  + :fts_weight  * ts_rank(
                        to_tsvector('simple', regexp_replace(original.text, '@\w+\s*', '', 'g')),
                        plainto_tsquery('simple', :incoming)
                    )
                ) AS score
            FROM chat_message reply
            JOIN chat_message original
                ON  original.telegram_message_id    = reply.reply_to_telegram_message_id
                AND original.chat_export_file_id    = :file_id
            WHERE reply.chat_export_file_id          = :file_id
              AND reply.from_id                      = :from_id
              AND reply.text                         IS NOT NULL
              AND original.text                      IS NOT NULL
              AND reply.reply_to_telegram_message_id IS NOT NULL
              AND similarity(lower(regexp_replace(original.text, '@\w+\s*', '', 'g')), lower(:incoming)) >= :min_similarity
            ORDER BY score DESC
            LIMIT :limit
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, [
                'file_id' => $file->getId(),
                'from_id' => $targetFromId,
                'incoming' => $incomingText,
                'limit' => $limit,
                'min_similarity' => $minSimilarity,
                'trgm_weight' => $trgmWeight,
                'fts_weight' => $ftsWeight,
            ])
            ->fetchAllAssociative();
    }

    /**
     * Sequential pairs: for each message the target sent (without an explicit reply),
     * find the closest preceding message from someone else within 1 minute.
     * Ranked by trigram similarity of that preceding message to the incoming text.
     *
     * @return array<int, array{trigger_text: string, reply_text: string}>
     */
    public function findSequentialPairs(
        ChatExportFile $file,
        string         $targetFromId,
        string         $incomingText,
        int            $limit = 5,
        float          $minSimilarity = 0.0,
        float          $ftsWeight = 0.0,
    ): array
    {
        // Start from trigger messages that are similar to the incoming text —
        // the GIN trigram index on `text` prunes this set cheaply.
        // Then join forward to find the target's next message within 1 minute.
        // This is O(similar_triggers) rather than O(target_messages).
        $trgmWeight = 1.0 - $ftsWeight;
        $sql = <<<SQL
            SELECT
                trigger_msg.text AS trigger_text,
                next_msg.text    AS reply_text,
                (
                    :trgm_weight * similarity(lower(regexp_replace(trigger_msg.text, '@\w+\s*', '', 'g')), lower(:incoming))
                  + :fts_weight  * ts_rank(
                        to_tsvector('simple', regexp_replace(trigger_msg.text, '@\w+\s*', '', 'g')),
                        plainto_tsquery('simple', :incoming)
                    )
                ) AS score
            FROM chat_message trigger_msg
            JOIN LATERAL (
                SELECT cm.text, cm.from_id, cm.sent_at, cm.reply_to_telegram_message_id
                FROM chat_message cm
                WHERE cm.chat_export_file_id = :file_id
                  AND cm.telegram_message_id > trigger_msg.telegram_message_id
                ORDER BY cm.telegram_message_id ASC
                LIMIT 1
            ) next_msg ON next_msg.from_id                      = :from_id
                      AND next_msg.reply_to_telegram_message_id IS NULL
                      AND next_msg.text                         IS NOT NULL
                      AND next_msg.sent_at - trigger_msg.sent_at <= interval '1 minute'
            WHERE trigger_msg.chat_export_file_id = :file_id
              AND trigger_msg.from_id             != :from_id
              AND trigger_msg.text                IS NOT NULL
              AND similarity(lower(regexp_replace(trigger_msg.text, '@\w+\s*', '', 'g')), lower(:incoming)) >= :min_similarity
            ORDER BY score DESC
            LIMIT :limit
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, [
                'file_id' => $file->getId(),
                'from_id' => $targetFromId,
                'incoming' => $incomingText,
                'limit' => $limit,
                'min_similarity' => $minSimilarity,
                'trgm_weight' => $trgmWeight,
                'fts_weight' => $ftsWeight,
            ])
            ->fetchAllAssociative();
    }
}
