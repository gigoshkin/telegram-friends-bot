<?php

namespace App\Service\BotResponder;

use App\Entity\Bot;
use App\Repository\ChatMessageRepository;

class TriggramBotResponder implements BotResponderInterface
{
    public function __construct(
        private readonly ChatMessageRepository $chatMessageRepository,
    ) {
    }

    public function respond(Bot $bot, string $incomingMessage): ?string
    {
        $file   = $bot->getChatExportFile();
        $target = $bot->getTargetFromId();

        if ($file === null || $target === null) {
            return null;
        }

        if ($bot->getResponseProbability() < 1.0
            && (mt_rand() / mt_getrandmax()) > $bot->getResponseProbability()
        ) {
            return null;
        }

        $pairs = $this->chatMessageRepository->findBestReplyPairs(
            $file,
            $target,
            trim($incomingMessage),
            minSimilarity: $bot->getMinSimilarity(),
        );

        if (empty($pairs)) {
            return null;
        }

        $pair = $pairs[array_rand($pairs)];

        if ($bot->isDebugMode()) {
            return "🔍 <i>" . htmlspecialchars($pair['trigger_text']) . "</i>\n↪️ " . $pair['reply_text'];
        }

        return $pair['reply_text'];
    }
}
