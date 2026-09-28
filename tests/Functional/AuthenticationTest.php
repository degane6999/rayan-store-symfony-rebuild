<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseWebTestCase;

class AuthenticationTest extends DatabaseWebTestCase
{
    public function testCanRegisterAndLogIn(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();

        $crawler = $client->request('GET', '/inscription');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon compte')->form();
        $form['registration_form[firstName]'] = 'Nouveau';
        $form['registration_form[lastName]'] = 'Utilisateur';
        $form['registration_form[email]'] = 'nouveau@test.local';
        $form['registration_form[plainPassword][first]'] = 'motdepasse123';
        $form['registration_form[plainPassword][second]'] = 'motdepasse123';
        $client->submit($form);
        $this->assertResponseRedirects();

        $loginCrawler = $client->request('GET', '/connexion');
        $loginForm = $loginCrawler->selectButton('Se connecter')->form();
        $loginForm['email'] = 'nouveau@test.local';
        $loginForm['password'] = 'motdepasse123';
        $client->submit($loginForm);
        $this->assertResponseRedirects('/mon-compte');
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $this->makeUser('exists@test.local');

        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form();
        $form['email'] = 'exists@test.local';
        $form['password'] = 'wrong-password';
        $client->submit($form);

        // Symfony's default form auth failure redirects back to the login page.
        $this->assertResponseRedirects('/connexion');
    }

    public function testCartRequiresLogin(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $client->request('GET', '/panier');
        $this->assertResponseRedirects('/connexion');
    }

    public function testCheckoutRequiresLogin(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $client->request('GET', '/checkout');
        $this->assertResponseRedirects('/connexion');
    }

    public function testSellerAreaRequiresSellerRole(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser('plain@test.local');
        $this->login($client, $user);

        $client->request('GET', '/seller');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminAreaRequiresAdminRole(): void
    {
        $client = static::createClient();
        $this->prepareDatabase();
        $user = $this->makeUser('plain2@test.local');
        $this->login($client, $user);

        $client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);
    }
}
