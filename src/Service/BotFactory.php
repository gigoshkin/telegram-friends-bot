<?php

namespace App\Service;

use App\Entity\Bot;
use App\Entity\User;
use App\Exception\Service\BotFactory\BotAlreadyExistsException;
use App\Exception\Service\BotFactory\InvalidTokenException;
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

    public function create(User $owner, string $token): Bot
    {
        $encryptedToken = $this->tokenEncryptionService->encrypt($token);
        $bot = $this->em->getRepository(Bot::class)->findOneBy(['tokenEncrypted' => $encryptedToken]);
        if ($bot) {
            throw new BotAlreadyExistsException( "Bot with this token already exists");
        }

        try {
            $testBot = new Nutgram($token);
            $testBot->getMe();
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Invalid token.');
        }

        $bot = new Bot();
        $bot->setTokenEncrypted($encryptedToken);
        $bot->setOwner($owner);
        $this->em->persist($bot);
        $this->em->flush();

        return $bot;
    }
}
