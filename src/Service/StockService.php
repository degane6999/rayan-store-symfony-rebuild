<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Exception\OutOfStockException;

class StockService
{
    /**
     * @throws OutOfStockException if fewer than $quantity units are available
     */
    public function assertAvailable(Product $product, int $quantity): void
    {
        if ($product->getStock() < $quantity) {
            throw new OutOfStockException(sprintf('Product "%s" has only %d unit(s) in stock, %d requested.', $product->getName(), $product->getStock(), $quantity));
        }
    }

    public function decrement(Product $product, int $quantity): void
    {
        $product->decrementStock($quantity);
    }

    public function restore(Product $product, int $quantity): void
    {
        $product->restoreStock($quantity);
    }
}
