"""Loads and caches scoring configurations for fantasy leagues."""

from ingestion.db.connection import get_connection

class ScoringConfigLoader:
    def __init__(self):
        self._configs: dict[str, dict] = {}
        self._loaded: bool = False

    def get_config(self, league_id: str) -> dict:
        if not self._loaded:
            self._load_all()
        return self._configs.get(league_id, {})

    def _load_all(self):
        with get_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT id, scoring_config FROM fantasy_leagues")
                rows = cur.fetchall()
                for row in rows:
                    league_id = str(row[0])
                    config = row[1] or {}
                    self._configs[league_id] = config
        self._loaded = True
