<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the order state machine only allows the transitions the business
 * actually needs, and rejects everything else -- including skipping states
 * (pending -> shipped) and reviving a terminal order.
 */
class OrderStatusTest extends TestCase
{
    private function makeOrder(): Order
    {
        $category = new Category('Test', 'test');
        $product = new Product('P', 'p', 'd', '10.00', 5, $category);
        $user = new User('a@b.com', 'A', 'B');

        return new Order($user, 'CMD-TEST-0001', ['fullName' => 'A B', 'address' => 'x', 'city' => 'x', 'postalCode' => 'x', 'country' => 'FR']);
    }

    public function testStartsAsPending(): void
    {
        $this->assertSame(Order::STATUS_PENDING, $this->makeOrder()->getStatus());
    }

    public function testPendingCanBeConfirmedOrCancelled(): void
    {
        $order = $this->makeOrder();
        $this->assertTrue($order->canTransitionTo(Order::STATUS_CONFIRMED));
        $this->assertTrue($order->canTransitionTo(Order::STATUS_CANCELLED));
        $this->assertFalse($order->canTransitionTo(Order::STATUS_SHIPPED));
    }

    public function testFullHappyPathTransitions(): void
    {
        $order = $this->makeOrder();
        $order->transitionTo(Order::STATUS_CONFIRMED);
        $order->transitionTo(Order::STATUS_PREPARING);
        $order->transitionTo(Order::STATUS_SHIPPED);
        $order->transitionTo(Order::STATUS_DELIVERED);
        $this->assertSame(Order::STATUS_DELIVERED, $order->getStatus());
    }

    public function testCannotSkipStates(): void
    {
        $order = $this->makeOrder();
        $this->expectException(\DomainException::class);
        $order->transitionTo(Order::STATUS_SHIPPED);
    }

    public function testDeliveredIsTerminal(): void
    {
        $order = $this->makeOrder();
        $order->transitionTo(Order::STATUS_CONFIRMED);
        $order->transitionTo(Order::STATUS_PREPARING);
        $order->transitionTo(Order::STATUS_SHIPPED);
        $order->transitionTo(Order::STATUS_DELIVERED);
        $this->assertFalse($order->isCancellable());
        $this->expectException(\DomainException::class);
        $order->transitionTo(Order::STATUS_CANCELLED);
    }

    public function testShippedOrderIsNotCancellable(): void
    {
        $order = $this->makeOrder();
        $order->transitionTo(Order::STATUS_CONFIRMED);
        $order->transitionTo(Order::STATUS_PREPARING);
        $order->transitionTo(Order::STATUS_SHIPPED);
        $this->assertFalse($order->isCancellable());
    }
}
