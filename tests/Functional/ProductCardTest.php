<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseWebTestCase;

/**
 * Carte produit du catalogue : marque, prix barré et remise, ajout direct au panier.
 */
class ProductCardTest extends DatabaseWebTestCase
{
    public function testCardShowsBrandReferencePriceAndDiscount(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $product = $this->makeProduct($this->makeCategory(), 'iPhone Test', 'iphone-test', '1229.00', 10);
        $product->setBrand('Apple');
        $product->setCompareAtPrice('1329.00');
        $this->em->flush();

        $crawler = $client->request('GET', '/catalogue');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Apple');
        $this->assertSelectorTextContains('.line-through', "1\u{a0}329,00");
        $this->assertSelectorTextContains('.discount-badge', '-8%');
    }

    public function testAddToCartDirectlyFromCatalogCard(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $this->login($client, $this->makeUser());
        $this->makeProduct($this->makeCategory(), 'Console Test', 'console-test', '449.99', 4);

        $crawler = $client->request('GET', '/catalogue');
        $form = $crawler->filter('form[action="/panier/ajouter/console-test"]')->form();
        $client->submit($form);
        $this->assertResponseRedirects('/panier');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Console Test');
    }
}
