<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\User;
use App\Exception\EmptyCartException;
use App\Exception\OutOfStockException;
use App\Repository\CartRepository;
use App\Util\Money;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * placeOrder() is the concurrency-critical path in the whole application.
 *
 * Correctness requirement: when N users try to buy the last unit(s) of the same
 * product(s) at the same instant, exactly as many orders succeed as there is stock,
 * never more (no oversell) — and none of them deadlock each other into failure
 * under normal load.
 *
 * How it's achieved:
 *  1. A single DBAL transaction wraps the whole read-check-write sequence.
 *  2. Every product line is locked with SELECT ... FOR UPDATE (LockMode::PESSIMISTIC_WRITE)
 *     via EntityManager::refresh(), so a second transaction trying to touch the same
 *     row blocks until the first commits or rolls back — eliminating the classic
 *     read-then-write race (check stock, then decrement, with another transaction's
 *     write landing in between).
 *  3. Cart lines are locked in ascending product-ID order (deterministic ordering)
 *     specifically so that two transactions locking the same *set* of products
 *     never do so in opposite order — that's what causes a distributed deadlock
 *     in InnoDB/MariaDB, which lets a client legitimately end up with a
 *     DeadlockException instead of a clean success/failure.
 */
class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartRepository $carts,
        private readonly StockService $stock,
        private readonly SluggerService $slugger,
        private readonly EmailService $emails,
    ) {
    }

    /** @param array<string,string> $shippingAddress */
    public function placeOrder(User $user, array $shippingAddress): Order
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $cart = $this->carts->findOneByUser($user);
            if (null === $cart || $cart->getItems()->isEmpty()) {
                throw new EmptyCartException('Cannot place an order from an empty cart.');
            }

            /** @var CartItem[] $lines */
            $lines = $cart->getItems()->toArray();
            usort(
                $lines,
                static fn (CartItem $a, CartItem $b): int => $a->getProduct()->getId() <=> $b->getProduct()->getId()
            );

            foreach ($lines as $line) {
                $this->em->refresh($line->getProduct(), LockMode::PESSIMISTIC_WRITE);
            }

            foreach ($lines as $line) {
                $this->stock->assertAvailable($line->getProduct(), $line->getQuantity());
            }

            $order = new Order($user, $this->slugger->generateOrderNumber(), $shippingAddress);
            $totalCents = 0;
            foreach ($lines as $line) {
                $product = $line->getProduct();
                $this->stock->decrement($product, $line->getQuantity());
                $order->addItem(new \App\Entity\OrderItem($order, $product, $line->getQuantity()));
                $totalCents += Money::multiply($product->getPrice(), $line->getQuantity());
            }
            $order->setTotalAmount(Money::fromCents($totalCents));

            $this->em->persist($order);
            $cart->clear();
            $this->em->flush();
            $connection->commit();
        } catch (EmptyCartException|OutOfStockException $e) {
            $connection->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            $connection->rollBack();
            // The EntityManager is unusable after any non-business exception
            // (e.g. a real DB deadlock) inside a flush; close it so Symfony
            // rebuilds a fresh one on the next request/call in this process.
            $this->em->close();
            throw $e;
        }

        $this->emails->sendOrderConfirmation($order);

        return $order;
    }

    public function cancel(Order $order): void
    {
        if (!$order->isCancellable()) {
            throw new \DomainException('This order can no longer be cancelled.');
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($order->getItems() as $item) {
                $this->em->refresh($item->getProduct(), LockMode::PESSIMISTIC_WRITE);
                $this->stock->restore($item->getProduct(), $item->getQuantity());
            }
            $order->transitionTo(Order::STATUS_CANCELLED);
            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->em->close();
            throw $e;
        }
    }

    public function updateStatus(Order $order, string $newStatus): void
    {
        $order->transitionTo($newStatus);
        $this->em->flush();
    }
}
