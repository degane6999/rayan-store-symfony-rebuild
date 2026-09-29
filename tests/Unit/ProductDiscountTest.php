<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class ProductDiscountTest extends TestCase
{
    private function product(string $price, ?string $compareAtPrice): Product
    {
        $product = new Product('Produit', 'produit', 'd', $price, 5, new Category('C', 'c'));
        $product->setCompareAtPrice($compareAtPrice);

        return $product;
    }

    public function testDiscountIsComputedAndRounded(): void
    {
        // 1329.00 → 1229.00 : 100 / 1329 = 7,52 % → -8 %
        $this->assertSame(8, $this->product('1229.00', '1329.00')->getDiscountPercent());
        $this->assertSame(10, $this->product('449.99', '499.99')->getDiscountPercent());
    }

    public function testNoDiscountWithoutReferencePrice(): void
    {
        $this->assertNull($this->product('799.00', null)->getDiscountPercent());
    }

    public function testNoDiscountWhenReferenceIsNotHigher(): void
    {
        // Un prix de référence inférieur ou égal ne doit jamais afficher de remise.
        $this->assertNull($this->product('799.00', '799.00')->getDiscountPercent());
        $this->assertNull($this->product('799.00', '699.00')->getDiscountPercent());
    }
}
