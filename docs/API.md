# LinkerLee REST API

The LinkerLee API allows authenticated users to manage links, tags, and groups programmatically.

- **Base URL**: `https://linkerlee.com/api`
- **Authentication**: [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens
- **Content type**: `application/json` for requests; responses are `application/json` unless noted

## Authentication

All endpoints require a valid Sanctum personal access token. Send it via the `Authorization` header:

```
Authorization: Bearer <token>
Accept: application/json
```

Tokens can be issued from the user's account settings. Some endpoints require specific token abilities (see each endpoint).

Unauthenticated requests return `401 Unauthorized`. Requests with a token lacking the required ability return `403 Forbidden`.

## Endpoints

### `GET /api/user`

Returns the authenticated user.

**Response — `200 OK`**

```json
{
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "created_at": "2026-01-01T12:00:00.000000Z",
    "updated_at": "2026-01-01T12:00:00.000000Z"
}
```

---

### `GET /api/all-tags`

Returns all tags belonging to the authenticated user, ordered by name.

**Response — `200 OK`**

```json
[
    { "id": 1, "name": "laravel" },
    { "id": 2, "name": "react" }
]
```

---

### `GET /api/all-groups`

Returns all groups belonging to the authenticated user, ordered by title.

**Response — `200 OK`**

```json
[
    { "id": 1, "title": "Reading list" },
    { "id": 2, "title": "Work" }
]
```

---

### `POST /api/links`

Creates a new link for the authenticated user.

**Required token ability**: `create`

**Request body**

| Field      | Type       | Required | Notes |
|------------|------------|----------|-------|
| `link`     | string     | yes      | Must be a valid URL, at most 2048 characters. If the value is missing a scheme, `https://` is prepended automatically. Must be unique per user. |
| `title`    | string     | no       | Minimum 2 characters; longer than 255 characters is truncated. If omitted or empty, the title is fetched from the target page. |
| `groups`   | int[]      | no       | Group IDs to attach. Each must exist and belong to the current user. |
| `tags`     | int[]      | no       | Tag IDs to attach. Each must exist and belong to the current user. |

**Example request**

```json
{
    "link": "https://laravel.com/docs",
    "title": "Laravel Documentation",
    "groups": [1, 4],
    "tags": [2]
}
```

**Response — `200 OK`** (`text/plain`)

```
The link was added.
```

**Response — `422 Unprocessable Entity`**

```json
{
    "success": false,
    "message": "Validation errors",
    "data": {
        "link": ["The link field is required."]
    }
}
```

## Error responses

| Status | Meaning |
|--------|---------|
| `401`  | Missing or invalid token |
| `403`  | Token lacks the required ability, or resource ownership check failed |
| `422`  | Validation failed (see error envelope above) |
| `500`  | Server error |

## OpenAPI

A machine-readable spec is available at [`docs/openapi.yaml`](openapi.yaml). Import it into Postman, Insomnia, or Swagger UI to explore the API interactively.
