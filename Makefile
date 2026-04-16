# Oneshot Fantasy — root Makefile
# Symfony = HTTP API + UI; backend/ = Python worker (ingestion + scoring); Postgres via Docker.
# Run `make help` for documented targets. Keep `##` descriptions in sync when adding targets.

.PHONY: help install install-symfony install-worker install-db start symfony-serve symfony-cc \
	db-up db-down db-logs stop healthcheck probe-db worker-sync worker-score worker-score-batch \
	ingest-nba ingest-nba-games ingest-nba-playoffs ingest-nba-participations

# --- Paths ---
BACKEND_DIR        := backend
SYMFONY_DIR        := symfony
COMPOSE_FILE       := $(BACKEND_DIR)/docker-compose.yml
COMPOSE            := docker compose -f $(COMPOSE_FILE)
SCHEMA_FILE        := $(BACKEND_DIR)/sql/schema.sql
VENV               := $(CURDIR)/$(BACKEND_DIR)/.venv
PIP                := $(VENV)/bin/pip
PY                 := $(VENV)/bin/python

# Postgres (override if backend/.env differs)
POSTGRES_USER      ?= postgres
POSTGRES_DB        ?= oneshot_fantasy

# Symfony built-in server
SYMFONY_HOST       ?= 127.0.0.1
SYMFONY_PORT       ?= 8080
HEALTH_URL         ?= http://$(SYMFONY_HOST):$(SYMFONY_PORT)/health

.DEFAULT_GOAL := help

help: ## Show this help (default target)
	@echo "Oneshot Fantasy — Symfony + Python worker + Postgres"
	@echo ""
	@grep -E '^[a-zA-Z0-9_.-]+:.*?## ' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "URLs: Symfony http://$(SYMFONY_HOST):$(SYMFONY_PORT)/  health $(HEALTH_URL)  API /api"
	@echo "DB: SCHEMA_FILE=$(SCHEMA_FILE) POSTGRES_USER=$(POSTGRES_USER) POSTGRES_DB=$(POSTGRES_DB)"

install: install-symfony install-worker install-db ## Composer + Python venv + Postgres schema (Docker)

install-symfony: ## composer install in symfony/
	cd $(SYMFONY_DIR) && composer install --no-interaction

install-worker: ## Python venv in backend/ and pip install -e ".[dev]" (worker libs)
	cd $(BACKEND_DIR) && python3 -m venv .venv && $(PIP) install -U pip && $(PIP) install -e ".[dev]"

install-deps: install-worker ## Deprecated alias for install-worker

install-db: db-up ## Apply SCHEMA_FILE via psql (re-run fails if tables already exist)
	@test -f $(SCHEMA_FILE) || (echo "Missing $(SCHEMA_FILE)" >&2 && exit 1)
	@echo "Waiting for Postgres ($(POSTGRES_USER) @ $(POSTGRES_DB))..."
	@i=0; \
	while ! $(COMPOSE) exec -T db pg_isready -U $(POSTGRES_USER) -d $(POSTGRES_DB) -q 2>/dev/null; do \
		i=$$((i + 1)); \
		if [ $$i -gt 60 ]; then echo "Postgres did not become ready in time." >&2; exit 1; fi; \
		sleep 1; \
	done
	cat $(SCHEMA_FILE) | $(COMPOSE) exec -T db psql -v ON_ERROR_STOP=1 -U $(POSTGRES_USER) -d $(POSTGRES_DB)
	@echo "install-db: applied $(SCHEMA_FILE)"

db-up: ## Start PostgreSQL (Docker) in the background
	$(COMPOSE) up -d

start: db-up symfony-serve ## Postgres + Symfony dev server (foreground; Ctrl+C stops PHP only)

symfony-serve: ## PHP built-in server for symfony/public (use Symfony CLI if you prefer)
	cd $(SYMFONY_DIR) && php -S $(SYMFONY_HOST):$(SYMFONY_PORT) -t public

symfony-cc: ## Symfony cache clear (dev)
	cd $(SYMFONY_DIR) && php bin/console cache:clear

healthcheck: ## GET Symfony /health (JSON liveness; start Symfony first)
	@curl -sfS "$(HEALTH_URL)" | python3 -m json.tool
	@echo ""
	@echo "healthcheck: OK ($(HEALTH_URL))"

stop: ## Stop Postgres containers (volume kept)
	$(COMPOSE) down

db-down: stop ## Alias for stop

db-logs: ## Follow Postgres container logs
	$(COMPOSE) logs -f db

probe-db: ## Python SELECT 1 against DATABASE_URL (no HTTP)
	cd $(BACKEND_DIR) && $(PY) -m app.infrastructure.db_connectivity

worker-sync: ## Ingest stat_events (set TOURNAMENT_ID=uuid; loads backend/.env)
	@test -n "$(TOURNAMENT_ID)" || (echo "Set TOURNAMENT_ID to a tournament UUID" >&2; exit 1)
	cd $(BACKEND_DIR) && ( set -a; [ -f .env ] && . ./.env; set +a; $(PY) -m scripts.sync_data --tournament-id "$(TOURNAMENT_ID)" )

worker-score: ## Recompute round_scores (set LEAGUE_ID= FANTASY_ROUND_ID=; loads backend/.env)
	@test -n "$(LEAGUE_ID)" && test -n "$(FANTASY_ROUND_ID)" || (echo "Set LEAGUE_ID and FANTASY_ROUND_ID" >&2; exit 1)
	cd $(BACKEND_DIR) && ( set -a; [ -f .env ] && . ./.env; set +a; $(PY) -m scripts.compute_scores --league-id "$(LEAGUE_ID)" --fantasy-round-id "$(FANTASY_ROUND_ID)" )

worker-score-batch: ## Batch scoring (set SCORE_JOBS_FILE=path; loads backend/.env)
	@test -n "$(SCORE_JOBS_FILE)" || (echo "Set SCORE_JOBS_FILE to a jobs file path" >&2; exit 1)
	cd $(BACKEND_DIR) && ( set -a; [ -f .env ] && . ./.env; set +a; $(PY) -m scripts.score_batch --jobs-file "$(SCORE_JOBS_FILE)" --continue-on-error )

ingest-nba: ## Import current NBA roster into participants table (loads backend/.env)
	cd $(BACKEND_DIR) && ( set -a; [ -f .env ] && . ./.env; set +a; $(PY) scripts/import_nba_players.py )

ingest-nba-games: ## Import NBA playoff games into games table (SLUG=required, SEASON=optional; loads backend/.env)
	@test -n "$(SLUG)" || (echo "Usage: make ingest-nba-games SLUG=nba-playoffs-2025 [SEASON=2024-25]" >&2 && exit 1)
	cd $(BACKEND_DIR) && ( set -a; [ -f .env ] && . ./.env; set +a; $(PY) ingestion/scripts/import_nba_games.py --slug "$(SLUG)" $(if $(SEASON),--season "$(SEASON)",) )

ingest-nba-playoffs: ## Import NBA playoff series into tournament_rounds (SLUG=required, SEASON=optional; loads backend/.env)
	@test -n "$(SLUG)" || (echo "Usage: make ingest-nba-playoffs SLUG=nba-playoffs-2026 [SEASON=2025-26]" >&2 && exit 1)
	cd $(BACKEND_DIR) && ( set -a; [ -f .env ] && . ./.env; set +a; $(PY) ingestion/scripts/import_nba_playoffs.py --slug "$(SLUG)" $(if $(SEASON),--season "$(SEASON)",) )

ingest-nba-participations: ## Sync team participation status from rounds (SLUG=required; loads backend/.env)
	@test -n "$(SLUG)" || (echo "Usage: make ingest-nba-participations SLUG=nba-playoffs-2025" >&2 && exit 1)
	cd $(BACKEND_DIR) && ( set -a; [ -f .env ] && . ./.env; set +a; $(PY) ingestion/scripts/import_nba_participations.py --slug "$(SLUG)" )
