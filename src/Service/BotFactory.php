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
        try {
            $testBot = new Nutgram($token);
            $botData = $testBot->getMe();
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Invalid token.');
        }

        $userId = $botData->id;
        $bot = $this->em->getRepository(Bot::class)->findOneBy(['telegramUserId' => $userId]);
        if ($bot) {
            if ($bot->isTrained()) {
                throw new BotAlreadyExistsException("Bot with this token already exists");
            }

            return $bot;
        }

        $bot = new Bot();
        $bot->setTelegramUserId($userId);
        $bot->setToken($token, $this->tokenEncryptionService);
        $bot->setOwner($owner);
        $this->em->persist($bot);
        $this->em->flush();

        return $bot;
    }
}
