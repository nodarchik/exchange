<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Custom exception for rate service operations
 */
class RateServiceException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
