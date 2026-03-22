<?php

namespace App\Entity;

use App\Repository\ChatExportUploadLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ChatExportUploadLinkRepository::class)]
class ChatExportUploadLink
{

    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $token = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isUsed = false;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?ChatExportFile $resultedFile = null;

    #[ORM\ManyToOne(inversedBy: 'chatExportUploadLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bot $bot = null;

    private const int TTL = 10 * 60;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function setIsUsed(bool $isUsed): static
    {
        $this->isUsed = $isUsed;

        return $this;
    }

    public function getResultedFile(): ChatExportFile
    {
        return $this->resultedFile;
    }

    public function setResultedFile(ChatExportFile $resultedFile): static
    {
        $this->resultedFile = $resultedFile;

        return $this;
    }

    public function isExpired(): bool
    {
        return ($this->createdAt->getTimestamp() + self::TTL) < time();
    }

    public function getBot(): Bot
    {
        return $this->bot;
    }

    public function setBot(?Bot $bot): static
    {
        $this->bot = $bot;

        return $this;
    }
}
