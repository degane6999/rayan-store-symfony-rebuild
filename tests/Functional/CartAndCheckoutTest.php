<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Tests\Support\DatabaseWebTestCase;

class CartAndCheckoutTest extends DatabaseWebTestCase
{
    public function testAddToCartAndSeeItInCart(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser();
        $this->login($client, $user);
        $category = $this->makeCategory();
        $product = $this->makeProduct($category, 'Souris Test', 'souris-test', '25.00', 10);

        $crawler = $client->request('GET', '/produit/souris-test');
        $token = $this->csrfFrom($crawler, '_token');

        $client->request('POST', '/panier/ajouter/souris-test', ['_token' => $token, 'quantity' => 2]);
        $this->assertResponseRedirects('/panier');

        $client->request('GET', '/panier');
        $this->assertSelectorTextContains('body', 'Souris Test');
    }

    public function testAddToCartRejectsExcessiveQuantity(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser();
        $this->login($client, $user);
        $category = $this->makeCategory();
        $this->makeProduct($category, 'Rare', 'rare', '99.00', 2);

        $crawler = $client->request('GET', '/produit/rare');
        $token = $this->csrfFrom($crawler, '_token');

        $client->request('POST', '/panier/ajouter/rare', ['_token' => $token, 'quantity' => 5]);
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'stock');
    }

    public function testFullCheckoutFlowPlacesRealOrderAndDecrementsStock(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser();
        $this->login($client, $user);
        $category = $this->makeCategory();
        $product = $this->makeProduct($category, 'Clavier Test', 'clavier-test', '49.99', 10);

        $productCrawler = $client->request('GET', '/produit/clavier-test');
        $addToken = $this->csrfFrom($productCrawler, '_token');
        $client->request('POST', '/panier/ajouter/clavier-test', ['_token' => $addToken, 'quantity' => 3]);

        $checkoutCrawler = $client->request('GET', '/checkout');
        $this->assertResponseIsSuccessful();
        $shippingToken = $this->csrfFrom($checkoutCrawler, 'shipping[_token]');

        $client->request('POST', '/checkout', [
            'shipping' => [
                'fullName' => 'Test User',
                'address' => '1 Rue de Test',
                'city' => 'Lyon',
                'postalCode' => '69000',
                'country' => 'FR',
                '_token' => $shippingToken,
            ],
        ]);
        $this->assertResponseRedirects();

        // Stock really decremented in the database, not just in memory.
        $this->em->clear();
        $refreshed = $this->em->getRepository(\App\Entity\Product::class)->find($product->getId());
        $this->assertSame(7, $refreshed->getStock());

        $orders = $this->em->getRepository(Order::class)->findAll();
        $this->assertCount(1, $orders);
        $this->assertSame('149.97', $orders[0]->getTotalAmount());
        $this->assertSame(Order::STATUS_PENDING, $orders[0]->getStatus());
    }

    public function testCheckoutWithEmptyCartRedirectsToCart(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser();
        $this->login($client, $user);

        $client->request('GET', '/checkout');
        $this->assertResponseRedirects('/panier');
    }

    public function testCannotBuyMoreThanStockThroughFullFlow(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser();
        $this->login($client, $user);
        $category = $this->makeCategory();
        $this->makeProduct($category, 'Édition limitée', 'edition-limitee', '199.00', 1);

        // Add exactly the available stock, then manually push the DB stock to
        // zero to simulate another buyer winning the race in between.
        $productCrawler = $client->request('GET', '/produit/edition-limitee');
        $addToken = $this->csrfFrom($productCrawler, '_token');
        $client->request('POST', '/panier/ajouter/edition-limitee', ['_token' => $addToken, 'quantity' => 1]);

        $this->em->getConnection()->executeStatement("UPDATE product SET stock = 0 WHERE slug = 'edition-limitee'");
        $this->em->clear();

        $checkoutCrawler = $client->request('GET', '/checkout');
        $shippingToken = $this->csrfFrom($checkoutCrawler, 'shipping[_token]');
        $client->request('POST', '/checkout', [
            'shipping' => [
                'fullName' => 'Test User', 'address' => 'x', 'city' => 'x', 'postalCode' => 'x', 'country' => 'FR',
                '_token' => $shippingToken,
            ],
        ]);
        $this->assertResponseIsSuccessful(); // re-renders the form with a flash error, no redirect
        $this->assertSelectorTextContains('.flash-error, body', 'stock');

        $orders = $this->em->getRepository(Order::class)->findAll();
        $this->assertCount(0, $orders);
    }
}
