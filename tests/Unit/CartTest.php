<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Cart;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
    private function cart(): Cart
    {
        return new Cart(new User('a@b.com', 'A', 'B'));
    }

    private function product(string $price = '10.00', int $stock = 100): Product
    {
        return new Product('P', 'p', 'd', $price, $stock, new Category('C', 'c'));
    }

    public function testAddingSameProductTwiceMergesQuantity(): void
    {
        $cart = $this->cart();
        $product = $this->product();
        $cart->addProduct($product, 2);
        $cart->addProduct($product, 3);

        $this->assertCount(1, $cart->getItems());
        $this->assertSame(5, $cart->getItems()->first()->getQuantity());
    }

    public function testTotalCentsSumsAllLines(): void
    {
        $cart = $this->cart();
        $cart->addProduct($this->product('10.00'), 2); // 2000
        $cart->addProduct($this->product('5.50', 100), 1); // 550
        $this->assertSame(2550, $cart->getTotalCents());
    }

    public function testClearEmptiesCart(): void
    {
        $cart = $this->cart();
        $cart->addProduct($this->product(), 1);
        $cart->clear();
        $this->assertCount(0, $cart->getItems());
    }

    public function testRemoveItem(): void
    {
        $cart = $this->cart();
        $product = $this->product();
        $cart->addProduct($product, 1);
        $item = $cart->findItemForProduct($product);
        $cart->removeItem($item);
        $this->assertCount(0, $cart->getItems());
    }
}
