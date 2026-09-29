# Rayan.store

Application web de vente en ligne réalisée dans le cadre du titre professionnel
**Concepteur Développeur d'Applications** (RNCP niveau 6) — IPSSI Lyon, session 2026.

Auteur : Wafo Mbé Rayan Degane

---

## Le projet

Rayan.store permet à des clients d'acheter des produits en ligne et aux
administrateurs de la boutique de gérer le catalogue. L'application est une
**application Symfony monolithique** (front et back-office dans la même
application), conformément au cahier des charges fonctionnel.

## Fonctionnalités du cahier des charges

| # | Fonctionnalité | État |
|---|---|---|
| 1 | Gestion des utilisateurs : inscription, connexion, rôles client / administrateur | ✅ — modification du profil à venir |
| 2 | Catalogue : liste, fiche produit (marque, description, prix, prix barré et remise, stock, image), recherche par nom et par catégorie, ajout au panier depuis le catalogue | ✅ — tri à venir |
| 3 | Panier et commande : ajout, suppression, quantités, validation et création de la commande | ✅ |
| 4 | Paiement en ligne : paiement simulé, confirmation de commande après paiement | ✅ |
| 5 | Suivi des commandes : historique client, statuts, annulation | ✅ — changement de statut côté admin à venir |
| 6 | Interface d'administration : produits, commandes, utilisateurs | 🟡 zones protégées, écrans CRUD à venir |

Au-delà du cahier des charges : avis réservés aux acheteurs, export et
anonymisation des données personnelles (RGPD), protection du stock contre les
commandes simultanées.

Hors périmètre (cahier des charges) : application mobile, logistique avancée,
marketplace multi-vendeurs.

## Stack technique

- PHP 8.2+ (`declare(strict_types=1)`), **Symfony 6.4 LTS**
- **Doctrine ORM** + migrations versionnées
- **MariaDB 10.11** (compatible MySQL, moteur InnoDB)
- Twig + Tailwind CSS
- **Docker Compose** (application + base de données)
- PHPUnit 10, PHPStan niveau 8, PHP-CS-Fixer

## Lancer le projet

Prérequis : Docker Desktop.

```bash
git clone https://github.com/degane6999/rayan-store-symfony-rebuild.git
cd rayan-store-symfony-rebuild
docker compose up -d --build
```

Au démarrage, le conteneur applique les migrations, charge les données de
démonstration puis lance le serveur. L'application est disponible sur
**http://localhost:8000**.

Comptes de démonstration (mot de passe `password123`) :

| Rôle | Email |
|---|---|
| Client | `demo@rayan.store` |
| Gestionnaire du catalogue | `vendeur@rayan.store` |
| Administrateur | `admin@rayan.store` |

## Photos des produits

Les données de démonstration (`php bin/console app:seed`) contiennent 10 produits
de marque. Pour chaque produit, l'application cherche une photo dans
`public/images/products/` nommée d'après son identifiant (`iphone-16-pro.jpg`,
`.png` ou `.webp`) ; à défaut, elle affiche l'illustration `.svg` fournie.

## Sécurité

- Mots de passe hachés avec **bcrypt** (coût 12)
- **Jeton CSRF** sur chaque formulaire et chaque action (panier, paiement, annulation, avis, suppression de compte)
- Limitation à **5 tentatives de connexion par minute**
- Requêtes Doctrine **paramétrées**, échappement automatique Twig
- **Voter** `OrderVoter` : un client ne peut ni voir, ni annuler, ni payer la commande d'un autre
- Paiement simulé : **aucune donnée bancaire** saisie ni stockée (hors périmètre PCI-DSS)

## Intégrité du stock

La validation de commande (`src/Service/OrderService.php`) s'exécute dans une
transaction avec un **verrou pessimiste** (`SELECT … FOR UPDATE`) sur chaque
produit, posé dans l'ordre croissant des identifiants pour éviter les
interblocages. Les montants sont calculés en **centimes entiers**
(`src/Util/Money.php`).

Un test de concurrence lance 50 processus PHP qui commandent le même produit au
même instant : avec 10 articles en stock, exactement 10 commandes réussissent.

## Tests et qualité

```bash
php vendor/bin/phpunit                          # 65 tests
php vendor/bin/phpunit --testsuite unit         # 34 tests unitaires
php vendor/bin/phpunit --testsuite functional   # 28 tests fonctionnels
php vendor/bin/phpunit --testsuite concurrency  # 3 tests de concurrence
```

| Contrôle | Résultat |
|---|---|
| PHPUnit | 65 tests, 151 assertions, 0 échec |
| PHPStan niveau 8 | 0 erreur |
| PHP-CS-Fixer (@Symfony) | 0 écart |
| Couverture des lignes (PCOV) | 59,1 % |

## Structure

```
src/
  Controller/   contrôleurs (catalogue, panier, commande, paiement, compte…)
  Entity/       entités Doctrine (User, Product, Order, Cart, Review…)
  Repository/   requêtes
  Security/     authentification par formulaire, OrderVoter
  Service/      logique métier (OrderService, PaymentService, StockService…)
  Util/Money    calculs monétaires en centimes
templates/      vues Twig
migrations/     schéma de base de données versionné
tests/          Unit, Functional, Concurrency
public/images/  photos et illustrations des produits
```

## Historique du projet

Un premier prototype a été réalisé en React + Supabase. Ne répondant pas au
cahier des charges (application Symfony, tests, intégrité du stock),
l'application a été reconstruite en Symfony en septembre 2026, avec l'aide d'un
assistant IA (Claude).

## Évolutions prévues

- Écrans d'administration (CRUD produits, commandes, utilisateurs) et changement de statut des commandes
- Modification du profil, tri du catalogue, réinitialisation du mot de passe
- Intégration continue (GitHub Actions)
- Mise en production (Nginx + PHP-FPM, HTTPS, en-têtes de sécurité)
- Paiement réel avec Stripe
