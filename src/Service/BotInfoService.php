<?php

namespace App\Service;

use App\Entity\Bot;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;

class BotInfoService
{
    private const CACHE_SECONDS = 86400; // 24 h

    public function __construct(
        private readonly TokenEncryptionService $encryption,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Returns a human-readable display name for the bot, e.g. "MyBot (@mybot)".
     * Fetches from Telegram and caches for 24 h.
     */
    public function getDisplayName(Bot $bot): string
    {
        $cachedAt = $bot->getTelegramNameCachedAt();
        $isStale  = $cachedAt === null
            || $cachedAt->getTimestamp() < time() - self::CACHE_SECONDS;

        if ($bot->getTelegramName() === null || $isStale) {
            $this->refresh($bot);
        }

        return $bot->getTelegramName() ?? ('Bot #' . $bot->getId());
    }

    private function refresh(Bot $bot): void
    {
        try {
            $nutgram = new Nutgram($bot->getToken($this->encryption));
            $me      = $nutgram->getMe();

            $name = $me->first_name;
            if ($me->username) {
                $name .= ' (@' . $me->username . ')';
            }

            $bot->setTelegramName($name);
            $bot->setTelegramNameCachedAt(new \DateTimeImmutable());
            $this->em->flush();
        } catch (\Throwable) {
            // API failure — keep existing cached value, don't crash
        }
    }
}
