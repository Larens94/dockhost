# Infra template (fase 0)

Il pannello pubblica questo stack in un **nuovo progetto Dokploy** (`project.create`, environment production, poi `compose.create` / `deploy`). Isolated Deployments **off**. Non va nel progetto del pannello.

I servizi stanno su `dokploy-network` così le Application vedono `${INFRA_SLUG}-mariadb` come `DB_HOST`.

## Servizi

| Servizio | Nome DNS interno | Ruolo |
| --- | --- | --- |
| `mariadb` | `{slug}-mariadb` | Motore condiviso. Gli schema li crea il pannello. |
| `phpmyadmin` | `{slug}-phpmyadmin` | GUI MariaDB. Dominio `pma-{slug}.{publicHost}` (opzionale, on di default). |
| `postgres` | `{slug}-postgres` | Motore condiviso. Gli schema li crea il pannello. |
| `sftp` | `{slug}-sftp` | Un demone, N utenti virtuali (pannello). |
| `pgadmin` | `{slug}-pgadmin` | GUI Postgres. Dominio `pga-{slug}.{publicHost}`, off di default. |
| `redis` | `{slug}-redis` | Cache, solo rete interna, off di default. |
| `minio` | `{slug}-minio` | S3 interno `:9000`; console `minio-{slug}.{publicHost}` `:9001`, off di default. |
| volume `{slug}_data` | `/data` e `/home` | Nome fisso per le app Laravel. Gli altri volumi (MariaDB, Postgres, …) sono dello stack Dokploy e spariscono con Elimina. |
| volume stack `sftp_config` | `/etc/sftp` | `users.conf` (atmoz), scritto da `sftp-sync`. |
| `sftp-sync` | `{slug}-sftp-sync` | API HTTP interna; DokHosts non monta questi volumi. |

Se l’infra è satura: crea **infra2** dal pannello (un altro progetto Dokploy, porta SFTP diversa).

## Dopo il primo deploy (dal pannello)

I grant `CREATE DATABASE` / `CREATE USER` li applica il servizio `mysql-grants`. Gli utenti SFTP li scrive `sftp-sync` (token nello env dello stack). Il pannello DokHosts non monta i volumi dell’infra.
