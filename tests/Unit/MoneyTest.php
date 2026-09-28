<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Util\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testToCentsBasic(): void
    {
        $this->assertSame(1999, Money::toCents('19.99'));
        $this->assertSame(10000, Money::toCents('100'));
        $this->assertSame(50, Money::toCents('0.50'));
    }

    public function testToCentsAcceptsComma(): void
    {
        $this->assertSame(1999, Money::toCents('19,99'));
    }

    public function testToCentsRejectsGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toCents('not-a-number');
    }

    public function testFromCents(): void
    {
        $this->assertSame('19.99', Money::fromCents(1999));
        $this->assertSame('100.00', Money::fromCents(10000));
        $this->assertSame('0.05', Money::fromCents(5));
    }

    public function testMultiplyAvoidsFloatingPointDrift(): void
    {
        // Classic float trap: 0.1 + 0.2 !== 0.3 in IEEE-754. Money must not hit it.
        $this->assertSame(30, Money::multiply('0.10', 3));
        $this->assertSame(950000, Money::multiply('19.00', 500));
    }

    public function testRoundTrip(): void
    {
        foreach (['0.01', '19.99', '1234.56', '0.99'] as $amount) {
            $this->assertSame($amount, Money::fromCents(Money::toCents($amount)));
        }
    }

    public function testAddCents(): void
    {
        $this->assertSame(300, Money::addCents(100, 100, 100));
        $this->assertSame(0, Money::addCents());
    }
}
