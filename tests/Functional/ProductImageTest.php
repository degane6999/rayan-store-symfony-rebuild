<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseWebTestCase;

/**
 * Images produits : l'image est affichée quand elle existe, avec un texte
 * alternatif ; sinon le pictogramme par défaut prend le relais.
 */
class ProductImageTest extends DatabaseWebTestCase
{
    public function testProductImageIsShownOnCatalogAndProductPage(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $product = $this->makeProduct($this->makeCategory(), 'Casque Test', 'casque-test');
        $product->setImagePath('products/casque-audio-sans-fil.svg');
        $this->em->flush();

        $crawler = $client->request('GET', '/catalogue');
        $img = $crawler->filter('img[alt="Casque Test"]');
        $this->assertCount(1, $img);
        $this->assertSame('/images/products/casque-audio-sans-fil.svg', $img->attr('src'));

        $crawler = $client->request('GET', '/produit/casque-test');
        $this->assertCount(1, $crawler->filter('img[alt="Casque Test"]'));

        // Le fichier référencé existe bien dans public/.
        $this->assertFileExists(static::getContainer()->getParameter('kernel.project_dir').'/public/images/products/casque-audio-sans-fil.svg');
    }

    public function testProductWithoutImageShowsPlaceholder(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $this->makeProduct($this->makeCategory(), 'Sans Image', 'sans-image');

        $crawler = $client->request('GET', '/produit/sans-image');
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('img[alt="Sans Image"]'));
        $this->assertCount(1, $crawler->filter('svg[aria-hidden="true"]'));
    }
}
