# Pika in Docker

`docker-compose.yml` in the repository root runs a complete Pika instance in containers:
Apache/PHP, MariaDB, memcached, the two Solr nodes and a mail catcher. It is the container equivalent of the
server built by the scripts in the PikaSettings repository under `bash/pika-server-setup/`.

It is meant for local development. The Pika checkout and the settings checkout are mounted
into the containers, so code and config edits take effect without rebuilding.

> **Status:** this setup has not yet been run end to end. Expect to fix a thing or two on the
> first `docker compose up --build`, and update this file when you do.

## Requirements

- Docker with the Compose plugin (`docker compose version`). On WSL, turn on WSL integration
  in Docker Desktop.
- A checkout of the PikaSettings repository on the Docker host. The default location is
  `/usr/share/php/pika/settings`.
- The site linked into this checkout: `sites/<site>` must be a symlink to
  `<settings>/<site>/` (this is what `bash/installSettings.sh <site>` creates), and the site
  needs its `conf/config.pwd.ini`.

## Quick start

1. Check that the site is linked and has its password file:

   ```bash
   cd /usr/local/pika
   ls -l sites/marmot.localhost/conf/config.ini sites/marmot.localhost/conf/config.pwd.ini
   ```

2. Create `.env` and set the two passwords:

   ```bash
   cp docker/env.example .env
   ```

   `PIKA_DB_PASSWORD` must be the same password the site uses for the `pika` database user in
   `config.pwd.ini` under `[Database]`. `PIKA_DB_ROOT_PASSWORD` can be anything. `.env` is
   git-ignored.

3. Build and start. The first build takes several minutes.

   ```bash
   docker compose up -d --build
   ```

4. Check that everything came up. `web` waits for the database, so on the first start it
   appears about a minute after the others, once the schema has loaded. When it has
   settled, `web`, `db`, `memcached` and both Solr nodes should all show `healthy`.

   ```bash
   docker compose ps
   docker compose logs db | grep 'Pika: loading'
   ```

5. Browse to <http://marmot.localhost:8088>.

If something fails, see [Troubleshooting](#troubleshooting).

## Services

| Service | Image | Listens on | Purpose |
| --- | --- | --- | --- |
| `net` | `alpine:3` | | holds the shared network, the hostname and the published ports |
| `web` | built from `docker/web/Dockerfile` | 80 | Apache, PHP 8.5, Java 8 for the indexing jars |
| `db` | `mariadb:11.8` | 3306 | `pika` and `econtent` databases |
| `memcached` | `memcached:1.6-alpine` | 11211 | cache |
| `mail` | `axllent/mailpit` | 25 (SMTP), 8025 (web UI) | catches outgoing e-mail |
| `solr_master` | `solr:8.8.2` | 8180 | index writes from the reindexer |
| `solr_searcher` | `solr:8.8.2` | 8080 | search queries from PHP; replicates from `solr_master` |

### Published ports

| Host | Goes to | Notes |
| --- | --- | --- |
| `8088` | the site | change with `PIKA_HTTP_PORT` |
| `127.0.0.1:8025` | Mailpit web UI | change with `PIKA_MAIL_UI_PORT` |

Solr and MariaDB are not published by default, because 8080, 8180 and 3306 often clash with
a local Solr or MariaDB. To reach the Solr admin UIs or connect a database client from the
host, add this line to `.env` and run `docker compose up -d`:

```
COMPOSE_FILE=docker-compose.yml:docker/compose.ports.yml
```

They are then published on `127.0.0.1` only, on the ports set by `SOLR_SEARCHER_HOST_PORT`,
`SOLR_MASTER_HOST_PORT` and `PIKA_DB_HOST_PORT`.

### Networking

Every service joins the network namespace of the `net` container
(`network_mode: "service:net"`), so they all reach each other on `localhost`, as on a single
server.

Pika depends on that. `config.pwd.ini` points the database at `localhost`, the default config
points memcached and Solr at `localhost`, and `http://localhost` is hard-coded in the Java
indexers (`reindexer`, `sierra_export_api`, `polaris_export`) and in the searcher's
`solrconfig.xml`. Giving each service its own hostname would need source changes and rebuilt
jars.

`net` does nothing but hold the namespace, so it never needs rebuilding. `web` and the other
services can be rebuilt, restarted or recreated on their own. Only recreating `net` itself
(for example after changing a published port) restarts everything, and
`docker compose up -d` handles that.

PHP treats a `localhost` database host as a unix socket rather than TCP, so the `db_socket`
volume shares `/run/mysqld` between `db` and `web`.

### Logs

Apache's access and error logs go to the container's output, so PHP fatals and warnings
show up in `docker compose logs web`. The entrypoint reads the `ErrorLog` and `CustomLog`
paths from the site's vhost file and links each one to stdout or stderr.

Pika's own Monolog output still goes to a file in the `pika_logs` volume:
`/var/log/pika/<site>/messages.log`.

### Mail

Nothing leaves the stack. `mail` accepts whatever Pika sends to `localhost:25` and shows it
at <http://localhost:8025>. This relies on the site having `[Mail] host = localhost`.

## Settings (`.env`)

| Variable | Default | Meaning |
| --- | --- | --- |
| `PIKA_SITE` | `marmot.localhost` | Site directory under `sites/`, and the hostname to browse to |
| `PIKA_SETTINGS_DIR` | `/usr/share/php/pika/settings` | PikaSettings checkout on the host |
| `PIKA_VHOST_CONF` | `httpd-marmot.locaclhost-2.4-linux.conf` | Apache vhost file inside `<settings>/<site>/` |
| `PIKA_DB_PASSWORD` | required | Password for the `pika` database user |
| `PIKA_DB_ROOT_PASSWORD` | required | MariaDB root password |
| `PIKA_HTTP_PORT` | `8088` | Host port for the site |
| `PIKA_MAIL_UI_PORT` | `8025` | Host port for the Mailpit web UI |
| `COMPOSE_FILE` | unset | Set to `docker-compose.yml:docker/compose.ports.yml` to publish Solr and MariaDB |
| `SOLR_SEARCHER_HOST_PORT` | `8080` | Host port for the searcher, when published |
| `SOLR_MASTER_HOST_PORT` | `8180` | Host port for the master, when published |
| `PIKA_DB_HOST_PORT` | `3306` | Host port for MariaDB, when published |
| `PHP_VERSION` | `8.5` | PHP version installed in the web image (rebuild after changing) |
| `MARIADB_VERSION` | `11.8` | MariaDB image tag |
| `SOLR_VERSION` | `8.8.2` | Solr image tag |
| `MAILPIT_VERSION` | `latest` | Mailpit image tag |
| `SOLR_HEAP` | `1g` | Heap for each Solr node |
| `XDEBUG_MODE` | `off` | Xdebug mode, e.g. `debug,develop` |
| `TZ` | `America/Denver` | Time zone |

## Files and volumes

Mounted from the host:

| Host path | In the container | Notes |
| --- | --- | --- |
| this repository | `/usr/local/pika` | the code that runs |
| `PIKA_SETTINGS_DIR` | `/usr/share/php/pika/settings` | same path as on the host, so the `sites/<site>` symlink resolves |
| `data_dir_setup/solr_master`, `solr_searcher` | `/opt/pika-solr-home` (read-only) | Solr core configuration |
| `docker/db/sql` | `/pika-schema` (read-only) | database schema |

Named volumes:

| Volume | Holds |
| --- | --- |
| `db_data` | MariaDB data |
| `solr_master`, `solr_searcher` | Solr indexes |
| `pika_data` | `/data/pika` (covers, MARC records, QR codes) |
| `pika_logs` | `/var/log/pika` (also reachable as `/var/log/vufind-plus`) |
| `smarty_compile`, `smarty_cache` | Smarty compile and cache directories, kept out of the host checkout |
| `db_socket` | the MariaDB socket shared between `db` and `web` |

## Database

The first time `db` starts with an empty `db_data` volume, `docker/db/10-pika-init.sh`:

1. creates the `econtent` database and grants the `pika` user access to both databases;
2. loads the schema from `docker/db/sql/`:
   - `pika_schema.sql` and `econtent_schema.sql`: table structure only, no data;
   - `pika_db_update_data.sql`: the `db_update` rows that record which DB Maintenance updates
     the schema already includes;
3. loads starter data (roles, variables, materials request statuses, spelling words) from
   `bash/pika-server-setup/sql/` in the settings repository.

The result is an empty Pika database: no libraries, locations or users. For a working catalog,
import a dump of a real database:

```bash
docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" pika' < pika_dump.sql
```

The password is read from the container's own environment, so there is nothing to export in
your shell.

After pulling code with new schema updates, run Admin > DB Maintenance as usual.

### Refreshing the schema dumps

After schema updates have been run on a development database, regenerate the dumps on the
machine that hosts that database:

```bash
docker/db/dump-schema.sh              # uses $PIKA_SITE, or marmot.localhost
docker/db/dump-schema.sh marmot.test  # or name the site
```

The script reads the connection details from the site's `config.pwd.ini` and rewrites all
three files together, so the `db_update` rows always match the schema. It dumps table
structure and `db_update` only. Review the diff and commit it.

The init script only runs on an empty data volume. To rebuild the database from the dumps:

```bash
docker compose down
docker volume rm pika_db_data
docker compose up -d
```

## Solr

Each node copies its core configuration from `data_dir_setup/` on every start, so a schema or
`solrconfig.xml` change is picked up by restarting the node:

```bash
docker compose restart solr_master solr_searcher
```

Index data stays in the named volumes and is not touched by the copy.

The indexes start empty. Check where the site searches: `[Index] url` in the site's
`config.ini` has to be `http://localhost:8080/solr` to use the container. The
`marmot.localhost` config currently points at a remote Solr server, so with that config the
local Solr containers are unused.

The Java jars run inside the `web` container, where the database and Solr are on `localhost`:

```bash
docker compose exec web bash
cd /usr/local/pika/vufind/record_grouping && java -jar record_grouping.jar "$PIKA_SITE" fullRegroupingNoClear
cd /usr/local/pika/vufind/reindexer && java -jar reindexer.jar "$PIKA_SITE" fullReindex
```

## Everyday commands

```bash
docker compose ps                         # what is running
docker compose logs -f web                # Apache access and error logs, PHP errors
docker compose logs -f db                 # any other service's output
docker compose exec web bash              # shell in the web container
docker compose exec web tail -f /var/log/pika/marmot.localhost/messages.log   # Pika's own log
docker compose up -d                      # start (the stack does not start itself with Docker)
docker compose build web                  # after changing composer.json or the Dockerfile
docker compose down                       # stop; volumes are kept
docker compose down -v                    # stop and delete all data, indexes and logs
```

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| `required variable PIKA_DB_PASSWORD is missing a value` | `.env` is missing or incomplete. Copy `docker/env.example` to `.env` in the repository root. |
| `port is already allocated` / `address already in use` | Something on the host already uses that port, usually a web server on 8088, or a local Solr or MariaDB if you turned on `docker/compose.ports.yml`. Change the matching `*_PORT` variable in `.env`. |
| `Invalid configuration, could not find configuration file for ...` | The hostname you browsed to has no `sites/<name>/conf/config.ini`. Check `PIKA_SITE` and the `sites/<site>` symlink. |
| Database connection errors in the browser | `PIKA_DB_PASSWORD` does not match `config.pwd.ini`. The password is only applied when the database is first created, so fix `.env` and rebuild the database (see [Database](#database)). |
| Blank or cut-short page | A PHP fatal. Run `docker compose logs web`. |
| `web` never starts | It waits for `db` to be healthy. Run `docker compose logs db`; a failed schema load stops there. |
| A service stays `unhealthy` | Run `docker compose logs <service>`. If the service works and only the check fails, the check command in `docker-compose.yml` needs adjusting for that image. |
| E-mail does not show up in Mailpit | The site's `[Mail] host` is not `localhost`, or `port` is not 25. |
| Schema change in `data_dir_setup/` not visible in Solr | Restart the Solr nodes (see [Solr](#solr)). |
| Build fails installing a `php8.5-*` package | That extension is not published for this PHP version in the `ondrej/php` PPA. Remove it from `docker/web/Dockerfile` or change `PHP_VERSION`. |

## Xdebug

Set `XDEBUG_MODE=debug,develop` in `.env` and run `docker compose up -d`. Xdebug connects to
the Docker host on port 9003. Paths in the container match the host, so the existing launch
configs in `.vscode/launch.json` work unchanged. See `documentation/xdebug-setup.md`.

## Differences from a server install

- PHP comes from the `ondrej/php` PPA on Ubuntu 24.04. The `xmlrpc` extension from
  `pika-server-setup-php8.sh` is not installed.
- No cron jobs, logrotate or FTP mount. Outgoing mail is caught by Mailpit, not delivered.
- Containers have no restart policy: after a reboot, start the stack with
  `docker compose up -d`.
- Solr runs from the official image rather than `install_solr_service.sh`, and its settings
  come from `docker-compose.yml`, not the site's `solr_*.in.sh` files.
- Composer packages are installed into the image at build time. Rebuild the `web` image after
  changing `install/composer.json` or `install/composer.lock`.
