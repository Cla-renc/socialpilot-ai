from __future__ import annotations

from typing import Any


class VideoStoryboardService:
    """Generates structured video storyboards from brand and content data."""

    # Scene templates per visual identity
    _SCENE_TEMPLATES: dict[str, list[dict[str, str]]] = {
        "dark_cinematic": [
            {"description": "Opening cinematic shot — dark, dramatic atmosphere establishing the brand world.", "transition": "fade_in"},
            {"description": "Close-up of brand symbol or key visual element emerging from shadow.", "transition": "cut"},
            {"description": "Action montage — high-intensity movement or training sequence.", "transition": "fast_cut"},
            {"description": "Quote or key message on-screen with dramatic background.", "transition": "dissolve"},
            {"description": "Product/service feature reveal in cinematic light.", "transition": "cut"},
            {"description": "Closing brand statement with logo reveal over dark gradient.", "transition": "fade_out"},
        ],
        "commercial": [
            {"description": "Lifestyle opening — people enjoying the product in real settings.", "transition": "fade_in"},
            {"description": "Product hero shot — clean, well-lit, inviting display.", "transition": "cut"},
            {"description": "Customer benefit demonstration with friendly energy.", "transition": "dissolve"},
            {"description": "Social proof moment — happy customers or testimonial context.", "transition": "cut"},
            {"description": "Product variety or range showcase.", "transition": "cut"},
            {"description": "Call-to-action ending with brand logo and tagline.", "transition": "fade_out"},
        ],
        "product_ad": [
            {"description": "Studio product reveal — isolated subject on clean background.", "transition": "fade_in"},
            {"description": "360-degree product rotation or key feature close-up.", "transition": "cut"},
            {"description": "In-use demonstration showing product utility.", "transition": "dissolve"},
            {"description": "Key differentiator highlighted with on-screen text.", "transition": "cut"},
            {"description": "Side-by-side comparison or packaging reveal.", "transition": "cut"},
            {"description": "Brand close — logo, website, and purchase call-to-action.", "transition": "fade_out"},
        ],
        "educational": [
            {"description": "Problem statement — clear visual representation of the challenge being solved.", "transition": "fade_in"},
            {"description": "Step 1 introduction — first concept explained with supporting visual.", "transition": "cut"},
            {"description": "Step 2 — deeper explanation with example or demonstration.", "transition": "dissolve"},
            {"description": "Step 3 — practical application or key takeaway.", "transition": "cut"},
            {"description": "Summary recap — bullet points of what was covered.", "transition": "dissolve"},
            {"description": "Call-to-action — next step for the viewer to take.", "transition": "fade_out"},
        ],
        "inspirational": [
            {"description": "Emotional opening — evocative imagery that resonates with the audience's aspirations.", "transition": "fade_in"},
            {"description": "The struggle — authentic representation of challenge or starting point.", "transition": "dissolve"},
            {"description": "Turning point — moment of insight, change, or decision.", "transition": "cut"},
            {"description": "Journey forward — progress, transformation, and growth.", "transition": "dissolve"},
            {"description": "Arrival — the inspiring outcome or achievement.", "transition": "cut"},
            {"description": "Brand message — uplifting tagline with brand identity.", "transition": "fade_out"},
        ],
        "corporate": [
            {"description": "Professional establishing shot — office, team, or technology environment.", "transition": "fade_in"},
            {"description": "Problem context — industry challenge this brand addresses.", "transition": "cut"},
            {"description": "Solution overview — brand's approach and methodology.", "transition": "dissolve"},
            {"description": "Proof points — data, results, or case study reference.", "transition": "cut"},
            {"description": "Team or technology feature — building trust and authority.", "transition": "dissolve"},
            {"description": "Partnership invitation — CTA for prospects or stakeholders.", "transition": "fade_out"},
        ],
        "minimalist": [
            {"description": "Clean opening — single powerful visual with minimal elements.", "transition": "fade_in"},
            {"description": "Core message — one idea, stated clearly against white space.", "transition": "cut"},
            {"description": "Visual proof — elegant demonstration of the concept.", "transition": "dissolve"},
            {"description": "Secondary benefit — brief, clean visual communication.", "transition": "cut"},
            {"description": "Brand identity — logo on clean background.", "transition": "dissolve"},
            {"description": "CTA — simple, direct next step.", "transition": "fade_out"},
        ],
    }

    _MUSIC_SUGGESTIONS: dict[str, str] = {
        "dark_cinematic": "Epic orchestral with driving percussion; Hans Zimmer-inspired dark ambient",
        "commercial": "Upbeat acoustic pop with positive energy; friendly and memorable",
        "product_ad": "Modern electronic with clean beats; professional and sleek",
        "educational": "Light background instrumental; piano or guitar, non-distracting",
        "inspirational": "Emotional piano or strings building to uplifting crescendo",
        "corporate": "Professional corporate ambient; subtle and trust-building",
        "minimalist": "Minimal ambient electronic; clean and focused",
    }

    _DURATIONS_BY_PLATFORM: dict[str, int] = {
        "instagram_reel": 30,
        "instagram_story": 15,
        "tiktok": 30,
        "tiktok_video": 30,
        "youtube_shorts": 60,
        "youtube": 90,
        "facebook": 30,
        "linkedin": 60,
        "twitter": 30,
    }

    @classmethod
    def _get_content_summary(cls, draft: dict[str, Any] | None, video_idea: str | None) -> str:
        """Extract content summary from draft or video_idea."""
        if draft:
            parts = []
            for key in ["title", "hook", "body", "caption"]:
                val = draft.get(key)
                if val and str(val).strip():
                    parts.append(str(val).strip())
            if parts:
                return " | ".join(parts[:3])
        if video_idea:
            return video_idea
        return "Brand story and value proposition"

    @classmethod
    def generate_storyboard(
        cls,
        brand_payload: dict[str, Any],
        visual_profile: dict[str, Any] | None = None,
        draft: dict[str, Any] | None = None,
        video_idea: str | None = None,
        platform: str = "instagram",
        aspect_ratio: str = "9:16",
        num_scenes: int = 6,
    ) -> dict[str, Any]:
        """Generate a structured video storyboard."""
        if visual_profile is None:
            visual_profile = {}
        brand_name = visual_profile.get("brand_name") or str(brand_payload.get("name") or "Brand")
        visual_identity = visual_profile.get("visual_identity", "minimalist")

        content_summary = cls._get_content_summary(draft, video_idea)

        # Title derivation
        title: str
        if draft and draft.get("title"):
            title = str(draft["title"]).strip()
        elif video_idea:
            # Truncate video idea to reasonable title length
            title = video_idea[:80].strip()
        else:
            title = f"{brand_name} — Brand Video"

        # Concept
        concept = (
            f"A {visual_identity.replace('_', ' ')} video for {brand_name} targeting "
            f"{brand_payload.get('audience', 'their audience')} on {platform}. "
            f"Content focus: {content_summary[:200]}."
        )

        # Estimated duration
        estimated_duration = cls._DURATIONS_BY_PLATFORM.get(platform.lower(), 30)

        # Music suggestion
        music_suggestion = cls._MUSIC_SUGGESTIONS.get(visual_identity, "Background music matching brand tone")

        # Scene templates
        templates = cls._SCENE_TEMPLATES.get(visual_identity, cls._SCENE_TEMPLATES["minimalist"])

        # Build scenes
        scenes: list[dict[str, Any]] = []
        num_scenes = max(2, min(num_scenes, 12))  # clamp between 2 and 12
        per_scene_duration = max(3, estimated_duration // num_scenes)

        # Extract content pieces for narration
        narration_pieces: list[str] = []
        if draft:
            for key in ["hook", "title", "body", "caption", "call_to_action"]:
                val = draft.get(key)
                if val and str(val).strip():
                    narration_pieces.append(str(val).strip())
        if video_idea and not narration_pieces:
            narration_pieces = [video_idea]
        if not narration_pieces:
            narration_pieces = [f"{brand_name} — delivering value to {brand_payload.get('audience', 'you')}."]

        for i in range(num_scenes):
            # Cycle through templates if fewer than requested scenes
            template = templates[i % len(templates)]

            # Assign narration piece
            narration_index = int(i / num_scenes * len(narration_pieces))
            narration_text = narration_pieces[min(narration_index, len(narration_pieces) - 1)]
            # Truncate for per-scene
            if len(narration_text) > 200:
                narration_text = narration_text[:197] + "..."

            # On-screen text — shorter, punchy
            on_screen = narration_text[:60].strip()
            if len(narration_pieces[min(narration_index, len(narration_pieces) - 1)]) > 60:
                on_screen = on_screen.rsplit(" ", 1)[0] + "..."

            # Visual suggestion from template + brand style
            visual_suggestion = (
                f"{template['description']} "
                f"Brand visual style: {visual_profile.get('style_directives', '')[:100]}."
            )

            scenes.append({
                "scene_number": i + 1,
                "description": template["description"],
                "visual_suggestion": visual_suggestion,
                "narration_text": narration_text,
                "on_screen_text": on_screen,
                "duration_seconds": per_scene_duration,
                "transition": template["transition"],
            })

        return {
            "title": title,
            "concept": concept,
            "platform": platform,
            "aspect_ratio": aspect_ratio,
            "estimated_duration": estimated_duration,
            "background_music_suggestion": music_suggestion,
            "scenes": scenes,
        }
