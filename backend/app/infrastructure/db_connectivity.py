"""
Ad-hoc connectivity check against PostgreSQL using the app's async engine.
Run from backend/:  python -m app.infrastructure.db_connectivity
"""

import asyncio
import time
from dataclasses import dataclass

from sqlalchemy import text

from app.infrastructure.database import dispose_engine, get_engine


@dataclass(frozen=True)
class DatabaseAccessResult:
    ok: bool
    message: str
    elapsed_ms: float | None = None


class DatabaseAccessProbe:
    """Verifies that Python can reach Postgres with the configured DATABASE_URL."""

    async def check(self) -> DatabaseAccessResult:
        started = time.perf_counter()
        try:
            engine = get_engine()
            async with engine.connect() as conn:
                value = (await conn.execute(text("SELECT 1"))).scalar_one()
            elapsed_ms = (time.perf_counter() - started) * 1000
            if value != 1:
                return DatabaseAccessResult(
                    ok=False,
                    message=f"Unexpected scalar from SELECT 1: {value!r}",
                    elapsed_ms=elapsed_ms,
                )
            return DatabaseAccessResult(
                ok=True,
                message="SELECT 1 succeeded",
                elapsed_ms=elapsed_ms,
            )
        except Exception as exc:  # noqa: BLE001 — surface any driver/connection error
            elapsed_ms = (time.perf_counter() - started) * 1000
            return DatabaseAccessResult(
                ok=False,
                message=f"{type(exc).__name__}: {exc}",
                elapsed_ms=elapsed_ms,
            )


async def _main() -> None:
    probe = DatabaseAccessProbe()
    result = await probe.check()
    status = "OK" if result.ok else "FAIL"
    ms = f"{result.elapsed_ms:.1f} ms" if result.elapsed_ms is not None else "n/a"
    print(f"[{status}] {result.message} ({ms})")
    await dispose_engine()
    raise SystemExit(0 if result.ok else 1)


if __name__ == "__main__":
    asyncio.run(_main())
