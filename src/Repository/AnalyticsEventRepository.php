<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\AnalyticsEvent;

/**
 * @extends ServiceEntityRepository<AnalyticsEvent>
 */
class AnalyticsEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsEvent::class);
    }

    public function countByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.recordedAt >= :from')
            ->andWhere('e.recordedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{name: string, occurrences: int, distinctValues: int}>
     */
    public function findTopEvents(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.name, COUNT(e.id) AS occurrences, COUNT(DISTINCT e.value) AS distinctValues')
            ->where('e.recordedAt >= :from')
            ->andWhere('e.recordedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('e.name')
            ->orderBy('occurrences', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countDistinctTypes(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.name)')
            ->where('e.recordedAt >= :from')
            ->andWhere('e.recordedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUniqueActors(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.fingerprint)')
            ->where('e.recordedAt >= :from')
            ->andWhere('e.recordedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function countByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                TO_CHAR(recorded_at, 'YYYY-MM-DD') AS date,
                COUNT(*) AS count
            FROM ca_analytics_event
            WHERE recorded_at >= :from AND recorded_at <= :to
            GROUP BY date
            ORDER BY date ASC
        SQL;

        return $conn->executeQuery($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();
    }
}
