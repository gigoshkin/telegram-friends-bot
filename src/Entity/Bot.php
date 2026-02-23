<?php

namespace App\Entity;

use App\Repository\BotRepository;
use App\Service\TokenEncryptionService;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BotRepository::class)]
class Bot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $tokenEncrypted = null;

    #[ORM\ManyToOne(inversedBy: 'bots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(options: ["default" => false])]
    private bool $isBeingTrained = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenEncrypted(): ?string
    {
        return $this->tokenEncrypted;
    }

    public function setTokenEncrypted(string $tokenEncrypted): static
    {
        $this->tokenEncrypted = $tokenEncrypted;

        return $this;
    }

    public function setToken(string $token, TokenEncryptionService $encryption): void
    {
        $this->tokenEncrypted = $encryption->encrypt($token);
    }

    public function getToken(TokenEncryptionService $encryption): string
    {
        return $encryption->decrypt($this->tokenEncrypted);
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function isBeingTrained(): ?bool
    {
        return $this->isBeingTrained;
    }

    public function setIsBeingTrained(bool $isBeingTrained): static
    {
        $this->isBeingTrained = $isBeingTrained;

        return $this;
    }
}
