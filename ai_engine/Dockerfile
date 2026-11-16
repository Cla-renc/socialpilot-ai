FROM python:3.12-slim AS python-ai-runtime

LABEL maintainer="Antigravity AI Engineering"
LABEL project="SocialPilot AI - Python Engine"

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PORT=8080

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

COPY ai_engine ./ai_engine

EXPOSE 8080

HEALTHCHECK --interval=20s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/health || exit 1

CMD ["uvicorn", "ai_engine.main:app", "--host", "0.0.0.0", "--port", "8080", "--workers", "1"]
