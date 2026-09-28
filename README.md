

## Ce qui a été réellement exécuté (pas seulement écrit)

| Vérification | Résultat mesuré | Commande pour le reproduire |
|---|---|---|
| Tests unitaires | **28/28 passent** | `php vendor/bin/phpunit --testsuite unit` |
| Tests fonctionnels (HTTP, vraie base MariaDB) | **20/20 passent** | `php vendor/bin/phpunit --testsuite functional` |
| Tests de concurrence (voir plus bas) | **3/3 passent** | `php vendor/bin/phpunit --testsuite concurrency` |
| Analyse statique PHPStan niveau 8 (strict) | **0 erreur** | `php phpstan.phar analyse` |
| Style de code PHP-CS-Fixer (règles @Symfony) | **

---

## Comment relancer les vérifications soi-même

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed                      # données de démo (mot de passe : password123)

php vendor/bin/phpunit                         # les 51 tests, y compris la concurrence
php phpstan.phar analyse --memory-limit=512M   # 0 erreur attendue
php php-cs-fixer.phar fix --dry-run --allow-risky=yes  # 0 violation attendue

php -S 127.0.0.1:8000 -t public public/index.php   # lancer le site
```


---


