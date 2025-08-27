<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Performance monitoring service for tracking API and system metrics
 * Provides insights for optimization and scaling decisions
 */
class PerformanceMonitoringService
{
    private array $metrics = [];
    private array $timers = [];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Start timing an operation
     */
    public function startTimer(string $operation): void
    {
        $this->timers[$operation] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    /**
     * End timing and log performance metrics
     */
    public function endTimer(string $operation, array $context = []): float
    {
        if (!isset($this->timers[$operation])) {
            return 0.0;
        }

        $timer = $this->timers[$operation];
        $duration = (microtime(true) - $timer['start']) * 1000; // Convert to milliseconds
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];
        $memoryPeak = memory_get_peak_usage(true);

        $metrics = [
            'operation' => $operation,
            'duration_ms' => round($duration, 2),
            'memory_used_bytes' => $memoryUsed,
            'memory_peak_bytes' => $memoryPeak,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            ...$context
        ];

        // Log performance metrics
        $level = $duration > 1000 ? 'warning' : 'info'; // Warn on operations > 1s
        $this->logger->log($level, "Performance: {$operation}", $metrics);

        // Store metrics for aggregation
        $this->metrics[$operation][] = $metrics;

        unset($this->timers[$operation]);
        
        return $duration;
    }

    /**
     * Track database query performance
     */
    public function trackDatabaseQuery(string $query, float $duration, array $params = []): void
    {
        $metrics = [
            'type' => 'database_query',
            'query' => $this->sanitizeQuery($query),
            'duration_ms' => round($duration, 2),
            'param_count' => count($params),
            'slow_query' => $duration > 100 // Flag queries > 100ms as slow
        ];

        $level = $duration > 100 ? 'warning' : 'debug';
        $this->logger->log($level, 'Database query performance', $metrics);
    }

    /**
     * Track API endpoint performance
     */
    public function trackApiEndpoint(string $endpoint, string $method, float $duration, int $statusCode, array $context = []): void
    {
        $metrics = [
            'type' => 'api_endpoint',
            'endpoint' => $endpoint,
            'method' => $method,
            'duration_ms' => round($duration, 2),
            'status_code' => $statusCode,
            'success' => $statusCode < 400,
            ...$context
        ];

        $level = match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            $duration > 500 => 'warning',
            default => 'info'
        };

        $this->logger->log($level, "API endpoint performance: {$method} {$endpoint}", $metrics);
    }

    /**
     * Track cache performance
     */
    public function trackCacheOperation(string $operation, string $key, bool $hit, float $duration = 0): void
    {
        $metrics = [
            'type' => 'cache_operation',
            'operation' => $operation, // hit, miss, set, delete
            'cache_key' => $key,
            'cache_hit' => $hit,
            'duration_ms' => round($duration, 2)
        ];

        $this->logger->info('Cache operation', $metrics);
    }

    /**
     * Get performance statistics for monitoring dashboard
     */
    public function getPerformanceStats(): array
    {
        $stats = [
            'memory' => [
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit_mb' => ini_get('memory_limit')
            ],
            'operations' => []
        ];

        // Aggregate operation metrics
        foreach ($this->metrics as $operation => $operationMetrics) {
            if (empty($operationMetrics)) continue;

            $durations = array_column($operationMetrics, 'duration_ms');
            $stats['operations'][$operation] = [
                'count' => count($operationMetrics),
                'avg_duration_ms' => round(array_sum($durations) / count($durations), 2),
                'min_duration_ms' => round(min($durations), 2),
                'max_duration_ms' => round(max($durations), 2),
                'total_duration_ms' => round(array_sum($durations), 2)
            ];
        }

        return $stats;
    }

    /**
     * Monitor system resources
     */
    public function getSystemMetrics(): array
    {
        $loadAvg = sys_getloadavg();
        
        return [
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'memory' => [
                'usage_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
                'usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit' => ini_get('memory_limit')
            ],
            'cpu' => [
                'load_avg_1min' => $loadAvg[0] ?? null,
                'load_avg_5min' => $loadAvg[1] ?? null,
                'load_avg_15min' => $loadAvg[2] ?? null
            ],
            'php' => [
                'version' => PHP_VERSION,
                'max_execution_time' => ini_get('max_execution_time'),
                'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status()
            ]
        ];
    }

    /**
     * Clean sensitive information from SQL queries for logging
     */
    private function sanitizeQuery(string $query): string
    {
        // Remove potentially sensitive parameter values
        return preg_replace('/\'[^\']*\'/', '?', $query);
    }

    /**
     * Reset metrics (useful for testing or periodic cleanup)
     */
    public function resetMetrics(): void
    {
        $this->metrics = [];
        $this->timers = [];
    }
}
