<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RateResponseDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * High-performance caching service for rate data
 * Implements smart caching strategies for different data types
 */
class RateCacheService
{
    private const CACHE_TTL_24H = 300; // 5 minutes for recent data
    private const CACHE_TTL_HISTORICAL = 3600; // 1 hour for historical data
    private const CACHE_TTL_STATS = 60; // 1 minute for statistics

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Get cached API response or execute callback to generate it
     */
    public function getCachedApiResponse(
        string $cacheKey,
        callable $dataProvider,
        int $ttl = self::CACHE_TTL_24H
    ): array {
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($dataProvider, $ttl) {
                $item->expiresAfter($ttl);
                
                $startTime = microtime(true);
                $result = $dataProvider();
                $duration = (microtime(true) - $startTime) * 1000;
                
                $this->logger->info('Cache miss - generated new data', [
                    'cache_key' => $item->getKey(),
                    'ttl' => $ttl,
                    'generation_time_ms' => round($duration, 2)
                ]);
                
                return $result;
            });
        } catch (\Throwable $e) {
            $this->logger->error('Cache error, falling back to direct data generation', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to direct data generation if cache fails
            return $dataProvider();
        }
    }

    /**
     * Cache last 24h rates with smart TTL based on data freshness
     */
    public function getLast24hRates(string $pair, callable $dataProvider): array
    {
        $cacheKey = "rates_24h_" . str_replace('/', '_', $pair);
        return $this->getCachedApiResponse($cacheKey, $dataProvider, self::CACHE_TTL_24H);
    }

    /**
     * Cache daily rates with longer TTL for historical data
     */
    public function getDailyRates(string $pair, string $date, callable $dataProvider): array
    {
        $cacheKey = "rates_day_" . str_replace('/', '_', $pair) . "_{$date}";
        
        // Use longer cache for historical data (past dates)
        $isHistorical = $date < date('Y-m-d');
        $ttl = $isHistorical ? self::CACHE_TTL_HISTORICAL : self::CACHE_TTL_24H;
        
        return $this->getCachedApiResponse($cacheKey, $dataProvider, $ttl);
    }

    /**
     * Cache latest rate data for health checks
     */
    public function getLatestRates(callable $dataProvider): array
    {
        $cacheKey = 'latest_rates_health';
        return $this->getCachedApiResponse($cacheKey, $dataProvider, self::CACHE_TTL_STATS);
    }

    /**
     * Cache rate statistics
     */
    public function getRateStatistics(string $cacheKey, callable $dataProvider): array
    {
        return $this->getCachedApiResponse($cacheKey, $dataProvider, self::CACHE_TTL_STATS);
    }

    /**
     * Invalidate cache for a specific pair (useful when new rates are added)
     */
    public function invalidatePairCache(string $pair): void
    {
        try {
            $keysToInvalidate = [
                "rates_24h_" . str_replace('/', '_', $pair),
                "rates_day_" . str_replace('/', '_', $pair) . "_" . date('Y-m-d'),
                'latest_rates_health'
            ];

            foreach ($keysToInvalidate as $key) {
                $this->cache->delete($key);
            }

            $this->logger->info('Cache invalidated for pair', [
                'pair' => $pair,
                'invalidated_keys' => $keysToInvalidate
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Cache invalidation failed', [
                'pair' => $pair,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUpCache(array $pairs = ['EUR/BTC', 'EUR/ETH', 'EUR/LTC']): void
    {
        $this->logger->info('Starting cache warm-up', ['pairs' => $pairs]);
        
        foreach ($pairs as $pair) {
            try {
                // Pre-warm 24h cache (most frequently accessed)
                $this->getLast24hRates($pair, function() {
                    return ['status' => 'warming']; // Placeholder - would call actual data provider
                });
                
                // Pre-warm today's daily cache
                $today = date('Y-m-d');
                $this->getDailyRates($pair, $today, function() {
                    return ['status' => 'warming'];
                });
                
            } catch (\Throwable $e) {
                $this->logger->warning('Cache warm-up failed for pair', [
                    'pair' => $pair,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('Cache warm-up completed');
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(): array
    {
        // Note: Symfony Cache doesn't provide built-in stats
        // This would need to be implemented with a custom cache adapter or Redis directly
        return [
            'cache_type' => 'redis',
            'status' => 'active',
            'ttl_24h' => self::CACHE_TTL_24H,
            'ttl_historical' => self::CACHE_TTL_HISTORICAL,
            'ttl_stats' => self::CACHE_TTL_STATS
        ];
    }
}
