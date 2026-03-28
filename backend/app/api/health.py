import time
from typing import Literal

from pydantic import BaseModel, Field
from sqlalchemy import text

from app.infrastructure.database import get_engine


class CheckPayload(BaseModel):
    status: Literal["ok", "error"]
    message: str
    latency_ms: float | None = Field(
        default=None,
        description="Round-trip time for this check, when applicable (milliseconds).",
    )


class HealthResponse(BaseModel):
    checks: dict[str, CheckPayload] = Field(
        ...,
        description="Named checks: backend liveness, database read, database write.",
    )


async def collect_health_checks() -> tuple[HealthResponse, bool]:
    """
    Runs health probes in order: backend, db_read, db_write.
    Returns the response body and whether every check succeeded.
    """
    checks: dict[str, CheckPayload] = {}

    checks["backend"] = CheckPayload(
        status="ok",
        message="FastAPI application process is handling requests",
    )

    engine = get_engine()
    all_ok = True

    # --- db read: simple SELECT ---------------------------------------------
    t0 = time.perf_counter()
    try:
        async with engine.connect() as conn:
            value = (await conn.execute(text("SELECT 1"))).scalar_one()
        elapsed_ms = (time.perf_counter() - t0) * 1000
        if value != 1:
            checks["db_read"] = CheckPayload(
                status="error",
                message=f"Read probe returned unexpected value: {value!r} (expected 1)",
                latency_ms=round(elapsed_ms, 3),
            )
            all_ok = False
        else:
            checks["db_read"] = CheckPayload(
                status="ok",
                message="Database read: SELECT 1 returned expected result",
                latency_ms=round(elapsed_ms, 3),
            )
    except Exception as exc:  # noqa: BLE001
        elapsed_ms = (time.perf_counter() - t0) * 1000
        checks["db_read"] = CheckPayload(
            status="error",
            message=f"Database read failed: {type(exc).__name__}: {exc}",
            latency_ms=round(elapsed_ms, 3),
        )
        all_ok = False

    # --- db write: temp table + INSERT (dropped on commit, no app tables) ---
    t0 = time.perf_counter()
    try:
        async with engine.connect() as conn:
            async with conn.begin():
                await conn.execute(
                    text(
                        "CREATE TEMP TABLE _oneshot_health_write_probe (v int) "
                        "ON COMMIT DROP"
                    )
                )
                await conn.execute(
                    text("INSERT INTO _oneshot_health_write_probe VALUES (1)")
                )
        elapsed_ms = (time.perf_counter() - t0) * 1000
        checks["db_write"] = CheckPayload(
            status="ok",
            message=(
                "Database write: INSERT into a session temp table succeeded "
                "(table uses ON COMMIT DROP; no persistent data)"
            ),
            latency_ms=round(elapsed_ms, 3),
        )
    except Exception as exc:  # noqa: BLE001
        elapsed_ms = (time.perf_counter() - t0) * 1000
        checks["db_write"] = CheckPayload(
            status="error",
            message=f"Database write failed: {type(exc).__name__}: {exc}",
            latency_ms=round(elapsed_ms, 3),
        )
        all_ok = False

    return HealthResponse(checks=checks), all_ok
