from fastapi import APIRouter, status
from fastapi.responses import JSONResponse

from app.api.health import HealthResponse, collect_health_checks

api_router = APIRouter()


@api_router.get(
    "/health",
    tags=["system"],
    response_model=HealthResponse,
    responses={
        status.HTTP_503_SERVICE_UNAVAILABLE: {
            "model": HealthResponse,
            "description": "One or more health checks failed.",
        },
    },
    summary="Liveness and dependency checks",
)
async def health() -> HealthResponse | JSONResponse:
    body, ok = await collect_health_checks()
    if ok:
        return body
    return JSONResponse(
        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
        content=body.model_dump(),
    )
