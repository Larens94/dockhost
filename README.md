# DockHost

Control plane for dense multi-tenant hosting on top of **Dokploy**.

DockHost is the **admin panel** (anagrafica, wizard, billing, pools).  
**Laravel is the first installable application** in the recipe catalog — not “the panel”.

Durable pieces:

- **Domain**: clients, sites, pools, servers, recipes, templates
- **Recipes**: installable apps (Laravel first; WordPress / Node / static / custom next)
- **InfrastructureDriver**: Dokploy API today; swappable later

Dokploy remains the runtime executor (deploy, SSL, logs, cron). DockHost owns anagrafica and provisioning choices.

## Architecture

```
DockHost (panel: clients, billing, wizard, pools)
    └── recipes → first app: Laravel (others later)
    └── InfrastructureDriver → Dokploy API
            └── Swarm / remote VPS
```

Data HA / volume replication is intentionally **phase 2**.

## Layers

| Layer | Role |
|---|---|
| Admin panel | DockHost (UI + business logic) |
| Installable apps | Recipes — Laravel is #1 product |
| Infra services | MariaDB, Postgres, Redis, SFTP… |
| Driver | Dokploy API |

## Quick start

```bash
cd dockhost
composer install
npm install --legacy-peer-deps
php artisan migrate --seed
npm run build
php artisan serve
```

Login: `admin@dockhost.local` / `password` (superadmin only — public registration disabled)

```
DOKPLOY_URL=https://your-dokploy.example
DOKPLOY_API_KEY=...
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

CodeDNA (agent support): https://github.com/Larens94/codedna — `.codedna` + L1 headers installed.

## Billing & wizard

- **Billing**: Stripe plans/subscriptions on clients (`/billing`). Stub without keys.
- **Wizard** (`/wizard`): sì/no + pool for DB, storage, SFTP, cache, then choose app recipe (Laravel first).
- Do **not** rebuild Dokploy features (deployments, logs, SSL UI, cron, docker inspect).

## UI

Light theme by default, patterned after Dokploy (sidebar, home metrics, Projects-style lists) so DockHost reads as a companion extension.
