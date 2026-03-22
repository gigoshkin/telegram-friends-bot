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

        $pairs = $this->chatMessageRepository->findBestReplyPairs($file, $target, trim($incomingMessage));

        if (empty($pairs)) {
            return null;
        }

        return $pairs[array_rand($pairs)]['reply_text'];
    }
}
