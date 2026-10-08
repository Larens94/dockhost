# Silicore Host

Pannello hosting clienti sopra [Dokploy](https://dokploy.com). Non è un fork di Dokploy e non vive in vibes-bridge.

- **Dokploy** = Swarm, Git deploy, SSL, log, terminal.
- **Questo pannello** = cliente → spazio (iscrizione) → dominio → DB condiviso opzionale + storage/SFTP, poi Laravel/Dokploy opzionale.

Vedi [docs/PLAN.md](docs/PLAN.md).

## Repo layout

- `infra/` — template Compose (il pannello lo pubblica su Dokploy via API)
- root — Laravel 13 + Inertia Vue pannello admin

## Avvio locale

Herd PHP va lanciato senza `auto_prepend` (dump-loader rotto):

```bash
PHP="/Users/fabriziocorpora/Library/Application Support/Herd/bin/php84 -d auto_prepend_file= -d auto_append_file="
cp .env.example .env
$PHP artisan key:generate
$PHP artisan migrate --force
$PHP artisan db:seed
npm install && npm run build
# artisan serve re-applies Herd dump-loader; use the built-in server:
$PHP -S 127.0.0.1:8000 -t public
```

Login: `ADMIN_EMAIL` / `ADMIN_PASSWORD` (`.env.example` usa `admin@example.com`; se `ADMIN_PASSWORD` è vuota lo seed imposta `password`).

Test:

```bash
$PHP vendor/bin/phpunit
```

Non eseguire `php artisan config:cache` in build Docker.

## Deploy su Dokploy

Remote: `https://git.silicoreautomation.com/internal/dokhosts.git` (`main`).

Il pannello è un’Application autonoma (SQLite + volume). Da UI *Infrastructures* il pannello crea un **nuovo progetto Dokploy** (`project.create` + environment production + `compose.create` / `update` / `deploy`, isolated off, `dokploy-network`). Non mette lo stack nel progetto del pannello.

- Provider GitLab, branch `main`, build **Dockerfile**, porta **80**, isolated **off**
- Volume persistente su `database/` e `storage/`
- Env runtime (secret solo in Dokploy):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<pannello>
APP_KEY=base64:...
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
DOKPLOY_URL=https://<dokploy>
DOKPLOY_API_KEY=
DOKPLOY_ENVIRONMENT_ID=
DOKPLOY_SELF_APPLICATION_ID=
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

`DOKPLOY_ENVIRONMENT_ID` è solo un default storico; le Application Laravel vanno nell'environment dell'infra (`infrastructure.dokploy_environment_id`). Il pannello non monta i volumi delle infra: lo stack espone `{slug}-sftp-sync`. `config:cache` solo a runtime.

## Sicurezza

Token Dokploy e password SQL admin solo in env server. Mai in chat, mai nel frontend.
