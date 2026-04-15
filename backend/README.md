# Python worker (`backend/`)

This package is **not** an HTTP server. **Symfony** (`../symfony/`) owns the HTTP API and UI.
Python handles two distinct concerns:

## 1. NBA data ingestion (`ingestion/`)

Scripts that pull data from `stats.nba.com` via the `nba_api` library and upsert it into the
shared PostgreSQL database. Symfony reads this data via Doctrine — the two sides never conflict.

See **[ingestion/INGESTERS.md](ingestion/INGESTERS.md)** for the full pipeline, run order, and
an explanation of each script aimed at PHP developers.

Quick reference:

```bash
make ingest-nba-teams           # teams table
make ingest-nba                 # participants table (players)
make ingest-nba-stats           # season stats
make ingest-nba-games           # games table (playoff schedule)
make ingest-nba-game-stats      # per-game boxscores
make ingest-nba-playoffs SLUG=nba-playoffs-2026   # tournament_rounds
```

## 2. Batch scoring worker (`app/`)

Async jobs that compute fantasy scores and ingest stat events. Uses async SQLAlchemy against
the same PostgreSQL.

- **Domain** logic: `app/domains/`
- **Infrastructure**: `app/infrastructure/` (async SQLAlchemy, ingestion adapters)
- **Batch scoring**: `app/application/scoring_service.py`
- **Ingestion runner**: `app/application/ingestion/runner.py`

```bash
make install-worker                              # create .venv and install deps
make probe-db                                    # SELECT 1 via DATABASE_URL in backend/.env
make worker-sync TOURNAMENT_ID=<uuid>            # ingest stat_events
make worker-score LEAGUE_ID=<uuid> FANTASY_ROUND_ID=<uuid>   # recompute scores
```

Cron, `flock`, and log rotation: **[docs/worker-automation.md](../docs/worker-automation.md)**.
