# Documentation

Human-written specs for Oneshot Fantasy. When you add or change HTTP behavior, update these files together with Symfony (API Platform) resources and `/api/docs`.

## Contents

| Document | Description |
|----------|-------------|
| [API (MVP)](api/README.md) | REST routes, methods, and JSON request/response examples for tournaments, leagues, draft, lineups, and scores |
| [Worker automation](worker-automation.md) | Cron, logging, idempotency, `flock`, batch scoring |

## Generated API reference

With Symfony running locally (`make start` from the repo root):

- [Swagger UI](http://127.0.0.1:8080/api/docs)
- OpenAPI JSON: `GET /api/docs` with `Accept: application/vnd.openapi+json` (or use the export control in the Swagger UI)

Treat `docs/api/README.md` as the **product-level MVP contract**; Symfony’s `/api/docs` reflects what is implemented. Keep them aligned as endpoints land.

## Maintenance

- **New route group:** add a subsection under `docs/api/README.md` and a row in its summary table.
- **Breaking change:** note it in the API doc and bump any version prefix if you introduce `/api/v2` later.
