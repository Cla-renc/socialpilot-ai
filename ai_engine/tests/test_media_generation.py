"""
Phase 7 Media Generation Engine Unit Tests
"""
from __future__ import annotations

import unittest
from ai_engine.brand_visual_intelligence import BrandVisualIntelligenceService
from ai_engine.media_provider_manager import MediaProviderManager
from ai_engine.media_processing import MediaProcessingService
from ai_engine.video_storyboard import VideoStoryboardService
from ai_engine.media_composer import MediaComposerService


class BrandVisualIntelligenceTests(unittest.TestCase):
    def setUp(self):
        self.brand_payload = {
            "id": 1,
            "name": "Bruce Lee Discipline Diaries",
            "industry": "Fitness & Philosophy",
            "category": "personal_development",
            "niche": "Martial arts discipline and mental strength",
            "audience": "Dedicated practitioners and martial artists",
            "archetype": "the_warrior",
            "visual_settings": {
                "visual_identity": "dark_cinematic",
                "primary_color": "#0d1b2a",
                "secondary_color": "#1b263b",
                "accent_color": "#e0a96d",
                "primary_font": "Oswald",
                "secondary_font": "Roboto",
                "preferred_platforms": ["instagram", "youtube"],
                "preferred_aspect_ratios": ["9:16", "1:1"],
            },
        }

    def test_build_profile_respects_brand_identity(self):
        profile = BrandVisualIntelligenceService.build_visual_profile(self.brand_payload)
        self.assertEqual(profile["brand_name"], "Bruce Lee Discipline Diaries")
        self.assertEqual(profile["visual_identity"], "dark_cinematic")
        self.assertEqual(profile["primary_color"], "#0d1b2a")
        self.assertEqual(profile["accent_color"], "#e0a96d")
        self.assertIn("cinematic", profile["style_directives"].lower())

    def test_build_profile_provides_sensible_defaults_for_minimal_brand(self):
        minimal_payload = {"name": "Simple Brand"}
        profile = BrandVisualIntelligenceService.build_visual_profile(minimal_payload)
        self.assertEqual(profile["brand_name"], "Simple Brand")
        self.assertIn("visual_identity", profile)
        self.assertIn("primary_color", profile)
        self.assertIn("preferred_platforms", profile)

    def test_style_prompt_builder(self):
        profile = BrandVisualIntelligenceService.build_visual_profile(self.brand_payload)
        prompt = BrandVisualIntelligenceService.build_image_prompt(
            visual_profile=profile,
            objective="social_post",
            instructions="A focused martial artist practicing forms at dawn",
            platform="instagram",
        )
        self.assertIn("Bruce Lee Discipline Diaries", prompt)
        self.assertIn("cinematic", prompt.lower())


class MediaProviderManagerTests(unittest.TestCase):
    def setUp(self):
        self.manager = MediaProviderManager()

    def test_check_image_providers(self):
        for provider in ["openai", "stability", "gemini"]:
            status = self.manager.check_image_provider(provider)
            self.assertIsInstance(status, dict)
            self.assertEqual(status["provider"], provider)
            self.assertIn("status", status)

    def test_check_video_providers(self):
        for provider in ["runway", "stability_video", "pika"]:
            status = self.manager.check_video_provider(provider)
            self.assertIsInstance(status, dict)
            self.assertEqual(status["provider"], provider)
            self.assertIn("status", status)

    def test_unconfigured_provider_reports_configuration_required(self):
        status = self.manager.check_image_provider("stability")
        self.assertIn("status", status)
        self.assertIn(status["status"], ["ready", "configuration_required"])


class MediaProcessingServiceTests(unittest.TestCase):
    def setUp(self):
        self.service = MediaProcessingService()

    def test_aspect_ratio_to_dimensions(self):
        w, h = self.service.aspect_ratio_to_dimensions("1:1")
        self.assertEqual(w, 1080)
        self.assertEqual(h, 1080)

        w, h = self.service.aspect_ratio_to_dimensions("9:16")
        self.assertEqual(w, 1080)
        self.assertEqual(h, 1920)

        w, h = self.service.aspect_ratio_to_dimensions("16:9")
        self.assertEqual(w, 1920)
        self.assertEqual(h, 1080)

    def test_platform_recommendations(self):
        insta = self.service.get_recommended_formats("instagram")
        self.assertIsInstance(insta, list)
        self.assertTrue(any(f["aspect_ratio"] == "1:1" for f in insta))
        self.assertTrue(any(f["aspect_ratio"] == "9:16" for f in insta))


class VideoStoryboardTests(unittest.TestCase):
    def test_generate_storyboard_scenes(self):
        brand_payload = {"name": "Bruce Lee Discipline Diaries", "category": "martial_arts"}
        storyboard = VideoStoryboardService.generate_storyboard(
            brand_payload=brand_payload,
            video_idea="Demonstrate the power of consistent daily repetition",
            platform="instagram",
            aspect_ratio="9:16",
            num_scenes=4,
        )
        self.assertIn("scenes", storyboard)
        self.assertEqual(len(storyboard["scenes"]), 4)
        for scene in storyboard["scenes"]:
            self.assertIn("scene_number", scene)
            self.assertIn("description", scene)
            self.assertIn("duration_seconds", scene)
            self.assertIn("visual_suggestion", scene)


class MediaComposerTests(unittest.TestCase):
    def test_compose_quote_card_preserves_text_and_branding(self):
        res = MediaComposerService.compose_graphic(
            template="quote_card",
            brand_name="Bruce Lee Discipline Diaries",
            quote="Knowing is not enough, we must apply. Willing is not enough, we must do.",
            author="Bruce Lee",
            primary_color="#0d1b2a",
            accent_color="#e0a96d",
            aspect_ratio="1:1",
        )
        self.assertTrue(res["success"])
        self.assertEqual(res["template"], "quote_card")
        self.assertEqual(res["image_format"], "svg")
        self.assertEqual(res["width"], 1080)
        self.assertEqual(res["height"], 1080)
        self.assertIn("Knowing is not enough", res["raw_svg"])
        self.assertIn("Bruce Lee", res["raw_svg"])
        self.assertIn("#0d1b2a", res["raw_svg"])

    def test_compose_educational_graphic_with_bullets(self):
        res = MediaComposerService.compose_graphic(
            template="educational",
            brand_name="NairobiMart",
            headline="3 Tips for Quality Shopping",
            bullets=["Inspect freshness", "Compare origin labels", "Buy in bulk"],
            aspect_ratio="16:9",
        )
        self.assertTrue(res["success"])
        self.assertEqual(res["aspect_ratio"], "16:9")
        self.assertIn("Inspect freshness", res["raw_svg"])
        self.assertIn("NairobiMart", res["raw_svg"])

    def test_compose_motivational_poster_with_cta(self):
        res = MediaComposerService.compose_graphic(
            template="motivational_poster",
            brand_name="Apex Habit",
            headline="Standards Over Feelings",
            call_to_action="Start Your Journey",
            aspect_ratio="9:16",
        )
        self.assertTrue(res["success"])
        self.assertEqual(res["height"], 1920)
        self.assertIn("Standards Over Feelings", res["raw_svg"])
        self.assertIn("Start Your Journey", res["raw_svg"])


if __name__ == "__main__":
    unittest.main()
