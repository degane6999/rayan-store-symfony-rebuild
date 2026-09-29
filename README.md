
---

#

| Vérification | Résultat mesuré | Commande pour le reproduire |
|---|---|---|
| Tests unitaires | **31/31 passent** | `php vendor/bin/phpunit --testsuite unit` |
| Tests fonctionnels (HTTP, vraie base MariaDB) | **24/24 passent** | `php vendor/bin/phpunit --testsuite functional` |
| Tests de concurrence (voir plus bas) | **3/3 passent** | `php vendor/bin/phpunit --testsuite concurrency` |
| Analyse statique PHPStan niveau 8 (strict) | **0 erreur** | `php phpstan.phar analyse` |
| Style de code PHP-CS-Fixer (règles @Symfony) | **0 violation** | `php php-cs-fixer.phar fix --dry-run` |
| Parcours complet réel (login → panier → checkout → commande) | Testé via HTTP réel (curl), commande créée en base, stock décrémenté correctement | voir `tests/Functional/CartAndCheckoutTest.php` |

Total : **58 tests automatisés réels**, tous exécutés dans cet environnement,
pas rédigés puis laissés de côté.

---

## Ajout du 29/09/2026 — paiement simulé (fonctionnalité 4 du cahier des charges)

Après la validation du panier, la commande (statut `pending`) passe par une page
de paiement **simulé** (`/commande/{numéro}/paiement`) avant la confirmation.
Aucune donnée bancaire n'est saisie ni stockée (hors périmètre PCI-DSS).

- `Order::markAsPaid()` enregistre la date et une référence `SIM-…`, puis fait
  passer la commande à `confirmed` via la machine à états.
- `PaymentService::paySimulated()` génère la référence et enregistre.
- `OrderVoter::PAY` : seul le propriétaire d'une commande en attente peut la payer.
- Jeton CSRF sur le formulaire de paiement ; migration `Version20260929070000`.
- 7 nouveaux tests (3 unitaires, 4 fonctionnels), dont un qui vérifie qu'un avis
  devient possible après paiement.
- `Dockerfile` : ajout de `ENV COMPOSER_ALLOW_SUPERUSER=1` (sans quoi le plugin
  symfony/runtime n'était pas installé et l'application répondait en erreur 500).

---

## Le test qui compte le plus : la concurrence à 50 acheteurs simultanés

C'est la vérification centrale demandée : est-ce que le système empêche
vraiment la survente quand 50 utilisateurs tentent d'acheter le même produit
au même instant ?

`tests/Concurrency/OrderConcurrencyTest.php` lance **50 vrais processus PHP
indépendants** (pas 50 itérations dans une boucle : 50 process séparés,
chacun avec sa propre connexion à la base), synchronisés par une barrière
fichier pour qu'ils frappent tous `placeOrder()` au même instant.

### Résultat avec le verrou pessimiste réel (`OrderService`, celui utilisé par l'application)

```
50 acheteurs, 10 unités en stock -> 10 succès, 40 "rupture de stock", 0 erreur, stock final = 0
50 acheteurs, 1 unité en stock  -> exactement 1 succès, stock final = 0
```

Exactement autant de commandes réussies que d'unités en stock. Jamais plus.
Reproduit de façon stable sur 5 exécutions consécutives.

### Résultat SANS verrou (service `NaiveOrderService`, volontairement non protégé, présent uniquement à des fins de comparaison dans les tests — jamais utilisé par l'application)

```
50 acheteurs, 10 unités en stock -> entre 27 et 34 succès selon l'exécution (stock final incohérent)
```



---



---

## Comment relancer les vérifications soi-même

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed                      # données de démo (mot de passe : password123)

php vendor/bin/phpunit                         # les 58 tests, y compris la concurrence
php phpstan.phar analyse --memory-limit=512M   # 0 erreur attendue
php php-cs-fixer.phar fix --dry-run --allow-risky=yes  # 0 violation attendue

php -S 127.0.0.1:8000 -t public public/index.php   # lancer le site
```

Nécessite MariaDB/MySQL accessible via `DATABASE_URL` (voir `.env` /
`.env.test`), PHP 8.2+ avec les extensions pdo_mysql, intl, mbstring.

---


---

*Ce document et le code associé ont été produits avec l'assistance de
Claude (Anthropic). Toute utilisation académique doit être conforme au
règlement de l'établissement concernant l'usage de l'IA.*
