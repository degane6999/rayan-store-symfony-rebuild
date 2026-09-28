<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Tests\Support\DatabaseWebTestCase;

/**
 * IDOR: a user must never be able to view or cancel another user's order by
 * guessing/enumerating its order number.
 */
class OrderSecurityTest extends DatabaseWebTestCase
{
    private function placeOrderFor(\App\Entity\User $user, \App\Entity\Product $product): Order
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

    public function testCannotViewSomeoneElsesOrder(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $victim = $this->makeUser('victim@test.local');
        $attacker = $this->makeUser('attacker@test.local');
        $category = $this->makeCategory();
        $product = $this->makeProduct($category);
        $order = $this->placeOrderFor($victim, $product);

        $this->login($client, $attacker);
        $client->request('GET', '/commande/'.$order->getOrderNumber());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCannotCancelSomeoneElsesOrder(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $victim = $this->makeUser('victim2@test.local');
        $attacker = $this->makeUser('attacker2@test.local');
        $category = $this->makeCategory();
        $product = $this->makeProduct($category);
        $order = $this->placeOrderFor($victim, $product);

        $this->login($client, $attacker);
        $client->request('POST', '/commande/'.$order->getOrderNumber().'/annuler', ['_token' => 'whatever']);
        $this->assertResponseStatusCodeSame(403);

        $this->em->clear();
        $stillPending = $this->em->getRepository(Order::class)->find($order->getId());
        $this->assertSame(Order::STATUS_PENDING, $stillPending->getStatus());
    }

    public function testOwnerCanViewTheirOwnOrder(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser('owner@test.local');
        $category = $this->makeCategory();
        $product = $this->makeProduct($category);
        $order = $this->placeOrderFor($user, $product);

        $this->login($client, $user);
        $client->request('GET', '/commande/'.$order->getOrderNumber());
        $this->assertResponseIsSuccessful();
    }

    public function testShippedOrderCannotBeCancelledEvenByOwner(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser('owner2@test.local');
        $category = $this->makeCategory();
        $product = $this->makeProduct($category);
        $order = $this->placeOrderFor($user, $product);
        $order->transitionTo(Order::STATUS_CONFIRMED);
        $order->transitionTo(Order::STATUS_PREPARING);
        $order->transitionTo(Order::STATUS_SHIPPED);
        $this->em->flush();

        $this->login($client, $user);
        $crawler = $client->request('GET', '/commande/'.$order->getOrderNumber());
        // The template should not even offer a cancel button once shipped.
        $this->assertSame(0, $crawler->filter('form[action*="annuler"]')->count());
    }
}
