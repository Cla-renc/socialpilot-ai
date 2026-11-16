"""Platform Compatibility Checker - Phase 9 SocialPilot AI"""

PLATFORM_REQUIREMENTS = {
    "facebook":  {
        "text":  {"max_chars": 63206},
        "image": {"max_mb": 10,   "formats": ["jpg","jpeg","png","gif","bmp"]},
        "video": {"max_mb": 4096, "formats": ["mp4","mov","avi"], "max_duration_seconds": 14400},
        "supported_content_types": ["text","image","video"],
    },
    "instagram": {
        "text":  {"max_chars": 2200},
        "image": {"max_mb": 8,    "formats": ["jpg","jpeg","png"], "aspect_ratios": ["1:1","4:5","16:9"]},
        "video": {"max_mb": 100,  "formats": ["mp4","mov"],        "max_duration_seconds": 60,  "min_duration_seconds": 3},
        "reel":  {"max_mb": 1024, "formats": ["mp4","mov"],        "max_duration_seconds": 900},
        "supported_content_types": ["image","video","reel"],
    },
    "tiktok":    {
        "text":  {"max_chars": 2200},
        "video": {"max_mb": 287,  "formats": ["mp4","mov","webm"], "max_duration_seconds": 600, "min_duration_seconds": 3},
        "supported_content_types": ["video"],
    },
    "youtube":   {
        "text":  {"title_max": 100, "description_max": 5000},
        "video": {"max_gb": 256,  "formats": ["mp4","avi","mov","wmv","flv","webm","mkv"], "max_duration_seconds": 43200},
        "supported_content_types": ["video"],
    },
    "linkedin":  {
        "text":  {"max_chars": 3000},
        "image": {"max_mb": 10,   "formats": ["jpg","jpeg","png","gif"]},
        "video": {"max_mb": 5120, "formats": ["mp4","mov","avi"],        "max_duration_seconds": 600},
        "supported_content_types": ["text","image","video"],
    },
}


class PlatformCompatibility:
    def check_compatibility(self, platform: str, content_type: str, media_format: str = None, file_size_mb: float = 0) -> dict:
        platform     = platform.lower()
        content_type = content_type.lower()
        reqs         = PLATFORM_REQUIREMENTS.get(platform, {})
        errors, warnings = [], []

        supported = reqs.get("supported_content_types", [])
        if content_type not in supported:
            errors.append(f"{platform.capitalize()} does not support {content_type} posts. Supported: {', '.join(supported)}")
            return {"compatible": False, "errors": errors, "warnings": warnings, "platform": platform, "content_type": content_type}

        type_reqs = reqs.get(content_type, {})
        if media_format and "formats" in type_reqs:
            fmt = media_format.lower().lstrip(".")
            if fmt not in type_reqs["formats"]:
                errors.append(f"Format .{fmt} not supported for {content_type} on {platform}. Allowed: {', '.join(type_reqs['formats'])}")

        if file_size_mb > 0:
            max_mb = type_reqs.get("max_mb") or (type_reqs.get("max_gb", 0) * 1024)
            if max_mb:
                if file_size_mb > max_mb:
                    errors.append(f"File {file_size_mb:.1f}MB exceeds {platform} limit of {max_mb}MB for {content_type}")
                elif file_size_mb > max_mb * 0.9:
                    warnings.append(f"File {file_size_mb:.1f}MB is close to {platform} limit of {max_mb}MB")

        return {"compatible": len(errors) == 0, "errors": errors, "warnings": warnings, "platform": platform, "content_type": content_type, "requirements": type_reqs}

    def get_requirements(self, platform: str) -> dict:
        return PLATFORM_REQUIREMENTS.get(platform.lower(), {})
