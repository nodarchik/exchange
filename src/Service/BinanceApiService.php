<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\CryptoPairs;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Service for interacting with Binance API
 * Follows single responsibility principle for external API communication
 */
class BinanceApiService
{
    private const BASE_URL = 'https://api.binance.com/api/v3';
    private const TIMEOUT = 10;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1000; // milliseconds

    /**
     * Use mapping from constants
     */
    private const PAIR_MAPPING = CryptoPairs::BINANCE_SYMBOLS;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Fetch current price for a specific cryptocurrency pair
     * 
     * @throws BinanceApiException When API call fails or returns invalid data
     */
    public function getCurrentPrice(string $pair): float
    {
        $symbol = $this->mapPairToSymbol($pair);
        
        $this->logger->info('Fetching current price from Binance', [
            'pair' => $pair,
            'symbol' => $symbol
        ]);

        try {
            $response = $this->makeRequest('/ticker/price', ['symbol' => $symbol]);
            
            if (!isset($response['price'])) {
                throw new BinanceApiException(
                    "Invalid response format: missing 'price' field",
                    $pair,
                    $response
                );
            }

            $price = (float) $response['price'];
            
            if ($price <= 0) {
                throw new BinanceApiException(
                    "Invalid price received: {$price}",
                    $pair,
                    $response
                );
            }

            $this->logger->info('Successfully fetched price from Binance', [
                'pair' => $pair,
                'price' => $price
            ]);

            return $price;

        } catch (BinanceApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error fetching price from Binance', [
                'pair' => $pair,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
            
            throw new BinanceApiException(
                "Unexpected error: {$e->getMessage()}",
                $pair,
                null,
                $e
            );
        }
    }

    /**
     * Fetch current prices for all supported pairs
     * More efficient than individual calls
     * 
     * @return array<string, float> Array with pair as key and price as value
     * @throws BinanceApiException When API call fails
     */
    public function getAllCurrentPrices(): array
    {
        $this->logger->info('Fetching all current prices from Binance');

        try {
            $symbols = array_values(self::PAIR_MAPPING);
            $symbolsParam = implode(',', $symbols);
            
            $response = $this->makeRequest('/ticker/price', ['symbols' => "[\"" . implode('","', $symbols) . "\"]"]);
            
            if (!is_array($response)) {
                throw new BinanceApiException(
                    "Invalid response format: expected array",
                    'ALL_PAIRS',
                    $response
                );
            }

            $prices = [];
            $symbolToPair = array_flip(self::PAIR_MAPPING);

            foreach ($response as $item) {
                if (!isset($item['symbol'], $item['price'])) {
                    $this->logger->warning('Invalid item in response', ['item' => $item]);
                    continue;
                }

                $symbol = $item['symbol'];
                if (!isset($symbolToPair[$symbol])) {
                    continue; // Skip symbols we don't track
                }

                $pair = $symbolToPair[$symbol];
                $price = (float) $item['price'];

                if ($price <= 0) {
                    $this->logger->warning('Invalid price for symbol', [
                        'symbol' => $symbol,
                        'price' => $price
                    ]);
                    continue;
                }

                $prices[$pair] = $price;
            }

            if (empty($prices)) {
                throw new BinanceApiException(
                    "No valid prices received from API",
                    'ALL_PAIRS',
                    $response
                );
            }

            $this->logger->info('Successfully fetched all prices from Binance', [
                'pairs_count' => count($prices),
                'pairs' => array_keys($prices)
            ]);

            return $prices;

        } catch (BinanceApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error fetching all prices from Binance', [
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
            
            throw new BinanceApiException(
                "Unexpected error: {$e->getMessage()}",
                'ALL_PAIRS',
                null,
                $e
            );
        }
    }

    /**
     * Check if Binance API is available
     */
    public function isApiAvailable(): bool
    {
        try {
            $this->makeRequest('/ping');
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Binance API health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get supported trading pairs
     * 
     * @return array<string> List of supported pairs
     */
    public function getSupportedPairs(): array
    {
        return array_keys(self::PAIR_MAPPING);
    }

    /**
     * Map internal pair format to Binance symbol
     * 
     * @throws BinanceApiException When pair is not supported
     */
    private function mapPairToSymbol(string $pair): string
    {
        if (!isset(self::PAIR_MAPPING[$pair])) {
            throw new BinanceApiException(
                "Unsupported trading pair: {$pair}. Supported pairs: " . implode(', ', array_keys(self::PAIR_MAPPING)),
                $pair
            );
        }

        return self::PAIR_MAPPING[$pair];
    }

    /**
     * Make HTTP request to Binance API with retry logic
     * 
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws BinanceApiException
     */
    private function makeRequest(string $endpoint, array $params = []): array
    {
        $url = self::BASE_URL . $endpoint;
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                $response = $this->httpClient->request('GET', $url, [
                    'query' => $params,
                    'timeout' => self::TIMEOUT,
                    'headers' => [
                        'Accept' => 'application/json',
                        'User-Agent' => 'CryptoExchange/1.0'
                    ]
                ]);

                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    throw new BinanceApiException(
                        "HTTP {$statusCode} response from Binance API",
                        $endpoint,
                        ['url' => $url, 'params' => $params]
                    );
                }

                $data = $response->toArray();
                
                $this->logger->debug('Binance API request successful', [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'response_size' => is_array($data) ? count($data) : strlen(json_encode($data))
                ]);

                return $data;

            } catch (TransportExceptionInterface $e) {
                $this->logger->warning('Transport error on Binance API request', [
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= self::MAX_RETRIES) {
                    throw new BinanceApiException(
                        "Transport error after {$attempt} attempts: {$e->getMessage()}",
                        $endpoint,
                        ['url' => $url, 'params' => $params],
                        $e
                    );
                }

                // Wait before retry
                usleep(self::RETRY_DELAY * 1000 * $attempt);
                continue;

            } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
                throw new BinanceApiException(
                    "HTTP error: {$e->getMessage()}",
                    $endpoint,
                    ['url' => $url, 'params' => $params],
                    $e
                );

            } catch (DecodingExceptionInterface $e) {
                throw new BinanceApiException(
                    "JSON decoding error: {$e->getMessage()}",
                    $endpoint,
                    ['url' => $url, 'params' => $params],
                    $e
                );
            }
        }

        throw new BinanceApiException(
            "Failed after {$attempt} attempts",
            $endpoint,
            ['url' => $url, 'params' => $params]
        );
    }
}
