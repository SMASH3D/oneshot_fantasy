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
make install      # once: venv + editable install + dev tools
cp backend/.env.example backend/.env   # then edit secrets/ports if needed
make start        # starts Postgres, then API on http://127.0.0.1:8000 (Ctrl+C stops API only)
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
| `make install` | Create `backend/.venv` and `pip install -e ".[dev]"` |
| `make start` | `docker compose up -d` (Postgres) + Uvicorn (foreground) |
| `make healthcheck` | `GET /api/v1/health` (pretty-printed JSON; fails on HTTP/network errors) |
| `make stop` | `docker compose down` (database container; volume retained) |
| `make probe-db` | Python `SELECT 1` smoke test (no HTTP server) |
| `make db-logs` | Follow Postgres logs |

Override examples:

```bash
make healthcheck API_URL=http://127.0.0.1:8000/api/v1/health
make start UVICORN_PORT=8001
```

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

## Git

This repo is intended for **personal** GitHub use. For isolating identity and remotes from work (e.g. GitLab), maintain a **local-only** `README-GIT.md` in the repo root: it is **gitignored** and holds SSH/Git identity notes for your machine.

To initialize Git and publish (after configuring identity per `README-GIT.md`):

```bash
git init
git add .
git status
git commit -m "Initial commit: FastAPI backend, Docker Postgres, health checks"
git remote add origin git@github.com:SMASH3D/oneshot_fantasy.git
git branch -M main
git push -u origin main
```

Replace the remote URL with your real repository path if the name differs.

## License

Personal project; add a license file when you are ready.
