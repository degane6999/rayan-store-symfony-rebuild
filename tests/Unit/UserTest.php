<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testEmailIsLowercasedAndTrimmed(): void
    {
        $user = new User('  Test@EXAMPLE.com  ', 'A', 'B');
        $this->assertSame('test@example.com', $user->getEmail());
    }

    public function testAlwaysHasRoleUser(): void
    {
        $user = new User('a@b.com', 'A', 'B');
        $user->setRoles([]);
        $this->assertContains(User::ROLE_USER, $user->getRoles());
    }

    public function testAnonymizeScrubsPersonalData(): void
    {
        $user = new User('real@person.com', 'Real', 'Person');
        $user->setPassword('hash');
        $user->anonymize();

        $this->assertStringContainsString('anonymized.invalid', $user->getEmail());
        $this->assertNotSame('Real', $user->getFirstName());
        $this->assertTrue($user->isAnonymized());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User('id@example.com', 'A', 'B');
        $this->assertSame('id@example.com', $user->getUserIdentifier());
    }

    public function testFullName(): void
    {
        $user = new User('a@b.com', 'Jean', 'Dupont');
        $this->assertSame('Jean Dupont', $user->getFullName());
    }
}
