# Mise en production de Rayan.store

Ce document décrit comment passer du dépôt Git à une application en production.
Il complète [DOCKER.md](DOCKER.md), qui décrit l'environnement de développement.

## Les trois environnements

| | Développement | Test | Production |
|---|---|---|---|
| Fichiers | `Dockerfile`, `docker-compose.yml` | `.env.test`, `.github/workflows/ci.yml` | `Dockerfile.prod`, `docker-compose.prod.yml` |
| `APP_ENV` | `dev` (erreurs détaillées) | `test` | `prod` (erreurs masquées, cache optimisé) |
| Serveur web | serveur intégré de PHP, port 8000 | — | Nginx + PHP-FPM, port 80 derrière HTTPS |
| Base | port 3307 ouvert sur la machine | base `rayan_store_test` | aucun port ouvert, réseau interne Docker |
| Secrets | valeurs de démonstration | valeurs de test | `.env.prod.local`, jamais versionné |
| Données | `app:seed` (démonstration) | vidées avant chaque test | données réelles, sauvegardées |

## Architecture déployée

```
Internet ──HTTPS──▶ reverse proxy (Let's Encrypt) ──HTTP──▶ web (Nginx)
                                                              │ FastCGI :9000
                                                              ▼
                                                        app (PHP-FPM 8.4 + Symfony)
                                                              │ réseau interne Docker
                                                              ▼
                                                        db (MariaDB 10.11, volume db_data)
```

- **web** : Nginx sert les fichiers statiques de `public/`, ajoute les en-têtes de sécurité et transmet les autres requêtes à PHP-FPM.
- **app** : PHP-FPM exécute Symfony. Image construite sans les dépendances de développement, avec OPcache.
- **db** : MariaDB n'expose aucun port : seul le conteneur `app` peut l'atteindre.

## Procédure de première mise en production

Prérequis sur le serveur : Docker Engine et Docker Compose, un nom de domaine pointant vers le serveur.

1. **Récupérer la version à déployer** (un tag de release) :
   ```bash
   git clone https://github.com/degane6999/rayan-store-symfony-rebuild.git
   cd rayan-store-symfony-rebuild
   git checkout v1.0.0
   ```
2. **Créer les secrets** à partir du modèle, puis remplacer chaque valeur :
   ```bash
   cp .env.prod.example .env.prod.local
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # valeur pour APP_SECRET
   ```
3. **Construire et démarrer** (avec `HTTP_PORT=80` dans `.env.prod.local`) :
   ```bash
   docker compose --env-file .env.prod.local -f docker-compose.prod.yml up -d --build
   ```
4. **Créer le schéma de la base** :
   ```bash
   docker compose --env-file .env.prod.local -f docker-compose.prod.yml exec app php bin/console doctrine:migrations:migrate --no-interaction
   ```
5. **Activer HTTPS** avec un reverse proxy devant le port 80 (Caddy, Traefik ou Nginx + Certbot), puis décommenter l'en-tête `Strict-Transport-Security` dans `docker/nginx/default.conf`.
6. **Vérifier** : la page d'accueil répond, `/admin` renvoie vers la connexion, une commande de test passe jusqu'au paiement simulé.

La commande `app:seed` (données de démonstration) n'est **pas** lancée en production.

## Essai de la configuration de production sur un PC

Le nom de projet `rayan-store-prod` sépare complètement ces conteneurs et leur
volume de ceux du développement : la base de développement n'est pas touchée.

```bash
cp .env.prod.example .env.prod.local        # garder HTTP_PORT=8080
docker compose --env-file .env.prod.local -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.prod.local -f docker-compose.prod.yml exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.prod.local -f docker-compose.prod.yml exec app php bin/console app:seed   # données de démonstration, essai uniquement
```

L'application répond sur http://localhost:8080. `curl -I http://localhost:8080`
affiche les en-têtes de sécurité ajoutés par Nginx. Pour tout arrêter :
`docker compose --env-file .env.prod.local -f docker-compose.prod.yml down`
(ajouter `-v` pour supprimer aussi la base d'essai).

## Mise à jour d'une version

Pour ce projet, la stratégie retenue est la **mise à jour pendant une fenêtre de maintenance** : le trafic est faible et une coupure de quelques minutes est acceptable.

1. Sauvegarder la base :
   ```bash
   docker compose --env-file .env.prod.local -f docker-compose.prod.yml exec db sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" rayan_store' > sauvegarde-$(date +%F).sql
   ```
2. Récupérer le nouveau tag : `git fetch --tags && git checkout v1.1.0`
3. Reconstruire et redémarrer : `docker compose --env-file .env.prod.local -f docker-compose.prod.yml up -d --build`
4. Appliquer les nouvelles migrations (commande de l'étape 4 ci-dessus).
5. **Retour arrière** en cas de problème : revenir au tag précédent, reconstruire, et restaurer la sauvegarde si une migration a modifié les données.

Stratégies envisagées pour une boutique à fort trafic :

- **Bleu/vert** : la nouvelle version (verte) est déployée à côté de l'ancienne (bleue), testée, puis le reverse proxy bascule le trafic d'un coup. Le retour arrière consiste à rebasculer.
- **Mise à jour progressive (rolling update)** : avec plusieurs conteneurs `app`, on les remplace un par un ; le service n'est jamais interrompu.

Dans les deux cas, les migrations doivent rester compatibles avec l'ancienne version du code pendant la bascule (ajouter une colonne avant de l'utiliser, la supprimer seulement à la version suivante).

## Intégration et déploiement continus

- **CI (en place)** : à chaque push sur `main` ou `develop` et à chaque pull request, GitHub Actions installe les dépendances, lance `composer audit`, applique les migrations sur une base MariaDB de test, puis exécute PHPStan, PHP-CS-Fixer et les trois suites PHPUnit, sous PHP 8.2 et 8.4. Il construit ensuite les deux images de production (PHP-FPM et Nginx).
- **CD (prévu)** : sur un tag `v*`, publier les images sur un registre (GitHub Container Registry), puis se connecter au serveur en SSH pour exécuter les étapes 3 et 4.
