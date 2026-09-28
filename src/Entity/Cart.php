<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private User $user;

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, CartItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function findItemForProduct(Product $product): ?CartItem
    {
        foreach ($this->items as $item) {
            // Compare by identity first: before a Product is persisted, getId()
            // is null for every unsaved instance, so comparing IDs alone would
            // wrongly merge two different not-yet-persisted products.
            if ($item->getProduct() === $product) {
                return $item;
            }
            if (null !== $product->getId() && $item->getProduct()->getId() === $product->getId()) {
                return $item;
            }
        }

        return null;
    }

    public function addProduct(Product $product, int $quantity): void
    {
        $existing = $this->findItemForProduct($product);
        if (null !== $existing) {
            $existing->increaseQuantity($quantity);

            return;
        }
        $this->items->add(new CartItem($this, $product, $quantity));
    }

    public function removeItem(CartItem $item): void
    {
        $this->items->removeElement($item);
    }

    public function clear(): void
    {
        $this->items->clear();
    }

    public function getTotalCents(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += \App\Util\Money::multiply($item->getProduct()->getPrice(), $item->getQuantity());
        }

        return $total;
    }
}
