<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Product;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Only sellers/admins may edit product catalog entries. Ownership isn't modeled per-seller
 * in this scope, so any ROLE_SELLER may manage the catalog; ROLE_ADMIN always can.
 *
 * @extends Voter<string, Product|null>
 */
class ProductVoter extends Voter
{
    public const MANAGE = 'PRODUCT_MANAGE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && ($subject instanceof Product || null === $subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && (
            $this->security->isGranted('ROLE_SELLER') || $this->security->isGranted('ROLE_ADMIN')
        );
    }
}
