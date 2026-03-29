# Symfony backend setup (Step 2)

Stack: **Symfony 7**, **API Platform 4**, **Doctrine ORM 3**, **Twig**, **PostgreSQL** (same database as `backend/sql/schema.sql`).

Project path: **`symfony/`** (repository root).

## Prerequisites

- PHP **8.2+** with extensions: `ctype`, `iconv`, `intl`, `pdo_pgsql`, `tokenizer`, `xml`, `mbstring`
- [Composer](https://getcomposer.org/)
- PostgreSQL running (e.g. `make db-up` from repo root using `backend/docker-compose.yml`)

## Install dependencies

```bash
cd symfony
composer install
```

## Environment variables

| Variable | Purpose |
|----------|---------|
| `DATABASE_URL` | Doctrine DBAL connection (PostgreSQL). Default in `.env` matches `backend/.env.example` (`postgres` / `oneshot_fantasy` on `127.0.0.1:5432`). |
| `APP_ENV` | `dev` or `prod` |
| `APP_SECRET` | Required for sessions/csrf; override in `.env.local` or secrets vault in production |
| `DEFAULT_URI` | Used when generating URLs outside HTTP (e.g. CLI) |
| `CORS_ALLOW_ORIGIN` | Nelmio CORS regex for browser clients hitting `/api` |

**Local overrides:** copy `symfony/.env.local.dist` to `symfony/.env.local` and set `DATABASE_URL` / `APP_SECRET` as needed.

```bash
cp symfony/.env.local.dist symfony/.env.local
# edit symfony/.env.local
```

## Database connection check

```bash
cd symfony
php bin/console dbal:run-sql "SELECT 1"
```

Expected: query runs without error (Postgres must be up and database `oneshot_fantasy` must exist).

Doctrine **`server_version`** is set to **`16`** in `config/packages/doctrine.yaml` (align with Docker `postgres:16-alpine`).

## Run the application

From the **repository root** (starts Postgres + PHP server):

```bash
make start
```

Or manually:

```bash
cd symfony
php -S 127.0.0.1:8080 -t public
```

Or with [Symfony CLI](https://symfony.com/download): `symfony server:start -d --port=8080`

- **Twig UI:** http://127.0.0.1:8080/
- **Liveness JSON:** http://127.0.0.1:8080/health (`make healthcheck`)
- **API Platform:** http://127.0.0.1:8080/api  
- **Swagger UI:** http://127.0.0.1:8080/api/docs  

Default port **8080** matches the root `Makefile` (`SYMFONY_PORT`).

## Key config files

| File | Role |
|------|------|
| `config/packages/doctrine.yaml` | DBAL URL, PostgreSQL `server_version`, ORM mapping `src/Entity` |
| `config/packages/api_platform.yaml` | API title, version, defaults |
| `config/packages/nelmio_cors.yaml` | CORS for `/api` |
| `config/packages/twig.yaml` | Twig paths |
| `config/routes/api_platform.yaml` | API Platform routes |
| `config/routes.yaml` | Attribute-based controllers (`src/Controller`) |

## Next steps (migration)

1. Add Doctrine **entities** under `src/Entity/` matching existing tables (or import mapping).
2. Register **API Resource** classes or use `#[ApiResource]` on entities.
3. Prefer **`schema:validate`** / migrations that align with `backend/sql/schema.sql` before `doctrine:schema:update --force` on a shared DB.

## Composer packages (reference)

- `api-platform/api-pack` — API Platform + Doctrine bridge + Hydra/JSON-LD
- `symfony/orm-pack` — DoctrineBundle + Migrations
- `symfony/twig-bundle` — Twig rendering
- `nelmio/cors-bundle` — CORS
