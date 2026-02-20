<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $telegramId = null;

    /**
     * @var Collection<int, Bot>
     */
    #[ORM\OneToMany(targetEntity: Bot::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $bots;

    public function __construct()
    {
        $this->bots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramId(): ?string
    {
        return $this->telegramId;
    }

    public function setTelegramId(string $telegramId): static
    {
        $this->telegramId = $telegramId;

        return $this;
    }

    /**
     * @return Collection<int, Bot>
     */
    public function getBots(): Collection
    {
        return $this->bots;
    }

    public function addBot(Bot $bot): static
    {
        if (!$this->bots->contains($bot)) {
            $this->bots->add($bot);
            $bot->setOwner($this);
        }

        return $this;
    }

    public function removeBot(Bot $bot): static
    {
        if ($this->bots->removeElement($bot)) {
            // set the owning side to null (unless already changed)
            if ($bot->getOwner() === $this) {
                $bot->setOwner(null);
            }
        }

        return $this;
    }
}
