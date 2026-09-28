<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** @return Order[] */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    public function findOneByOrderNumber(string $orderNumber): ?Order
    {
        return $this->findOneBy(['orderNumber' => $orderNumber]);
    }

    /**
     * Used to enforce "verified purchase" before allowing a review: true only if
     * $user has an order containing $product that is not pending/cancelled.
     */
    public function hasPurchased(User $user, Product $product): bool
    {
        $count = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->join('o.items', 'oi')
            ->where('o.user = :user')
            ->andWhere('oi.product = :product')
            ->andWhere('o.status NOT IN (:excluded)')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->setParameter('excluded', [Order::STATUS_PENDING, Order::STATUS_CANCELLED])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /** @return array{orders: int, revenue_cents: int} */
    public function stats(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('COUNT(o.id) as cnt', 'SUM(o.totalAmount) as revenue')
            ->where('o.status != :cancelled')
            ->setParameter('cancelled', Order::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleResult();

        return [
            'orders' => (int) $rows['cnt'],
            'revenue_cents' => (int) round(((float) ($rows['revenue'] ?? 0)) * 100),
        ];
    }
}
