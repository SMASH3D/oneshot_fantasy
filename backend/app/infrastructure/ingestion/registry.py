"""Resolve IStatProvider by stats_adapter_key."""

from app.domains.ingestion.ports import IStatProvider
from app.infrastructure.ingestion.adapters.noop import NoopStatProvider

_registry: dict[str, IStatProvider] = {
    "noop": NoopStatProvider(),
}


def get_stat_provider(stats_adapter_key: str | None) -> IStatProvider:
    if not stats_adapter_key:
        return NoopStatProvider()
    return _registry.get(stats_adapter_key, NoopStatProvider())


def register_stat_provider(stats_adapter_key: str, provider: IStatProvider) -> None:
    _registry[stats_adapter_key] = provider
