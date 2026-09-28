<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(length: 180)]
    private string $productNameSnapshot;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPriceSnapshot;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    public function __construct(Order $order, Product $product, int $quantity)
    {
        $this->order = $order;
        $this->product = $product;
        $this->productNameSnapshot = $product->getName();
        $this->unitPriceSnapshot = $product->getPrice();
        $this->quantity = $quantity;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getProductNameSnapshot(): string
    {
        return $this->productNameSnapshot;
    }

    public function getUnitPriceSnapshot(): string
    {
        return $this->unitPriceSnapshot;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getLineTotalCents(): int
    {
        return \App\Util\Money::multiply($this->unitPriceSnapshot, $this->quantity);
    }
}
