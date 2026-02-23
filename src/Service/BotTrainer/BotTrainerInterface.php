<?php

namespace App\Service\BotTrainer;

use App\Entity\Bot;
use App\Entity\ChatExportFile;

interface BotTrainerInterface
{
    public function train(Bot $bot, ChatExportFile $chatExportFile): true;
}
