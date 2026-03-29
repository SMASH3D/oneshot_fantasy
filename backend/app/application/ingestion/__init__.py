"""Ingestion orchestration (worker entrypoints call StatIngestionRunner)."""

from app.application.ingestion.runner import StatIngestionRunner

__all__ = ["StatIngestionRunner"]
