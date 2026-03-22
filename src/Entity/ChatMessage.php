<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Index(columns: ['chat_export_file_id', 'from_id'], name: 'idx_from_id')]
#[ORM\Index(columns: ['chat_export_file_id', 'telegram_message_id'], name: 'idx_telegram_msg_id')]
#[ORM\Index(columns: ['chat_export_file_id', 'from_id', 'sent_at', 'telegram_message_id'], name: 'idx_sequential_lookup')]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChatExportFile $chatExportFile = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $telegramMessageId = null;

    #[ORM\Column(length: 255)]
    private ?string $fromId = null;

    #[ORM\Column(length: 255)]
    private ?string $sender = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $replyToTelegramMessageId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChatExportFile(): ?ChatExportFile
    {
        return $this->chatExportFile;
    }

    public function setChatExportFile(ChatExportFile $chatExportFile): static
    {
        $this->chatExportFile = $chatExportFile;
        return $this;
    }

    public function getTelegramMessageId(): ?string
    {
        return $this->telegramMessageId;
    }

    public function setTelegramMessageId(string $telegramMessageId): static
    {
        $this->telegramMessageId = $telegramMessageId;
        return $this;
    }

    public function getFromId(): ?string
    {
        return $this->fromId;
    }

    public function setFromId(string $fromId): static
    {
        $this->fromId = $fromId;
        return $this;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(string $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getReplyToTelegramMessageId(): ?string
    {
        return $this->replyToTelegramMessageId;
    }

    public function setReplyToTelegramMessageId(?string $replyToTelegramMessageId): static
    {
        $this->replyToTelegramMessageId = $replyToTelegramMessageId;
        return $this;
    }
}