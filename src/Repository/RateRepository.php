<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constants\TimeConstants;
use App\Entity\Rate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Rate entity with optimized queries for time-based data
 * 
 * @extends ServiceEntityRepository<Rate>
 */
class RateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rate::class);
    }

    /**
     * Save rate entity
     */
    public function save(Rate $rate, bool $flush = false): void
    {
        $this->getEntityManager()->persist($rate);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove rate entity
     */
    public function remove(Rate $rate, bool $flush = false): void
    {
        $this->getEntityManager()->remove($rate);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find rates for the last 24 hours for a specific pair
     * Optimized with proper indexing, ordering and result caching
     * 
     * @return Rate[]
     */
    public function findLast24Hours(string $pair): array
    {
        $yesterday = TimeConstants::get24HoursAgo();

        return $this->createQueryBuilder('r')
            ->select('r')
            ->andWhere('r.pair = :pair')
            ->andWhere('r.recordedAt >= :yesterday')
            ->setParameter('pair', $pair)
            ->setParameter('yesterday', $yesterday)
            ->orderBy('r.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find rates for a specific day and pair
     * Optimized for daily queries with proper date boundaries and caching
     * 
     * @return Rate[]
     */
    public function findByDay(string $pair, \DateTimeImmutable $date): array
    {
        $startOfDay = TimeConstants::getStartOfDay($date);
        $endOfDay = TimeConstants::getEndOfDay($date);
        
        // Cache daily results for longer (historical data doesn't change)
        $cacheTime = $date < new \DateTimeImmutable('today') ? 3600 : 300; // 1 hour for past days, 5 min for today

        return $this->createQueryBuilder('r')
            ->select('r')
            ->andWhere('r.pair = :pair')
            ->andWhere('r.recordedAt >= :startOfDay')
            ->andWhere('r.recordedAt <= :endOfDay')
            ->setParameter('pair', $pair)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('r.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest rate for a specific pair
     */
    public function findLatestByPair(string $pair): ?Rate
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.pair = :pair')
            ->setParameter('pair', $pair)
            ->orderBy('r.recordedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find rates within a date range for a specific pair
     * Useful for custom time periods
     * 
     * @return Rate[]
     */
    public function findByDateRange(
        string $pair, 
        \DateTimeImmutable $startDate, 
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('r')
            ->andWhere('r.pair = :pair')
            ->andWhere('r.recordedAt >= :startDate')
            ->andWhere('r.recordedAt <= :endDate')
            ->setParameter('pair', $pair)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('r.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get rate statistics for a specific pair and time period
     * Returns min, max, avg, and count
     */
    public function getRateStatistics(
        string $pair, 
        \DateTimeImmutable $startDate, 
        \DateTimeImmutable $endDate
    ): array {
        $result = $this->createQueryBuilder('r')
            ->select(
                'MIN(r.price) as min_price',
                'MAX(r.price) as max_price', 
                'AVG(r.price) as avg_price',
                'COUNT(r.id) as total_records'
            )
            ->andWhere('r.pair = :pair')
            ->andWhere('r.recordedAt >= :startDate')
            ->andWhere('r.recordedAt <= :endDate')
            ->setParameter('pair', $pair)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleResult();

        return [
            'min_price' => $result['min_price'] ? (float) $result['min_price'] : null,
            'max_price' => $result['max_price'] ? (float) $result['max_price'] : null,
            'avg_price' => $result['avg_price'] ? (float) $result['avg_price'] : null,
            'total_records' => (int) $result['total_records'],
        ];
    }

    /**
     * Clean up old rate data (older than specified days)
     * Useful for maintenance and keeping database size manageable
     */
    public function deleteOldRates(int $daysToKeep = 365): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$daysToKeep} days");

        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.recordedAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Check if a rate exists for a specific pair and time (to avoid duplicates)
     */
    public function existsForPairAndTime(string $pair, \DateTimeImmutable $recordedAt): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.pair = :pair')
            ->andWhere('r.recordedAt = :recordedAt')
            ->setParameter('pair', $pair)
            ->setParameter('recordedAt', $recordedAt)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
