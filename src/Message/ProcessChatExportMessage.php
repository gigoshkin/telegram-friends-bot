<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class ProcessChatExportMessage
{
    public function __construct(
        private int $chatExportFileId,
        private int $botId
    )
    {
    }

    public function getChatExportFileId(): int
    {
        return $this->chatExportFileId;
    }

    public function getBotId(): int
    {
        return $this->botId;
    }
}
