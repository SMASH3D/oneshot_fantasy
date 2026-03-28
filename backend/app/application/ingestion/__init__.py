"""
Ingestion orchestration (scheduled pulls, cursors, dedupe).

Adapters live in app.infrastructure.ingestion.adapters; this package will host
runners that call IStatProvider implementations and persist StatEvent rows.
"""


class StatIngestionRunner:
    """Placeholder for a future job/cron entrypoint."""

    async def run_once(self, tournament_id: object) -> None:
        del tournament_id
        raise NotImplementedError("StatIngestionRunner will be implemented with IStatProvider.")
