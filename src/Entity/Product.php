<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use App\Util\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 200, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    /** Stored as DECIMAL(10,2) string; arithmetic goes through App\Util\Money. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(type: Types::INTEGER)]
    private int $stock;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 80, options: ['default' => ''])]
    private string $brand = '';

    /**
     * Prix de référence avant remise (affiché barré). Null = pas de remise.
     * Même format que $price : DECIMAL(10,2) sous forme de chaîne.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $compareAtPrice = null;

    /** Chemin de l'image relatif à public/images/ (ex. « products/casque.svg »). Null = pictogramme par défaut. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Review::class)]
    private Collection $reviews;

    public function __construct(string $name, string $slug, string $description, string $price, int $stock, Category $category)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->price = $price;
        $this->stock = $stock;
        $this->category = $category;
        $this->createdAt = new \DateTimeImmutable();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function setCategory(Category $category): void
    {
        $this->category = $category;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        if ($stock < 0) {
            throw new \InvalidArgumentException('Stock cannot be negative.');
        }
        $this->stock = $stock;
    }

    /**
     * Only ever called from inside StockService under a pessimistic write lock.
     *
     * @throws \InvalidArgumentException if the resulting stock would be negative
     */
    public function decrementStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity to decrement must be positive.');
        }
        if ($quantity > $this->stock) {
            throw new \InvalidArgumentException(sprintf('Cannot decrement %d units: only %d in stock.', $quantity, $this->stock));
        }
        $this->stock -= $quantity;
    }

    public function restoreStock(int $quantity): void
    {
        $this->stock += $quantity;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): void
    {
        $this->brand = $brand;
    }

    public function getCompareAtPrice(): ?string
    {
        return $this->compareAtPrice;
    }

    public function setCompareAtPrice(?string $compareAtPrice): void
    {
        $this->compareAtPrice = $compareAtPrice;
    }

    /**
     * Pourcentage de remise arrondi (ex. 8 pour « -8 % »), calculé en centimes
     * pour éviter les erreurs d'arrondi. Null s'il n'y a pas de vraie remise.
     */
    public function getDiscountPercent(): ?int
    {
        if (null === $this->compareAtPrice) {
            return null;
        }
        $reference = Money::toCents($this->compareAtPrice);
        $current = Money::toCents($this->price);
        if ($reference <= $current || 0 === $reference) {
            return null;
        }

        return (int) round(($reference - $current) * 100 / $reference);
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getAverageRating(): ?float
    {
        if ($this->reviews->isEmpty()) {
            return null;
        }
        $sum = 0;
        foreach ($this->reviews as $review) {
            $sum += $review->getRating();
        }

        return round($sum / $this->reviews->count(), 1);
    }

    public function getReviewCount(): int
    {
        return $this->reviews->count();
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }
}
