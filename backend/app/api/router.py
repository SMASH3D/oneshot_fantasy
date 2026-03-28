from fastapi import APIRouter

from app.api.v1 import fantasy, health, scoring, tournaments

api_router = APIRouter()
api_router.include_router(health.router, tags=["system"])
api_router.include_router(tournaments.router, prefix="/tournaments", tags=["tournaments"])
api_router.include_router(fantasy.router, prefix="/fantasy", tags=["fantasy"])
api_router.include_router(scoring.router, prefix="/scoring", tags=["scoring"])
