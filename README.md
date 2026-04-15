# Oneshot Fantasy

Survival-style fantasy for **elimination tournaments**: users draft a pool of players but may use each player **only once** for the whole event. The design is **sport-agnostic**; only scoring rules and stats ingestion change per sport.

## Stack

- **HTTP API + UI:** Symfony 7, API Platform, Doctrine, Twig (`symfony/`)
- **Batch jobs:** Python 3.11+ — stat **ingestion** and **scoring** (`backend/app/`, no HTTP server)
- **Database:** PostgreSQL 16 (Docker Compose under `backend/`)

## Prerequisites

- PHP 8.2+, [Composer](https://getcomposer.org/)
- Python 3.11+ (`python3`)
- Docker (Compose v2) for Postgres

## Quick start

From the **repository root**:

```bash
cp backend/.env.example backend/.env   # Compose + Python DATABASE_URL
# Optional: cp symfony/.env.local.dist symfony/.env.local — see docs/symfony-setup.md
make install       # composer + Python venv + apply sql/schema.sql (needs Docker)
make start         # Postgres + Symfony dev server → http://127.0.0.1:8080/
```

In another terminal (Symfony must be running):

```bash
make healthcheck   # GET /health on Symfony
```

- **Home (Twig):** http://127.0.0.1:8080/
- **API Platform:** http://127.0.0.1:8080/api — docs: http://127.0.0.1:8080/api/docs

Python DB smoke test (no HTTP):

```bash
make probe-db
```

## Documentation

- **[docs/README.md](docs/README.md)** — documentation index  
- **[MVP REST API (human spec)](docs/api/README.md)** — contract (implemented in Symfony over time)  
- **[Symfony setup](docs/symfony-setup.md)** — env, Doctrine, URLs  
- **[Python worker](backend/README.md)** — what remains under `backend/app/`
- **[NBA ingestion scripts](backend/ingestion/INGESTERS.md)** — how NBA data gets into PostgreSQL

## Makefile (documented)

Run **`make help`**.

| Target | Purpose |
|--------|---------|
| `make install` | `install-symfony` + `install-worker` + `install-db` |
| `make install-symfony` | `composer install` in `symfony/` |
| `make install-worker` | `backend/.venv` + `pip install -e ".[dev]"` |
| `make install-db` | Docker Postgres + apply `backend/sql/schema.sql` |
| `make start` | `db-up` + Symfony PHP server on port **8080** |
| `make symfony-serve` | Symfony only (same as `start` without implying db is new) |
| `make symfony-cc` | `php bin/console cache:clear` |
| `make healthcheck` | `GET /health` on Symfony (JSON) |
| `make stop` / `make db-down` | `docker compose down` |
| `make probe-db` | Python `SELECT 1` via `backend/.env` |
| `make worker-sync` | Ingest stats (`TOURNAMENT_ID=uuid`) |
| `make worker-score` | Recompute scores (`LEAGUE_ID=` + `FANTASY_ROUND_ID=`) |
| `make worker-score-batch` | Batch scoring (`SCORE_JOBS_FILE=path`) |
| `make ingest-nba` | Import NBA players into `participants` |
| `make ingest-nba-playoffs SLUG=…` | Import playoff series into `tournament_rounds` (`SEASON=` optional) |
| `make db-logs` | Follow Postgres logs |

Worker cron / logs: [docs/worker-automation.md](docs/worker-automation.md).

Overrides:

```bash
make start SYMFONY_PORT=8081
make healthcheck HEALTH_URL=http://127.0.0.1:8081/health
```

`install-db` is **not** idempotent if tables already exist (use Symfony migrations or reset the volume).

## Configuration

- **Docker + Python:** `backend/.env` (from `.env.example`). `DATABASE_URL` uses `postgresql+asyncpg://…` for the worker.
- **Symfony:** `symfony/.env` and optional `symfony/.env.local` — use `postgresql://` (see [docs/symfony-setup.md](docs/symfony-setup.md)).
- If you change `POSTGRES_PUBLISH_PORT`, update both Symfony and Python URLs.

## Project layout

```text
.
├── Makefile
├── README.md
├── docs/
├── symfony/                   # Symfony + API Platform (HTTP API + Twig UI)
└── backend/                   # Python batch jobs — no HTTP server
    ├── README.md              # Python worker scope
    ├── docker-compose.yml     # PostgreSQL via Docker
    ├── pyproject.toml         # Python dependencies (like composer.json)
    ├── .env                   # DATABASE_URL — copied from .env.example, never committed
    ├── sql/schema.sql
    ├── ingestion/             # NBA data pipeline (stats.nba.com → PostgreSQL)
    │   ├── INGESTERS.md       # How to run each script; PHP-developer-friendly
    │   ├── clients/           # nba_api wrappers (one per NBA endpoint)
    │   ├── scripts/           # Entry points (run via `make ingest-nba-*`)
    │   └── services/          # Importers — normalize API data and upsert to DB
    └── app/
        ├── config.py
        ├── application/       # scoring_service, ingestion placeholder
        ├── domains/
        └── infrastructure/    # DB, persistence, ingestion adapters
```

## License

Personal project; add a license file when you are ready.
