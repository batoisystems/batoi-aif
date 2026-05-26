# REST API Contract

Batoi AIF REST endpoints should be thin wrappers around the governed gateway. Host framework controllers, RAD controllers, and external clients should receive the same envelope shape.

## Response Envelope

Successful responses:

```json
{
  "ok": true,
  "data": {},
  "error": null
}
```

Failed responses:

```json
{
  "ok": false,
  "data": null,
  "error": {
    "code": "error_code",
    "message": "Human-readable message"
  }
}
```

## Initial Endpoints

- `POST /aif/infer`
- `POST /aif/embed`
- `POST /aif/moderate`
- `POST /aif/prompt/render`
- `GET /aif/providers`
- `GET /aif/models`
- `GET /aif/audit/{uid}`
- `GET /aif/reviews`
- `POST /aif/reviews/{uid}/approve`
- `POST /aif/reviews/{uid}/reject`

## Infer Request

```json
{
  "input": "Summarize this ticket",
  "prompt_code": "summarize_ticket",
  "prompt_version": "1.0.0",
  "provider": "openai",
  "model": "gpt-4.1-mini",
  "variables": {},
  "metadata": {}
}
```

## Embed Request

```json
{
  "input": "Text to embed",
  "provider": "openai",
  "model": "text-embedding-3-small",
  "metadata": {}
}
```

## Embed Response Data

```json
{
  "embedding": [0.1, 0.2],
  "provider": "openai",
  "model": "text-embedding-3-small",
  "usage": {},
  "metadata": {}
}
```

## Moderate Request

```json
{
  "input": "Text to check",
  "provider": "openai",
  "model": "omni-moderation-latest",
  "metadata": {}
}
```

## Moderate Response Data

```json
{
  "flagged": false,
  "categories": [],
  "metadata": {}
}
```

## Infer Response Data

```json
{
  "request_uid": "provider_request_id",
  "provider": "openai",
  "model": "gpt-4.1-mini",
  "output": "Summary text",
  "usage": {},
  "metadata": {}
}
```
