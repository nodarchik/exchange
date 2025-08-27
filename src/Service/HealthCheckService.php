<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service for handling application health checks
 * Centralizes health monitoring and diagnostics
 */
class HealthCheckService
{
    public function __construct(
        private readonly RateService $rateService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Perform comprehensive health check
     */
    public function getHealthStatus(): HealthStatus
    {
        try {
            // Check database connectivity and latest rates
            $latestRates = $this->rateService->getLatestRatesHealth();
            
            // Check data freshness (should have data from last 10 minutes)
            $dataFreshness = $this->checkDataFreshness();
            
            $status = $dataFreshness['all_fresh'] ? 'healthy' : 'degraded';
            
            return new HealthStatus(
                status: $status,
                timestamp: new \DateTimeImmutable(),
                database: 'connected',
                latestRates: $latestRates,
                dataFreshness: $dataFreshness,
                checks: $this->getDetailedChecks()
            );

        } catch (\Throwable $e) {
            $this->logger->error('Health check failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);

            return new HealthStatus(
                status: 'unhealthy',
                timestamp: new \DateTimeImmutable(),
                database: 'error',
                error: 'Database connection failed'
            );
        }
    }

    /**
     * Check if data is fresh for all supported pairs
     */
    private function checkDataFreshness(): array
    {
        $pairs = $this->rateService->getSupportedPairs();
        $freshness = [];
        $allFresh = true;

        foreach ($pairs as $pair) {
            $isFresh = $this->rateService->hasRecentData($pair);
            $freshness[$pair] = $isFresh;
            
            if (!$isFresh) {
                $allFresh = false;
            }
        }

        return [
            'all_fresh' => $allFresh,
            'pairs' => $freshness,
            'check_time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ];
    }

    /**
     * Get detailed system checks
     */
    private function getDetailedChecks(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => $this->getMemoryUsage(),
            'uptime' => $this->getSystemUptime(),
            'supported_pairs' => $this->rateService->getSupportedPairs(),
            'last_check' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ];
    }

    /**
     * Get memory usage information
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => $this->formatBytes(memory_get_usage(true)),
            'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Get system uptime (simplified)
     */
    private function getSystemUptime(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return sprintf('Load: %.2f %.2f %.2f', $load[0], $load[1], $load[2]);
        }
        
        return 'Load information not available';
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}

/**
 * Value object representing health status
 */
readonly class HealthStatus
{
    public function __construct(
        public string $status,
        public \DateTimeImmutable $timestamp,
        public string $database = '',
        public array $latestRates = [],
        public array $dataFreshness = [],
        public array $checks = [],
        public ?string $error = null
    ) {}

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
            'timestamp' => $this->timestamp->format(\DateTimeInterface::ATOM),
            'database' => $this->database
        ];

        if (!empty($this->latestRates)) {
            $data['latest_rates'] = $this->latestRates;
        }

        if (!empty($this->dataFreshness)) {
            $data['data_freshness'] = $this->dataFreshness;
        }

        if (!empty($this->checks)) {
            $data['system_checks'] = $this->checks;
        }

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        return $data;
    }

    /**
     * Determine HTTP status code based on health status
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->status) {
            'healthy' => 200,
            'degraded' => 200, // Still functional, just with warnings
            'unhealthy' => 503,
            default => 500
        };
    }
}
