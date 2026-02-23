<?php

namespace App\Service\BotTrainer;

use App\Entity\Bot;
use App\Entity\ChatExportFile;

class LevenshteinBotTrainer implements BotTrainerInterface
{

    public function train(Bot $bot, ChatExportFile $chatExportFile): true
    {
        throw new \Exception('Not implemented');
    }
}
