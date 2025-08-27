<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Rate;
use App\Message\FetchRateMessage;
use App\Repository\RateRepository;
use App\Service\BinanceApiService;
use App\Service\RateCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async handler for rate fetching messages
 * Improves performance by processing rate updates in background
 */
#[AsMessageHandler]
class FetchRateMessageHandler
{
    public function __construct(
        private readonly BinanceApiService $binanceApiService,
        private readonly RateRepository $rateRepository,
        private readonly RateCacheService $cacheService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(FetchRateMessage $message): void
    {
        $startTime = microtime(true);
        
        $this->logger->info('Processing async rate fetch message', [
            'pairs' => $message->pairs,
            'invalidate_cache' => $message->invalidateCache,
            'requested_at' => $message->requestedAt->format('Y-m-d H:i:s')
        ]);

        try {
            // Fetch all current prices in one API call
            $prices = $this->binanceApiService->getAllCurrentPrices();
            
            $recordedAt = new \DateTimeImmutable();
            $successCount = 0;
            
            foreach ($message->pairs as $pair) {
                if (!isset($prices[$pair])) {
                    $this->logger->warning('Price not available for pair', ['pair' => $pair]);
                    continue;
                }

                try {
                    // Check for duplicates to avoid unnecessary database operations
                    if ($this->rateRepository->existsForPairAndTime($pair, $recordedAt)) {
                        continue;
                    }

                    // Create and save new rate
                    $rate = new Rate($pair, (string) $prices[$pair], $recordedAt);
                    $this->rateRepository->save($rate);
                    
                    $successCount++;
                    
                    // Invalidate cache for this pair to ensure fresh data
                    if ($message->invalidateCache) {
                        $this->cacheService->invalidatePairCache($pair);
                    }
                    
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to save rate in async handler', [
                        'pair' => $pair,
                        'price' => $prices[$pair],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Flush all changes
            if ($successCount > 0) {
                $this->entityManager->flush();
            }

            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('Async rate fetch completed', [
                'success_count' => $successCount,
                'total_pairs' => count($message->pairs),
                'duration_ms' => round($duration, 2),
                'recorded_at' => $recordedAt->format('Y-m-d H:i:s')
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Async rate fetch failed', [
                'pairs' => $message->pairs,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
            
            // Re-throw to trigger retry mechanism if configured
            throw $e;
        }
    }
}
