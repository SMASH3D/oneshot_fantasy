from contextlib import asynccontextmanager

from fastapi import FastAPI

from app.api.router import api_router
from app.config import get_settings
from app.infrastructure.database import dispose_engine


@asynccontextmanager
async def lifespan(app: FastAPI):
    yield
    await dispose_engine()


def create_app() -> FastAPI:
    settings = get_settings()
    app = FastAPI(
        title="Oneshot Fantasy",
        description="Survival / use-once fantasy for elimination tournaments",
        lifespan=lifespan,
    )
    app.include_router(api_router, prefix=settings.api_v1_prefix)
    return app


app = create_app()
