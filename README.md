#

**Avant toute utilisation en soutenance, voir la section "Ce que l'étudiant
doit faire avant de s'en servir" en bas de ce document — c'est une condition,
pas une formalité.**

---

## Ce qui a été réellement exécuté (pas seulement écrit)

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

C'est-à-dire une survente de **17 à 24 commandes** au-delà du stock réel,
selon l'exécution. C'est très exactement le bug qui existait dans le code
React/Supabase original (aucun verrou), et c'est ce que le mécanisme
`LockMode::PESSIMISTIC_WRITE` + verrouillage en ordre déterministe
(`src/Service/OrderService.php`) corrige, de façon vérifiée et reproductible,
pas supposée.

**Ce que ça veut dire pour le mémoire** : le mémoire peut désormais dire, en
toute honnêteté, "sous charge simultanée de 50 acheteurs, le système garantit
zéro survente, vérifié par un test automatisé reproductible" — avec ce test
comme preuve, montrable au jury.

---

## Écarts avec le mémoire original — à corriger avant la soutenance

| Affirmation du mémoire | Réalité vérifiée ici |
|---|---|
| Stack Symfony/PHP | Le code livré était React + Supabase. Cette reconstruction Symfony 6.4.46 / PHP 8.4 / MariaDB 10.11 est neuve, datée du 28/09/2026, sans historique Git de plusieurs mois. |
| 62 tests automatisés, 92.1 % de couverture | 58 tests (31 unitaires + 24 fonctionnels + 3 concurrence). Couverture mesurée avec PCOV : 58,5 % des lignes (les tests de concurrence, exécutés dans des processus séparés, ne sont pas comptés). |
| 200 req/s | Aucune mesure de charge HTTP (ab/wrk) n'a été refaite dans cette reconstruction. Ne pas réutiliser ce chiffre : il n'a jamais été mesuré sur le code réel, ni dans l'ancienne tentative ni ici. Si ce chiffre doit apparaître au mémoire, il doit d'abord être mesuré (voir section suivante). |
| 50 commandes simultanées gérées correctement | **Vérifié et reproductible** (voir ci-dessus) : exactement stock-many commandes réussissent, jamais plus, sous 50 accès concurrents réels au niveau base de données. |
| Verrouillage pessimiste (`PESSIMISTIC_WRITE`) | Réellement implémenté dans `src/Service/OrderService.php`, avec verrouillage en ordre déterministe (tri par ID produit croissant) pour éviter les deadlocks entre transactions concurrentes. |

---

## Ce qui n'a pas encore été refait (honnêteté sur le périmètre)

Une précédente tentative de reconstruction (dans une session de travail
antérieure) avait aussi mis en place : test de charge HTTP de bout en bout
(nginx + php-fpm réels, ~75-85 commandes/s mesurées), Docker/docker-compose,
CI GitHub Actions, RGPD (export/anonymisation), système d'avis produits,
espace vendeur complet. Cette tentative a été perdue lors d'une
réinitialisation de l'environnement de travail avant d'avoir été livrée à
l'étudiant — **elle n'a donc jamais existé pour lui**, uniquement pour
l'assistant dans une session précédente.

Cette reconstruction-ci a délibérément priorisé, dans l'ordre : (1) un
squelette qui démarre réellement, (2) les entités et la base de données
réelles, (3) le mécanisme métier critique (`OrderService` + verrou), (4) les
routes/templates nécessaires au parcours complet, (5) une vraie suite de
tests incluant le test de concurrence à 50 acheteurs, (6) la qualité de code
(PHPStan, CS-Fixer) — pour livrer quelque chose de réellement vérifié le plus
vite possible plutôt que de refaire un périmètre plus large et risquer de
tout reperdre avant livraison.

**Peuvent être ajoutés ensuite, sur demande** : test de charge HTTP réel,
mesure de couverture de code, Docker, CI, espace vendeur/admin plus complet,
système d'avis, export RGPD.

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

## Ce que l'étudiant doit faire avant de s'en servir

Ceci n'est pas une formalité : c'est une condition posée dès le début de ce
travail, et elle reste valable.

1. **Prévenir le formateur / l'école** que le code du dépôt initial ne
   correspondait pas au mémoire, et que ce dépôt Symfony est une
   reconstruction faite avec l'assistance d'une IA (Claude, Anthropic),
   datée du 28/09/2026 — avant la soutenance, pas après.
2. **Ne pas présenter ce code comme le fruit de 6 mois de développement** :
   son historique réel (une reconstruction en une session, sans historique
   Git étalé dans le temps) doit être assumé si la question est posée.
3. **Ne réutiliser aucun chiffre non vérifié** (couverture de tests,
   requêtes/seconde) tant qu'il n'a pas été mesuré ici ou ailleurs.
4. **Être capable d'expliquer chaque choix technique** présenté ci-dessus
   (pourquoi un verrou pessimiste, pourquoi cet ordre de verrouillage,
   pourquoi les montants sont stockés en centimes) — pas seulement le citer.

---

*Ce document et le code associé ont été produits avec l'assistance de
Claude (Anthropic). Toute utilisation académique doit être conforme au
règlement de l'établissement concernant l'usage de l'IA.*
