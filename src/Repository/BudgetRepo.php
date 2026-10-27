<?php
namespace App\Repository;

use App\Entity\BudgetEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BudgetRepo extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetEntity::class);
    }

    /**
     * @return BudgetEntity[]
     */
    public function findAllOrderedByNewestForUser(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('b.budgetId', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
