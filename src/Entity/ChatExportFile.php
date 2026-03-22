<?php

namespace App\Entity;

use App\Repository\ChatExportFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ChatExportFileRepository::class)]
class ChatExportFile
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\ManyToOne(inversedBy: 'chatExportFiles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telegramFileId = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isImported = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chatName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
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

    public function getTelegramFileId(): ?string
    {
        return $this->telegramFileId;
    }

    public function setTelegramFileId(?string $telegramFileId): static
    {
        $this->telegramFileId = $telegramFileId;

        return $this;
    }

    public function isImported(): bool
    {
        return $this->isImported;
    }

    public function setIsImported(bool $isImported): static
    {
        $this->isImported = $isImported;

        return $this;
    }

    public function getChatName(): ?string
    {
        return $this->chatName;
    }

    public function setChatName(?string $chatName): static
    {
        $this->chatName = $chatName;

        return $this;
    }
}