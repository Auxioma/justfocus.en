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

    public function save(Articles $article): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->beginTransaction();
        try {
            $entityManager->persist($article);
            $entityManager->flush();
            $entityManager->commit();
        } catch (\Exception $e) {
            $entityManager->rollback();
            throw $e;
        }
    }

    /**
     * @return Articles[]
     */
    public function findByCategory(string $categoryName): array
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')
            ->andWhere('c.slugSql = :categoryName')
            ->setParameter('categoryName', $categoryName)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery();

        $query->useQueryCache(true); // Activer le cache de requête

        return $query->getResult();
    }

    /**
     * @return Articles[]
     */
    public function RandomArticles(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('RAND()')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult()
        ;
    }
}
