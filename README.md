# Oneshot Fantasy

Survival-style fantasy for **elimination tournaments**: users draft a pool of players but may use each player **only once** for the whole event. The design is **sport-agnostic**; only scoring rules and stats ingestion change per sport.

## Stack

- **Backend:** Python 3.11+, FastAPI, SQLAlchemy (async), `asyncpg`
- **Database:** PostgreSQL 16 (Docker Compose)
- **Layout:** `backend/app/` with `api/`, `infrastructure/`, and `domains/` (bounded contexts to grow over time)

## Prerequisites

- Python 3.11 or newer (`python3` on PATH)
- Docker Desktop (or Docker Engine + Compose v2) for Postgres

## Quick start

From the **repository root**:

```bash
cp backend/.env.example backend/.env   # first: local env for Compose + DATABASE_URL
make install      # venv + Python deps + Docker Postgres + sql/schema.sql
make start        # ensures Postgres, then API on http://127.0.0.1:8000 (Ctrl+C stops only Uvicorn)
```

In another terminal:

```bash
make healthcheck
```

Open interactive docs: [http://127.0.0.1:8000/docs](http://127.0.0.1:8000/docs)

## Makefile (documented)

Run **`make help`** for the canonical list. Common targets:

| Target | Purpose |
|--------|---------|
| `make install` | `install-deps` + `install-db` (Python venv + schema; needs Docker) |
| `make install-deps` | Create `backend/.venv` and `pip install -e ".[dev]"` only |
| `make install-db` | `docker compose up -d`, wait for health, apply `backend/sql/schema.sql` |
| `make start` | `docker compose up -d` (Postgres) + Uvicorn (foreground) |
| `make healthcheck` | `GET /api/v1/health` (pretty-printed JSON; fails on HTTP/network errors) |
| `make stop` | `docker compose down` (database container; volume retained) |
| `make probe-db` | Python `SELECT 1` smoke test (no HTTP server) |
| `make db-logs` | Follow Postgres logs |

Override examples:

```bash
make healthcheck API_URL=http://127.0.0.1:8000/api/v1/health
make start UVICORN_PORT=8001
make install-db POSTGRES_USER=postgres POSTGRES_DB=oneshot_fantasy
```

`install-db` is **not** idempotent: a second run errors if tables already exist (use migrations or reset the volume when iterating).

**When adding or renaming Make targets**, update the `##` descriptions next to each rule so `make help` stays accurate.

## Configuration

- **Compose + app env:** `backend/.env` (not committed). Template: `backend/.env.example`.
- If you change `POSTGRES_PUBLISH_PORT`, update `DATABASE_URL` to use the same host port.

## Project layout

```text
.
├── Makefile
├── README.md
├── backend/
│   ├── .env                 # local only (see .env.example)
│   ├── docker-compose.yml
│   ├── pyproject.toml
│   ├── sql/
│   │   └── schema.sql        # applied by make install-db
│   └── app/
│       ├── main.py
│       ├── api/             # HTTP routers, health checks
│       ├── infrastructure/  # DB, future adapters
│       └── domains/         # core logic (to be expanded)
```

## Health endpoint

`GET /api/v1/health` returns structured checks:

- **backend** — process is serving requests
- **db_read** — `SELECT 1`
- **db_write** — temporary table `INSERT` with `ON COMMIT DROP` (no app tables)

HTTP **503** if any check fails (useful for orchestrators).

## License

Personal project; add a license file when you are ready.
