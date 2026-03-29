# Python worker (`backend/`)

This package is **not** an HTTP server. **Symfony** (`../symfony/`) exposes the API and UI.

Python keeps:

- **Domain** logic under `app/domains/` (tournament, fantasy, scoring, ingestion contracts)
- **Infrastructure**: async SQLAlchemy access to the **same PostgreSQL** as Doctrine (`app/infrastructure/`)
- **Batch scoring** orchestration: `app/application/scoring_service.py` (calculator registry + `recompute_membership_round`)
- **Ingestion**: `StatIngestionRunner` in `app/application/ingestion/runner.py` (adapters in `app/infrastructure/ingestion/`)
- **Worker scripts** (async, `DATABASE_URL` in `backend/.env`):

```bash
cd backend
.venv/bin/python -m scripts.sync_data --tournament-id <uuid>
.venv/bin/python -m scripts.compute_scores --league-id <uuid> --fantasy-round-id <uuid>
```

Run from repo root:

```bash
make install-worker   # or full make install
make probe-db         # SELECT 1 via DATABASE_URL in backend/.env
make worker-sync TOURNAMENT_ID=<uuid>
make worker-score LEAGUE_ID=<uuid> FANTASY_ROUND_ID=<uuid>
```

Cron, `flock`, and log rotation: **[docs/worker-automation.md](../docs/worker-automation.md)** (and `crontab.example` in this directory).
