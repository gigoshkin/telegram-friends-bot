<?php

namespace App\Service\BotTrainer;

use App\Entity\Bot;

interface BotTrainerInterface
{
    /**
     * Perform any algo-specific preparation after the chat export has been imported
     * and the target sender has been set on the bot.
     * For algorithm 1 (Levenshtein) this is a no-op — the data in chat_message is enough.
     * For future algorithms (e.g. embeddings) this is where pre-computation happens.
     */
    public function train(Bot $bot): void;
}
