<?php

declare(strict_types=1);

namespace App\Message;

use App\Constants\CryptoPairs;

/**
 * Async message for rate fetching to improve performance
 * Allows non-blocking rate updates
 */
final readonly class FetchRateMessage
{
    public function __construct(
        public ?array $pairs = null,
        public bool $invalidateCache = true,
        public ?\DateTimeImmutable $requestedAt = null
    ) {}
    
    /**
     * Get the pairs to fetch (use all supported if none specified)
     */
    public function getPairs(): array
    {
        return $this->pairs ?? CryptoPairs::getAllSupported();
    }
    
    /**
     * Get the request timestamp
     */
    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt ?? new \DateTimeImmutable();
    }
}
