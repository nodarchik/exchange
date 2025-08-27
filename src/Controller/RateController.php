<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RateQueryDto;
use App\Service\HealthCheckService;
use App\Service\RateService;
use App\Service\RateServiceException;
use App\Service\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Controller for cryptocurrency exchange rates
 * Provides clean, RESTful endpoints for rate data consumption
 * All business logic delegated to service layer
 */
#[Route('/api/rates', name: 'api_rates_')]
class RateController extends AbstractController
{
    public function __construct(
        private readonly RateService $rateService,
        private readonly ValidationService $validationService,
        private readonly HealthCheckService $healthCheckService,
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

        // Validate request
        $validationResult = $this->validationService->validateRateQuery($queryDto);
        if (!$validationResult->isValid) {
            return $this->createErrorResponse(
                'Validation failed',
                $validationResult->message,
                $validationResult->errors,
                Response::HTTP_BAD_REQUEST
            );
        }

        // Additional pair validation
        $pairValidation = $this->validationService->validateSupportedPair($queryDto->pair);
        if (!$pairValidation->isValid) {
            return $this->createErrorResponse(
                'Invalid pair',
                $pairValidation->message,
                $pairValidation->errors,
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $responseDto = $this->rateService->getLast24HoursRates($queryDto->pair);

            if ($responseDto === null) {
                return $this->createErrorResponse(
                    'No data available',
                    "No rates found for {$queryDto->pair} in the last 24 hours",
                    ['pair' => $queryDto->pair, 'requested_period' => 'last-24h'],
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->json($responseDto->toArray());

        } catch (RateServiceException $e) {
            return $this->createErrorResponse(
                'Service error',
                'An error occurred while fetching rate data',
                [],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
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

        // Validate request
        $validationResult = $this->validationService->validateRateQuery($queryDto);
        if (!$validationResult->isValid) {
            return $this->createErrorResponse(
                'Validation failed',
                $validationResult->message,
                $validationResult->errors,
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate date requirement
        $dateValidation = $this->validationService->validateDateRequirement($queryDto);
        if (!$dateValidation->isValid) {
            return $this->createErrorResponse(
                'Validation failed',
                $dateValidation->message,
                $dateValidation->errors,
                Response::HTTP_BAD_REQUEST
            );
        }

        // Additional pair validation
        $pairValidation = $this->validationService->validateSupportedPair($queryDto->pair);
        if (!$pairValidation->isValid) {
            return $this->createErrorResponse(
                'Invalid pair',
                $pairValidation->message,
                $pairValidation->errors,
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $date = $queryDto->getParsedDate();
            $responseDto = $this->rateService->getDailyRates($queryDto->pair, $date);

            if ($responseDto === null) {
                return $this->createErrorResponse(
                    'No data available',
                    "No rates found for {$queryDto->pair} on {$queryDto->date}",
                    [
                        'pair' => $queryDto->pair,
                        'requested_date' => $queryDto->date,
                        'requested_period' => 'day'
                    ],
                    Response::HTTP_NOT_FOUND
                );
            }

            return $this->json($responseDto->toArray());

        } catch (\InvalidArgumentException $e) {
            return $this->createErrorResponse(
                'Invalid date format',
                $e->getMessage(),
                [],
                Response::HTTP_BAD_REQUEST
            );

        } catch (RateServiceException $e) {
            return $this->createErrorResponse(
                'Service error',
                'An error occurred while fetching rate data',
                [],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Health check endpoint with comprehensive system diagnostics
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        try {
            $healthStatus = $this->healthCheckService->getHealthStatus();
            
            return $this->json(
                $healthStatus->toArray(),
                $healthStatus->getHttpStatusCode()
            );

        } catch (\Throwable $e) {
            $this->logger->error('Health check failed', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'unhealthy',
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'error' => 'Health check failed'
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Create standardized error response
     */
    private function createErrorResponse(
        string $error,
        string $message,
        array $details = [],
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR
    ): JsonResponse {
        $response = [
            'error' => $error,
            'message' => $message
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        return $this->json($response, $statusCode);
    }
}