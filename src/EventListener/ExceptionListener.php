<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\BinanceApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Global exception handler for consistent error responses
 * Ensures all exceptions are properly logged and formatted
 */
class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $environment
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests (paths starting with /api/)
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $this->logger->error('API Exception occurred', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => $request->getUri(),
            'method' => $request->getMethod(),
            'user_agent' => $request->headers->get('User-Agent'),
            'ip' => $request->getClientIp(),
        ]);

        $response = $this->createApiErrorResponse($exception);
        $event->setResponse($response);
    }

    private function createApiErrorResponse(\Throwable $exception): JsonResponse
    {
        // Handle different exception types
        match (true) {
            $exception instanceof BinanceApiException => $response = $this->handleBinanceApiException($exception),
            $exception instanceof ValidationFailedException => $response = $this->handleValidationException($exception),
            $exception instanceof HttpExceptionInterface => $response = $this->handleHttpException($exception),
            default => $response = $this->handleGenericException($exception)
        };

        return $response;
    }

    private function handleBinanceApiException(BinanceApiException $exception): JsonResponse
    {
        $data = [
            'error' => 'External API Error',
            'message' => 'Unable to fetch cryptocurrency data from external provider',
            'pair' => $exception->getPair(),
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        // Include more details in development
        if ($this->environment === 'dev') {
            $data['debug'] = [
                'original_message' => $exception->getMessage(),
                'context' => $exception->getContext(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_SERVICE_UNAVAILABLE);
    }

    private function handleValidationException(ValidationFailedException $exception): JsonResponse
    {
        $violations = $exception->getViolations();
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return new JsonResponse([
            'error' => 'Validation Failed',
            'message' => 'The request data is invalid',
            'details' => $errors,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_BAD_REQUEST);
    }

    private function handleHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $statusCode = $exception->getStatusCode();
        
        $data = [
            'error' => Response::$statusTexts[$statusCode] ?? 'HTTP Error',
            'message' => $exception->getMessage() ?: 'An HTTP error occurred',
            'status_code' => $statusCode,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        // Include debug info in development
        if ($this->environment === 'dev' && method_exists($exception, 'getHeaders')) {
            $data['debug'] = [
                'headers' => $exception->getHeaders(),
            ];
        }

        return new JsonResponse($data, $statusCode);
    }

    private function handleGenericException(\Throwable $exception): JsonResponse
    {
        $data = [
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        // Include more details in development
        if ($this->environment === 'dev') {
            $data['debug'] = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
