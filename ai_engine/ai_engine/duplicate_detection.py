from __future__ import annotations

from difflib import SequenceMatcher
import re
import unicodedata


def normalize_title(title: str) -> str:
    normalized = unicodedata.normalize("NFKC", title).casefold()
    return " ".join(re.findall(r"[\w]+", normalized, flags=re.UNICODE))


def duplicate_index(title: str, existing: dict[str, int], threshold: float = 0.88) -> str | None:
    normalized = normalize_title(title)
    if normalized in existing:
        return normalized
    for candidate in existing:
        if SequenceMatcher(None, normalized, candidate).ratio() >= threshold:
            return candidate
    return None
