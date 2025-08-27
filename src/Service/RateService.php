<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ApiConstants;
use App\Constants\CryptoPairs;
use App\Constants\TimeConstants;
use App\Dto\RateResponseDto;
use App\Repository\RateRepository;
use Psr\Log\LoggerInterface;

/**
 * Service for handling rate business logic and data operations
 * Separates business logic from controller layer
 */
class RateService
{
    public function __construct(
        private readonly RateRepository $rateRepository,
        private readonly RateCacheService $cacheService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Get rates for the last 24 hours with caching and error handling
     */
    public function getLast24HoursRates(string $pair): ?RateResponseDto
    {
        $this->logger->info('Fetching last 24h rates', ['pair' => $pair]);

        try {
            $response = $this->cacheService->getLast24hRates($pair, function() use ($pair) {
                $rates = $this->rateRepository->findLast24Hours($pair);

                if (empty($rates)) {
                    return null;
                }

                $responseDto = RateResponseDto::fromRates($rates, ApiConstants::RESPONSE_TYPE_24H);
                return $responseDto->toArray();
            });

            if ($response === null) {
                $this->logger->warning('No rates found for last 24h', ['pair' => $pair]);
                return null;
            }

            $this->logger->info('Successfully fetched last 24h rates', [
                'pair' => $pair,
                'rate_count' => $response['count'] ?? 0,
                'cached' => true
            ]);

            return RateResponseDto::fromArray($response);

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching last 24h rates', [
                'pair' => $pair,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
            throw new RateServiceException("Failed to fetch last 24h rates for {$pair}", 0, $e);
        }
    }

    /**
     * Get rates for a specific day with caching and error handling
     */
    public function getDailyRates(string $pair, \DateTimeImmutable $date): ?RateResponseDto
    {
        $dateString = $date->format('Y-m-d');
        $this->logger->info('Fetching daily rates', ['pair' => $pair, 'date' => $dateString]);

        try {
            $response = $this->cacheService->getDailyRates($pair, $dateString, function() use ($pair, $date, $dateString) {
                $rates = $this->rateRepository->findByDay($pair, $date);

                if (empty($rates)) {
                    return null;
                }

                $responseDto = RateResponseDto::fromRates($rates, ApiConstants::getDayResponseType($dateString));
                return $responseDto->toArray();
            });

            if ($response === null) {
                $this->logger->warning('No rates found for specific day', [
                    'pair' => $pair,
                    'date' => $dateString
                ]);
                return null;
            }

            $this->logger->info('Successfully fetched daily rates', [
                'pair' => $pair,
                'date' => $dateString,
                'rate_count' => $response['count'] ?? 0
            ]);

            return RateResponseDto::fromArray($response);

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching daily rates', [
                'pair' => $pair,
                'date' => $dateString,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
            throw new RateServiceException("Failed to fetch daily rates for {$pair} on {$dateString}", 0, $e);
        }
    }

    /**
     * Get latest rates for health checking
     */
    public function getLatestRatesHealth(): array
    {
        try {
            $result = $this->cacheService->getLatestRates(function() {
                $latestRates = [];
                $pairs = CryptoPairs::getAllSupported();

                foreach ($pairs as $pair) {
                    $rate = $this->rateRepository->findLatestByPair($pair);
                    if ($rate) {
                        $latestRates[$pair] = $rate->getRecordedAt()->format(TimeConstants::FORMAT_DATETIME);
                    }
                }

                return $latestRates;
            });

            return $result ?? [];

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching latest rates for health check', [
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
            throw new RateServiceException('Failed to fetch latest rates for health check', 0, $e);
        }
    }

    /**
     * Get comprehensive rate statistics for a pair
     */
    public function getRateStatistics(string $pair, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        try {
            $this->logger->info('Fetching rate statistics', [
                'pair' => $pair,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ]);

            $statistics = $this->rateRepository->findMinMaxAvgForPeriod($pair, $startDate, $endDate);
            
            if (empty($statistics)) {
                return [];
            }

            return [
                'min_price' => $statistics['min_price'],
                'max_price' => $statistics['max_price'],
                'avg_price' => $statistics['avg_price'],
                'total_records' => $statistics['total_records'],
                'period_start' => $startDate->format(TimeConstants::FORMAT_ISO8601),
                'period_end' => $endDate->format(TimeConstants::FORMAT_ISO8601)
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching rate statistics', [
                'pair' => $pair,
                'error' => $e->getMessage()
            ]);
            throw new RateServiceException("Failed to fetch statistics for {$pair}", 0, $e);
        }
    }

    /**
     * Check if we have recent data for a pair (within last 10 minutes)
     */
    public function hasRecentData(string $pair): bool
    {
        try {
            $latestRate = $this->rateRepository->findLatestByPair($pair);
            
            if (!$latestRate) {
                return false;
            }

            $tenMinutesAgo = TimeConstants::getDataFreshnessThreshold();
            return $latestRate->getRecordedAt() > $tenMinutesAgo;

        } catch (\Throwable $e) {
            $this->logger->error('Error checking recent data', [
                'pair' => $pair,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get supported trading pairs
     */
    public function getSupportedPairs(): array
    {
        return CryptoPairs::getAllSupported();
    }

    /**
     * Validate if a pair is supported
     */
    public function isPairSupported(string $pair): bool
    {
        return CryptoPairs::isSupported($pair);
    }
}
