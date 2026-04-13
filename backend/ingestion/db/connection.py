"""PostgreSQL access via psycopg2 using DATABASE_URL from backend/.env (same DB as Symfony/Doctrine)."""

from __future__ import annotations

import os
from collections.abc import Callable, Iterator, Sequence
from contextlib import contextmanager
from pathlib import Path
from typing import Any, TypeVar

import psycopg2
from psycopg2.extensions import connection as PgConnection
from psycopg2.extensions import cursor as PgCursor

# Load .env from backend/ (parents[2] = backend/ when file is at backend/ingestion/db/connection.py)
_ENV_FILE = Path(__file__).resolve().parents[2] / ".env"
if _ENV_FILE.exists():
    from dotenv import load_dotenv
    load_dotenv(_ENV_FILE, override=False)

T = TypeVar("T")


def require_database_url() -> str:
    url = os.environ.get("DATABASE_URL", "").strip()
    if not url:
        raise RuntimeError("DATABASE_URL is required (set it in backend/.env or environment)")
    # Strip async driver suffixes so psycopg2 can connect (backend/.env may use +asyncpg)
    url = url.replace("+asyncpg", "").replace("+asyncio", "").replace("+aiosqlite", "")
    return url


def connect() -> PgConnection:
    return psycopg2.connect(require_database_url())


@contextmanager
def get_connection() -> Iterator[PgConnection]:
    conn = connect()
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def run_query(
    sql: str,
    params: Sequence[Any] | None = None,
    *,
    fetch: Callable[[PgCursor], T] | None = None,
) -> T | None:
    """
    Run a single statement. Commits the transaction on success.
    If fetch is provided, it receives the cursor after execute (e.g. cursor.fetchall).
    """
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params or ())
            if fetch is None:
                return None
            return fetch(cur)
