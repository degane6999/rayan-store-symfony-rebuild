# Lancer avec Docker (recommandé si PHP 8.2+/zip posent problème)

Nécessite seulement **Docker Desktop** installé (Mac/Windows) ou **Docker
Engine + Docker Compose** (Linux) — pas de PHP, Composer ou MariaDB à
installer soi-même.

```bash
cd rayan-store-symfony-rebuild
docker compose up --build
```

Premier lancement : télécharge l'image PHP, installe les dépendances via
Composer (avec zip/unzip déjà inclus dans l'image), démarre MariaDB, attend
qu'elle soit prête, lance les migrations, charge les données de démo, puis
démarre le serveur. Compte 2 à 5 minutes selon la connexion.

Une fois que le terminal affiche que le serveur écoute, ouvre :

**http://127.0.0.1:8000**

Comptes de démo (mot de passe `password123`) :
- `demo@rayan.store` (client)
- `vendeur@rayan.store` (vendeur)
- `admin@rayan.store` (admin)

Pour arrêter : `Ctrl+C` puis `docker compose down` (ajoute `-v` pour aussi
supprimer les données de la base et repartir de zéro au prochain lancement).

## Important — non testé dans mon environnement

Contrairement à tout le reste de ce dépôt (tests, PHPStan, parcours complet),
je n'ai **pas pu exécuter ce `docker-compose.yml` moi-même** : le
sandbox où j'ai travaillé n'a pas de démon Docker actif. Le fichier suit un
schéma standard pour une app Symfony + MariaDB, mais **teste-le avant de t'y
fier pour la soutenance** — lance `docker compose up --build` à l'avance, pas
la veille au soir.

Si quelque chose ne démarre pas, lance `docker compose logs app` et
`docker compose logs db` et montre-moi le message d'erreur exact.
