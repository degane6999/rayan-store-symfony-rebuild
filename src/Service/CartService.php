<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartRepository $carts,
        private readonly StockService $stock,
    ) {
    }

    public function getOrCreateCartForUser(User $user): Cart
    {
        $cart = $this->carts->findOneByUser($user);
        if (null === $cart) {
            $cart = new Cart($user);
            $this->em->persist($cart);
            $this->em->flush();
        }

        return $cart;
    }

    public function addToCart(User $user, Product $product, int $quantity): Cart
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }
        $this->stock->assertAvailable($product, $quantity);
        $cart = $this->getOrCreateCartForUser($user);
        $cart->addProduct($product, $quantity);
        $this->em->flush();

        return $cart;
    }

    public function removeFromCart(Cart $cart, int $cartItemId): void
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $cartItemId) {
                $cart->removeItem($item);
                $this->em->flush();

                return;
            }
        }
    }

    public function updateQuantity(Cart $cart, int $cartItemId, int $quantity): void
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $cartItemId) {
                if ($quantity <= 0) {
                    $cart->removeItem($item);
                } else {
                    $item->setQuantity($quantity);
                }
                $this->em->flush();

                return;
            }
        }
    }
}
