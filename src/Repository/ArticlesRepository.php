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
            ->andWhere('a.isOnline = true')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery();

        $query->useQueryCache(true);

        /** @var Articles[] $result */
        $result = $query->getResult(); // Assurez-vous que le type est bien Articles[]

        return $result;
    }

    /**
     * @return Articles[]
     */
    public function PaginationCategoryAndArticle(string $categoryName): array
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')
            ->andWhere('c.slug = :categoryName')
            ->setParameter('categoryName', $categoryName)
            ->andWhere('a.isOnline = true')
            ->orderBy('a.id', 'DESC')
            ->getQuery();

        /** @var Articles[] $result */
        $result = $query->getResult(); // Assurez-vous que le type est bien Articles[]

        return $result;
    }

    /**
     * @return Articles[]
     */
    public function RandomArticles(): array
    {
        $query = $this->createQueryBuilder('a')
            ->where('a.isOnline = true')
            ->orderBy('RAND()')
            ->setMaxResults(4)
            ->getQuery();

        /** @var Articles[] $result */
        $result = $query->getResult(); // Assurez-vous que le type est bien Articles[]

        return $result;
    }

    /**
     * @return Articles[]
     */
    public function findArticlesWithoutMedia(): array
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.media', 'm')
            ->where('m.id IS NULL')
            ->getQuery();

        /** @var Articles[] $result */
        $result = $query->getResult(); // Assurez-vous que le type est bien Articles[]

        return $result;
    }

    /**
     * @return Articles[]
     */
    public function findArticlesWithoutTags(): array
    {
        $query = $this->createQueryBuilder('a')
            ->leftJoin('a.tags', 't')
            ->where('t.id IS NULL')
            ->getQuery();

        /** @var Articles[] $result */
        $result = $query->getResult(); // Assurez-vous que le type est bien Articles[]

        return $result;
    }
}
