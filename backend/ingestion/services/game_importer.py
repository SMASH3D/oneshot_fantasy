"""Importer abstracting external schedule data into tournament specific Postgres schema."""
import logging
from dataclasses import dataclass
from typing import Protocol, Any
from psycopg2.extras import execute_values
from ingestion.db.connection import get_connection

_LOG = logging.getLogger(__name__)

class SupportsGameFetch(Protocol):
    def get_playoff_games(self, season: str | None = None) -> list[dict[str, Any]]: ...

@dataclass
class GameImportSummary:
    inserted: int
    updated: int
    total: int

class GameImporter:
    def __init__(self, client: SupportsGameFetch, *, sport: str = "basketball") -> None:
        self._client = client
        self._sport = sport

    def run(self, season: str) -> GameImportSummary:
        games = self._client.get_playoff_games(season=season)
        if not games:
            return GameImportSummary(0, 0, 0)
            
        with get_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT id, external_id FROM teams WHERE sport = %s", (self._sport,))
                teams = {str(row[1]): row[0] for row in cur.fetchall()}
                
                cur.execute("SELECT id FROM tournaments WHERE sport = %s LIMIT 1", (self._sport,))
                t_row = cur.fetchone()
                if not t_row:
                    _LOG.error("No active tournament configured for %s", self._sport)
                    return GameImportSummary(0, 0, 0)
                    
                tournament_id = t_row[0]
                
                upsert_rows = []
                for g in games:
                    home_fk = teams.get(g["home_team_external_id"])
                    away_fk = teams.get(g["away_team_external_id"])
                    
                    if not home_fk or not away_fk:
                        continue
                        
                    winner_fk = None
                    if g["status"] == "complete":
                        h_score = int(g["home_score"] or 0)
                        a_score = int(g["away_score"] or 0)
                        if h_score > a_score:
                            winner_fk = home_fk
                        elif a_score > h_score:
                            winner_fk = away_fk
                            
                    upsert_rows.append((
                        tournament_id,
                        None, # tournament_round_id placeholder
                        g["date"],
                        home_fk,
                        away_fk,
                        g["home_score"],
                        g["away_score"],
                        winner_fk,
                        g["status"],
                        g["external_id"]
                    ))

                if not upsert_rows:
                    return GameImportSummary(0, 0, len(games))

                query = """
                    INSERT INTO games (id, tournament_id, tournament_round_id, date, home_team_id, away_team_id, home_score, away_score, winner_team_id, status, external_id, created_at, updated_at)
                    VALUES %s
                    ON CONFLICT (external_id) DO UPDATE SET
                        date = EXCLUDED.date,
                        home_score = EXCLUDED.home_score,
                        away_score = EXCLUDED.away_score,
                        winner_team_id = EXCLUDED.winner_team_id,
                        status = EXCLUDED.status,
                        updated_at = NOW()
                    RETURNING (xmax = 0) AS inserted;
                """
                
                template = "(gen_random_uuid(), %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())"
                
                results = execute_values(
                    cur, 
                    query, 
                    upsert_rows, 
                    template=template,
                    fetch=True
                )
                
                if results is None:
                     return GameImportSummary(0, 0, len(games))

                inserted_count = sum(1 for row in results if row[0] is True)
                updated_count = len(upsert_rows) - inserted_count

                _LOG.info("Game Import finished: %d individual matchups pushed (%d new, %d updated)", len(upsert_rows), inserted_count, updated_count)
                return GameImportSummary(inserted=inserted_count, updated=updated_count, total=len(games))
