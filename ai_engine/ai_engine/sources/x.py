from __future__ import annotations

from typing import Any


class XConnector:
    """X (Twitter) API v2 Connector.

    Transparently enforces the zero-cost requirement:
    X API v2 abolished free-tier search and trends endpoints in April 2023.
    Free tier is write-only (500 posts/month). Read access requires a paid Basic tier ($100/mo).
    """

    source_key = "x"
    source_name = "X API v2"

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
            "X API v2 requires a paid Basic subscription ($100/mo) for search/trend endpoints. "
            "Zero-cost policy active."
        ]
