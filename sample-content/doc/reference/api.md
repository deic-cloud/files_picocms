---

Title: API
Description: Endpoints and return values
Author: 
Access: public
Theme: documentation
date: 2001-01-06

---

## `GET /api/v1/[resource]`

[What it returns and who may call it.]

```json
{ "id": 42, "status": "ok" }
```

| Parameter | Required | Description |
|-----------|----------|-------------|
| `[id]` | yes | [What identifies the resource] |
| `[format]` | no | [json (default) or csv] |

## `POST /api/v1/[resource]`

[Request body, side effects, error codes. Document the failure modes —
that is what people come here for.]
