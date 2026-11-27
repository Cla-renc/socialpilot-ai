from __future__ import annotations

from typing import Any


class FacebookConnector:
    """Facebook Graph API Connector.

    Transparently enforces official platform compliance:
    Meta deprecated the public trend/feed discovery APIs in 2018.
    Platform-wide trend discovery is not supported at $0; connected Page insights
    are strictly account-scoped and do not reflect global trends.
    """

    source_key = "facebook"
    source_name = "Facebook Graph API"

    def __init__(self, *args: Any, **kwargs: Any) -> None:
        pass

    @property
    def is_configured(self) -> bool:
        return False

    async def discover(
        self,
        queries: list[str],
        days: int = 30,
        market: str | None = None,
    ) -> tuple[list[dict[str, Any]], int, list[str]]:
        del queries, days, market
        return [], 0, [
            "Facebook Public Feed API was deprecated by Meta. "
            "Public trend discovery is unavailable at $0 (connected Page insights only)."
        ]
