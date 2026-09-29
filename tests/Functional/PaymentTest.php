<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Fonctionnalité 4 du cahier des charges : paiement simulé puis confirmation.
 */
class PaymentTest extends DatabaseWebTestCase
{
    /** Parcours réel : ajout au panier puis validation du panier. Renvoie le numéro de commande. */
    private function checkout(KernelBrowser $client, string $slug): string
    {
        $crawler = $client->request('GET', '/produit/'.$slug);
        $client->request('POST', '/panier/ajouter/'.$slug, ['_token' => $this->csrfFrom($crawler, '_token'), 'quantity' => 1]);

        $crawler = $client->request('GET', '/checkout');
        $client->request('POST', '/checkout', [
            'shipping' => [
                'fullName' => 'Test User', 'address' => '1 Rue de Test', 'city' => 'Lyon', 'postalCode' => '69000', 'country' => 'FR',
                '_token' => $this->csrfFrom($crawler, 'shipping[_token]'),
            ],
        ]);

        $this->em->clear();
        $orders = $this->em->getRepository(Order::class)->findAll();
        $this->assertCount(1, $orders);

        return $orders[0]->getOrderNumber();
    }

    private function makeOrderFor(User $user, Product $product): Order
    {
        $order = new Order($user, 'CMD-TEST-'.uniqid(), [
            'fullName' => 'X', 'address' => 'x', 'city' => 'x', 'postalCode' => 'x', 'country' => 'FR',
        ]);
        $order->addItem(new \App\Entity\OrderItem($order, $product, 1));
        $order->setTotalAmount($product->getPrice());
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    public function testCheckoutLeadsToPaymentThenConfirmation(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $this->login($client, $this->makeUser());
        $this->makeProduct($this->makeCategory(), 'Casque', 'casque', '79.99', 5);

        $orderNumber = $this->checkout($client, 'casque');
        $this->assertResponseRedirects('/commande/'.$orderNumber.'/paiement');

        // Tant que la commande n'est pas payée, la confirmation renvoie vers le paiement.
        $client->request('GET', '/commande/confirmation/'.$orderNumber);
        $this->assertResponseRedirects('/commande/'.$orderNumber.'/paiement');

        $crawler = $client->request('GET', '/commande/'.$orderNumber.'/paiement');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Paiement simulé');

        $client->request('POST', '/commande/'.$orderNumber.'/paiement', ['_token' => $this->csrfFrom($crawler, '_token')]);
        $this->assertResponseRedirects('/commande/confirmation/'.$orderNumber);
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Paiement accepté');

        $this->em->clear();
        $order = $this->em->getRepository(Order::class)->findOneBy(['orderNumber' => $orderNumber]);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->getStatus());
        $this->assertStringStartsWith('SIM-', (string) $order->getPaymentReference());
    }

    public function testPaymentRequiresValidCsrfToken(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser();
        $order = $this->makeOrderFor($user, $this->makeProduct($this->makeCategory()));

        $this->login($client, $user);
        $client->request('POST', '/commande/'.$order->getOrderNumber().'/paiement', ['_token' => 'faux-jeton']);
        $this->assertResponseStatusCodeSame(403);

        $this->em->clear();
        $this->assertSame(Order::STATUS_PENDING, $this->em->getRepository(Order::class)->find($order->getId())->getStatus());
    }

    public function testCannotPaySomeoneElsesOrder(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $victim = $this->makeUser('victim@test.local');
        $attacker = $this->makeUser('attacker@test.local');
        $order = $this->makeOrderFor($victim, $this->makeProduct($this->makeCategory()));

        $this->login($client, $attacker);
        $client->request('POST', '/commande/'.$order->getOrderNumber().'/paiement', ['_token' => 'x']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testPaidOrderUnlocksVerifiedReview(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $this->login($client, $this->makeUser());
        $this->makeProduct($this->makeCategory(), 'Lampe', 'lampe', '24.50', 5);

        $orderNumber = $this->checkout($client, 'lampe');
        $crawler = $client->request('GET', '/commande/'.$orderNumber.'/paiement');
        $client->request('POST', '/commande/'.$orderNumber.'/paiement', ['_token' => $this->csrfFrom($crawler, '_token')]);

        // Le formulaire d'avis n'est accepté qu'après un achat payé.
        $crawler = $client->request('GET', '/produit/lampe');
        $reviewToken = $crawler->filter('form[action$="/avis"] input[name="_token"]')->attr('value');
        $client->request('POST', '/produit/lampe/avis', ['_token' => $reviewToken, 'rating' => 5, 'comment' => 'Très bien']);
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Merci pour votre avis');
    }
}
