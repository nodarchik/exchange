<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Rate;

/**
 * Data Transfer Object for API responses
 * Provides consistent JSON structure for all rate endpoints
 */
final readonly class RateResponseDto
{
    /**
     * @param RateItemDto[] $rates
     */
    public function __construct(
        public string $pair,
        public array $rates,
        public RateStatisticsDto $statistics,
        public string $requestedPeriod,
        public \DateTimeImmutable $generatedAt
    ) {}

    /**
     * Create response DTO from Rate entities
     * 
     * @param Rate[] $rates
     */
    public static function fromRates(array $rates, string $requestedPeriod): self
    {
        if (empty($rates)) {
            throw new \InvalidArgumentException('Cannot create response from empty rates array');
        }

        $pair = $rates[0]->getPair();
        $rateItems = array_map(fn(Rate $rate) => RateItemDto::fromRate($rate), $rates);
        $statistics = RateStatisticsDto::fromRates($rates);

        return new self(
            pair: $pair,
            rates: $rateItems,
            statistics: $statistics,
            requestedPeriod: $requestedPeriod,
            generatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Convert to array for JSON serialization
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pair' => $this->pair,
            'requested_period' => $this->requestedPeriod,
            'statistics' => $this->statistics->toArray(),
            'rates' => array_map(fn(RateItemDto $item) => $item->toArray(), $this->rates),
            'generated_at' => $this->generatedAt->format(\DateTimeInterface::ATOM),
            'count' => count($this->rates),
        ];
    }
}

/**
 * Individual rate item in the response
 */
final readonly class RateItemDto
{
    public function __construct(
        public string $price,
        public \DateTimeImmutable $recordedAt,
        public int $id
    ) {}

    public static function fromRate(Rate $rate): self
    {
        return new self(
            price: $rate->getPrice(),
            recordedAt: $rate->getRecordedAt(),
            id: $rate->getId() ?? 0
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'price' => $this->price,
            'recorded_at' => $this->recordedAt->format(\DateTimeInterface::ATOM),
            'timestamp' => $this->recordedAt->getTimestamp(),
        ];
    }
}

/**
 * Statistics for the rate data
 */
final readonly class RateStatisticsDto
{
    public function __construct(
        public float $minPrice,
        public float $maxPrice,
        public float $avgPrice,
        public int $totalRecords,
        public float $priceChange,
        public float $priceChangePercent
    ) {}

    /**
     * Calculate statistics from Rate entities
     * 
     * @param Rate[] $rates
     */
    public static function fromRates(array $rates): self
    {
        if (empty($rates)) {
            return new self(0.0, 0.0, 0.0, 0, 0.0, 0.0);
        }

        $prices = array_map(fn(Rate $rate) => $rate->getPriceAsFloat(), $rates);
        
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $avgPrice = array_sum($prices) / count($prices);
        
        // Calculate price change (first to last)
        $firstPrice = $rates[0]->getPriceAsFloat();
        $lastPrice = end($rates)->getPriceAsFloat();
        $priceChange = $lastPrice - $firstPrice;
        $priceChangePercent = $firstPrice > 0 ? ($priceChange / $firstPrice) * 100 : 0.0;

        return new self(
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            avgPrice: $avgPrice,
            totalRecords: count($rates),
            priceChange: $priceChange,
            priceChangePercent: $priceChangePercent
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'min_price' => number_format($this->minPrice, 8),
            'max_price' => number_format($this->maxPrice, 8),
            'avg_price' => number_format($this->avgPrice, 8),
            'price_change' => number_format($this->priceChange, 8),
            'price_change_percent' => number_format($this->priceChangePercent, 2),
            'total_records' => $this->totalRecords,
        ];
    }
}
