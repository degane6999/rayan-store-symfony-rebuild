<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseWebTestCase;

class HomeAndCatalogTest extends DatabaseWebTestCase
{
    public function testHomePageLoads(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('title', 'Rayan.store');
    }

    public function testCatalogShowsSeededProducts(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $category = $this->makeCategory();
        $this->makeProduct($category, 'Casque Test', 'casque-test');

        $client->request('GET', '/catalogue');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Casque Test');
    }

    public function testProductPageShowsPrice(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $category = $this->makeCategory();
        $this->makeProduct($category, 'Enceinte Test', 'enceinte-test', '42.50', 7);

        $client->request('GET', '/produit/enceinte-test');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '42.50');
    }

    public function testUnknownProductReturns404(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $client->request('GET', '/produit/does-not-exist');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testLegalPagesLoad(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $client->request('GET', '/legal/cgv');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/legal/mentions');
        $this->assertResponseIsSuccessful();
    }
}
