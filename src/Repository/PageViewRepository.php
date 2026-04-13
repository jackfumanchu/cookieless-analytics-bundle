<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Jackfumanchu\CookielessAnalyticsBundle\Entity\PageView;
use Jackfumanchu\CookielessAnalyticsBundle\Service\SqlDialect;

/**
 * @extends ServiceEntityRepository<PageView>
 */
class PageViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly SqlDialect $dialect)
    {
        parent::__construct($registry, PageView::class);
    }

    public function countByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUniqueVisitorsByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.fingerprint)')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByPeriodForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->andWhere('p.pageUrl = :url')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('url', $pageUrl)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUniqueVisitorsByPeriodForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.fingerprint)')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->andWhere('p.pageUrl = :url')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('url', $pageUrl)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{pageUrl: string, views: int, uniqueVisitors: int}>
     */
    public function findTopPages(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 20, ?string $search = null, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.pageUrl, COUNT(p.id) AS views, COUNT(DISTINCT p.fingerprint) AS uniqueVisitors')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        /** @infection-ignore-all — &&→|| adds LIKE '%%' which matches everything; result identical */
        if ($search !== null && $search !== '') {
            $qb->andWhere('p.pageUrl LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->groupBy('p.pageUrl')
            ->orderBy('views', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countDistinctPages(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $search = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.pageUrl)')
            ->where('p.viewedAt >= :from')
            ->andWhere('p.viewedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        /** @infection-ignore-all — &&→|| adds LIKE '%%' which matches everything; result identical */
        if ($search !== null && $search !== '') {
            $qb->andWhere('p.pageUrl LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<array{date: string, count: int, unique: int}>
     */
    public function countByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $dateExpr = $this->dialect->dateToDay('viewed_at');

        $sql = "
            SELECT
                {$dateExpr} AS date,
                COUNT(*) AS count,
                COUNT(DISTINCT fingerprint) AS unique
            FROM ca_page_view
            WHERE viewed_at >= :from AND viewed_at <= :to
            GROUP BY date
            ORDER BY date ASC
        ";

        return $conn->executeQuery($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();
    }

    /**
     * @return list<array{source: string, visits: int}>
     */
    public function findTopReferrers(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT source, visits FROM (
                SELECT
                    CASE
                        WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                        ELSE SUBSTRING(referrer FROM '://([^/]+)')
                    END AS source,
                    COUNT(*) AS visits
                FROM ca_page_view
                WHERE viewed_at >= :from AND viewed_at <= :to
                GROUP BY source
            ) sub
            ORDER BY visits DESC, source = 'Direct' ASC, source ASC
            LIMIT :limit
        SQL;

        return $conn->executeQuery($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
            'limit' => $limit,
        ])->fetchAllAssociative();
    }

    /**
     * @return list<array{source: string, visits: int}>
     */
    public function findTopReferrersForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT source, visits FROM (
                SELECT
                    CASE
                        WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                        ELSE SUBSTRING(referrer FROM '://([^/]+)')
                    END AS source,
                    COUNT(*) AS visits
                FROM ca_page_view
                WHERE viewed_at >= :from AND viewed_at <= :to AND page_url = :pageUrl
                GROUP BY source
            ) sub
            ORDER BY visits DESC, source = 'Direct' ASC, source ASC
            LIMIT :limit
        SQL;

        return $conn->executeQuery($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
            'pageUrl' => $pageUrl,
            'limit' => $limit,
        ])->fetchAllAssociative();
    }

    /**
     * @return list<array{date: string, count: int, unique: int}>
     */
    public function countByDayForPage(string $pageUrl, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $dateExpr = $this->dialect->dateToDay('viewed_at');

        $sql = "
            SELECT
                {$dateExpr} AS date,
                COUNT(*) AS count,
                COUNT(DISTINCT fingerprint) AS unique
            FROM ca_page_view
            WHERE viewed_at >= :from AND viewed_at <= :to AND page_url = :pageUrl
            GROUP BY date
            ORDER BY date ASC
        ";

        return $conn->executeQuery($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
            'pageUrl' => $pageUrl,
        ])->fetchAllAssociative();
    }

    public function findEarliestViewedAt(): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('p')
            ->select('MIN(p.viewedAt)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? new \DateTimeImmutable($result) : null;
    }
}
