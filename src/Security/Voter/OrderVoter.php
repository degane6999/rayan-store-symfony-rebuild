<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Prevents IDOR: a user can only view/cancel/pay their own orders, unless ROLE_ADMIN.
 *
 * @extends Voter<string, Order>
 */
class OrderVoter extends Voter
{
    public const VIEW = 'ORDER_VIEW';
    public const CANCEL = 'ORDER_CANCEL';
    public const PAY = 'ORDER_PAY';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CANCEL, self::PAY], true) && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // L'administrateur peut consulter et annuler, mais seul le client paie sa commande.
        if (self::PAY !== $attribute && $this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Order $order */
        $order = $subject;
        if ($order->getUser()->getId() !== $user->getId()) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::CANCEL => $order->isCancellable(),
            self::PAY => $order->isPayable(),
            default => false,
        };
    }
}
