# DockHost — handoff per la prossima chat

Usa questo file se apri un clone o una nuova chat. Aggiornato: 11 ottobre 2026.

## Cosa è

Pannello hosting clienti **sopra** Dokploy (ibrido Plesk-like + PaaS). Non è un clone di Plesk, non è un fork di Dokploy.

- **Dokploy** = Swarm, Git provider, Deploy, SSL, log, terminal. Non clonarli in UI.
- **Questo pannello** = Cliente → **Spazio** (subscription) → Dominio → schema sul DB **condiviso** (opzionale) + storage + SFTP, poi Application Dokploy/Laravel (env + volume + HTTPS; git su Dokploy).

Un motore MariaDB/Postgres per N app. **Non** un container DB per sito (`mariadb.create` è un container nuovo: vietato).

## Dove sta il codice

| | |
|---|---|
| **Source of truth** | https://github.com/Larens94/dockhost |
| Branch | `main` |
| Vecchio GitLab (non usare) | `git.silicoreautomation.com/internal/dokhosts.git` |
| Vecchio nome GitHub | `Larens94/management-cloud` (rinominato / sostituito da `dockhost`) |

Ignorati di proposito: `.env`, `database/database.sqlite`, `vendor/`, `node_modules/`, `public/build/`, `storage/inertia-devtools/`.

## Fasi (docs/PLAN.md)

| Fase | Stato |
|---|---|
| **0** Template infra Compose | Fatto (deploy via API pannello, non Compose a mano) |
| **1** Pannello admin + wizard + API Dokploy | Fatto, testato locale + cloud |
| **1b** Ricetta release Laravel (artisan a runtime) | Minimo fatto; migrate a runtime |
| **2** Catalogo prodotti (ERP/Go, wizard tipo WP) | Non fatto |
| **3** API pannello per Neo AI / ERP | Non fatto |

SSH per-sito, file manager, email, WordPress pubblico, quote disco, billing: **fuori v1**.

## Infra (`infra/infra1/`)

Compose, Isolated Deployments **OFF**, rete esterna `dokploy-network`, nomi univoci:

- `infra1-mariadb` (MariaDB 11)
- `infra1-postgres` (Postgres 16)
- `infra1-phpmyadmin`
- `infra1-sftp` (atmoz/sftp)
- volume `infra1_data` → `/data` e `/home`
- volume `infra1_sftp_config` → `/etc/sftp` (`users.conf`)

Il pannello scrive utenti SFTP via `sftp-sync`. PDO admin **pigri** (Closure): il container parte anche se MariaDB/Postgres non sono up.

Se l’infra è satura: crea **infra2** solo per il DB.

**Password MariaDB:** tre copie (pannello / env Dokploy / datadir). Solo il datadir è source of truth dopo il primo init. Non wipeare e non “Aggiorna stack” se sospetti drift: ispezionare grants/log prima.

## Pannello (Laravel 13 + Inertia Vue)

Admin + membri dominio. Flusso: cliente → spazio (piano) → dominio.

- `POST customers/{id}/subscriptions` oppure `POST /subscriptions`
- `POST subscriptions/{id}/domains` — storage + SFTP; DB e stack opzionali
- Dopo: DB, SFTP extra, attach Laravel, Toolkit
- Lista Laravel: `GET /laravel-toolkit`

## Integrazione Dokploy

**HTTP API** (`x-api-key`). MCP Dokploy opzionale in Cursor (`mcp/dokploy`), non obbligatorio in produzione.

`app/Services/Dokploy/DokployClient.php`: compose/application/domain/deploy/env.

Env: `DOKPLOY_URL`, `DOKPLOY_API_KEY`. Le Application Laravel usano `infrastructure.dokploy_environment_id`.

Production foundations (branch `cursor/production-foundations`): heal MariaDB senza wipe (`POST …/heal-mysql`); backup DB dominio; environment Dokploy sotto infra (tab Ambienti); allinea DB da altro dominio con guardrail production. Dopo deploy: `php artisan migrate`.

## Deploy pannello

Su Dokploy: Application Git da **GitHub** `Larens94/dockhost`, branch `main`, Dockerfile, porta 80, isolated off. Non usare più il remote GitLab.

## Regole da non rompere

- No fork Dokploy, no vibes-bridge, no secondo Traefik, no MySQL-per-sito.
- Isolated **off** altrimenti le app non vedono `infra1-mariadb`.
- Segreti solo in env Dokploy, mai in chat/frontend.
- Source of truth codice: **GitHub** `Larens94/dockhost`.

## Prompt da incollare nella chat nuova

```
Apri https://github.com/Larens94/dockhost (main). Leggi docs/HANDOFF.md e docs/PLAN.md.
Pannello sopra Dokploy; infra su dokploy-network; isolated off.
Non usare il vecchio GitLab internal/dokhosts. API HTTP x-api-key.
Non committare .env.
```
