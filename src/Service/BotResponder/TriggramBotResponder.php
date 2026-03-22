<?php

namespace App\Service\BotResponder;

use App\Entity\Bot;
use App\Enum\ResponseMode;
use App\Repository\ChatMessageRepository;

class TriggramBotResponder implements BotResponderInterface
{
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
    )
    {
    }

    public function respond(Bot $bot, string $incomingMessage): ?string
    {
        $file = $bot->getChatExportFile();
        $target = $bot->getTargetFromId();

        if ($file === null || $target === null) {
            return null;
        }

        if ($bot->getResponseProbability() < 1.0
            && (mt_rand() / mt_getrandmax()) > $bot->getResponseProbability()
        ) {
            return null;
        }

        $text = trim($incomingMessage);
        $queryText = trim(preg_replace('/@\w+\s*/u', '', $text));
        if ($queryText === '') {
            $queryText = $text;
        }

        $minSimilarity = $this->dynamicMinSimilarity($queryText, $bot->getMinSimilarity());

        $matchLimit = $bot->getMatchLimit();
        $ftsWeight = $bot->getFtsWeight();

        $t0 = microtime(true);
        $directPairs = $this->chatMessageRepository->findBestReplyPairs(
            $file, $target, $queryText, limit: $matchLimit, minSimilarity: $minSimilarity, ftsWeight: $ftsWeight,
        );
        $directMs = (int)round((microtime(true) - $t0) * 1000);

        $seqPairs = [];
        $seqMs = 0;
        if ($bot->getResponseMode() === ResponseMode::Hybrid) {
            $t1 = microtime(true);
            $seqPairs = $this->chatMessageRepository->findSequentialPairs(
                $file, $target, $queryText, limit: $matchLimit, minSimilarity: $minSimilarity, ftsWeight: $ftsWeight,
            );
            $seqMs = (int)round((microtime(true) - $t1) * 1000);
        }

        if (empty($directPairs) && empty($seqPairs)) {
            return null;
        }

        $pair = $this->selectPair($directPairs, $seqPairs, $bot->getSequentialWeight());
        $isSeqPair = !in_array($pair, $directPairs, true);

        if ($bot->isDebugMode()) {
            $source = $isSeqPair ? 'sequential' : 'direct';
            $score = round((float)($pair['score'] ?? 0), 2);
            $timingStr = "direct: {$directMs}ms";
            if ($bot->getResponseMode() === ResponseMode::Hybrid) {
                $timingStr .= ", seq: {$seqMs}ms";
            }
            $minStr = round($minSimilarity, 2);
            return "🔍 <i>" . htmlspecialchars($pair['trigger_text']) . "</i>\n"
                . "↪️ " . $pair['reply_text'] . "\n"
                . "<code>[{$source} · score: {$score} · min: {$minStr} · {$timingStr}]</code>";
        }

        return $pair['reply_text'];
    }

    /**
     * Raises the similarity floor for short messages.
     * "ok" or "yes" have so few trigrams that a 0.3 score matches almost anything —
     * short messages need a stricter threshold, not a looser one.
     * The configured $floor is always respected as the minimum possible value.
     */
    private function dynamicMinSimilarity(string $text, float $floor): float
    {
        $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));

        // If the user explicitly disabled the floor (0.0), skip dynamic adjustment entirely.
        if ($floor === 0.0) {
            return 0.0;
        }

        $dynamic = match (true) {
            $words <= 1 => 0.7,
            $words <= 2 => 0.5,
            $words <= 4 => 0.3,
            $words <= 7 => 0.2,
            default => $floor,
        };

        return max($floor, $dynamic);
    }

    /**
     * @param array<int, array{trigger_text: string, reply_text: string}> $directPairs
     * @param array<int, array{trigger_text: string, reply_text: string}> $seqPairs
     * @return array{trigger_text: string, reply_text: string}
     */
    private function selectPair(array $directPairs, array $seqPairs, float $seqWeight): array
    {
        $hasSeq = !empty($seqPairs);
        $hasDirect = !empty($directPairs);

        if ($hasSeq && $hasDirect) {
            $useSeq = (mt_rand() / mt_getrandmax()) < $seqWeight;
            $pool = $useSeq ? $seqPairs : $directPairs;
        } else {
            $pool = $hasDirect ? $directPairs : $seqPairs;
        }

        return $pool[array_rand($pool)];
    }
}
