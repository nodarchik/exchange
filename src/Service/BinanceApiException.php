<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Custom exception for Binance API related errors
 * Provides structured error information for better debugging and monitoring
 */
class BinanceApiException extends \Exception
{
    /**
     * @param array<string, mixed>|null $context Additional context data
     */
    public function __construct(
        string $message,
        private readonly string $pair,
        private readonly ?array $context = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getPair(): string
    {
        return $this->pair;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * Get structured error information for logging
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'pair' => $this->pair,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'previous' => $this->getPrevious()?->getMessage(),
        ];
    }
}
