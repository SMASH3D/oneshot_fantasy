# Oneshot Fantasy — root Makefile
# Run `make help` for documented targets. Keep this header and ## comments in sync when adding targets.

.PHONY: help install start healthcheck stop db-up db-down db-logs probe-db

# Paths (backend holds Python app, Docker Compose, and .env)
BACKEND_DIR        := backend
COMPOSE_FILE       := $(BACKEND_DIR)/docker-compose.yml
COMPOSE            := docker compose -f $(COMPOSE_FILE)
VENV               := $(BACKEND_DIR)/.venv
PIP                := $(VENV)/bin/pip
PY                 := $(VENV)/bin/python
UVICORN            := $(VENV)/bin/uvicorn

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

install: ## Create backend venv and install the app + dev dependencies
	cd $(BACKEND_DIR) && python3 -m venv .venv && $(PIP) install -U pip && $(PIP) install -e ".[dev]"

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
