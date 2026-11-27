from __future__ import annotations

from typing import Any


class TikTokConnector:
    """TikTok Research API Connector.

    Transparently enforces the zero-cost compliance boundary:
    TikTok Research API is gated to vetted non-profit academic institutions.
    Commercial trend discovery APIs are not provided at $0 by TikTok.
    """

    source_key = "tiktok"
    source_name = "TikTok Research API"

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
            "TikTok Research API is restricted to approved non-profit academic researchers. "
            "General public commercial trend discovery is unavailable at $0."
        ]
