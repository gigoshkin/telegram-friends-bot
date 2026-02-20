<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

readonly class TelegramUserProvider
{
    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function getOrCreate(int $userId): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['telegramId' => $userId]);

        if (!$user)
        {
            $user = new User();
            $user->setTelegramId($userId);
            $this->em->persist($user);
            $this->em->flush();
        }

        return $user;
    }
}
