# SOCIALPILOT AI — PYTHON FASTAPI AI ENGINE DEPLOYMENT GUIDE

**Stack:** Python 3.11+ / 3.12+ / 3.14 + FastAPI + Uvicorn + Pydantic v2  
**Service:** Trend Intelligence & Multi-Agent AI Engine  
**Port:** 8080  
**Security:** Internal Bearer Token Authentication  

---

## 1. SERVICE ARCHITECTURE & RUNTIME

The Python AI Engine is an internal microservice providing specialized AI intelligence to the Laravel backend:
- **Phase 5:** Trend Intelligence & Multi-Source Research Orchestration (RSS, Google Trends, NewsAPI, Reddit, YouTube)
- **Phase 6:** Brand Context & Content Generation Engine (Archetypes, Variations, Quality Scoring)
- **Phase 7:** Brand Visual Profiles, Image/Video Generation Management & Media Storyboarding
- **Phase 8:** Scheduling Intelligence & Autopilot Strategy Optimization
- **Phase 9:** Multi-Platform Publishing Error Classification & Content Adaptation
- **Phase 10:** Engagement Quality Analysis & Performance Optimization Recommendations

---

## 2. PRODUCTION DOCKERFILE

The service is packaged using the existing `ai_engine/Dockerfile`:

```dockerfile
FROM python:3.11-slim

WORKDIR /app

# Install system utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application source
COPY . .

ENV PYTHONUNBUFFERED=1
ENV PYTHONPATH=/app

EXPOSE 8080

# Production startup command using Uvicorn ASGI server
CMD ["uvicorn", "ai_engine.main:app", "--host", "0.0.0.0", "--port", "8080", "--workers", "2", "--timeout-keep-alive", "65"]
```

---

## 3. INTERNAL AUTHENTICATION & SECURITY

> [!IMPORTANT]
> The Python AI Engine must never be publicly exposed to the internet without token verification. In a containerized or VPC setup, it should only bind to the internal network (`ai-engine:8080`).

All internal API endpoints require a shared secret bearer token:
```python
# ai_engine/main.py
def _verify_internal_token(authorization: str | None = Header(None)) -> None:
    expected = os.getenv("TREND_ENGINE_INTERNAL_TOKEN")
    if not expected:
        raise HTTPException(status_code=503, detail="Internal token not configured on AI engine.")
    if not authorization or authorization != f"Bearer {expected}":
        raise HTTPException(status_code=401, detail="Unauthorized internal service call.")
```
The identical token must be configured in Laravel's `.env` as `AI_ENGINE_INTERNAL_TOKEN` and `TREND_ENGINE_INTERNAL_TOKEN`.

---

## 4. STRICT ZERO-COST AI PROVIDER POLICY

The engine is engineered with a strict cost-prevention architecture:

### 1. Groq Cloud (Free Tier)
- **Status:** **Active / Preferred Cloud Provider**
- **Supported Free Model:** `openai/gpt-oss-120b` (Active open-weight MoE flagship; previous `llama-3.1` / `llama-3.3` models officially retired August 16, 2026)
- **Configuration:**
  ```env
  AI_PROVIDER=groq
  GROQ_API_KEY=your_free_groq_api_key
  GROQ_MODEL=openai/gpt-oss-120b
  GROQ_BASE_URL=https://api.groq.com/openai/v1
  ```
- Fast inference (300+ tokens/sec) within free tier rate limits.

### 2. OpenRouter (Verified Free Models Only)
- **Status:** **Active Secondary Cloud Provider**
- **Supported Free Model:** `meta-llama/llama-3.1-8b-instruct:free`
- **Configuration:**
  ```env
  AI_PROVIDER=openrouter
  OPENROUTER_API_KEY=your_openrouter_api_key
  OPENROUTER_MODEL=meta-llama/llama-3.1-8b-instruct:free
  OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
  ```
- Restricts inference exclusively to models ending in `:free`.

### 3. Local Ollama (Self-Hosted Inference)
- **Status:** **Active Local Development & On-Premise Fallback**
- **Model:** `llama3.2:3b`
- **Configuration:**
  ```env
  AI_PROVIDER=ollama
  OLLAMA_BASE_URL=http://localhost:11434
  OLLAMA_MODEL=llama3.2:3b
  ```

### 4. Paid Providers (OpenAI, Gemini)
- **Status:** **DISABLED & OPTIONAL**
- The system never defaults to paid APIs. When `OPENAI_API_KEY` or `GEMINI_API_KEY` are omitted, the provider manager cleanly reports `status: "configuration_required"` and falls back to internal heuristics without crashing.

---

## 5. HEALTH CHECK & MONITORING ENDPOINTS

### Public Liveness Probe
```bash
curl -i http://localhost:8080/health
```
**Response:**
```json
{"status": "ok", "service": "trend-and-content-engine"}
```

### Media Generation Health
```bash
curl -i http://localhost:8080/internal/v1/media/health \
  -H "Authorization: Bearer YOUR_INTERNAL_TOKEN"
```
**Response:**
```json
{
  "status": "ok",
  "providers": {
    "image": "internal_composer",
    "video": "storyboard_engine"
  }
}
```

### Scheduling Intelligence Health
```bash
curl -i http://localhost:8080/internal/v1/scheduling/health \
  -H "Authorization: Bearer YOUR_INTERNAL_TOKEN"
```
**Response:**
```json
{
  "status": "healthy",
  "supported_platforms": ["linkedin", "instagram", "tiktok", "youtube", "facebook"]
}
```
