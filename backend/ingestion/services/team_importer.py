"""Importer integrating external python definitions directly to Postgres Team entities flawlessly."""
import logging
from dataclasses import dataclass
from typing import Protocol, Any
from psycopg2.extras import execute_values, Json
from ingestion.db.connection import get_connection

_LOG = logging.getLogger(__name__)

class SupportsTeamFetch(Protocol):
    def get_teams(self) -> list[dict[str, Any]]: ...

@dataclass
class TeamImportSummary:
    inserted: int
    updated: int
    total: int

class TeamImporter:
    def __init__(self, client: SupportsTeamFetch, *, sport: str = "basketball") -> None:
        self._client = client
        self._sport = sport

    def run(self) -> TeamImportSummary:
        teams = self._client.get_teams()
        if not teams:
            return TeamImportSummary(0, 0, 0)
            
        upsert_rows = []
        for t in teams:
            upsert_rows.append((
                t["external_id"],
                t["name"],
                t.get("abbreviation"),
                t.get("city"),
                self._sport,
                Json(t.get("metadata")) if t.get("metadata") else None
            ))
            
        with get_connection() as conn:
            with conn.cursor() as cur:
                query = """
                    INSERT INTO teams 
                    (id, external_id, name, abbreviation, city, sport, metadata, created_at, updated_at)
                    VALUES %s
                    ON CONFLICT (external_id, sport) DO UPDATE SET
                        name = EXCLUDED.name,
                        abbreviation = EXCLUDED.abbreviation,
                        city = EXCLUDED.city,
                        metadata = EXCLUDED.metadata,
                        updated_at = NOW()
                    RETURNING (xmax = 0) AS inserted;
                """
                template = "(gen_random_uuid(), %s, %s, %s, %s, %s, %s, NOW(), NOW())"
                
                results = execute_values(
                    cur, 
                    query, 
                    upsert_rows, 
                    template=template,
                    fetch=True
                )
                
                if results is None:
                     return TeamImportSummary(0, 0, len(teams))

                inserted_count = sum(1 for row in results if row[0] is True)
                updated_count = len(upsert_rows) - inserted_count

                _LOG.info("Team Import finished: %d teams touched (%d new, %d updated)", len(upsert_rows), inserted_count, updated_count)
                return TeamImportSummary(inserted=inserted_count, updated=updated_count, total=len(teams))
