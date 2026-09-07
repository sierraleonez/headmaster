# Deploying HeadMaster with Docker

Four containers: **web** (nginx), **app** (php-fpm), **queue** (the worker that
drafts plans), and **mysql**. All three PHP images are built from one
`Dockerfile`; `docker-compose.yml` wires them together.

## Requirements

Docker Engine 24+ with the Compose plugin. Nothing else — PHP, Node and
Composer are only needed inside the build.

## First run

```bash
git clone git@github.com:sierraleonez/headmaster.git
cd headmaster

cp .env.docker.example .env.docker
```

Generate an application key and put it in `.env.docker` as `APP_KEY=`:

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Then edit `.env.docker`:

| Set                              | To                                                                 |
| -------------------------------- | ------------------------------------------------------------------ |
| `APP_KEY`                        | the value you just generated                                       |
| `APP_URL`                        | the address people will use, e.g. `https://headmaster.example.com` |
| `MYSQL_PASSWORD` / `DB_PASSWORD` | the same new password                                              |
| `MYSQL_ROOT_PASSWORD`            | a different new password                                           |
| `OPENROUTER_API_KEY`             | your key, or leave blank to run without the assistant              |
| `HEADMASTER_PORT`                | the host port to publish on (default `8080`)                       |

`MYSQL_*` is what the database container creates on first boot; `DB_*` is what
Laravel connects with. They must agree, and the `MYSQL_*` values only take
effect on a **fresh** `database` volume.

Bring it up:

```bash
docker compose up -d --build
```

The **app** container migrates the database and caches config, routes and views
on every start, so there is no separate deploy step. Open
`http://localhost:8080`, register, and you land on your root board.

> `.env.docker` holds your database passwords and API key. It is gitignored —
> keep it that way.

## Everyday operations

```bash
docker compose ps                    # what is running
docker compose logs -f app           # application log (LOG_CHANNEL=stderr)
docker compose logs -f queue         # every plan the assistant drafts
docker compose exec app php artisan tinker
docker compose down                  # stop, keep data
docker compose down -v               # stop and destroy the database
```

Deploy a new version:

```bash
git pull
docker compose up -d --build
```

Back up and restore:

```bash
docker compose exec mysql mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" headmaster > backup.sql
cat backup.sql | docker compose exec -T mysql mysql -u root -p"$MYSQL_ROOT_PASSWORD" headmaster
```

## Behind a reverse proxy

Publish on localhost and terminate TLS in front of it (Caddy, Traefik, nginx).
Set `APP_URL` to the public HTTPS address so generated links and passkeys use
the right origin, and make sure the proxy forwards `X-Forwarded-Proto`.

The one timeout worth knowing: `fastcgi_read_timeout` in `docker/nginx.conf` is
120s. It only has to outlast a page request, never a draft — drafting happens on
the queue, and `OPENROUTER_TIMEOUT` (default 300s) bounds that instead.

## Notes on the build

- The **assets** stage carries both PHP and Node: the Wayfinder Vite plugin runs
  `php artisan wayfinder:generate` to type the route helpers, so a Node-only
  image cannot build the frontend.
- Images are Debian-based rather than Alpine; the Tailwind and Rollup native
  binaries are better behaved against glibc.
- `bootstrap/cache/*.php` is in `.dockerignore`. A manifest built on a machine
  with dev dependencies lists packages the `--no-dev` image does not have, and
  the container dies on boot looking for them.
- Compose reads **only** `.env.docker`. It is deliberately not called `.env`:
  Compose interpolates `.env` by default, and would hand your local development
  database credentials to the MySQL container.

## Scaling

`docker compose up -d --scale queue=3` runs more workers; they share the
database queue and will not collide. Do not scale **app** past one instance
without moving `RUN_MIGRATIONS` out of its entrypoint, or several containers
will migrate at once.

## If something is wrong

| Symptom                                             | Cause                                                                                                       |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Chat sits on "Mapping your plan..." for ever        | The **queue** container is not running. `docker compose logs queue`.                                        |
| "No OpenRouter API key configured" in the thread    | `OPENROUTER_API_KEY` is blank in `.env.docker`.                                                             |
| "The assistant was still writing after 300 seconds" | A very large plan. Send one phase at a time, or raise `OPENROUTER_TIMEOUT`.                                 |
| `mysql` unhealthy on first boot                     | `MYSQL_USER=root` is rejected by the image. Use a non-root `MYSQL_USER`.                                    |
| Database changes do not appear                      | `MYSQL_*` only applies to a fresh volume. `docker compose down -v` to start over — this destroys your data. |
| 500 on every page                                   | `APP_KEY` is empty. Generate one and restart.                                                               |
