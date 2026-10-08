# Silicore Host — handoff per la prossima chat

Usa questo file se cambi cartella Cursor o apri un clone GitLab. Data: 20 settembre 2026.

## Cosa è

Pannello hosting clienti **sopra** Dokploy (ibrido Plesk-like + PaaS). Non è un clone di Plesk, non è un fork di Dokploy, non vive in vibes-bridge.

- **Dokploy** = Swarm, GitLab/provider, Deploy, SSL, log, terminal. Non clonarli in UI.
- **Questo pannello** = Cliente → **Spazio** (subscription) → Dominio → schema sul DB **condiviso** (opzionale) + storage + SFTP, poi Application Dokploy/Laravel (env + volume + HTTPS; git su Dokploy).

Un motore MariaDB/Postgres per N app. **Non** un container DB per sito (`mariadb.create` è un container nuovo: vietato).

## Dove sta il codice

| | |
|---|---|
| Path locale attuale | `/Users/fabriziocorpora/Desktop/silicore-new/silicore-workspaces/gitlabs/dokhosts` |
| Vecchio path | `/Users/fabriziocorpora/Desktop/studio/silicore-workspaces/management-cloud` (solo `docs/HANDOFF.md`, senza `.git`) |
| GitHub (ancora origin noto) | https://github.com/Larens94/management-cloud.git |
| Branch | `main` |
| SHA verificato | `ee8fb6cb051958cc7468afea7b303b5dcf92cc54` |
| Working tree all’ultimo check | pulito vs `origin/main` |

Ignorati di proposito: `.env`, `database/database.sqlite`, `vendor/`, `node_modules/`, `public/build/`, `storage/inertia-devtools/`.

## Fasi (docs/PLAN.md)

| Fase | Stato |
|---|---|
| **0** Template infra Compose | Fatto (deploy via API pannello, non Compose a mano) |
| **1** Pannello admin + wizard + API Dokploy | Fatto, testato locale + cloud |
| **1b** Ricetta release Laravel (artisan a runtime) | Non fatto |
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

Il pannello scrive `INFRA_SFTP_USERS_FILE` default `/etc/sftp/users.conf` e crea `/data/{username}` (home atmoz). PDO admin **pigri** (Closure): il container parte anche se MariaDB/Postgres non sono up.

Se l’infra è satura: clona come **infra2** solo per il DB.

## Pannello (Laravel 13 + Inertia Vue)

Admin only. Flusso: cliente → spazio (piano) → dominio.

- `POST customers/{id}/subscriptions` oppure `POST /subscriptions`
- `POST subscriptions/{id}/domains` — storage + SFTP sempre; `create_database` e `attach_laravel` opzionali
- Dopo: `POST domains/{id}/database` e `POST domains/{id}/laravel`
- Lista Laravel: `GET /laravel-toolkit`

**UI:** form minimale (card bianche), **non** ancora look Dokploy. Fabrizio lo vuole allineare dopo, per non sentire lo stacco.

## Integrazione Dokploy

**Solo HTTP API** (`x-api-key`), **non MCP**.

`app/Services/Dokploy/DokployClient.php`:

- `application.create`
- `application.saveEnvironment` (APP_URL + DB_*)
- `domain.create` (HTTPS Let’s Encrypt)
- GitLab/Deploy: **Dokploy**, non `saveGitProvider` da URL
- `project.all`

Env: `DOKPLOY_URL`, `DOKPLOY_API_KEY`. Senza environment ID il wizard **non** chiama Dokploy.

## Avvio locale (Herd)

Dump-loader Herd è rotto. Sempre:

```bash
PHP="/Users/fabriziocorpora/Library/Application Support/Herd/bin/php84"
$PHP -d auto_prepend_file= -d auto_append_file= artisan …

# HTTP: NON usare artisan serve (i figli re-iniettano dump-loader)
$PHP -d auto_prepend_file= -d auto_append_file= -S 127.0.0.1:8766 -t public
```

```bash
cp .env.example .env
# key:generate, migrate --force, db:seed
npm install && npm run build
$PHP -d auto_prepend_file= -d auto_append_file= vendor/bin/phpunit
```

Login: `ADMIN_EMAIL` / `ADMIN_PASSWORD`. Se password env vuota, seed = `password`. Email di default `.env.example` = `admin@example.com`.

**Mai** `php artisan config:cache` in build Docker; solo a runtime dopo gli env.

## Test

PHPUnit **32 test / 189 assertion** (ultimo run verde). Include cliente→spazio→dominio, DB/Laravel in create o dopo, Toolkit, provision MySQL/Postgres, storage/SFTP, attach Dokploy, guest redirect.

## Prossimo lavoro (rilascio)

1. Deploy **pannello** come Application Git (SQLite, isolated off). Env `DOKPLOY_URL`, `DOKPLOY_API_KEY`, `DOKPLOY_ENVIRONMENT_ID`.
2. Da UI Infrastructures crea `infra1` (API compose, isolated off).
3. Prova: login → infra → cliente → dominio → SFTP/DB. Poi attach app con environment ID + git URL GitLab.
5. UI stile Dokploy (sidebar/tema) se si vuole continuità visiva.
6. Fase 1b (migrate/queue a runtime).

## Regole da non rompere

- No fork Dokploy, no vibes-bridge, no secondo Traefik, no MySQL-per-sito.
- Isolated **off** altrimenti le app non vedono `infra1-mariadb`.
- Segreti solo in env Dokploy, mai in chat/frontend.
- Commit solo se chiesto (salvo push necessario per cloud).
- `.ai/rules` nel repo: assente all’ultimo check; Boost Laravel vale comunque.

## Memorie / canvas (Mac di Fabrizio)

- Personal store: `dokploy-laravel-toolkit.md`, `piano-pannello-hosting.md`, `silicore-host-handoff.md`
- Canvas: `dokploy-ecosystem`, `dokploy-laravel-gaps`, `hybrid-paas-hosting-research`, `piano-pannello-hosting`

## Prompt da incollare nella chat nuova

```
Apri il repo Silicore Host (management-cloud). Leggi docs/HANDOFF.md e docs/PLAN.md.
Siamo a fine fase 0–1 su main ee8fb6c.
Prossimo: remote GitLab + deploy infra1 e pannello su Dokploy. API HTTP
x-api-key, non MCP. Isolated off. Non committare .env.
```
