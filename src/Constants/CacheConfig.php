<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Cache configuration constants
 * Centralized cache TTL and key definitions
 */
final class CacheConfig
{
    // Cache TTL values (in seconds)
    public const TTL_RECENT_DATA = 300; // 5 minutes for recent data
    public const TTL_HISTORICAL_DATA = 3600; // 1 hour for historical data
    public const TTL_HEALTH_CHECK = 60; // 1 minute for health check data
    
    // Cache key prefixes
    public const KEY_PREFIX_RATES_24H = 'rates_24h_';
    public const KEY_PREFIX_RATES_DAY = 'rates_day_';
    public const KEY_PREFIX_LATEST_RATES = 'latest_rates_health';
    
    // Cache invalidation patterns
    public const INVALIDATION_PATTERN_PAIR = 'rates_*_{pair}_*';
    public const INVALIDATION_PATTERN_ALL = 'rates_*';

    /**
     * Get cache key for 24h rates
     */
    public static function getRates24hKey(string $pair): string
    {
        return self::KEY_PREFIX_RATES_24H . str_replace('/', '_', $pair);
    }

    /**
     * Get cache key for daily rates
     */
    public static function getRatesDayKey(string $pair, string $date): string
    {
        return self::KEY_PREFIX_RATES_DAY . str_replace('/', '_', $pair) . "_{$date}";
    }

    /**
     * Get cache key for latest rates health check
     */
    public static function getLatestRatesKey(): string
    {
        return self::KEY_PREFIX_LATEST_RATES;
    }

    /**
     * Get TTL based on data type and date
     */
    public static function getTtlForData(string $type, ?\DateTimeImmutable $date = null): int
    {
        return match ($type) {
            'recent' => self::TTL_RECENT_DATA,
            'historical' => self::TTL_HISTORICAL_DATA,
            'health' => self::TTL_HEALTH_CHECK,
            'daily' => $date && $date < new \DateTimeImmutable('today') 
                ? self::TTL_HISTORICAL_DATA 
                : self::TTL_RECENT_DATA,
            default => self::TTL_RECENT_DATA,
        };
    }
}
