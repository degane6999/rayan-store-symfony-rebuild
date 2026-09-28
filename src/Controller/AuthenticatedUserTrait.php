<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;

/**
 * AbstractController::getUser() returns ?UserInterface, which is technically
 * correct but useless in a controller guarded by #[IsGranted('ROLE_USER')]:
 * by the time the action runs, we know it's a logged-in App\Entity\User.
 * This narrows the type once, in one place, instead of an unchecked
 * assumption (or a suppressed PHPStan error) at every call site.
 */
trait AuthenticatedUserTrait
{
    protected function getAppUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            // Unreachable in practice behind #[IsGranted('ROLE_USER')], but if
            // it ever happened, failing loudly beats a confusing null-method call.
            throw new \LogicException('Expected an authenticated App\Entity\User.');
        }

        return $user;
    }
}
