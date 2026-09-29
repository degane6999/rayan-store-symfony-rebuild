<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Order;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderPaymentTest extends TestCase
{
    private function newOrder(): Order
    {
        return new Order(new User('client@test.local', 'Client', 'Test'), 'CMD-TEST-PAY', [
            'fullName' => 'X', 'address' => 'x', 'city' => 'x', 'postalCode' => 'x', 'country' => 'FR',
        ]);
    }

    public function testPaymentConfirmsThePendingOrder(): void
    {
        $order = $this->newOrder();
        $this->assertTrue($order->isPayable());
        $this->assertFalse($order->isPaid());

        $order->markAsPaid('SIM-ABC123');

        $this->assertSame(Order::STATUS_CONFIRMED, $order->getStatus());
        $this->assertTrue($order->isPaid());
        $this->assertSame('SIM-ABC123', $order->getPaymentReference());
        $this->assertNotNull($order->getPaidAt());
    }

    public function testOrderCannotBePaidTwice(): void
    {
        $order = $this->newOrder();
        $order->markAsPaid('SIM-1');

        $this->assertFalse($order->isPayable());
        $this->expectException(\DomainException::class);
        $order->markAsPaid('SIM-2');
    }

    public function testCancelledOrderCannotBePaid(): void
    {
        $order = $this->newOrder();
        $order->transitionTo(Order::STATUS_CANCELLED);

        $this->assertFalse($order->isPayable());
        $this->expectException(\DomainException::class);
        $order->markAsPaid('SIM-3');
    }
}
