<?php
namespace App\Repository;

use App\Entity\TransactionEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransactionRepo extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransactionEntity::class);
    }

    /**
     * @return TransactionEntity[]
     */
    public function findAllOrderedByNewestForUser(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('b')
            ->leftJoin('t.budget', 'b')
            ->andWhere('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.transactionId', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TransactionEntity[]
     */
    public function findRecentForUser(int $userId, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('b')
            ->leftJoin('t.budget', 'b')
            ->andWhere('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.transactionId', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Total spending per budget for one account.
     *
     * @return array<int, array{budgetId: int|string, totalSpent: string}>
     */
    public function getTotalSpentPerBudgetForUser(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->select('IDENTITY(t.budget) AS budgetId', 'SUM(t.amount) AS totalSpent')
            ->andWhere('t.budget IS NOT NULL')
            ->andWhere('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->groupBy('t.budget')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Uncategorized spending for one account.
     */
    public function getUncategorizedTotalSpentForUser(int $userId): float
    {
        return (float) $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->andWhere('t.budget IS NULL')
            ->andWhere('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
