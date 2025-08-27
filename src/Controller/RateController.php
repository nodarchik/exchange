<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RateQueryDto;
use App\Dto\RateResponseDto;
use App\Repository\RateRepository;
use App\Service\RateCacheService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API Controller for cryptocurrency exchange rates
 * Provides clean, RESTful endpoints for rate data consumption
 */
#[Route('/api/rates', name: 'api_rates_')]
class RateController extends AbstractController
{
    public function __construct(
        private readonly RateRepository $rateRepository,
        private readonly RateCacheService $cacheService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Get rates for the last 24 hours
     * 
     * @example GET /api/rates/last-24h?pair=EUR/BTC
     */
    #[Route('/last-24h', name: 'last_24h', methods: ['GET'])]
    public function getLast24Hours(
        #[MapQueryString] RateQueryDto $queryDto
    ): JsonResponse {
        $this->logger->info('API request for last 24h rates', [
            'pair' => $queryDto->pair,
            'endpoint' => 'last-24h'
        ]);

        try {
            // Validate input
            $violations = $this->validator->validate($queryDto);
            if (count($violations) > 0) {
                return $this->createValidationErrorResponse($violations);
            }

            // Use caching for performance
            $response = $this->cacheService->getLast24hRates($queryDto->pair, function() use ($queryDto) {
                $rates = $this->rateRepository->findLast24Hours($queryDto->pair);

                if (empty($rates)) {
                    return null; // Will be handled after cache call
                }

                $responseDto = RateResponseDto::fromRates($rates, 'last-24h');
                return $responseDto->toArray();
            });

            if ($response === null) {
                $this->logger->warning('No rates found for last 24h', ['pair' => $queryDto->pair]);
                
                return $this->json([
                    'error' => 'No data available',
                    'message' => "No rates found for {$queryDto->pair} in the last 24 hours",
                    'pair' => $queryDto->pair,
                    'requested_period' => 'last-24h'
                ], Response::HTTP_NOT_FOUND);
            }

            $this->logger->info('Successfully returned last 24h rates', [
                'pair' => $queryDto->pair,
                'rate_count' => $response['count'] ?? 0,
                'cached' => true
            ]);

            return $this->json($response);

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching last 24h rates', [
                'pair' => $queryDto->pair,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while fetching rate data'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get rates for a specific day
     * 
     * @example GET /api/rates/day?pair=EUR/BTC&date=2024-01-15
     */
    #[Route('/day', name: 'day', methods: ['GET'])]
    public function getDay(
        #[MapQueryString] RateQueryDto $queryDto
    ): JsonResponse {
        $this->logger->info('API request for daily rates', [
            'pair' => $queryDto->pair,
            'date' => $queryDto->date,
            'endpoint' => 'day'
        ]);

        try {
            // Validate input
            $violations = $this->validator->validate($queryDto);
            if (count($violations) > 0) {
                return $this->createValidationErrorResponse($violations);
            }

            // Date is required for this endpoint
            if ($queryDto->date === null) {
                return $this->json([
                    'error' => 'Validation failed',
                    'message' => 'Date parameter is required for daily rates',
                    'details' => ['date' => 'This field is required']
                ], Response::HTTP_BAD_REQUEST);
            }

            $date = $queryDto->getParsedDate();
            
            // Fetch rates from repository
            $rates = $this->rateRepository->findByDay($queryDto->pair, $date);

            if (empty($rates)) {
                $this->logger->warning('No rates found for specific day', [
                    'pair' => $queryDto->pair,
                    'date' => $queryDto->date
                ]);
                
                return $this->json([
                    'error' => 'No data available',
                    'message' => "No rates found for {$queryDto->pair} on {$queryDto->date}",
                    'pair' => $queryDto->pair,
                    'requested_date' => $queryDto->date,
                    'requested_period' => 'day'
                ], Response::HTTP_NOT_FOUND);
            }

            // Create response DTO
            $responseDto = RateResponseDto::fromRates($rates, "day:{$queryDto->date}");

            $this->logger->info('Successfully returned daily rates', [
                'pair' => $queryDto->pair,
                'date' => $queryDto->date,
                'rate_count' => count($rates)
            ]);

            return $this->json($responseDto->toArray());

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Invalid date format',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching daily rates', [
                'pair' => $queryDto->pair,
                'date' => $queryDto->date,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while fetching rate data'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Health check endpoint
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        try {
            // Simple database connectivity check
            $latestRates = [];
            foreach (['EUR/BTC', 'EUR/ETH', 'EUR/LTC'] as $pair) {
                $rate = $this->rateRepository->findLatestByPair($pair);
                if ($rate) {
                    $latestRates[$pair] = $rate->getRecordedAt()->format('Y-m-d H:i:s');
                }
            }

            return $this->json([
                'status' => 'healthy',
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'database' => 'connected',
                'latest_rates' => $latestRates
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Health check failed', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'unhealthy',
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'error' => 'Database connection failed'
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Create validation error response
     * 
     * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
     */
    private function createValidationErrorResponse($violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        $this->logger->warning('API validation failed', ['errors' => $errors]);

        return $this->json([
            'error' => 'Validation failed',
            'message' => 'The request parameters are invalid',
            'details' => $errors
        ], Response::HTTP_BAD_REQUEST);
    }
}
