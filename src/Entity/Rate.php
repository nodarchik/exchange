<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\RateRepository')]
#[ORM\Table(name: 'rates')]
#[ORM\Index(columns: ['pair', 'recorded_at'], name: 'idx_pair_recorded_at')]
#[ORM\Index(columns: ['recorded_at'], name: 'idx_recorded_at')]
#[ORM\HasLifecycleCallbacks]
class Rate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['EUR/BTC', 'EUR/ETH', 'EUR/LTC'])]
    private string $pair;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private string $price;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $pair, string $price, ?\DateTimeImmutable $recordedAt = null)
    {
        $this->pair = $pair;
        $this->price = $price;
        $this->recordedAt = $recordedAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPair(): string
    {
        return $this->pair;
    }

    public function setPair(string $pair): self
    {
        $this->pair = $pair;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPriceFromFloat(float $price): self
    {
        $this->price = number_format($price, 8, '.', '');
        return $this;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getPriceAsFloat(): float
    {
        return (float) $this->price;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeImmutable $recordedAt): self
    {
        $this->recordedAt = $recordedAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    /**
     * Get base currency from pair (e.g., EUR from EUR/BTC)
     */
    public function getBaseCurrency(): string
    {
        return explode('/', $this->pair)[0];
    }

    /**
     * Get quote currency from pair (e.g., BTC from EUR/BTC)
     */
    public function getQuoteCurrency(): string
    {
        return explode('/', $this->pair)[1];
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pair' => $this->pair,
            'price' => $this->price,
            'recorded_at' => $this->recordedAt->format(\DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
