# Oneshot Fantasy — root Makefile
# Run `make help` for documented targets. Keep this header and ## comments in sync when adding targets.

.PHONY: help install install-deps install-db start healthcheck stop db-up db-down db-logs probe-db

# Paths (backend holds Python app, Docker Compose, and .env)
BACKEND_DIR        := backend
COMPOSE_FILE       := $(BACKEND_DIR)/docker-compose.yml
COMPOSE            := docker compose -f $(COMPOSE_FILE)
SCHEMA_FILE        := $(BACKEND_DIR)/sql/schema.sql
VENV               := $(BACKEND_DIR)/.venv
PIP                := $(VENV)/bin/pip
PY                 := $(VENV)/bin/python
UVICORN            := $(VENV)/bin/uvicorn

# Postgres inside the Compose service (override if backend/.env differs)
POSTGRES_USER      ?= postgres
POSTGRES_DB        ?= oneshot_fantasy

# API (override if needed: make healthcheck API_URL=http://127.0.0.1:3000/api/v1/health)
API_URL            ?= http://127.0.0.1:8000/api/v1/health
UVICORN_HOST       ?= 127.0.0.1
UVICORN_PORT       ?= 8000

.DEFAULT_GOAL := help

help: ## Show this help (default target)
	@echo "Oneshot Fantasy — useful commands"
	@echo ""
	@grep -E '^[a-zA-Z0-9_.-]+:.*?## ' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Variables: API_URL=$(API_URL) UVICORN_PORT=$(UVICORN_PORT) (override on the command line)"
	@echo "DB install: SCHEMA_FILE=$(SCHEMA_FILE) POSTGRES_USER=$(POSTGRES_USER) POSTGRES_DB=$(POSTGRES_DB)"

install: install-deps install-db ## Venv + Python deps, then Postgres + apply sql/schema.sql (needs Docker)

install-deps: ## Create backend venv and pip install -e ".[dev]" only
	cd $(BACKEND_DIR) && python3 -m venv .venv && $(PIP) install -U pip && $(PIP) install -e ".[dev]"

install-db: db-up ## Start Postgres if needed and apply SCHEMA_FILE via psql (re-run fails if tables exist)
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

start: db-up ## Start Postgres, then run the API (foreground; Ctrl+C stops only Uvicorn)
	cd $(BACKEND_DIR) && $(UVICORN) app.main:app --reload --host $(UVICORN_HOST) --port $(UVICORN_PORT)

healthcheck: ## GET /api/v1/health (expects 200; fails on connection error or HTTP error)
	@curl -sfS "$(API_URL)" | python3 -m json.tool
	@echo ""
	@echo "healthcheck: OK ($(API_URL))"

stop: ## Stop and remove Postgres containers (data volume kept)
	$(COMPOSE) down

db-down: ## Alias for stop
	$(COMPOSE) down

db-logs: ## Follow Postgres container logs
	$(COMPOSE) logs -f db

probe-db: ## Python-only DB smoke test (no HTTP server required)
	cd $(BACKEND_DIR) && $(PY) -m app.infrastructure.db_connectivity
