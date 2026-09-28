<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Product;
use App\Exception\OutOfStockException;
use App\Service\StockService;
use PHPUnit\Framework\TestCase;

class StockServiceTest extends TestCase
{
    private function product(int $stock): Product
    {
        return new Product('P', 'p', 'd', '10.00', $stock, new Category('C', 'c'));
    }

    public function testAssertAvailablePassesWhenEnoughStock(): void
    {
        $service = new StockService();
        $service->assertAvailable($this->product(5), 3);
        $this->addToAssertionCount(1);
    }

    public function testAssertAvailableThrowsWhenNotEnough(): void
    {
        $service = new StockService();
        $this->expectException(OutOfStockException::class);
        $service->assertAvailable($this->product(2), 3);
    }

    public function testDecrementReducesStock(): void
    {
        $product = $this->product(10);
        (new StockService())->decrement($product, 4);
        $this->assertSame(6, $product->getStock());
    }

    public function testRestoreIncreasesStock(): void
    {
        $product = $this->product(10);
        (new StockService())->restore($product, 4);
        $this->assertSame(14, $product->getStock());
    }

    public function testCannotDecrementBelowZero(): void
    {
        $product = $this->product(2);
        $this->expectException(\InvalidArgumentException::class);
        (new StockService())->decrement($product, 5);
    }

    public function testSetStockRejectsNegative(): void
    {
        $product = $this->product(2);
        $this->expectException(\InvalidArgumentException::class);
        $product->setStock(-1);
    }
}
