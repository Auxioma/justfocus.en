<?php

namespace App\Repository;

use App\Entity\Articles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Articles>
 */
class ArticlesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Articles::class);
    }

    /**
     * @return Articles[]
     */
    public function findByCategory(string $categoryName): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.categories', 'c')
            ->andWhere('c.slugSql = :categoryName')
            ->setParameter('categoryName', $categoryName)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }
}
