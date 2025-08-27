<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * System configuration constants
 * Application-wide configuration values and limits
 */
final class SystemConfig
{
    // Application Metadata
    public const APP_NAME = 'Cryptocurrency Exchange API';
    public const APP_VERSION = '1.0.0';
    public const API_VERSION = 'v1';

    // Database Configuration
    public const DB_CHARSET = 'utf8mb4';
    public const DB_VERSION = '8.0';

    // Performance Limits
    public const MAX_RESULTS_PER_PAGE = 1000;
    public const DEFAULT_RESULTS_PER_PAGE = 288; // 24h * 12 (5-minute intervals)
    public const MAX_QUERY_TIMEOUT = 30; // seconds
    
    // Memory Limits
    public const MEMORY_LIMIT_CLI = '512M';
    public const MEMORY_LIMIT_WEB = '256M';

    // File Sizes
    public const LOG_FILE_MAX_SIZE = '100M';
    public const CACHE_MAX_SIZE = '1G';

    // Rate Limiting
    public const RATE_LIMIT_PER_MINUTE = 60;
    public const RATE_LIMIT_PER_HOUR = 1000;
    public const RATE_LIMIT_BURST = 10;

    // Security
    public const SESSION_TIMEOUT = 3600; // 1 hour
    public const TOKEN_EXPIRY = 86400; // 24 hours
    public const MAX_LOGIN_ATTEMPTS = 5;

    // Decimal Precision
    public const PRICE_PRECISION = 8; // 8 decimal places for crypto prices
    public const PERCENTAGE_PRECISION = 2; // 2 decimal places for percentages
    public const STATISTICS_PRECISION = 8; // 8 decimal places for statistics

    // Environment Types
    public const ENV_DEVELOPMENT = 'dev';
    public const ENV_PRODUCTION = 'prod';
    public const ENV_TEST = 'test';

    /**
     * Get all supported environments
     */
    public static function getSupportedEnvironments(): array
    {
        return [
            self::ENV_DEVELOPMENT,
            self::ENV_PRODUCTION,
            self::ENV_TEST,
        ];
    }

    /**
     * Check if environment is production
     */
    public static function isProduction(string $env): bool
    {
        return $env === self::ENV_PRODUCTION;
    }

    /**
     * Check if environment is development
     */
    public static function isDevelopment(string $env): bool
    {
        return $env === self::ENV_DEVELOPMENT;
    }

    /**
     * Get formatted price with proper precision
     */
    public static function formatPrice(float $price): string
    {
        return number_format($price, self::PRICE_PRECISION);
    }

    /**
     * Get formatted percentage with proper precision
     */
    public static function formatPercentage(float $percentage): string
    {
        return number_format($percentage, self::PERCENTAGE_PRECISION);
    }
}
