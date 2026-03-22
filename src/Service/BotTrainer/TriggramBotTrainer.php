<?php

namespace App\Service\BotTrainer;

use App\Entity\Bot;

class TriggramBotTrainer implements BotTrainerInterface
{
    public function train(Bot $bot): void
    {
        // No-op: all data needed for trigram similarity is already
        // in the chat_message table after import. Querying happens at response time.
    }
}
