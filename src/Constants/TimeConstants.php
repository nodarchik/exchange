<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Time-related constants
 * Centralized time intervals and date operations
 */
final class TimeConstants
{
    // Time intervals in seconds
    public const MINUTE = 60;
    public const FIVE_MINUTES = 300;
    public const TEN_MINUTES = 600;
    public const HOUR = 3600;
    public const DAY = 86400;

    // Time intervals for data fetching
    public const FETCH_INTERVAL = self::FIVE_MINUTES; // Fetch rates every 5 minutes
    public const DATA_FRESHNESS_THRESHOLD = self::TEN_MINUTES; // Data is fresh within 10 minutes
    
    // Relative time expressions
    public const RELATIVE_24_HOURS = '-24 hours';
    public const RELATIVE_10_MINUTES = '-10 minutes';
    public const RELATIVE_5_MINUTES = '-5 minutes';
    public const RELATIVE_1_MINUTE = '-1 minute';

    // Schedule expressions (for Symfony Scheduler)
    public const SCHEDULE_EVERY_5_MINUTES = '5 minutes';
    public const SCHEDULE_EVERY_MINUTE = '1 minute';
    public const SCHEDULE_EVERY_HOUR = '1 hour';

    // Date format constants
    public const FORMAT_DATE = 'Y-m-d';
    public const FORMAT_DATETIME = 'Y-m-d H:i:s';
    public const FORMAT_ISO8601 = \DateTimeInterface::ATOM;

    /**
     * Get DateTime object for X minutes ago
     */
    public static function getMinutesAgo(int $minutes): \DateTimeImmutable
    {
        return new \DateTimeImmutable("-{$minutes} minutes");
    }

    /**
     * Get DateTime object for X hours ago
     */
    public static function getHoursAgo(int $hours): \DateTimeImmutable
    {
        return new \DateTimeImmutable("-{$hours} hours");
    }

    /**
     * Get DateTime object for 24 hours ago
     */
    public static function get24HoursAgo(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::RELATIVE_24_HOURS);
    }

    /**
     * Get DateTime object for data freshness check
     */
    public static function getDataFreshnessThreshold(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::RELATIVE_10_MINUTES);
    }

    /**
     * Check if a date is today
     */
    public static function isToday(\DateTimeImmutable $date): bool
    {
        return $date->format(self::FORMAT_DATE) === date(self::FORMAT_DATE);
    }

    /**
     * Check if a date is in the past (before today)
     */
    public static function isPastDate(\DateTimeImmutable $date): bool
    {
        return $date < new \DateTimeImmutable('today');
    }

    /**
     * Get start of day for a given date
     */
    public static function getStartOfDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0, 0);
    }

    /**
     * Get end of day for a given date
     */
    public static function getEndOfDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(23, 59, 59);
    }
}
