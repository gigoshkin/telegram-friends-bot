<?php

namespace App\Service;

use App\Entity\Bot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;

readonly class BotFactory
{

    public function __construct(
        private EntityManagerInterface $em,
        private TokenEncryptionService $tokenEncryptionService,
    )
    {
    }

    public function create(User $owner, string $token): false|Bot
    {
        $encryptedToken = $this->tokenEncryptionService->encrypt($token);
        $bot = $this->em->getRepository(Bot::class)->findOneBy(['tokenEncrypted' => $encryptedToken]);
        if ($bot) {
            return false;
        }

        try {
            $testBot = new Nutgram($token);
            $testBot->getMe();
        } catch (\Throwable $e) {
            return false;
        }

        $bot = new Bot();
        $bot->setTokenEncrypted($encryptedToken);
        $bot->setOwner($owner);
        $this->em->persist($bot);
        $this->em->flush();

        return $bot;
    }
}
