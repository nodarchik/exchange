<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Rate;
use App\Repository\RateRepository;
use App\Service\BinanceApiException;
use App\Service\BinanceApiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-rates',
    description: 'Fetch current cryptocurrency rates from Binance API and store them in database'
)]
class FetchRatesCommand extends Command
{
    public function __construct(
        private readonly BinanceApiService $binanceApiService,
        private readonly RateRepository $rateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Fetching Cryptocurrency Rates');
        
        try {
            // Check if Binance API is available
            if (!$this->binanceApiService->isApiAvailable()) {
                $io->error('Binance API is not available');
                $this->logger->error('Binance API is not available during rate fetch');
                return Command::FAILURE;
            }

            $io->info('Binance API is available, fetching rates...');

            // Fetch all current prices
            $prices = $this->binanceApiService->getAllCurrentPrices();
            
            $recordedAt = new \DateTimeImmutable();
            $successCount = 0;
            $errors = [];

            foreach ($prices as $pair => $price) {
                try {
                    // Check if rate already exists for this exact time (avoid duplicates)
                    if ($this->rateRepository->existsForPairAndTime($pair, $recordedAt)) {
                        $io->note("Rate for {$pair} at {$recordedAt->format('Y-m-d H:i:s')} already exists, skipping");
                        continue;
                    }

                    // Create and save new rate
                    $rate = new Rate($pair, (string) $price, $recordedAt);
                    $this->rateRepository->save($rate);
                    
                    $successCount++;
                    $io->success("✓ {$pair}: {$price}");
                    
                    $this->logger->info('Rate saved successfully', [
                        'pair' => $pair,
                        'price' => $price,
                        'recorded_at' => $recordedAt->format('Y-m-d H:i:s')
                    ]);

                } catch (\Throwable $e) {
                    $error = "Failed to save rate for {$pair}: {$e->getMessage()}";
                    $errors[] = $error;
                    $io->error($error);
                    
                    $this->logger->error('Failed to save rate', [
                        'pair' => $pair,
                        'price' => $price,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e)
                    ]);
                }
            }

            // Flush all changes to database
            if ($successCount > 0) {
                $this->entityManager->flush();
                $io->success("Successfully saved {$successCount} rates to database");
                
                $this->logger->info('Batch rate fetch completed', [
                    'success_count' => $successCount,
                    'error_count' => count($errors),
                    'recorded_at' => $recordedAt->format('Y-m-d H:i:s')
                ]);
            }

            if (!empty($errors)) {
                $io->warning('Some rates failed to save:');
                foreach ($errors as $error) {
                    $io->writeln("  • {$error}");
                }
                return Command::FAILURE;
            }

            if ($successCount === 0) {
                $io->warning('No new rates were saved');
                return Command::SUCCESS;
            }

            return Command::SUCCESS;

        } catch (BinanceApiException $e) {
            $io->error("Binance API error: {$e->getMessage()}");
            $this->logger->error('Binance API error during rate fetch', $e->toArray());
            return Command::FAILURE;

        } catch (\Throwable $e) {
            $io->error("Unexpected error: {$e->getMessage()}");
            $this->logger->error('Unexpected error during rate fetch', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return Command::FAILURE;
        }
    }
}
