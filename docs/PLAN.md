# Piano

## Visione

Dokploy è il motore Docker/Swarm. Questo repo è l’anagrafica hosting (Plesk-like) e, dopo, il catalogo dei prodotti Silicore.

Un motore MariaDB/Postgres per N app. Non un container DB per sito.

## Strati

1. **Pannello** — Laravel + Inertia, admin only, Application autonoma (SQLite). API HTTP Dokploy (`x-api-key`). Progetto Dokploy dedicato (es. `internal-application-dokhosts-*`). **Non** è un’infrastruttura hosting.
2. **Infra** — creata dal pannello (`compose.create` + deploy), un **progetto Dokploy per infra** (`infra1`, `infra2`, …). Template in `infra/` / `ComposeTemplate`. Isolated **OFF**, rete `dokploy-network`, alias `${slug}-mariadb`. Volumi e container **solo** dentro quel progetto.
3. **Prodotti / ERP** — fase successiva. Neo AI chiama le API di questo pannello, non Dokploy.

## Separazione volumi (modello target)

| Application Dokploy | Volumi | Scopo |
| --- | --- | --- |
| **DokHosts** (pannello) | **Al massimo uno** (es. `dokhosts_panel` → `/var/www/html/database` o `storage/`) | SQLite, log, cache del panel. **Mai** `{slug}_data` delle infrastrutture. |
| **Stack infra1** | `infra1_data`, `infra1_sftp_config`, … | Storage clienti + `users.conf` SFTP dello stack `infra1`. |
| **Stack infra2** | `infra2_*` | Idem, progetto separato. |
| **App Laravel cliente** | mount `{slug}_data` → `/data` | Solo quell’app, path `DOKHOSTS_STORAGE_PATH`. |

Il pannello parla con MariaDB/Postgres via **rete** (`{slug}-mariadb` su `dokploy-network`). Non deve montare i volumi dell’infra per il DB.

### Sync SFTP (sidecar nello stack)

`sftp-sync` nello stack infra (Python su `:8787`, alias `{slug}-sftp-sync`) riceve da DokHosts la lista utenti e le directory. Scrive `/etc/sftp/users.conf` e crea path sotto `/data` sui volumi **interni** allo stack. Il pannello **non** monta `{slug}_data`.

`SftpDaemonSync` fa `POST http://{slug}-sftp-sync:8787/sync` con Bearer `sftp_sync_token`. Nuovi utenti atmoz richiedono un restart del container SFTP (Aggiorna stack) per essere letti da `users.conf`.

## Modello

Cliente → domini. Sotto un dominio: uno o più database (CREATE DATABASE sull’infra scelta) e uno o più storage (`/data/{cliente}/{dominio}/`). Un utente SFTP virtuale chrootato. Poi Application Dokploy (Laravel/Node/Go/Python).

SSH per-sito: no. Fatture, email, WordPress pubblico, quote disco: no.

## Fasi

- **0** Template infra (codice). Deploy Compose via API del pannello.
- **1** Pannello: clienti, wizard dominio+DB+storage+SFTP, crea/aggancia Application.
- **1b** Ricetta release Laravel (env minimi + artisan a runtime, mai `config:cache` in build). Fatto il minimo; niente Dockerfile per ogni repo cliente.
- **2** Catalogo prodotti.
- **3** API pannello per ERP.

## Necessario ora

Gap go-live del flusso Cliente → Spazio → Dominio. Billing, file manager, wizard Node/Go/Python, multi-env, applicazioni grandi (EWM) e servizi dedicati restano in «Più avanti».

### Fatto

- **Application Laravel nel project/env dell’infra** — `attachLaravel` usa `infrastructure.dokploy_environment_id` (e `projectId` se presente). Niente environment ID incollato in UI.
- **Volume `{slug}_data` sull’app Laravel** — `mounts.create` su `applicationId`, mount `/data`, env `DOKHOSTS_STORAGE_PATH` = path StorageShare (`/data/{slug}/{cliente}/{dominio}`).
- **Deploy dopo attach** — `application.deploy` dopo create/env/git/domain/mount.
- **Sidecar SFTP** — `sftp-sync` nello stack; il panel non monta volumi infra.
- **Credenziali al primo provision** — `DomainController::store` / `storeDatabase` flashano `revealed_credential` (banner ambra una volta).
- **Errori creazione infra** — riga locale `pending` poi `failed` + `last_error` e errore di form, non 500, se Dokploy fallisce dopo il insert.
- **SFTP pubblico** — card dominio mostra `dokploy.public_host` + `sftp_host_port`, non solo `{slug}-sftp`.
- **1b ricetta Laravel minima** — env `APP_URL`, `DB_*`, `LOG_CHANNEL=stderr`, `FILESYSTEM_DISK=local`, `DOKHOSTS_STORAGE_PATH`. Le app cliente devono fare `migrate` **a runtime** (entrypoint), mai `config:cache` in **build**. Dockerfile/Nixpacks restano nel repo del cliente.
- **Servizi opzionali stack** — phpMyAdmin (on di default), pgAdmin / Redis / MinIO (off). Card picker in create e in **Modifica servizi**. Aggiorna stack rigenera compose; domini `pma-` / `pga-` (porta 80) e `minio-` (console 9001) sul wildcard. Redis e API MinIO restano interni. Attach Laravel aggiunge `REDIS_*` / `AWS_*` solo se quei servizi sono attivi, senza forzare cache/disk. Infra già create: clicca Redis (o altro) su Show, poi lo stack si aggiorna.

### Operativo ora

- Sull’app `internal-application-dokhosts-*` **elimina** i mount verso `/data/…` e `/etc/sftp/…`.
- Redeploy del panel, poi **Aggiorna stack** su ogni infra già creata (aggiunge `sftp-sync` + token).
- Nuovi utenti SFTP: dopo il sync, restart del servizio `sftp` (o Aggiorna stack) perché atmoz legge `users.conf` all’avvio.

Isolated **off** e rete `dokploy-network` sono già nel template.

## Regole Dokploy

- Non usare `mariadb.create` per ogni sito (nuovo container).
- Isolated templates **off** altrimenti le app non vedono il DB.
- `config:cache` solo a runtime dopo gli env.
- Volume persistente su `storage` delle app Laravel.

## Più avanti: Mailhog e Meilisearch

Non ora. Restano servizi opzionali dello stack (come Redis/MinIO): toggle, hostname interno, eventuale dominio console. Aggiungerli dopo che pgAdmin/Redis/MinIO sono usati in produzione.

## Più avanti: FTPS e ACL fini

Non ora. Oggi gli utenti extra sono DB (ALL vs SELECT sullo stesso database) e SFTP (atmoz/sftp, password alfanumerica senza `:`). FTPS (TLS sul file transfer) e privilegi più granulari (tabella/colonna, path SFTP per-cartella) restano dopo.

## Più avanti: servizi dedicati per sito

Non ora. Solo dopo che l’infra centralizzata e il project-per-infra funzionano.

Default resta: le app (oggi Laravel; poi Node/Go/Python) si agganciano a uno stack infra condiviso scelto.

Opzione successiva: per ogni servizio, poter **non** usare l’infra condivisa e attaccare invece un servizio Dokploy **dedicato** al sito — es. MariaDB proprio, SFTP/volume proprio, ecc. I dedicati, quando arriveranno, restano **per environment**.

## Più avanti: environment Dokploy (dev / prod, e altri a richiesta)

Non ora. Oggi una Infrastructure DokHosts è già un **Project** Dokploy (`dokploy_project_id`) e un solo environment, di solito `production` (`dokploy_environment_id`). `DokployClient::createEnvironment` (`environment.create`) esiste già: il provisioner lo usa se `project.create` non restituisce un env.

Modello da implementare dopo:

- Infrastructure = un Project. Sotto, una lista di environment. Di base **dev** e **prod**. Staging, test o un altro nome si aggiungono: non è un elenco fisso di quattro.
- Ogni environment è la stessa repo, un branch, un database e le sue variabili. Dev non usa il database di prod. L’utente del sito resta `user@'%'` con GRANT solo su quel database.
- Replicare = `environment.create` sul project + clone compose/servizi, oppure stesso template Compose con suffix slug e credenziali nuove (non inventare API assenti dal client: compose create/update/deploy sì; un “clone environment” dedicato **no**).
- Le Application (Laravel ora; poi Node/Go/Python, compreso un caso come EWM) scelgono **infra + environment**, non un environment ID incollato a mano.
- Servizi condivisi su quell’env: decidere una regola nomi prima di scrivere codice. Proposta: production resta `${slug}-mariadb`; gli altri env `${slug}-${env}-mariadb` (stesso schema per postgres/sftp). Così `DB_HOST` punta al servizio di quell’env.
- I servizi dedicati per sito (sezione sopra) si applicano comunque **per env**.

## Più avanti: applicazioni grandi (es. EWM Platform)

Non ora. Primo caso oltre il sito Laravel singolo: API Node (Express, TypeScript, Node ≥ 20), monorepo client + server, Postgres, file, e un binario Go compilato in build (watcher FTP). Parte su porta propria (oggi 5001).

Cosa entra nel pannello:

- Stack **Node** sull’infra scelta. Nixpacks non inferisce da solo un monorepo Node + Go: il build va scritto (`go build` del watcher, `tsc`, build del client se c’è). Lo start è `node dist/index.js`. La porta del processo è la porta del dominio Traefik.
- Postgres dell’infra, un database per environment, utente con GRANT solo su quel database. I file possono stare su MinIO dell’infra. S3, Resend, Expo e — se resta — Supabase sono solo variabili d’ambiente verso servizi esterni.
- Le policy di riga (RLS) non le ricrea DokHosts. O restano su Supabase, o si riscrivono su Postgres.
- Helmet, CORS, rate limit, Swagger, cron, Vitest e Supertest restano nel repo e nella CI GitLab. SOC2, GDPR e ISO 27001 sono audit dell’applicazione, non un toggle del pannello.
- Gli environment sono quelli della sezione sopra: almeno dev e prod; gli altri si aggiungono.

---

# Masterplan prodotto: Amy AI Station

**Soluzione On-Premise per ERP, AI e Automazione a marchio Silicore Automation**

Appliance hardware all-in-one per aziende e mercato residenziale: ecosistema privato, esente da canoni cloud ricorrenti, sovrano sui dati. **DokHosts** (questo repo) è il pannello di orchestrazione Dokploy che prepara l’hosting multi-tenant; la Station è il prodotto commerciale che lo consuma insieme a ERP, AI e automazione.

## Architettura infrastrutturale e stack

Microservizi containerizzati in locale, latenza minima, alta affidabilità.

### Livello sistema e orchestrazione

- Host Linux ottimizzato; Docker orchestrato via **Dokploy** (invisibile al cliente).
- Reverse proxy **Traefik** per routing interno (`erp.local`, `ai.local`, …).
- Process manager (es. **PM2**) per demoni Node.js/TypeScript del layer type-safe, con riavvio automatico.

### Core gestionale (Amy AI)

- **Laravel** + **PostgreSQL**.
- Mobile/tablet: **React Native** + **Expo** (magazzino, mobilità).

### Storage e condivisione

- SMB/Samba locale: esperienza tipo Dropbox/Drive, on-prem.

### Accesso remoto e sicurezza

- Tunnel zero-config (**Cloudflare Tunnels** o **Tailscale**): accesso crittografato, nessuna porta aperta sul router del cliente.

## Motore AI, MCP e type-safe

Interfacce conversazionali rigorose al posto del prompting generico.

### Inferenza locale (Ollama)

| Worker | Ruolo |
| --- | --- |
| **Vision** | MiniCPM-V 8B — estrazione visiva JSON (fatture, DDT, scontrini) |
| **Intent** | Modelli compatti — parsing linguaggio naturale |

### Middleware Node.js type-safe

- **TypeScript** + validazione (**Zod**): l’LLM produce solo payload JSON conformi alle API del gestionale.

### Automazione MCP

- Server **MCP** per Web Agents (Playwright) e OS Agents (filesystem).
- Comandi vocali → trigger **MQTT/Zigbee** (domotica: luci, accessi, clima).

## Gestione, continuità e sicurezza

Hardware fisico in sede cliente ⇒ backup, OTA e telemetria non opzionali.

### Backup ibrido

- Snapshot giornalieri PostgreSQL + documenti.
- Copia su disco secondario interno **e** replica asincrona E2E-encrypted verso cloud centrale Silicore.

### Aggiornamenti OTA

- Pannello remoto → comandi **Dokploy** per aggiornare immagini Docker (ERP + pesi modelli AI) in finestra notturna, zero downtime lavorativo.

### Supporto e telemetria

- Monitoraggio leggero (**Prometheus/Grafana** o equivalente): alert su disco pieno, temperature Station, ecc. verso pannello centrale.

## Profili commerciali (Hardware-as-a-Service)

| Soluzione | Hardware | Servizi | Setup | Canone/mese |
| --- | --- | --- | --- | --- |
| **Station Start** | Mini PC Ryzen 5/i5, 32 GB, 1 TB SSD | Storage, ERP base, tunnel, backup remoto | 790–990 € | 29–49 € |
| **Station Business** | Workstation 64 GB, 2 TB + GPU | ERP completo, OCR MiniCPM-V, RAG aziendale | 1.890–2.490 € | 79–99 € |
| **Station Domus** | Mini PC 32 GB, 1 TB + dongle Zigbee | Home Assistant, voice agent type-safe, cloud media | 1.290–1.690 € | 29 € |

## Go-to-market

- **ROI e incentivi** — non “spesa IT”: risparmio canoni cloud + Sabatini, ZES Unica, voucher MIMIT.
- **Pilota 30 giorni** — installazione gratuita in 2–3 aziende locali; demo acquisizione fattura cartacea in ~10 secondi.
- **Video funnel** — split-screen azione fisica (unboxing, foto fattura, voce) vs reazione software.

## Quattro leve competitive

### 1. NPU per voice-wakeup a consumo quasi zero

Mini PC con Intel Core Ultra o AMD Ryzen AI: **NPU** per wake word e ASR leggero (&lt; 5 W). GPU solo per OCR e ragionamento pesante.

### 2. Audio design e feedback sensoriale

Suoni di conferma, toni di avvio, voce di sistema curati (non UI meccanica open source). Percezione premium tipo Apple/Tesla nei promo e nell’uso quotidiano.

### 3. Metadati strutturati in-source per il RAG (stile CodeDNA)

Prima dell’embedding, Vision/parser inietta metadati e annotazioni standard nel testo. RAG interno più preciso del vettoriale grezzo; meno contratti obsoleti o irrilevanti in retrieval.

### 4. App-esca: timbratore / registro presenze

Modulo base ERP (Laravel + React Native): timbratura mobile o tablet all’ingresso. Cavallo di Troia operativo — uso quotidiano obbligato da dipendenti e titolari.

## Collegamento con DokHosts (roadmap repo)

| Fase repo (sopra) | Ruolo verso Amy AI Station |
| --- | --- |
| **0–1** Infra + pannello hosting | Provision stack per tenant/Station; SFTP, DB, deploy app |
| **2** Catalogo prodotti | Offerte Start / Business / Domus come “piani” o bundle |
| **3** API pannello per ERP | Neo AI / Amy chiamano DokHosts, non Dokploy diretto |
| **Backup / OTA / telemetria** | Nuovi moduli pannello o sidecar; OTA via `compose.deploy` / `application.deploy` già esposti dal client Dokploy |
| **Marketplace 1-click** | Catalogo Dokploy via API DokHosts: app curate + repo pubbliche per chi è esperto |

## Più avanti: quattro pilastri Station

Non ora. Dopo hosting stabile e API pannello. Quattro assi: rete, ERP imprese, casa, AI.

### Intranet ed extranet

- **Intranet** — Traefik su domini locali (`erp.silicore.local`). Middleware Node.js sotto PM2, solo LAN.
- **Storage nativo** — Samba + Nextcloud: dischi di rete sui client, niente Drive/Dropbox.
- **Extranet zero-config** — Tailscale o Cloudflare Tunnels (VPN mesh). ERP e casa da fuori senza IP statico né port forwarding.

### Aziende (Amy AI ERP)

- Laravel + PostgreSQL isolato: contabilità, anagrafiche, fatturazione.
- React Native / Expo: magazzino e **timbratore** su tablet/smartphone.
- Documentale strutturato con metadati (ricerca interna, non dump grezzo).

### Casa (Smart Living)

- Home Assistant Core + MQTT; dongle USB Zigbee/Matter nello chassis (luci, clima, accessi).
- **Immich** per foto famiglia, riconoscimento facciale locale, zero cloud terzi.
- Dashboard unica: consumi, telecamere locali, stato dispositivi.

### AI type-safe e MCP

- **Ollama** headless: carica in VRAM modelli quantizzati on demand.
- **OCR MiniCPM-V** su PDF/foto DDT → JSON → API Laravel (popola il DB).
- Voce/testo → **Zod** → payload JSON esatto prima di MQTT (tapparella, giacenza).
- MCP: Playwright (estratti conto, portali) e OS agents sul filesystem.

## Più avanti: marketplace (App Store privato)

Non ora. **Piano futuro**: nessuna di queste app è installabile oggi dal pannello. 1-click via API Dokploy dal pannello Silicore. Ogni app entra nella rete isolata della Station: Traefik assegna `chat.silicore.local` / `media.casa.local` + SSL. Toggle LAN-only vs extranet (tunnel) per singola app.

**Catalogo:** immagini curate (registry Silicore, stabilità) **e** repo/immagini pubbliche inseribili a mano da chi è esperto. Non chiudere il catalogo.

Le aziende cercano i **nomi SaaS** (GitLab, Jira, Slack, Notion, Drive…). Amy Station installa l’equivalente **self-hosted / on-prem** (o, dove il vendor lo consente ancora, l’edizione CE). Il catalogo è organizzato per **categoria di azienda** e **casa**, non per stack tecnico: serve a vendere pack, non a elencare container.

### Tre livelli (come si vende)

| Livello | Per chi | Cosa include |
| --- | --- | --- |
| **Core pack** | Quasi ogni PMI, studio, startup | File, chat, wiki, project, password, identità, backup, osservabilità, helpdesk base |
| **Vertical pack** | Un settore (software house, legale, ristorazione, …) | Core + 4–12 app tipiche di quel mestiere |
| **Home pack** | Famiglia, prosumer, homelab, Station Domus | Media, foto, smart home, parental, NAS-like, privacy |

Un cliente può partire dal Core e aggiungere un vertical o un home pack sulla stessa Station. Pack = ricetta commerciale, non un lock-in: le app restano singolarmente installabili.

### Fattibilità self-host (legenda breve)

| Nome che cercano | Installabile 1-click? | Nota |
| --- | --- | --- |
| **GitLab** | Sì: **GitLab CE** (pesante) o **Gitea / Forgejo** | CE è self-host; EE è commerciale. Per PMI spesso basta Gitea/Forgejo |
| **GitHub** | No (SaaS). Alt: Gitea, Forgejo, GitLab CE | Actions ≈ **Gitea Actions**, Woodpecker, Drone |
| **Jira / Linear** | Jira Data Center non è più il percorso “facile”. Alt: **Plane**, **OpenProject**, **Focalboard**, **Taiga**, **Leantime** | Non promettere “Jira on-prem 1-click” |
| **Confluence** | Alt: **BookStack**, **Wiki.js**, **Outline** | Outline: self-host possibile, licenza da verificare in listing |
| **Notion** | Alt: **AppFlowy**, **Outline**, **AFFiNE**, **Docmost** | Nessun clone 1:1 |
| **Slack / Teams** | Alt: **Mattermost**, **Rocket.Chat**, **Zulip**, **Element/Matrix** | Mattermost Team Edition è il default “aziendale” |
| **Google Drive / Dropbox** | **Nextcloud** (+ Samba già in pilastri) | Seafile se serve sync “tipo Dropbox” più stretto |
| **1Password / Bitwarden** | **Vaultwarden** (compat. Bitwarden) o Bitwarden self-host ufficiale (più pesante) | Vaultwarden = default Station |
| **Okta / Auth0 / Entra** | **Authentik**, **Keycloak**, **Authelia** | SSO verso le altre app del catalogo |
| **Zoom / Meet** | **Jitsi**, **BigBlueButton** (formazione), **LiveKit** | BBB più adatto a scuola |
| **Calendly** | **Cal.com** self-host, **Easy!Appointments** | |
| **Sentry** | **GlitchTip** o Sentry self-host | GlitchTip più leggero |
| **Datadog** | **Grafana** + Prometheus + Loki (già in telemetria Station) | Non è un clone commerciale |
| **Zendesk / Freshdesk** | **Zammad**, **FreeScout**, **osTicket**, **GLPI** | |
| **Odoo / SAP Business One** | **Odoo Community**, **ERPNext** | ERP “vero” = vertical o Station Business, non Core leggero |
| **HubSpot / Salesforce** | **Twenty**, **EspoCRM**, **SuiteCRM**, **Odoo CRM** | |
| **Plex Pass / iCloud Foto** | **Jellyfin**, **Immich**, **PhotoPrism** | Casa / Domus |

Licenze: in listing indicare CE vs commerciale, RAM/CPU minimi, e se l’immagine è **curata Silicore** o **community**. Non dichiarare certificazioni (ISO, HIPAA, GDPR-as-a-product) solo perché l’app è on-prem.

---

### Core pack (must-have, quasi ogni azienda)

Cosa cercano i titolari: “un Drive, una chat tipo Slack, un Jira, un wiki, le password, i ticket IT”. Questo è il default Start/Business prima dei vertical.

| Area | Cercano | Installiamo | Ruolo |
| --- | --- | --- | --- |
| File sync | Drive, Dropbox, OneDrive | **Nextcloud**, Seafile | File, calendario, talk base, share link |
| Documentale | SharePoint “leggero” | **Paperless-ngx**, Nextcloud + full-text | Protocollo/scansioni con OCR locale (AI Station) |
| Chat | Slack, Teams | **Mattermost**, Rocket.Chat, Zulip | Chat team + integrazioni |
| Meeting | Zoom, Meet | **Jitsi** | Stanze video LAN/extranet |
| Project / agile | Jira, Linear, Trello, Asana, Monday | **Plane**, **OpenProject**, Focalboard, Taiga | Issue, board, roadmap |
| Wiki / knowledge | Confluence, Notion, Slite | **BookStack**, Outline, Wiki.js, Docmost | Procedure, runbook, onboarding |
| Password | 1Password, Bitwarden, LastPass | **Vaultwarden**, Passbolt (team) | Casseforti condivise |
| Identità / SSO | Okta, Google Workspace login | **Authentik**, Keycloak, Authelia | Un login verso Nextcloud/chat/wiki |
| VPN / remoto | Fortinet, Twingate, Tailscale | **Headscale** (+ client Tailscale), Netbird, WireGuard | Extranet già in pilastri |
| Tickets / helpdesk | Zendesk, Freshdesk | **Zammad**, FreeScout, osTicket | Ticket interni o verso clienti |
| ITAM / asset | ServiceNow “mini”, Lansweeper | **Snipe-IT**, GLPI | PC, licenze, telefoni |
| Tempo / presenze | Toggl, Harvest; badge | **Kimai**, **ERP Amy (timbratore)** | Ore progetto + badge (leva 4) |
| Fatture / preventivi | Fatture in Cloud, Stripe invoicing | **Invoice Ninja**, Crater | PMI senza ERP pieno |
| CRM base | HubSpot Starter | **Twenty**, EspoCRM | Pipeline commerciale |
| Backup | Backblaze, NAS Synology | **Kopia**, Restic UI, **Duplicati**; snapshot già in piano Station | Backup ibrido, non solo app |
| Osservabilità | Datadog, UptimeRobot, Statuspage | **Uptime Kuma**, Grafana, Prometheus, GlitchTip | Uptime + metriche host |
| Container UI | Portainer Cloud | **Portainer** (CE) | Solo per MSP / esperto; nascosto al titolare medio |
| Prenotazioni | Calendly | **Cal.com** | Call commerciali / sportello |
| Analytics BI | Looker, Power BI, Tableau | **Metabase**, Grafana, Evidence | Su Postgres ERP / Nextcloud |
| Email self-host | Google Workspace, M365 | **Stalwart**, Mailcow, Postal (transazionale) | Opzionale e delicato; default resta tunnel + provider esterno |

**Non** nel Core (troppo pesanti o di nicchia): GitLab CE completo, Odoo/ERPNext, BigBlueButton, stack CI, PACS medici, e-commerce.

---

### Pack verticali (per categoria di azienda)

Ogni sezione: cosa compra il cliente + tabella app. Le app del Core si danno per scontate; qui solo **delta** + 1–2 richiami se sono il “motore” del mestiere.

#### Software house / prodotto digitale / startup tech

Cercano GitLab, GitHub, Jira, Confluence, Slack, Sentry, CircleCI, Notion.

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Gitea** / **Forgejo** | Git + PR + package registry leggero | GitHub, GitLab “piccolo” |
| **GitLab CE** | Git + CI + registry + wiki in un pezzo | GitLab.com, GitHub Enterprise |
| **Woodpecker** / **Drone** / Gitea Actions | CI/CD | GitHub Actions, CircleCI |
| **Harbor** | Registry immagini privati | Docker Hub, ECR |
| **Plane** / **OpenProject** | Sprint, epic, bug | Jira, Linear |
| **BookStack** / **Outline** | RFC, runbook, onboarding | Confluence, Notion |
| **Mattermost** | Chat engineering | Slack |
| **GlitchTip** | Error tracking | Sentry |
| **Gitea/GitLab Pages** o **Outline** | Changelog interno | Notion |
| **SonarQube CE** | Qualità codice (se la Station regge) | SonarCloud |
| **Vaultwarden** + **Authentik** | Segreti team + SSO | 1Password + Okta |
| **Hedgedoc** | Note meeting collaborative | HackMD, Notion |

#### MSP / system integrator / IT interno

Cercano ServiceNow, Autotask, IT Glue, PRTG, JumpCloud, Passportal.

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **GLPI** o **Zammad** + **Snipe-IT** | Ticketing + CMDB/asset | ServiceNow, Freshservice |
| **NetBox** | DCIM / IPAM | phpIPAM cloud, SolarWinds |
| **phpIPAM** | Solo IP | fogli Excel IP |
| **Guacamole** o **Pangolin** / **Apache Guacamole** | Bastion browser | TeamViewer, AnyDesk |
| **MeshCentral** / **RustDesk server** | Remote desktop self-host | AnyDesk, Splashtop |
| **Authentik** + **Headscale** | Identità + mesh | JumpCloud, Twingate |
| **Passbolt** | Password MSP (audit) | Passportal, Keeper |
| **Wazuh** | SIEM leggero | Splunk, Microsoft Sentinel |
| **Grafana** + Prometheus + **Uptime Kuma** | NOC | PRTG, Datadog |
| **Portainer** / **Dockge** | Gestione stack clienti | Docker Desktop “a mano” |
| **Semaphore UI** / **Ansible AWX** | Automazione | Ansible Tower |
| **BookStack** | IT Glue-like | IT Glue, Hudu |

#### Agenzia (marketing, comms, social)

Cercano Asana, Monday, Notion, Slack, Drive, Figma (non self-hostabile), Later, HubSpot.

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Plane** / **Focalboard** / **Vikunja** | Delivery, brief, scadenze | Asana, Monday, ClickUp |
| **Outline** / **AFFiNE** | Brand book, copy, insight | Notion |
| **Nextcloud** + talk | File cliente + share | Drive, Dropbox |
| **Mattermost** | Chat agenzia/cliente | Slack |
| **Twenty** / **EspoCRM** | Pipeline e retainer | HubSpot |
| **Invoice Ninja** | Preventivi / ND | Fatture in Cloud |
| **Cal.com** | Booking call | Calendly |
| **Listmonk** o **Mautic** | Newsletter / automation | Mailchimp, ActiveCampaign |
| **Plausible** o **Umami** | Analytics siti clienti | GA4 |
| **Directus** / **Strapi** | Headless CMS campagne | Contentful |
| **Penpot** | UI collaborativa (non è Figma) | Figma (parziale) |

#### Studio creativo (design, foto, video, architettura)

Cercano Frame.io, Dropbox, Adobe cloud, Notion, Trello.

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Nextcloud** (quote grandi) | Consegna file pesanti | Dropbox, WeTransfer |
| **Immich** / **PhotoPrism** | Archivio scatti / raw preview | Lightroom cloud, iCloud |
| **Paperless-ngx** | Contratti e release | Drive cartelle |
| **Focalboard** / **Plane** | Pipeline job | Trello, Monday |
| **BookStack** | Moodboard testuali / brief | Notion |
| **Jellyfin** | Review reel in LAN | Frame.io “povero” |
| **Kimai** | Ore per commessa | Harvest, Toggl |

#### Studio legale

On-prem per **residenza dati** e pratica; **non** è un gestionale forense certificato né un sostituto di adempimenti deontologici.

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Nextcloud** + gruppi per pratica | Fascicoli e share controllato | Drive, OneDrive |
| **Paperless-ngx** | Protocollo corrispondenza | cartelle Windows |
| **BookStack** / **Docmost** | Knowledge interno (massime, modelli) | Confluence, Notion |
| **OpenProject** | Scadenze, udienze come milestone | Jira, Excel |
| **Vaultwarden** | Credenziali portali giustizia | fogli password |
| **Jitsi** (LAN/tunnel) | Consulenze video | Meet, Zoom |
| **OnlyOffice** o **Collabora** (con Nextcloud) | Modifica atti in sede | Google Docs, M365 |
| **Kimai** | Tempi per pratica | Toggl |
| **Invoice Ninja** | Parcelle / acconti | gestionali cloud |
| **CryptPad** (opz.) | Bozze zero-knowledge | Notion “sensibile” |

#### Studio medico / clinica / ambulatorio

Solo tool **organizzativi** on-prem (agenda, file, identità). **Nessuna** pretesa di cartella clinica certificata, dispositivo medico, o trattamento PHI “compliant by install”. Il vertical è “sede e agenda”, non “EMR”.

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Easy!Appointments** / **Cal.com** | Agenda visite | PrenotaSalute, Calendly |
| **Nextcloud** (cartelle per studio, non “cartella clinica”) | Consensi, modulistica, referti *se* il titolare li mette già lì | Drive |
| **Paperless-ngx** | Protocollo amministrativo | |
| **Vaultwarden** + **Authentik** | Accessi staff | |
| **Jitsi** | Teleconsulto organizzativo | Meet |
| **Invoice Ninja** | Fatturazione prestazione | |
| **Uptime Kuma** | Monitor PC/sala d’attesa | |
| **Home Assistant** (sede) | Clima, accessi, sale | |

Non in catalogo “1-click salute”: PACS, HL7/FHIR completi, cartelle elettroniche di terze parti, imaging diagnostico.

#### Studio commercialista / CAF / consulenza del lavoro

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Nextcloud** (quota per cliente) | Ricezione documenti | Drive, PEC folder |
| **Paperless-ngx** | Ciclo documentale | |
| **Odoo Accounting** o **ERPNext** (se Station Business) | Prima nota / anagrafiche | gestionali cloud |
| **Invoice Ninja** | Fatturazione dello studio | |
| **Kimai** | Ore pratica | |
| **Twenty** | Portafoglio clienti | |
| **BookStack** | Procedure interne / scadenziario testuale | Notion |
| **Vaultwarden** | Entratel, INPS, portali | |
| **Jitsi** | Call con clienti | |
| **Listmonk** | Circolari clienti | Mailchimp |

Fatturazione elettronica IT e dichiarativi restano **fuori** dal claim marketplace (intermediari/SDI).

#### Manifattura / industria / officina

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **ERPNext** / **Odoo** (mrp, stock) | Distinte, ordini, magazzino | SAP B1, TeamSystem |
| **Amy ERP + Expo** | Timbratore, giacenze tablet | |
| **OpenProject** | Commessa / avanzamento | MS Project, Jira |
| **Snipe-IT** | Attrezzature, DPI, macchinari | |
| **Grafana** + Prometheus | OEE / sensori (se espongono metriche) | SCADA cloud |
| **Nextcloud** + Paperless | Disegni, DDT, certificati | |
| **Mattermost** | Turni / produzione | WhatsApp gruppi |
| **Home Assistant** / MQTT | Officina: luci, allarmi, energia | |
| **Inventree** | Inventario componenti | Excel magazzino |
| **Part-DB** (elettronica) | Distinta componenti | |

#### Edilizia / impiantistica / cantiere

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **OpenProject** | SAL, WBS, ritardi | Primavera, MS Project |
| **Plane** | Issue cantiere / non conformità | Jira |
| **Nextcloud** | Elaborati, SAL fotografici | Dropbox |
| **Immich** | Foto cantiere geolocali | Drive foto |
| **Paperless-ngx** | Capitolati, DURC, contratti | |
| **Snipe-IT** | Ponteggi, utensili, veicoli | |
| **Kimai** + timbratore Amy | Ore squadre | |
| **Invoice Ninja** / Odoo | Stati avanzamento / fatture | |
| **BookStack** | Procedure sicurezza (testo) | |
| **Uptime Kuma** | VPN cantiere / NVR | |

#### Retail / negozio / e-commerce

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Medusa**, **Saleor**, **Vendure** | Storefront headless | Shopify |
| **WooCommerce** su stack già DokHosts | Catalogo classico | Shopify, Presta |
| **Odoo POS** / ERPNext | Cassa + magazzino | SumUp backoffice |
| **Bagisto** | Marketplace multi-vendor | |
| **Umami** / Plausible | Traffico sito | GA4 |
| **Listmonk** | Promo | Klaviyo, Mailchimp |
| **Nextcloud** | Schede prodotto, foto | |
| **Invoice Ninja** | B2B | |
| **Uptime Kuma** | Sito e POS | |
| **Cal.com** | Click & collect / consulenza | |

WordPress/Woo resta sul flusso hosting DokHosts, non duplicare un secondo “WP 1-click” se già c’è attach siti.

#### Ristorazione / bar / dark kitchen

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Odoo / ERPNext** (ristorazione se modulo) | Magazzino, fornitori | TheFork backoffice |
| **Easy!Appointments** | Prenotazioni tavoli (semplice) | TheFork, Quandoo |
| **Nextcloud** + Paperless | HACCP scan, bolle | Drive |
| **Kimai** + timbratore | Turni sala/cucina | Deputy, When I Work |
| **Home Assistant** | Celle frigo, energia, luci | |
| **Grafana** | Temperature se sensori MQTT | |
| **Invoice Ninja** | Catering B2B | |
| **Listmonk** | Coupon / newsletter | |
| **Vaultwarden** | Portali delivery | |

Niente claim “sostituisce i delivery marketplace” (Glovo/Just Eat).

#### Hospitality / hotel / B&B / agriturismo

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Cal.com** / Easy!Appointments | Richieste soggiorno (non PMS completo) | Booking engine light |
| **Nextcloud** | Documenti ospiti, staff | |
| **Paperless-ngx** | Contratti, corrispondenza | |
| **Home Assistant** | Camere, accessi, clima | Control4 “povero” |
| **Jellyfin** | Media in camera (LAN) | Chromecast hotel |
| **Vaultwarden** + Authentik | Staff | |
| **Umami** | Sito struttura | |
| **Invoice Ninja** | Fatture aziende | |
| **Mattermost** | Chat staff (non WhatsApp ospiti) | |
| **Uptime Kuma** | Wi‑Fi, PMS cloud, NVR | |

PMS alberghiero certificato (Opera, RoomRaccoon, ecc.) **non** è nel 1-click iniziale: integrare dopo, non inventare un clone.

#### Logistica / trasporti / magazzino

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **ERPNext** / Odoo (inventory, fleet) | Giacenze, DDT | WMS cloud |
| **Amy Expo** | Picking tablet | |
| **OpenProject** | Commesse trasporto | |
| **Grafana** | Tracking KPI (se dati in Postgres) | |
| **Snipe-IT** | Mezzi, scanner, PED | |
| **Nextcloud** | POD / foto consegna | |
| **Mattermost** | Dispatch | |
| **Uptime Kuma** | Gateway sedi | |
| **Traccar** | GPS flotta self-host | Verizon, Webfleet |

#### Scuola / formazione / academy

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Moodle** | LMS | Google Classroom, Thinkific |
| **BigBlueButton** | Aula virtuale | Zoom Education |
| **Outline** / BookStack | Dispense | Notion, Confluence |
| **Nextcloud** | Consegna compiti / file | Drive |
| **Jitsi** | Ricevimento | Meet |
| **Cal.com** | Sportello / orientamento | |
| **Kanboard** / Plane | Piano didattico | Trello |
| **Listmonk** | Circolari famiglie | |
| **Authentik** | Account docenti/studenti | Google Workspace Edu |
| **Flarum** / **Discourse** | Forum classe | |

Dati minori: LAN-only di default; extranet solo con consenso del titolare del trattamento.

#### ONG / associazione / no-profit

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **CiviCRM** (se fattibile Docker) o **Twenty** | Soci, donatori | Salesforce NPSP |
| **Nextcloud** | Documenti consiglio | Drive |
| **Listmonk** | Newsletter soci | Mailchimp |
| **Invoice Ninja** | Quote / ricevute | |
| **OpenProject** | Progetti e grant | Asana |
| **BookStack** | Procedure volontari | Notion |
| **Mattermost** | Coordinamento | Slack, WhatsApp |
| **Jitsi** | Assemblee | |
| **Kimai** | Ore volontariato | |
| **Gancio** o **Mobilizon** | Eventi pubblici | Facebook Events |

#### PMI generica / startup non-tech

Il Core pack **è** il prodotto. Extra frequenti:

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Odoo Community** *oppure* **ERPNext** | Gestione unica (se crescono) | TeamSystem, Sage |
| **Twenty** | Vendite | HubSpot |
| **Metabase** | “Quante fatture / quanto magazzino” | Excel, Power BI |
| **Cal.com** | Agenda titolare | Calendly |
| **Plausible/Umami** | Sito vetrina | GA4 |

#### Immobiliare / property

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Twenty** / EspoCRM | Mandati e pipeline | Immobiliare.it CRM |
| **Nextcloud** + OnlyOffice | Contratti, planimetrie | |
| **Paperless-ngx** | Visure, APE | |
| **Cal.com** | Prenota visita | |
| **OpenProject** | Ristrutturazioni | |
| **Immich** | Archivio immobili | |
| **Listmonk** | Alert acquirenti | |

---

### Pack casa / famiglia / prosumer (Station Domus e homelab)

Categorie **casa**, non “self-host in generale”. Il titolare cerca iCloud, Plex, Alexa, Google Home, Synology Photos, Control Parental.

#### Media e salotto

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Jellyfin** | Film, serie, musica in LAN | Plex, Netflix “propria libreria” |
| **Navidrome** / **Funkwhale** | Solo audio | Spotify / Plexamp |
| **Audiobookshelf** | Audiolibri / podcast | Audible |
| **Navidrome** + **Lidarr** (esperto) | Libreria musicale | |

Plex: possibile come immagine community, non come “curata Silicore” (licenza/claim).

#### Foto e ricordi

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Immich** | Backup telefono, volti locali | Google Foto, iCloud |
| **PhotoPrism** | Archivio grande, ricerca | |
| **Nextcloud** Memories | Sync famiglia | iCloud Drive |
| **Lychee** | Gallerie share | |

#### Smart home

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Home Assistant** | Hub locale + dashboard | Alexa, Google Home, Homey |
| **Zigbee2MQTT** / Matter (dongle nello chassis) | Radio dispositivi | hub vendor |
| **ESPHome** | Nodi DIY | |
| **Frigate** (NPU/GPU) | Telecamere, NVR con detect locale | Ring, Arlo cloud |
| **Scrypted** (opz.) | Bridge camere | |

Voce type-safe + MQTT è già nei pilastri AI: le app sopra sono il piano di controllo, Amy è l’interfaccia.

#### Rete, parental, privacy

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **AdGuard Home** / **Pi-hole** | DNS filtro ads/tracker | NextDNS, router ISP |
| **Authentik** + **Headscale** | Accesso da fuori | Tailscale SaaS only |
| **Vaultwarden** | Password famiglia | iCloud Keychain, 1Password Family |
| **SafeLine** / **CrowdSec** (esperto) | WAF/ban | |
| **ChangeDetection** | Alert prezzi / pagine | |

Parental: AdGuard + profili + Jellyfin libraries separate; non vendere “controllo genitori certificato”.

#### NAS-like, backup, documenti casa

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Nextcloud** | Cartelle condivise PC/telefono | Synology Drive, Dropbox |
| **Samba** (pilastro, non marketplace) | Disco di rete | NAS |
| **Paperless-ngx** | Bollette, contratti, 730 | faldoni |
| **Kopia** / Duplicati | Backup verso disco 2 + replica Silicore | Backblaze, Carbonite |
| **File Browser** / **Copyparty** | Upload ospite senza account | WeTransfer |

#### Homelab / power user (stesso hardware, UI “esperto”)

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Gitea** / Forgejo | Dotfiles, IaC casa | GitHub |
| **Portainer** / Dockge | Stack extra | Unraid UI |
| **Grafana** + Uptime Kuma | Salute Station | |
| **Homepage** / **Homarr** / **Dashy** | Launchpad famiglie | Synology DSM |
| **IT-Tools** | Utility | |
| **Stirling-PDF** | PDF in locale | iLovePDF |
| **ConvertX** / **Stirling** | Conversioni | |

#### Comunicazione famiglia

| App | Ruolo | Alt. a |
| --- | --- | --- |
| **Mattermost** o **Element** (Synapse) | Chat casa senza WhatsApp | Slack family, Telegram |
| **Jitsi** | Video nonni | Meet, FaceTime |
| **Nextcloud Talk** | Chat+file in un pezzo | |

---

### Mappa aree funzionali → dove si vendono

Serve a non perdere pezzi in roadmap listing. Ogni area è **già** dentro Core o un pack, non un sesto livello di menu.

| Area | Core | Vertical tipici | Casa |
| --- | --- | --- | --- |
| Source control / CI / DevOps | — | Software house, MSP | Homelab (Gitea) |
| Project / issue / agile | Plane / OpenProject | Tutti i mestieri a commessa | — |
| Wiki / docs | BookStack / Outline | Legale, agenzia, scuola, ONG | Paperless, BookStack ricette |
| Chat / meeting / email | Mattermost, Jitsi; mail opz. | Scuola (BBB), MSP | Element/Jitsi |
| CRM / ERP / fatture / HR / tempo | Twenty, Invoice Ninja, Kimai, timbratore | Manifattura/edilizia = Odoo/ERPNext | — |
| Ticket / helpdesk / ITAM / password | Zammad, Snipe-IT, Vaultwarden | MSP = GLPI + Passbolt | Vaultwarden |
| File sync / backup | Nextcloud, Kopia | Creativo, legale, cantiere | Immich + Kopia + Samba |
| Analytics / observability | Metabase, Grafana, Uptime Kuma | Industria, MSP | Homelab |
| Identity / SSO / VPN | Authentik, Headscale | MSP, scuola | Famiglia / remoto |
| CMS / e-commerce / booking | Cal.com | Retail, ristorazione, hotel, agenzia | — |
| Education / LMS | — | Scuola | — |
| Medico / legale | — | Solo organizzativo; no claim sanitario/forense | — |
| Media / smart home / parental | — | Hotel, ristorazione (HA) | Pack Domus |

---

### Bundle sintetici (come in vetrina)

Sostituiscono le tabelle corte precedenti; il dettaglio è sopra.

**Imprese (oltre il Core)**

| Uso | Esempi 1-click |
| --- | --- |
| BI / dati | Metabase, Grafana su Postgres ERP |
| Chat / task | Mattermost, Plane, OpenProject, Focalboard |
| Knowledge | BookStack, Outline, Wiki.js, Paperless-ngx |
| CRM / fatture | Twenty, EspoCRM, Invoice Ninja, Kimai |
| ERP verticale | Odoo Community, ERPNext |
| DevOps | Gitea/Forgejo, GitLab CE, Woodpecker, Harbor |
| IT / MSP | Zammad, GLPI, Snipe-IT, NetBox, Wazuh |
| Identità | Authentik, Keycloak, Vaultwarden, Headscale |

**Casa**

| Uso | Esempi 1-click |
| --- | --- |
| Media | Jellyfin, Navidrome, Audiobookshelf |
| Foto | Immich, PhotoPrism |
| Rete / parental | AdGuard Home, Pi-hole |
| Password / accesso | Vaultwarden, Authentik, Headscale |
| Smart home | Home Assistant, Zigbee2MQTT, Frigate |
| Documenti / NAS-like | Nextcloud, Paperless-ngx, Kopia |
| Hub UI | Homepage, Homarr, Dashy |

### Criteri per entrare nel catalogo curato

- Immagine Docker mantenuta, volume persistente chiaro, healthcheck.
- Si integra con Traefik + Authentik (OIDC) o almeno login locale.
- RAM compatibile con lo SKU (Start vs Business vs Domus): GitLab CE e BBB solo Business+.
- Licenza redistribuibile o CE; il commerciale si lista come “bring your own license”.
- Repo pubbliche: sempre installabili a mano (catalogo aperto), badge “non curata”.

### Fuori perimetro (non promettere 1-click)

Adobe / Figma / Canva, Microsoft 365 completo, Jira Cloud ufficiale, Slack ufficiale, Salesforce completo, PMS alberghieri certificati, cartelle cliniche / PACS, fatturazione elettronica SDI come prodotto, delivery food marketplace, app store iOS. Lì si fa **integrazione** o **alternativa**, non clone.
