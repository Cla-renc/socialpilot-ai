from __future__ import annotations

import base64
import html
import re
from typing import Any

from .media_processing import MediaProcessingService


class MediaComposerService:
    """Composes branded posters, quote cards, and informational graphics as vector SVG images."""

    _media_processing = MediaProcessingService()

    @staticmethod
    def _wrap_text(text: str, max_chars_per_line: int) -> list[str]:
        """Wrap text into lines without breaking words."""
        words = text.split()
        if not words:
            return []

        lines: list[str] = []
        current_line = []
        current_len = 0

        for word in words:
            if current_len + len(word) + (1 if current_line else 0) <= max_chars_per_line:
                current_line.append(word)
                current_len += len(word) + (1 if len(current_line) > 1 else 0)
            else:
                if current_line:
                    lines.append(" ".join(current_line))
                current_line = [word]
                current_len = len(word)

        if current_line:
            lines.append(" ".join(current_line))

        return lines

    @classmethod
    def compose_graphic(
        cls,
        template: str,
        brand_name: str,
        headline: str | None = None,
        quote: str | None = None,
        author: str | None = None,
        caption: str | None = None,
        call_to_action: str | None = None,
        bullets: list[str] | None = None,
        primary_color: str = "#0d1b2a",
        secondary_color: str = "#1b263b",
        accent_color: str = "#e0a96d",
        background_color: str = "#071326",
        text_color: str = "#ffffff",
        primary_font: str = "Oswald, sans-serif",
        secondary_font: str = "Roboto, sans-serif",
        logo_url: str | None = None,
        logo_placement: str = "bottom_right",
        aspect_ratio: str = "1:1",
        background_image_url: str | None = None,
    ) -> dict[str, Any]:
        """Compose a high-resolution, branded graphic using SVG."""
        width, height = cls._media_processing.aspect_ratio_to_dimensions(aspect_ratio, base_size=1080)

        # Sanitize text
        esc_brand = html.escape(brand_name)
        esc_headline = html.escape(headline or "")
        esc_quote = html.escape(quote or "")
        esc_author = html.escape(author or "")
        esc_caption = html.escape(caption or "")
        esc_cta = html.escape(call_to_action or "")

        # Logo coordinates
        logo_x = width - 180 if "right" in logo_placement else 60
        logo_y = height - 120 if "bottom" in logo_placement else 60
        if "center" in logo_placement:
            logo_x = width // 2 - 60

        svg_elements: list[str] = []

        # Background definition
        svg_elements.append(
            f'<defs>'
            f'<linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">'
            f'<stop offset="0%" stop-color="{primary_color}" />'
            f'<stop offset="60%" stop-color="{secondary_color}" />'
            f'<stop offset="100%" stop-color="{background_color}" />'
            f'</linearGradient>'
            f'<linearGradient id="accentGrad" x1="0%" y1="0%" x2="100%" y2="0%">'
            f'<stop offset="0%" stop-color="{accent_color}" />'
            f'<stop offset="100%" stop-color="{primary_color}" />'
            f'</linearGradient>'
            f'</defs>'
        )

        # Background fill
        svg_elements.append(f'<rect width="{width}" height="{height}" fill="url(#bgGrad)" />')

        # Background image overlay if provided
        if background_image_url:
            svg_elements.append(
                f'<image href="{html.escape(background_image_url)}" width="{width}" height="{height}" '
                f'preserveAspectRatio="xMidYMid slice" opacity="0.4" />'
            )

        # Decorative geometric border or accent lines
        svg_elements.append(
            f'<rect x="30" y="30" width="{width - 60}" height="{height - 60}" '
            f'fill="none" stroke="{accent_color}" stroke-width="2" opacity="0.3" rx="16" />'
        )

        # Brand header tag
        svg_elements.append(
            f'<text x="60" y="80" fill="{accent_color}" font-family="{secondary_font}" '
            f'font-size="20" font-weight="700" letter-spacing="3" text-transform="uppercase">'
            f'{esc_brand}'
            f'</text>'
        )

        if template == "quote_card":
            # Stylized quotation mark
            svg_elements.append(
                f'<text x="60" y="240" fill="{accent_color}" font-family="{primary_font}" '
                f'font-size="120" opacity="0.4">“</text>'
            )

            # Wrapped quote body
            quote_text = quote or headline or "Discipline is the bridge between goals and accomplishment."
            lines = cls._wrap_text(quote_text, max_chars_per_line=30)
            start_y = 280
            line_height = 56

            for i, line in enumerate(lines):
                esc_line = html.escape(line)
                svg_elements.append(
                    f'<text x="80" y="{start_y + (i * line_height)}" fill="{text_color}" '
                    f'font-family="{primary_font}" font-size="44" font-weight="600">'
                    f'{esc_line}'
                    f'</text>'
                )

            author_y = start_y + (len(lines) * line_height) + 40
            if esc_author:
                svg_elements.append(
                    f'<line x1="80" y1="{author_y - 20}" x2="160" y2="{author_y - 20}" '
                    f'stroke="{accent_color}" stroke-width="4" />'
                )
                svg_elements.append(
                    f'<text x="80" y="{author_y + 10}" fill="{accent_color}" '
                    f'font-family="{secondary_font}" font-size="28" font-weight="700">'
                    f'— {esc_author}'
                    f'</text>'
                )

        elif template == "educational" or bullets:
            # Informational / Infographic layout
            svg_elements.append(
                f'<text x="60" y="160" fill="{text_color}" font-family="{primary_font}" '
                f'font-size="48" font-weight="700">'
                f'{esc_headline or "Key Takeaways"}'
                f'</text>'
            )
            svg_elements.append(
                f'<line x1="60" y1="185" x2="220" y2="185" stroke="{accent_color}" stroke-width="4" />'
            )

            items = bullets or [
                "1. Establish non-negotiable daily baselines.",
                "2. Eliminate friction in high-priority workflows.",
                "3. Measure execution consistently without emotional bias.",
            ]
            bullet_y = 260
            for idx, item in enumerate(items[:5]):
                esc_item = html.escape(item)
                svg_elements.append(
                    f'<rect x="60" y="{bullet_y - 28}" width="36" height="36" rx="8" fill="{accent_color}" opacity="0.9" />'
                    f'<text x="73" y="{bullet_y - 5}" fill="{primary_color}" font-family="{secondary_font}" font-size="20" font-weight="900">{idx + 1}</text>'
                    f'<text x="116" y="{bullet_y - 4}" fill="{text_color}" font-family="{secondary_font}" font-size="30" font-weight="500">{esc_item}</text>'
                )
                bullet_y += 85

        else:
            # Default: Promotional Poster / Motivational Poster
            head_text = headline or quote or "Master Your Craft"
            lines = cls._wrap_text(head_text, max_chars_per_line=24)
            start_y = height // 2 - (len(lines) * 30)

            for i, line in enumerate(lines):
                esc_line = html.escape(line)
                svg_elements.append(
                    f'<text x="{width // 2}" y="{start_y + (i * 64)}" fill="{text_color}" '
                    f'font-family="{primary_font}" font-size="56" font-weight="800" text-anchor="middle">'
                    f'{esc_line}'
                    f'</text>'
                )

            if esc_caption:
                svg_elements.append(
                    f'<text x="{width // 2}" y="{start_y + (len(lines) * 64) + 30}" fill="{accent_color}" '
                    f'font-family="{secondary_font}" font-size="26" font-weight="600" text-anchor="middle">'
                    f'{esc_caption}'
                    f'</text>'
                )

        # Call to Action Pill
        if esc_cta:
            cta_y = height - 100
            cta_w = len(esc_cta) * 14 + 60
            cta_x = width // 2 - cta_w // 2
            svg_elements.append(
                f'<rect x="{cta_x}" y="{cta_y - 36}" width="{cta_w}" height="52" rx="26" fill="{accent_color}" />'
                f'<text x="{width // 2}" y="{cta_y}" fill="{primary_color}" font-family="{secondary_font}" '
                f'font-size="22" font-weight="700" text-anchor="middle">{esc_cta}</text>'
            )

        # Brand Logo if provided
        if logo_url:
            svg_elements.append(
                f'<image href="{html.escape(logo_url)}" x="{logo_x}" y="{logo_y}" width="80" height="80" '
                f'preserveAspectRatio="xMidYMid meet" />'
            )

        svg_content = (
            f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {height}" '
            f'width="{width}" height="{height}">\n'
            + "\n".join(svg_elements)
            + "\n</svg>"
        )

        raw_bytes = svg_content.encode("utf-8")
        base64_str = base64.b64encode(raw_bytes).decode("ascii")

        return {
            "success": True,
            "provider": "internal_composer",
            "model": "svg-vector-engine-2.0",
            "template": template,
            "image_url": None,
            "image_base64": base64_str,
            "image_format": "svg",
            "mime_type": "image/svg+xml",
            "width": width,
            "height": height,
            "aspect_ratio": aspect_ratio,
            "prompt_used": f"Composed {template} for {brand_name}: {headline or quote or 'Branded Graphic'}",
            "raw_svg": svg_content,
        }
