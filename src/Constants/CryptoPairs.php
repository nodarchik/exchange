<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Cryptocurrency trading pairs constants
 * Centralized definition of all supported trading pairs
 */
final class CryptoPairs
{
    // Supported trading pairs
    public const EUR_BTC = 'EUR/BTC';
    public const EUR_ETH = 'EUR/ETH';
    public const EUR_LTC = 'EUR/LTC';

    // All supported pairs array
    public const ALL_SUPPORTED = [
        self::EUR_BTC,
        self::EUR_ETH,
        self::EUR_LTC,
    ];

    // Base currencies
    public const BASE_CURRENCY_EUR = 'EUR';

    // Quote currencies
    public const QUOTE_CURRENCY_BTC = 'BTC';
    public const QUOTE_CURRENCY_ETH = 'ETH';
    public const QUOTE_CURRENCY_LTC = 'LTC';

    // All quote currencies
    public const ALL_QUOTE_CURRENCIES = [
        self::QUOTE_CURRENCY_BTC,
        self::QUOTE_CURRENCY_ETH,
        self::QUOTE_CURRENCY_LTC,
    ];

    // Binance API symbol mappings
    public const BINANCE_SYMBOLS = [
        self::EUR_BTC => 'BTCEUR',
        self::EUR_ETH => 'ETHEUR',
        self::EUR_LTC => 'LTCEUR',
    ];

    /**
     * Get all supported trading pairs
     */
    public static function getAllSupported(): array
    {
        return self::ALL_SUPPORTED;
    }

    /**
     * Check if a pair is supported
     */
    public static function isSupported(string $pair): bool
    {
        return in_array($pair, self::ALL_SUPPORTED, true);
    }

    /**
     * Get Binance symbol for a trading pair
     */
    public static function getBinanceSymbol(string $pair): ?string
    {
        return self::BINANCE_SYMBOLS[$pair] ?? null;
    }

    /**
     * Get base currency from pair (e.g., EUR from EUR/BTC)
     */
    public static function getBaseCurrency(string $pair): string
    {
        return explode('/', $pair)[0];
    }

    /**
     * Get quote currency from pair (e.g., BTC from EUR/BTC)
     */
    public static function getQuoteCurrency(string $pair): string
    {
        return explode('/', $pair)[1];
    }

    /**
     * Get formatted pair list for error messages
     */
    public static function getSupportedPairsString(): string
    {
        return implode(', ', self::ALL_SUPPORTED);
    }
}
