from __future__ import annotations

import re
import unicodedata
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

# Common tracking parameters to strip for clean URL normalization
_STRIP_QUERY_PARAMS = {
    "utm_source",
    "utm_medium",
    "utm_campaign",
    "utm_term",
    "utm_content",
    "utm_id",
    "ref",
    "ref_src",
    "fbclid",
    "gclid",
    "dclid",
    "msclkid",
    "mc_cid",
    "mc_eid",
    "igshid",
}

# Standard English stop words that carry negligible semantic weight for clustering,
# carefully excluding high-signal industry acronyms and domain terms ("ai", "b2b", "saas", "api", "seo").
_GENERIC_STOP_WORDS = {
    "a", "about", "above", "after", "again", "against", "all", "am", "an", "and",
    "any", "are", "aren't", "as", "at", "be", "because", "been", "before", "being",
    "below", "between", "both", "but", "by", "can", "can't", "cannot", "could",
    "couldn't", "did", "didn't", "do", "does", "doesn't", "doing", "don't", "down",
    "during", "each", "few", "for", "from", "further", "had", "hadn't", "has",
    "hasn't", "have", "haven't", "having", "he", "he'd", "he'll", "he's", "her",
    "here", "here's", "hers", "herself", "him", "himself", "his", "how", "how's",
    "i", "i'd", "i'll", "i'm", "i've", "if", "in", "into", "is", "isn't", "it",
    "it's", "its", "itself", "let's", "me", "more", "most", "mustn't", "my",
    "myself", "no", "nor", "not", "of", "off", "on", "once", "only", "or", "other",
    "ought", "our", "ours", "ourselves", "out", "over", "own", "same", "shan't",
    "she", "she'd", "she'll", "she's", "should", "shouldn't", "so", "some", "such",
    "than", "that", "that's", "the", "their", "theirs", "them", "themselves", "then",
    "there", "there's", "these", "they", "they'd", "they'll", "they're", "they've",
    "this", "those", "through", "to", "too", "under", "until", "up", "very", "was",
    "wasn't", "we", "we'd", "we'll", "we're", "we've", "were", "weren't", "what",
    "what's", "when", "when's", "where", "where's", "which", "while", "who", "who's",
    "whom", "why", "why's", "with", "won't", "would", "wouldn't", "you", "you'd",
    "you'll", "you're", "you've", "your", "yours", "yourself", "yourselves",
}


def clean_url(url: str | None) -> str:
    """Standardizes a source URL, stripping tracking parameters and fragments."""
    if not url:
        return ""
    try:
        parsed = urlparse(url.strip())
        if not parsed.scheme or not parsed.netloc:
            return url.strip()

        # Filter out tracking query parameters
        filtered_queries = [
            (k, v)
            for k, v in parse_qsl(parsed.query, keep_blank_values=True)
            if k.lower() not in _STRIP_QUERY_PARAMS and not k.lower().startswith("utm_")
        ]
        new_query = urlencode(filtered_queries)

        # Standardize path: remove trailing slash unless it's the root
        path = parsed.path.rstrip("/") if len(parsed.path) > 1 else parsed.path

        # Reconstruct URL without fragments or tracking parameters
        clean = urlunparse((
            parsed.scheme.lower(),
            parsed.netloc.lower(),
            path,
            parsed.params,
            new_query,
            "",  # strip fragment
        ))
        return clean
    except Exception:
        return (url or "").strip()


def normalize_text(text: str | None) -> str:
    """Normalizes Unicode characters (NFKC), collapses whitespace, and lowercases text."""
    if not text:
        return ""
    normalized = unicodedata.normalize("NFKC", str(text))
    # Replace smart quotes, em-dashes, and typographical symbols with standard ASCII
    normalized = normalized.replace("’", "'").replace("‘", "'")
    normalized = normalized.replace("“", '"').replace("”", '"')
    normalized = normalized.replace("—", " - ").replace("–", " - ")
    # Squish multiple whitespace characters into a single space
    return " ".join(normalized.split()).casefold()


def extract_hashtags(text: str | None) -> list[str]:
    """Extracts hashtags from text, normalized to lowercase without the leading hash."""
    if not text:
        return []
    matches = re.findall(r"(?:^|\s)#([a-zA-Z0-9_\u00c0-\u024f]+)", text)
    seen = set()
    deduped = []
    for tag in matches:
        tag_norm = tag.casefold()
        if tag_norm not in seen:
            seen.add(tag_norm)
            deduped.append(tag_norm)
    return deduped


def extract_tokens(text: str | None, min_length: int = 2) -> list[str]:
    """Extracts alphanumeric word tokens, preserving meaningful acronyms and compound entities."""
    if not text:
        return []
    norm = normalize_text(text)
    # Extract word sequences (including numbers)
    raw_tokens = re.findall(r"\b[a-z0-9_\-]+\b", norm, flags=re.UNICODE)
    cleaned = []
    for token in raw_tokens:
        clean = token.strip("-_")
        if len(clean) >= min_length:
            cleaned.append(clean)
    return cleaned


def extract_content_tokens(text: str | None) -> list[str]:
    """Extracts content-bearing tokens, removing generic English stopwords while preserving order."""
    tokens = extract_tokens(text)
    seen = set()
    content_tokens = []
    for token in tokens:
        if token not in _GENERIC_STOP_WORDS and token not in seen:
            seen.add(token)
            content_tokens.append(token)
    return content_tokens


def normalize_trend_title(title: str | None) -> str:
    """Produces a clean canonical normalized title for deduplication and grouping."""
    if not title:
        return ""
    norm = normalize_text(title)
    # Remove leading/trailing non-alphanumeric punctuation
    words = re.findall(r"[\w]+", norm, flags=re.UNICODE)
    return " ".join(words)
