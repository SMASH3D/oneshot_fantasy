"""Upsert sport participants loaded from a client (e.g. NBA) into Symfony's ``participants`` table."""

from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Any, Protocol

from psycopg2.extras import Json

from ingestion.db.connection import get_connection

_LOG = logging.getLogger(__name__)


class SupportsPlayerFetch(Protocol):
    def get_all_players(self, *, include_metadata: bool = False) -> list[dict[str, Any]]: ...


@dataclass
class ImportSummary:
    inserted: int
    updated: int
    total: int


class PlayerImporter:
    def __init__(self, client: SupportsPlayerFetch, *, sport: str = "basketball", participant_type: str = "player") -> None:
        self._client = client
        self._sport = sport
        self._type = participant_type

    def run(self) -> ImportSummary:
        players = self._client.get_all_players(include_metadata=True)
        if not players:
            return ImportSummary(inserted=0, updated=0, total=0)

        upsert_sql = """
            INSERT INTO participants (
                id,
                external_id,
                name,
                sport,
                "type",
                team_name,
                position,
                team_id,
                metadata,
                created_at,
                updated_at
            )
            VALUES (
                gen_random_uuid(),
                %s,
                %s,
                %s,
                %s,
                %s,
                %s,
                %s,
                %s,
                NOW(),
                NOW()
            )
            ON CONFLICT (external_id, sport) DO UPDATE SET
                name = EXCLUDED.name,
                "type" = EXCLUDED."type",
                team_name = EXCLUDED.team_name,
                position = EXCLUDED.position,
                team_id = EXCLUDED.team_id,
                metadata = EXCLUDED.metadata,
                updated_at = NOW()
            """

        with get_connection() as conn:
            with conn.cursor() as cur:
                # Gather internally tracked team ids matching external configurations
                cur.execute("SELECT id, external_id FROM teams WHERE sport = %s", (self._sport,))
                team_lookup = {str(row[1]): row[0] for row in cur.fetchall()}

                cur.execute(
                    "SELECT external_id FROM participants WHERE sport = %s",
                    (self._sport,),
                )
                existing = {row[0] for row in cur.fetchall()}

                inserted = sum(1 for p in players if p["id"] not in existing)
                updated = len(players) - inserted

                for p in players:
                    meta = p.get("metadata")
                    team_fk = team_lookup.get(str(p.get("team_external_id"))) if p.get("team_external_id") else None
                    cur.execute(
                        upsert_sql,
                        (
                            p["id"],
                            p["name"],
                            self._sport,
                            self._type,
                            p.get("team"),
                            p.get("position"),
                            team_fk,
                            Json(meta) if meta is not None else None,
                        ),
                    )

        _LOG.info("Import finished: %d rows touched (%d new, %d updated)", len(players), inserted, updated)
        return ImportSummary(inserted=inserted, updated=updated, total=len(players))
