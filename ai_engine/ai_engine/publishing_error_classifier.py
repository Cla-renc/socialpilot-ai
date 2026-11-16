"""Publishing Error Classifier - Phase 9 SocialPilot AI"""

ERROR_CATEGORIES = {
    "auth":       "Authentication / Authorization Error",
    "rate_limit": "Rate Limit Exceeded",
    "content":    "Content Policy / Format Error",
    "network":    "Network / Connectivity Error",
    "platform":   "Platform Service Error",
    "quota":      "API Quota Exceeded",
    "unknown":    "Unknown Error",
}

PLATFORM_ERROR_MAP = {
    "facebook":  {
        "190": ("auth",       False, "Facebook access token expired. Please reconnect your account."),
        "200": ("auth",       False, "Missing Facebook permission. Please reconnect and grant required permissions."),
        "4":   ("rate_limit", True,  "Facebook API rate limit reached. Will retry automatically."),
        "368": ("content",    False, "Post violates Facebook Community Standards."),
        "100": ("content",    False, "Invalid parameter sent to Facebook API."),
    },
    "instagram": {
        "10":      ("auth",       False, "Instagram permission denied. Reconnect your account."),
        "32":      ("rate_limit", True,  "Instagram rate limit reached."),
        "2207053": ("content",    False, "Instagram media format not supported."),
    },
    "tiktok":    {
        "10101": ("auth",       False, "TikTok access token expired."),
        "10006": ("rate_limit", True,  "TikTok API rate limit reached."),
        "10005": ("content",    False, "TikTok content policy violation."),
    },
    "youtube":   {
        "403":               ("auth",    False, "YouTube authorization failed."),
        "quotaExceeded":     ("quota",   True,  "YouTube API daily quota exceeded. Will retry tomorrow."),
        "uploadLimitExceeded": ("quota", False, "YouTube channel upload limit exceeded."),
        "videoNotFound":     ("platform",True,  "YouTube video not found after upload. Retrying."),
    },
    "linkedin":  {
        "401":                      ("auth",       False, "LinkedIn access token expired. Please reconnect."),
        "429":                      ("rate_limit", True,  "LinkedIn API rate limit reached."),
        "CONTENT_RATING_REJECTED":  ("content",    False, "LinkedIn content policy violation."),
    },
}


class PublishingErrorClassifier:
    def classify_error(self, error_code: str, error_message: str, platform: str) -> dict:
        platform       = platform.lower()
        platform_errors = PLATFORM_ERROR_MAP.get(platform, {})

        if str(error_code) in platform_errors:
            cat_key, is_retryable, user_msg = platform_errors[str(error_code)]
            return {
                "category":       cat_key,
                "category_label": ERROR_CATEGORIES[cat_key],
                "is_retryable":   is_retryable,
                "user_message":   user_msg,
                "technical_code": error_code,
                "platform":       platform,
            }

        msg = (error_message or "").lower()
        if any(w in msg for w in ("token", "auth", "unauthorized", "permission", "expired")):
            return self._result("auth", False, "Authentication failed. Please reconnect your account.", error_code, platform)
        if any(w in msg for w in ("rate limit", "too many", "throttle", "quota")):
            return self._result("rate_limit", True, "Rate limit reached. Will retry automatically.", error_code, platform)
        if any(w in msg for w in ("network", "connection", "timeout", "unreachable")):
            return self._result("network", True, "Network error. Will retry automatically.", error_code, platform)
        if any(w in msg for w in ("policy", "violation", "spam", "community standard")):
            return self._result("content", False, "Content rejected by platform policy.", error_code, platform)

        return self._result("unknown", True, f"An unexpected error occurred: {error_message}", error_code, platform)

    def _result(self, category, is_retryable, user_message, error_code, platform):
        return {
            "category":       category,
            "category_label": ERROR_CATEGORIES[category],
            "is_retryable":   is_retryable,
            "user_message":   user_message,
            "technical_code": error_code,
            "platform":       platform,
        }
