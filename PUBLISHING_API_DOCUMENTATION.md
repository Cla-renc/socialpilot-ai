# Live Social Media Publishing — REST API Reference

All publishing endpoints require authentication via Bearer token (`auth:sanctum`).

## Endpoints

### 1. Publish Now
`POST /api/v1/publishing/publish-now`
- **Body**:
  ```json
  {
    "brand_id": 1,
    "social_account_id": 4,
    "content": "Exciting product launch!",
    "media_urls": ["https://cdn.example.com/asset.jpg"]
  }
  ```
- **Response** (201 Created):
  ```json
  {
    "data": {
      "id": 12,
      "platform": "facebook",
      "status": "publishing",
      "external_post_id": null,
      "external_post_url": null,
      "error_code": null,
      "error_message": null,
      "attempt_number": 1,
      "is_retryable": true,
      "created_at": "2026-11-14T14:50:00Z"
    }
  }
  ```

### 2. List Attempts
`GET /api/v1/publishing/attempts?brand_id={id}&platform={platform}&status={status}&page=1`
- Returns paginated list of publishing attempts for the authenticated user.

### 3. Attempt Detail
`GET /api/v1/publishing/attempts/{attempt}`
- Returns attempt details if owned by user's brand; 403 Forbidden otherwise.

### 4. Retry Failed Attempt
`POST /api/v1/publishing/attempts/{attempt}/retry`
- Requeues retryable failed attempt with incremented attempt counter.

### 5. Cancel Attempt
`POST /api/v1/publishing/attempts/{attempt}/cancel`
- Cancels a pending or queued attempt.

### 6. Publishing Statistics
`GET /api/v1/publishing/statistics?brand_id={id}`
- Returns counts: `total`, `published`, `failed`, `processing`, `partially_published`, `cancelled`, and `by_platform`.

### 7. Publishing History
`GET /api/v1/publishing/history?brand_id={id}&platform={platform}&page=1`
- Returns paginated published attempts.

### 8. Notifications
- `GET /api/v1/publishing/notifications?unread_only=true`
- `GET /api/v1/publishing/notifications/unread-count`
- `POST /api/v1/publishing/notifications/{notification}/read`
- `POST /api/v1/publishing/notifications/mark-all-read`
