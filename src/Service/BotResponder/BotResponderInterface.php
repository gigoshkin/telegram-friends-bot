<?php

namespace App\Service\BotResponder;

use App\Entity\Bot;

interface BotResponderInterface
{
    /**
     * Given a trained bot and an incoming message text, returns a reply or null if no suitable response found.
     */
    public function respond(Bot $bot, string $incomingMessage): ?string;
}