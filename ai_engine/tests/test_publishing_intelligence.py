"""Tests for Phase 9 Publishing Intelligence modules"""
import sys, os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from ai_engine.publishing_intelligence import PublishingIntelligence
from ai_engine.publishing_error_classifier import PublishingErrorClassifier
from ai_engine.platform_compatibility import PlatformCompatibility
from ai_engine.publishing_content_validator import PublishingContentValidator
from ai_engine.publishing_optimization import PublishingOptimizer


def test_intelligence_instagram_suitable():
    pi = PublishingIntelligence()
    result = pi.analyze_content_suitability("instagram", {"text": "Hello #world #test", "media_urls": ["http://example.com/img.jpg"]})
    assert result["platform"] == "instagram"
    assert result["score"] >= 0
    assert "grade" in result
    print("✓ test_intelligence_instagram_suitable")

def test_intelligence_youtube_requires_video():
    pi = PublishingIntelligence()
    result = pi.analyze_content_suitability("youtube", {"text": "My video", "title": "My Title"})
    # No media_urls → score penalized
    assert result["score"] < 100
    print("✓ test_intelligence_youtube_requires_video")

def test_intelligence_text_too_long():
    pi = PublishingIntelligence()
    long_text = "x" * 3001
    result = pi.analyze_content_suitability("linkedin", {"text": long_text})
    assert any("exceeds" in w for w in result["warnings"])
    print("✓ test_intelligence_text_too_long")

def test_error_classifier_known_code():
    ec = PublishingErrorClassifier()
    result = ec.classify_error("190", "Token expired", "facebook")
    assert result["category"] == "auth"
    assert result["is_retryable"] is False
    print("✓ test_error_classifier_known_code")

def test_error_classifier_rate_limit_heuristic():
    ec = PublishingErrorClassifier()
    result = ec.classify_error("999", "rate limit exceeded", "instagram")
    assert result["category"] == "rate_limit"
    assert result["is_retryable"] is True
    print("✓ test_error_classifier_rate_limit_heuristic")

def test_error_classifier_unknown():
    ec = PublishingErrorClassifier()
    result = ec.classify_error("xyz", "some weird error", "tiktok")
    assert result["category"] == "unknown"
    print("✓ test_error_classifier_unknown")

def test_compatibility_tiktok_image_unsupported():
    pc = PlatformCompatibility()
    result = pc.check_compatibility("tiktok", "image")
    assert result["compatible"] is False
    assert len(result["errors"]) > 0
    print("✓ test_compatibility_tiktok_image_unsupported")

def test_compatibility_facebook_video_ok():
    pc = PlatformCompatibility()
    result = pc.check_compatibility("facebook", "video", "mp4", 100)
    assert result["compatible"] is True
    print("✓ test_compatibility_facebook_video_ok")

def test_compatibility_file_too_large():
    pc = PlatformCompatibility()
    result = pc.check_compatibility("instagram", "video", "mp4", 200)
    assert result["compatible"] is False
    print("✓ test_compatibility_file_too_large")

def test_validator_instagram_no_media():
    cv = PublishingContentValidator()
    result = cv.validate("instagram", {"text": "Hello"})
    assert result["valid"] is False
    assert any("image or video" in e for e in result["errors"])
    print("✓ test_validator_instagram_no_media")

def test_validator_linkedin_text_ok():
    cv = PublishingContentValidator()
    result = cv.validate("linkedin", {"text": "Professional post content here"})
    assert result["valid"] is True
    print("✓ test_validator_linkedin_text_ok")

def test_optimizer_returns_score():
    po = PublishingOptimizer()
    result = po.optimize("facebook", {"text": "Hello world, check this out!"})
    assert "content_score" in result
    assert "optimal_posting_time" in result
    print("✓ test_optimizer_returns_score")

def test_optimizer_compare_platforms():
    po = PublishingOptimizer()
    results = po.compare_platforms(["facebook", "linkedin"], {"text": "Great professional update"})
    assert len(results) == 2
    assert results[0]["content_score"] >= results[1]["content_score"]
    print("✓ test_optimizer_compare_platforms")

def test_multi_platform_validate():
    cv = PublishingContentValidator()
    results = cv.validate_multi_platform(["facebook", "instagram"], {"text": "Hello", "media_urls": ["http://example.com/img.jpg"]})
    assert "facebook" in results
    assert "instagram" in results
    print("✓ test_multi_platform_validate")


if __name__ == "__main__":
    test_intelligence_instagram_suitable()
    test_intelligence_youtube_requires_video()
    test_intelligence_text_too_long()
    test_error_classifier_known_code()
    test_error_classifier_rate_limit_heuristic()
    test_error_classifier_unknown()
    test_compatibility_tiktok_image_unsupported()
    test_compatibility_facebook_video_ok()
    test_compatibility_file_too_large()
    test_validator_instagram_no_media()
    test_validator_linkedin_text_ok()
    test_optimizer_returns_score()
    test_optimizer_compare_platforms()
    test_multi_platform_validate()
    print("\nALL 14 PUBLISHING INTELLIGENCE TESTS PASSED")
