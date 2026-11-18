# Recompo

Tracker sportif personnel, mono-utilisateur, pour une recomposition corporelle : course sur tapis + musculation avec haltères à domicile.
Laravel 13, Inertia v3, Vue 3 (TypeScript), Tailwind CSS 4, MySQL 8. Interface en français.

- Référence visuelle et fonctionnelle : [`docs/prototype.html`](docs/prototype.html). Cahier des charges : [`docs/PROMPT.md`](docs/PROMPT.md).
- Graphiques : composant SVG maison porté du prototype (`resources/js/components/Chart.vue`), sans dépendance : rendu identique au prototype, rien de lourd à charger.
- Illustrations d'exercices : portées du prototype (`resources/js/lib/illustrations.ts`). Une image importée (`image_path`) les remplace sans changer le code.

## Installation locale

Prérequis : PHP 8.4, Composer, Node 20+, MySQL 8 (ou SQLite pour essayer).

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
# Renseigner DB_* (ou DB_CONNECTION=sqlite) et APP_OWNER_EMAIL / APP_OWNER_PASSWORD
php artisan migrate --seed          # utilisateur unique, 13 exercices, programme du prototype
php artisan db:seed --class=DemoHistorySeeder   # facultatif : 6 semaines d'historique fictif (jamais en production)
php artisan storage:link
composer run dev                     # serveur + Vite
```

Ou avec Docker (application + MySQL 8) : `docker compose up -d --build`, puis `docker compose exec app php artisan db:seed --force`. L’application répond sur http://localhost:8080 (variable `APP_PORT`).

### Créer ou modifier l'utilisateur

L'inscription publique est désactivée. L'utilisateur unique est créé par le seeder (`APP_OWNER_*`) ou par :

```bash
php artisan app:create-user                       # interactif
php artisan app:create-user --name=Enzo --email=moi@exemple.fr --password='…'
```

Relancer la commande avec le même email met à jour le mot de passe.

## Tests et qualité

```bash
php artisan test        # Pest : règles métier (snapshot, volume, vitesse moyenne, pré-remplissage, statut de semaine, pesées…)
vendor/bin/pint         # style PHP
npm run types:check     # vue-tsc
```

## Organisation

- `app/Services` : logique métier testée (`SessionStarter`, `VolumeCalculator`, `RunMetrics`, `PersonalRecords`, `WeekStatus`, `StatsService`, `CalorieEstimator`, `WorkoutWriter`).
- `app/Http/Controllers` : un contrôleur par écran ; `app/Http/Requests` : validations ; `app/Http/Presenters/Present.php` : données envoyées aux pages.
- `resources/js/pages` : écrans Inertia ; `resources/js/components` : composants partagés ; `resources/js/lib/domain.ts` : mêmes formules que le serveur pour l'affichage en direct.
- Le programme (séances type) et l'historique (séances réalisées) sont séparés : une séance réalisée garde une copie des noms et des cibles. Supprimer une séance du programme l'archive si elle a un historique.
- Les routes JSON (`/api/live`, `/api/exercises/{id}/history`) utilisent la session web (middleware `auth`), pas de jeton.

## Déploiement sur VPS (Coolify, méthode par défaut)

1. Installer Coolify sur le VPS (Ubuntu LTS) et connecter le dépôt GitHub. Créer une ressource **Dockerfile** sur la branche `main` (déploiement automatique à chaque push).
2. Créer une base **MySQL 8** dans Coolify (même réseau) et reporter ses identifiants dans les variables `DB_*`.
3. Variables d'environnement (voir `.env.example`) : `APP_KEY` (`php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `APP_OWNER_*`, `BACKUP_S3_*`, `BACKUP_ARCHIVE_PASSWORD`.
4. **Volume persistant** monté sur `/app/storage` (images importées, logs). Il n'est jamais dans l'image.
5. Healthcheck : `/up` (port 80). Le conteneur lance les migrations, `storage:link` et les caches au démarrage (`docker/entrypoint.sh`).
6. Premier déploiement : `php artisan db:seed --force` dans le terminal Coolify (utilisateur, exercices, programme).
7. **Scheduler** : tâche planifiée Coolify `php artisan schedule:run` chaque minute (ou un second conteneur `php artisan schedule:work`, voir `docker-compose.yml`). Pas de file d'attente en V1.

L'image utilise FrankenPHP (PHP + Caddy dans un seul processus) : un seul service à superviser au lieu de Nginx + PHP-FPM ; le TLS est géré par le proxy de Coolify.

### Méthode alternative : installation manuelle (Nginx + PHP-FPM + MySQL)

```bash
# Une fois : PHP 8.4 (fpm, mysql, zip, mbstring, xml, curl), Nginx (root = public/), MySQL 8, Node 22, client mysql, certbot
# Puis à chaque déploiement :
cd /var/www/recompo
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
sudo systemctl reload php8.4-fpm
```

Cron : `* * * * * cd /var/www/recompo && php artisan schedule:run >> /dev/null 2>&1`.

## Sauvegardes

`spatie/laravel-backup`, planifié chaque nuit (`routes/console.php`) : dump MySQL + `storage/app/public` (images), envoyé sur un stockage compatible S3 hors du VPS (`BACKUP_S3_*`), archive chiffrée si `BACKUP_ARCHIVE_PASSWORD` est défini. Rétention : 7 sauvegardes quotidiennes, 4 hebdomadaires. Mail en cas d'échec (`BACKUP_NOTIFY_EMAIL`).

```bash
php artisan backup:run            # sauvegarde immédiate
php artisan backup:list           # sauvegardes disponibles
php artisan app:backup-restore    # restaure la plus récente (demande confirmation)
php artisan app:backup-restore "Recompo/2026-11-09-03-00-00.zip" --force
```

La restauration remplace la base (client `mysql` requis, présent dans l'image Docker) et les images importées.

## Checklist sécurité du serveur (hors code)

- [ ] SSH par clé uniquement (`PasswordAuthentication no`, `PermitRootLogin no`)
- [ ] Pare-feu : seulement 22, 80, 443 (`ufw`)
- [ ] Mises à jour de sécurité automatiques (`unattended-upgrades`)
- [ ] `fail2ban` sur SSH
- [ ] MySQL non exposé publiquement ; un utilisateur MySQL par projet du VPS
- [ ] Ressources limitées par conteneur (le VPS est partagé avec d'autres projets)
- [ ] Sauvegardes hors du VPS vérifiées : tester une restauration de temps en temps

Côté application : pas de route d'inscription, connexion limitée à 5 tentatives par minute, HTTPS forcé en production, cookies sécurisés, en-têtes de sécurité (`SecurityHeaders`), proxys de confiance (`TRUSTED_PROXIES`).

# training-sport
