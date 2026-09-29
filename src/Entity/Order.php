<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PREPARING, self::STATUS_CANCELLED],
        self::STATUS_PREPARING => [self::STATUS_SHIPPED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $orderNumber;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'])]
    private Collection $items;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    /** @var array<string,string> */
    #[ORM\Column(type: Types::JSON)]
    private array $shippingAddress;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Date du paiement (simulé). Null tant que la commande n'est pas payée. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** Référence de transaction renvoyée par le service de paiement (simulé). */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $paymentReference = null;

    /** @param array<string,string> $shippingAddress */
    public function __construct(User $user, string $orderNumber, array $shippingAddress)
    {
        $this->user = $user;
        $this->orderNumber = $orderNumber;
        $this->shippingAddress = $shippingAddress;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): void
    {
        $this->items->add($item);
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * @throws \DomainException if the transition isn't legal from the current status
     */
    public function transitionTo(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \DomainException(sprintf('Cannot transition order from "%s" to "%s".', $this->status, $newStatus));
        }
        $this->status = $newStatus;
    }

    public function isCancellable(): bool
    {
        return $this->canTransitionTo(self::STATUS_CANCELLED);
    }

    /** @return array<string,string> */
    public function getShippingAddress(): array
    {
        return $this->shippingAddress;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Une commande ne peut être payée qu'une fois, et seulement si elle est
     * encore en attente (ni annulée, ni déjà confirmée).
     */
    public function isPayable(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function isPaid(): bool
    {
        return null !== $this->paidAt;
    }

    /**
     * Enregistre le paiement puis confirme la commande en passant par la
     * machine à états (pending → confirmed).
     *
     * @throws \DomainException si la commande n'est pas payable
     */
    public function markAsPaid(string $paymentReference, ?\DateTimeImmutable $paidAt = null): void
    {
        if (!$this->isPayable()) {
            throw new \DomainException(sprintf('Order "%s" cannot be paid in status "%s".', $this->orderNumber, $this->status));
        }
        $this->transitionTo(self::STATUS_CONFIRMED);
        $this->paymentReference = $paymentReference;
        $this->paidAt = $paidAt ?? new \DateTimeImmutable();
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }
}
