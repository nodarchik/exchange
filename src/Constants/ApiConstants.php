<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * API-related constants
 * HTTP status codes, response formats, and API configurations
 */
final class ApiConstants
{
    // API Response Types
    public const RESPONSE_TYPE_24H = 'last-24h';
    public const RESPONSE_TYPE_DAY = 'day';
    public const RESPONSE_TYPE_HEALTH = 'health';

    // API Error Messages
    public const ERROR_VALIDATION_FAILED = 'Validation failed';
    public const ERROR_INVALID_PAIR = 'Invalid pair';
    public const ERROR_NO_DATA_AVAILABLE = 'No data available';
    public const ERROR_SERVICE_ERROR = 'Service error';
    public const ERROR_INTERNAL_SERVER = 'Internal server error';
    public const ERROR_INVALID_DATE_FORMAT = 'Invalid date format';

    // API Success Messages
    public const MESSAGE_NO_RATES_24H = 'No rates found for {pair} in the last 24 hours';
    public const MESSAGE_NO_RATES_DAY = 'No rates found for {pair} on {date}';
    public const MESSAGE_DATE_REQUIRED = 'Date parameter is required for daily rates';
    public const MESSAGE_INVALID_PARAMETERS = 'The request parameters are invalid';
    public const MESSAGE_SERVICE_ERROR = 'An error occurred while fetching rate data';

    // Health Check Statuses
    public const HEALTH_STATUS_HEALTHY = 'healthy';
    public const HEALTH_STATUS_DEGRADED = 'degraded';
    public const HEALTH_STATUS_UNHEALTHY = 'unhealthy';

    // Database Status
    public const DB_STATUS_CONNECTED = 'connected';
    public const DB_STATUS_ERROR = 'error';

    // Validation Error Templates
    public const VALIDATION_UNSUPPORTED_PAIR = 'Unsupported pair. Supported pairs are: {pairs}';
    public const VALIDATION_REQUIRED_FIELD = 'This field is required';

    /**
     * Get formatted message for no rates found (24h)
     */
    public static function getNoRates24hMessage(string $pair): string
    {
        return str_replace('{pair}', $pair, self::MESSAGE_NO_RATES_24H);
    }

    /**
     * Get formatted message for no rates found (daily)
     */
    public static function getNoRatesDayMessage(string $pair, string $date): string
    {
        return str_replace(
            ['{pair}', '{date}'],
            [$pair, $date],
            self::MESSAGE_NO_RATES_DAY
        );
    }

    /**
     * Get formatted validation message for unsupported pair
     */
    public static function getUnsupportedPairMessage(): string
    {
        return str_replace(
            '{pairs}',
            CryptoPairs::getSupportedPairsString(),
            self::VALIDATION_UNSUPPORTED_PAIR
        );
    }

    /**
     * Get response type for daily endpoint
     */
    public static function getDayResponseType(string $date): string
    {
        return self::RESPONSE_TYPE_DAY . ":{$date}";
    }
}
