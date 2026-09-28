<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Exception\EmptyCartException;
use App\Repository\CartRepository;
use App\Service\EmailService;
use App\Service\SluggerService;
use App\Service\StockService;
use App\Util\Money;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A deliberately naive reimplementation of OrderService::placeOrder() with NO
 * transaction and NO pessimistic lock: it reads stock, then writes it, with no
 * protection against another process doing the same thing in between.
 *
 * This class exists ONLY to be used side-by-side with the real OrderService in
 * OrderConcurrencyTest, to empirically demonstrate what the pessimistic lock in
 * the real service actually prevents. It is never wired into the application
 * and is not used by any controller.
 */
class NaiveOrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartRepository $carts,
        private readonly StockService $stock,
        private readonly SluggerService $slugger,
        private readonly EmailService $emails,
    ) {
    }

    public function placeOrder(User $user, array $shippingAddress): Order
    {
        $cart = $this->carts->findOneByUser($user);
        if (null === $cart || $cart->getItems()->isEmpty()) {
            throw new EmptyCartException('Cannot place an order from an empty cart.');
        }

        /** @var CartItem[] $lines */
        $lines = $cart->getItems()->toArray();

        // No lock: read stock now, decide now. Another process can do the exact
        // same read before this process writes its decrement.
        foreach ($lines as $line) {
            $this->em->refresh($line->getProduct());
            $this->stock->assertAvailable($line->getProduct(), $line->getQuantity());
        }

        $order = new Order($user, $this->slugger->generateOrderNumber(), $shippingAddress);
        $totalCents = 0;
        foreach ($lines as $line) {
            $product = $line->getProduct();
            $this->stock->decrement($product, $line->getQuantity());
            $order->addItem(new OrderItem($order, $product, $line->getQuantity()));
            $totalCents += Money::multiply($product->getPrice(), $line->getQuantity());
        }
        $order->setTotalAmount(Money::fromCents($totalCents));

        $this->em->persist($order);
        $cart->clear();
        $this->em->flush();

        $this->emails->sendOrderConfirmation($order);

        return $order;
    }
}
