<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for functional tests that hit a real database (the same MariaDB
 * instance used by the app, isolated in the rayan_store_test schema).
 *
 * Truncates all tables before each test so tests never depend on each other's
 * data, then provides small factory helpers for the common fixtures.
 */
abstract class DatabaseWebTestCase extends WebTestCase
{
    protected EntityManagerInterface $em;

    /**
     * WebTestCase forbids booting the kernel (e.g. via getContainer()) before
     * createClient() is called. So instead of booting in setUp(), each test
     * calls self::createClient() itself as usual, then this helper prepares
     * the database against the container that call just booted.
     */
    protected function prepareDatabase(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncateAll();
    }

    protected function truncateAll(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['review', 'order_item', '`order`', 'cart_item', 'cart', 'product', 'category', '`user`'] as $table) {
            $conn->executeStatement('TRUNCATE TABLE '.$table);
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    protected function makeCategory(string $name = 'Électronique', string $slug = 'electronique'): Category
    {
        $category = new Category($name, $slug);
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    protected function makeProduct(Category $category, string $name = 'Produit test', string $slug = 'produit-test', string $price = '19.99', int $stock = 10): Product
    {
        $product = new Product($name, $slug, 'Description de test.', $price, $stock, $category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    protected function makeUser(string $email = 'user@test.local', array $roles = [User::ROLE_USER], string $plainPassword = 'password123'): User
    {
        $user = new User($email, 'Test', 'User');
        $user->setRoles($roles);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function login(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, User $user): void
    {
        $client->loginUser($user);
    }

    /**
     * Extracts a Symfony CSRF token value from a rendered crawler form field, e.g.
     * csrfFrom($crawler, 'shipping[_token]').
     */
    protected function csrfFrom(\Symfony\Component\DomCrawler\Crawler $crawler, string $fieldName): string
    {
        $node = $crawler->filter(sprintf('input[name="%s"]', $fieldName));
        if (0 === $node->count()) {
            throw new \RuntimeException(sprintf('CSRF field "%s" not found in page.', $fieldName));
        }

        return (string) $node->attr('value');
    }
}
