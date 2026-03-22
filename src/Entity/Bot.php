<?php

namespace App\Entity;

use App\Enum\ResponseMode;
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

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $telegramName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $telegramNameCachedAt = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 1.0])]
    private float $responseProbability = 1.0;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.1])]
    private float $minSimilarity = 0.1;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.5])]
    private float $directResponseProbability = 0.5;

    #[ORM\Column(type: 'string', enumType: ResponseMode::class, options: ['default' => 'direct'])]
    private ResponseMode $responseMode = ResponseMode::Direct;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.3])]
    private float $sequentialWeight = 0.3;

    #[ORM\Column(options: ['default' => false])]
    private bool $debugMode = false;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 5])]
    private int $matchLimit = 5;

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

    public function getTelegramName(): ?string
    {
        return $this->telegramName;
    }

    public function setTelegramName(?string $telegramName): static
    {
        $this->telegramName = $telegramName;

        return $this;
    }

    public function getTelegramNameCachedAt(): ?\DateTimeImmutable
    {
        return $this->telegramNameCachedAt;
    }

    public function setTelegramNameCachedAt(?\DateTimeImmutable $telegramNameCachedAt): static
    {
        $this->telegramNameCachedAt = $telegramNameCachedAt;

        return $this;
    }

    public function getResponseProbability(): float
    {
        return $this->responseProbability;
    }

    public function setResponseProbability(float $responseProbability): static
    {
        $this->responseProbability = max(0.0, min(1.0, $responseProbability));

        return $this;
    }

    public function getMinSimilarity(): float
    {
        return $this->minSimilarity;
    }

    public function setMinSimilarity(float $minSimilarity): static
    {
        $this->minSimilarity = max(0.0, min(1.0, $minSimilarity));

        return $this;
    }

    public function getDirectResponseProbability(): float
    {
        return $this->directResponseProbability;
    }

    public function setDirectResponseProbability(float $directResponseProbability): static
    {
        $this->directResponseProbability = max(0.0, min(1.0, $directResponseProbability));

        return $this;
    }

    public function getResponseMode(): ResponseMode
    {
        return $this->responseMode;
    }

    public function setResponseMode(ResponseMode $responseMode): static
    {
        $this->responseMode = $responseMode;

        return $this;
    }

    public function getSequentialWeight(): float
    {
        return $this->sequentialWeight;
    }

    public function setSequentialWeight(float $sequentialWeight): static
    {
        $this->sequentialWeight = max(0.0, min(1.0, $sequentialWeight));

        return $this;
    }

    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function setDebugMode(bool $debugMode): static
    {
        $this->debugMode = $debugMode;

        return $this;
    }

    public function getMatchLimit(): int
    {
        return $this->matchLimit;
    }

    public function setMatchLimit(int $matchLimit): static
    {
        $this->matchLimit = max(1, min(50, $matchLimit));

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