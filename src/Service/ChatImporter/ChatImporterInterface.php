<?php

namespace App\Service\ChatImporter;

use App\Entity\ChatExportFile;

interface ChatImporterInterface
{
    /**
     * Parses the export file and stores all messages in the chat_message table.
     * Sets $file->isImported = true when done.
     */
    public function import(ChatExportFile $file): void;
}