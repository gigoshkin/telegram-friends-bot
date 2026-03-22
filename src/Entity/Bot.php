<?php

namespace App\Entity;

use App\Repository\BotRepository;
use App\Service\TokenEncryptionService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: BotRepository::class)]
class Bot
{
    use TimestampableEntity;

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

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $telegramUserId = null;

    #[ORM\Column(options: ["default" => false])]
    private ?bool $isTrained = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?ChatExportFile $chatExportFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetFromId = null;

    /**
     * @var Collection<int, ChatExportUploadLink>
     */
    #[ORM\OneToMany(targetEntity: ChatExportUploadLink::class, mappedBy: 'bot', orphanRemoval: true)]
    private Collection $chatExportUploadLinks;

    public function __construct()
    {
        $this->chatExportUploadLinks = new ArrayCollection();
    }

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

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function isBeingTrained(): bool
    {
        return $this->isBeingTrained;
    }

    public function setIsBeingTrained(bool $isBeingTrained): static
    {
        $this->isBeingTrained = $isBeingTrained;

        return $this;
    }

    public function getTelegramUserId(): string
    {
        return $this->telegramUserId;
    }

    public function setTelegramUserId(string $telegramUserId): static
    {
        $this->telegramUserId = $telegramUserId;

        return $this;
    }

    public function isTrained(): ?bool
    {
        return $this->isTrained;
    }

    public function setIsTrained(bool $isTrained): static
    {
        $this->isTrained = $isTrained;

        return $this;
    }

    public function getChatExportFile(): ?ChatExportFile
    {
        return $this->chatExportFile;
    }

    public function setChatExportFile(?ChatExportFile $chatExportFile): static
    {
        $this->chatExportFile = $chatExportFile;

        return $this;
    }

    public function getTargetFromId(): ?string
    {
        return $this->targetFromId;
    }

    public function setTargetFromId(string $targetFromId): static
    {
        $this->targetFromId = $targetFromId;

        return $this;
    }

    /**
     * @return Collection<int, ChatExportUploadLink>
     */
    public function getChatExportUploadLinks(): Collection
    {
        return $this->chatExportUploadLinks;
    }

    public function addChatExportUploadLink(ChatExportUploadLink $chatExportUploadLink): static
    {
        if (!$this->chatExportUploadLinks->contains($chatExportUploadLink)) {
            $this->chatExportUploadLinks->add($chatExportUploadLink);
            $chatExportUploadLink->setBot($this);
        }

        return $this;
    }

    public function removeChatExportUploadLink(ChatExportUploadLink $chatExportUploadLink): static
    {
        if ($this->chatExportUploadLinks->removeElement($chatExportUploadLink)) {
            if ($chatExportUploadLink->getBot() === $this) {
                $chatExportUploadLink->setBot(null);
            }
        }

        return $this;
    }
}