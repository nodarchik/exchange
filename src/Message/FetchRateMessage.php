<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Async message for rate fetching to improve performance
 * Allows non-blocking rate updates
 */
final readonly class FetchRateMessage
{
    public function __construct(
        public array $pairs = ['EUR/BTC', 'EUR/ETH', 'EUR/LTC'],
        public bool $invalidateCache = true,
        public \DateTimeImmutable $requestedAt = new \DateTimeImmutable()
    ) {}
}
