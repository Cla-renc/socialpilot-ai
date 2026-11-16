from __future__ import annotations

import unittest
from unittest.mock import patch

from ai_engine.brand_context import BrandContextService, BrandProfile
from ai_engine.content_strategy import ContentStrategyService, ContentIdeaGenerator
from ai_engine.platform_adaptation import PlatformAdaptationService
from ai_engine.component_generators import HookGenerator, CaptionGenerator, CallToActionGenerator, HashtagGenerator
from ai_engine.content_quality import ContentQualityEvaluator
from ai_engine.content_variation import ContentVariationGenerator
from ai_engine.content_improvement import ContentImprovementService
from ai_engine.ai_providers import AIProviderManager
from ai_engine.content_service import ContentGenerationService


class ContentGenerationEngineTests(unittest.IsolatedAsyncioTestCase):
    def setUp(self):
        self.brand_payload = {
            "id": 1,
            "name": "Apex Discipline",
            "industry": "Fitness & Personal Development",
            "niche": "Mindset and Habit Systems",
            "audience": "High-performing professionals and founders",
            "audience_interests": ["stoicism", "daily routines", "deep work"],
            "region": "US",
            "language": "en",
            "tone": "direct and uncompromising",
            "settings": {
                "brand_voice": "accountability-driven",
                "writing_style": "short, punchy, zero fluff",
                "brand_keywords": ["standards", "discipline", "execution"],
                "content_formats": ["thought_leadership", "educational"],
                "marketing_objectives": ["brand_authority", "engagement"],
                "restrictions": ["get rich quick", "crypto hype"],
                "hashtag_preferences": ["#Discipline", "#Mindset"],
            },
        }

    def test_brand_context_parses_profile_and_determines_archetype(self):
        profile = BrandContextService.parse(self.brand_payload)
        self.assertEqual(profile.name, "Apex Discipline")
        self.assertEqual(profile.archetype, "discipline_coach")
        self.assertIn("standards", profile.keywords)
        self.assertIn("get rich quick", profile.topics_to_avoid)
        self.assertEqual(len(profile.missing_fields), 0)

        directives = BrandContextService.get_style_directives(profile)
        self.assertIn("discipline and high-performance brand", directives)

    def test_brand_context_handles_missing_fields_gracefully(self):
        minimal_payload = {"name": "Simple Brand"}
        profile = BrandContextService.parse(minimal_payload)
        self.assertEqual(profile.name, "Simple Brand")
        self.assertIn("industry", profile.missing_fields)
        self.assertIn("audience", profile.missing_fields)
        self.assertEqual(profile.industry, "General Business")

    def test_content_strategy_service_with_and_without_trend(self):
        profile = BrandContextService.parse(self.brand_payload)

        # Without trend (evergreen)
        strategy = ContentStrategyService.develop_strategy(profile, objective="brand_authority", platform="linkedin")
        self.assertEqual(strategy.objective, "brand_authority")
        self.assertEqual(len(strategy.trend_references), 0)
        self.assertIn("Apex Discipline", strategy.recommended_angle)

        # With trend
        trend_data = {
            "id": 10,
            "title": "New Workplace Focus Habits in 2026",
            "description": "Studies reveal shorter micro-breaks yield higher afternoon retention.",
            "momentum": "growing",
            "relevance_score": 88,
            "sources": [{"source_name": "BBC Tech", "url": "https://example.com/habits"}],
        }
        trend_strategy = ContentStrategyService.develop_strategy(profile, trend=trend_data, platform="linkedin")
        self.assertEqual(len(trend_strategy.trend_references), 1)
        self.assertEqual(trend_strategy.trend_references[0]["momentum"], "growing")
        self.assertIn("New Workplace Focus Habits in 2026", trend_strategy.topic)

    def test_content_idea_generator(self):
        profile = BrandContextService.parse(self.brand_payload)
        ideas = ContentIdeaGenerator.generate_ideas(profile, count=4)
        self.assertEqual(len(ideas), 4)
        self.assertTrue(all("title" in idea and "angle" in idea for idea in ideas))

    def test_platform_adaptation_for_all_supported_platforms(self):
        profile = BrandContextService.parse(self.brand_payload)
        title = "The Power of Standards"
        hook = "Why standards matter more than goals:"
        body = "Here are the 3 principles of execution."
        caption = "Standards over feelings."
        cta = "Drop your thoughts below."
        tags = ["#Discipline", "#Habits", "#Leadership"]

        platforms = ["linkedin", "instagram", "tiktok", "youtube", "facebook"]
        for p in platforms:
            adapted = PlatformAdaptationService.format_content_for_platform(
                platform=p, title=title, hook=hook, body=body, caption=caption, call_to_action=cta, hashtags=tags
            )
            self.assertIn("title", adapted)
            self.assertIn("body", adapted)
            self.assertIn("hashtags", adapted)
            if p == "tiktok":
                self.assertIn("[HOOK", adapted["body"])
            elif p == "youtube":
                self.assertIn("KEY TAKEAWAYS", adapted["body"])
            elif p == "instagram":
                self.assertIn(hook, adapted["body"])

    def test_component_generators(self):
        profile = BrandContextService.parse(self.brand_payload)

        hook = HookGenerator.generate("morning routines", "linkedin", profile)
        self.assertTrue(len(hook) > 10)

        cta = CallToActionGenerator.generate("engagement", "linkedin", profile)
        self.assertIn("thoughts", cta.lower())

        caption = CaptionGenerator.generate("Test Title", hook, "linkedin", profile)
        self.assertIn(hook, caption)

        tags = HashtagGenerator.generate(profile, "morning routines", "linkedin", count=4)
        self.assertLessEqual(len(tags), 4)
        self.assertTrue(all(t.startswith("#") for t in tags))

    def test_content_quality_evaluator_scoring_and_issue_detection(self):
        profile = BrandContextService.parse(self.brand_payload)

        # High quality content
        title = "Apex Discipline: Master Your Daily Standards"
        hook = "Standards over feelings: why execution separates the top 1% from the rest."
        body = (
            "In fitness and performance, motivation is unreliable.\n\n"
            "At Apex Discipline, we measure progress strictly through non-negotiable daily habits.\n\n"
            "1. Define the baseline standard.\n"
            "2. Audit friction daily.\n"
            "3. Execute without emotion."
        )
        caption = f"{hook} Commit to the baseline."
        cta = "What non-negotiable standard are you upholding today?"
        hashtags = ["#Discipline", "#Mindset", "#Standards"]

        result = ContentQualityEvaluator.evaluate(
            title=title, hook=hook, body=body, caption=caption, call_to_action=cta, hashtags=hashtags,
            platform="linkedin", brand=profile
        )

        self.assertGreaterEqual(result["quality_score"], 80)
        self.assertIn("disclaimer", result)
        self.assertEqual(len(result["issues"]), 0)

        # Low quality content with generic phrase and forbidden topic
        bad_body = "In today's fast-paced world, you can get rich quick by unlocking your potential."
        bad_result = ContentQualityEvaluator.evaluate(
            title="Bad Post", hook="bad hook", body=bad_body, caption="", call_to_action="",
            hashtags=[], platform="tiktok", brand=profile
        )

        self.assertLess(bad_result["quality_score"], 75)
        self.assertGreater(result["quality_score"], bad_result["quality_score"])
        self.assertTrue(any("restricted topic" in issue for issue in bad_result["issues"]))
        self.assertTrue(any("Missing or empty call to action" in issue for issue in bad_result["issues"]))

    def test_content_variation_generator_creates_meaningfully_different_versions(self):
        profile = BrandContextService.parse(self.brand_payload)
        variations = ContentVariationGenerator.generate_variations(
            base_title="Focus on Execution",
            base_body="Execution is key.",
            topic="Daily Habit Systems",
            platform="linkedin",
            brand=profile,
            count=3,
        )

        self.assertEqual(len(variations), 3)
        styles = [v["style"] for v in variations]
        self.assertEqual(styles, ["professional", "conversational", "bold"])
        # Ensure variations have distinct hooks and bodies
        self.assertNotEqual(variations[0]["hook"], variations[1]["hook"])
        self.assertNotEqual(variations[0]["body"], variations[1]["body"])
        self.assertTrue(all(v["quality_score"] > 60 for v in variations))

    def test_content_improvement_service_operations(self):
        profile = BrandContextService.parse(self.brand_payload)
        base_draft = {
            "title": "Initial Draft",
            "hook": "An initial hook line.",
            "body": "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.",
            "caption": "Initial caption.",
            "call_to_action": "Initial CTA.",
            "hashtags": ["#Tag1"],
            "platform": "linkedin",
            "topic": "standards",
        }

        # Shorten
        shortened = ContentImprovementService.improve(base_draft, "shorten", profile)
        self.assertIn("Shortened post", shortened["action_summary"])

        # Expand
        expanded = ContentImprovementService.improve(base_draft, "expand", profile)
        self.assertIn("Expanded with concrete", expanded["action_summary"])
        self.assertGreater(len(expanded["body"]), len(base_draft["body"]))

        # Improve hook
        hook_revised = ContentImprovementService.improve(base_draft, "improve_hook", profile)
        self.assertNotEqual(hook_revised["hook"], base_draft["hook"])

        # Adapt platform to instagram
        adapted = ContentImprovementService.improve(base_draft, "adapt_platform", profile, target_platform="instagram")
        self.assertEqual(adapted["platform"], "instagram")

    async def test_content_generation_service_end_to_end(self):
        service = ContentGenerationService()
        result = await service.generate(
            brand_payload=self.brand_payload,
            objective="education",
            category="educational",
            platform="linkedin",
            content_format="post",
            include_variations=True,
            variation_count=2,
        )

        self.assertIn("title", result)
        self.assertIn("hook", result)
        self.assertIn("body", result)
        self.assertIn("caption", result)
        self.assertIn("call_to_action", result)
        self.assertIn("hashtags", result)
        self.assertGreaterEqual(result["quality_score"], 70)
        self.assertEqual(len(result["variations"]), 2)
        self.assertEqual(result["ai_provider"], "internal")

    def test_ai_provider_manager_availability_and_failure(self):
        with patch.dict("os.environ", {"GROQ_API_KEY": "test-groq-key"}, clear=False):
            groq_mgr = AIProviderManager()
            groq_status = groq_mgr.check_availability("groq")
            self.assertTrue(groq_status["available"])
            self.assertEqual(groq_status["model"], "openai/gpt-oss-120b")

        mgr = AIProviderManager()
        internal_status = mgr.check_availability("internal")
        self.assertTrue(internal_status["available"])

        with patch.dict("os.environ", {"OPENAI_API_KEY": ""}):
            openai_status = mgr.check_availability("openai")
            self.assertFalse(openai_status["available"])
            self.assertEqual(openai_status["status"], "configuration_required")


class ContentApiEndpointTests(unittest.TestCase):
    def setUp(self):
        from fastapi.testclient import TestClient
        from ai_engine.main import app
        self.client = TestClient(app)
        self.brand_payload = {
            "id": 1,
            "name": "Apex Discipline",
            "industry": "Fitness",
            "niche": "Mindset",
            "audience": "Founders",
        }

    def tearDown(self):
        if hasattr(self, 'client') and self.client:
            self.client.close()

    def test_internal_content_endpoints_enforce_token(self):
        with patch.dict("os.environ", {"TREND_ENGINE_INTERNAL_TOKEN": "secret-token"}):
            res = self.client.post("/internal/v1/content/generate", json={"brand": self.brand_payload})
            self.assertEqual(res.status_code, 401)

            res_valid = self.client.post(
                "/internal/v1/content/strategy",
                json={"brand": self.brand_payload, "objective": "awareness"},
                headers={"Authorization": "Bearer secret-token"},
            )
            self.assertEqual(res_valid.status_code, 200)
            self.assertIn("topic", res_valid.json())

    def test_internal_generate_and_variations_endpoints(self):
        with patch.dict("os.environ", {"TREND_ENGINE_INTERNAL_TOKEN": "secret-token"}):
            gen_res = self.client.post(
                "/internal/v1/content/generate",
                json={
                    "brand": self.brand_payload,
                    "platform": "linkedin",
                    "include_variations": True,
                    "variation_count": 2,
                },
                headers={"Authorization": "Bearer secret-token"},
            )
            self.assertEqual(gen_res.status_code, 200)
            data = gen_res.json()
            self.assertIn("title", data)
            self.assertIn("body", data)
            self.assertEqual(len(data["variations"]), 2)

            var_res = self.client.post(
                "/internal/v1/content/variations",
                json={
                    "brand": self.brand_payload,
                    "base_title": "Test Title",
                    "base_body": "Test Body",
                    "topic": "execution",
                    "platform": "linkedin",
                    "count": 2,
                },
                headers={"Authorization": "Bearer secret-token"},
            )
            self.assertEqual(var_res.status_code, 200)
            self.assertEqual(len(var_res.json()["variations"]), 2)

    def test_internal_improve_and_evaluate_endpoints(self):
        with patch.dict("os.environ", {"TREND_ENGINE_INTERNAL_TOKEN": "secret-token"}):
            imp_res = self.client.post(
                "/internal/v1/content/improve",
                json={
                    "brand": self.brand_payload,
                    "draft": {
                        "title": "Old Title",
                        "hook": "Old Hook",
                        "body": "Paragraph 1.\n\nParagraph 2.\n\nParagraph 3.",
                        "caption": "Old Caption",
                        "call_to_action": "Old CTA",
                        "platform": "linkedin",
                    },
                    "operation": "shorten",
                },
                headers={"Authorization": "Bearer secret-token"},
            )
            self.assertEqual(imp_res.status_code, 200)
            self.assertIn("action_summary", imp_res.json())

            eval_res = self.client.post(
                "/internal/v1/content/evaluate",
                json={
                    "brand": self.brand_payload,
                    "title": "Good Title",
                    "hook": "Sharp hook line:",
                    "body": "Useful body paragraph.\n\nAnother clear point.",
                    "caption": "Caption",
                    "call_to_action": "Comment below.",
                    "hashtags": ["#Fitness"],
                    "platform": "linkedin",
                },
                headers={"Authorization": "Bearer secret-token"},
            )
            self.assertEqual(eval_res.status_code, 200)
            self.assertIn("quality_score", eval_res.json())


if __name__ == "__main__":
    unittest.main()
