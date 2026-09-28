<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findOneBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** @return Product[] */
    public function findByCategory(Category $category): array
    {
        return $this->findBy(['category' => $category], ['createdAt' => 'DESC']);
    }

    /** @return Product[] */
    public function search(string $query): array
    {
        $escaped = addcslashes(trim($query), '%_\\');

        return $this->createQueryBuilder('p')
            ->where('LOWER(p.name) LIKE :q')
            ->orWhere('LOWER(p.description) LIKE :q')
            ->setParameter('q', '%'.strtolower($escaped).'%')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /** @return Product[] */
    public function findLatest(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
