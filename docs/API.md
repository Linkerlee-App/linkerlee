# LinkerLee REST API

The LinkerLee API allows authenticated users to manage links, tags, and groups programmatically.

- **Base URL**: `https://linkerlee.com/api` — or `<your-instance>/api` if self-hosting
- **Authentication**: [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens
- **Content type**: `application/json` for requests; responses are `application/json` unless noted

> **Stability:** this API is **unversioned and pre-1.0**. There is no `/v1` prefix and no
> deprecation window. The [browser extension](https://github.com/linkerlee-app/linkerlee-browser-extension)
> is the reference consumer; breaking changes are coordinated with it, but third-party
> clients should pin to a commit and expect to adapt.

## Authentication

All endpoints require a valid Sanctum personal access token. Send it via the `Authorization` header:

```
Authorization: Bearer <token>
Accept: application/json
```

**Tokens are created in the web UI only**, at **Settings → API tokens**. There is no endpoint
that mints or revokes a token, so a client cannot bootstrap its own credentials — the user
must paste one in.

Every token is issued with exactly one ability, `create`. There is currently **no way to mint
a read-only token**, and because `DELETE /api/links/{id}` also accepts `create`, any valid
token can delete links. Treat a token as full write access to the account's links.

Unauthenticated requests return `401 Unauthorized`. A token lacking the required ability
returns `403 Forbidden`.

### CORS

`config/cors.php` allows any `chrome-extension://` or `moz-extension://` origin against
`api/*`, so a browser extension can call a self-hosted instance without configuration.
Credentials are not supported — authenticate with the bearer header, not cookies.

## Endpoints

| Method | Path | Ability |
|---|---|---|
| `GET` | [`/api/user`](#get-apiuser) | any |
| `GET` | [`/api/all-tags`](#get-apiall-tags) | any |
| `GET` | [`/api/all-groups`](#get-apiall-groups) | any |
| `POST` | [`/api/links`](#post-apilinks) | `create` |
| `GET` | [`/api/links/find`](#get-apilinksfind) | `create` |
| `PUT` | [`/api/links/{linkId}`](#put-apilinkslinkid) | `create` |
| `DELETE` | [`/api/links/{linkId}`](#delete-apilinkslinkid) | `create` |
| `POST` | [`/api/suggest-tags`](#post-apisuggest-tags) | `create` |

---

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
| `title`    | string     | no       | Minimum 2 characters. If omitted or empty, the title is fetched from the target page. |
| `groups`   | int[]      | no       | Group IDs to attach. Each must exist and belong to the current user. |
| `tags`     | int[]      | no       | Tag IDs to attach. Each must exist. |
| `newTags`  | string[]   | no       | Tag names to create and attach, 1–50 characters each. Existing tags with the same name are reused. |

`tags` and `newTags` combine — you can attach known IDs and create new names in one request.
Sending neither leaves the link untagged; unlike `PUT`, it does not clear anything.

**Example request**

```json
{
    "link": "https://laravel.com/docs",
    "title": "Laravel Documentation",
    "groups": [1, 4],
    "tags": [2],
    "newTags": ["framework"]
}
```

**Response — `200 OK`** (`text/plain`)

```
The link was added.
```

> **Known wart:** this is the only endpoint that returns plain text rather than JSON, and it
> does not return the created link. Clients that need the new record must follow up with
> `GET /api/links/find`. The shipped browser extension depends on the current shape, so it is
> documented as-is rather than silently changed.

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

---

### `GET /api/links/find`

Looks up one of the authenticated user's links by exact URL. This is how a client answers
"have I already saved this page?" before offering to save it again.

**Required token ability**: `create`

**Query parameters**

| Field  | Type   | Required | Notes |
|--------|--------|----------|-------|
| `link` | string | yes      | Must be a valid URL. Matched exactly — a trailing slash or differing query string will not match. |

**Example request**

```
GET /api/links/find?link=https%3A%2F%2Flaravel.com%2Fdocs
```

**Response — `200 OK`**

```json
{
    "id": 42,
    "title": "Laravel Documentation",
    "link": "https://laravel.com/docs",
    "read_at": null,
    "favicon_url": "https://laravel.com/favicon.ico",
    "preview_image_url": "https://laravel.com/og-image.png",
    "tags": [
        { "id": 2, "name": "laravel" }
    ]
}
```

**Response — `404 Not Found`**

```json
null
```

> **This 404 is a normal result, not an error.** It means "you have not saved this URL", which
> is the common case. Clients should treat it as a negative answer rather than a failure.

`favicon_url` and `preview_image_url` are `null` until the background metadata job has run.

---

### `PUT /api/links/{linkId}`

Updates the title and tags of an existing link. **The URL itself is immutable here** — there
is no way to change `link` through the API.

**Required token ability**: `create`

**Request body**

| Field     | Type     | Required | Notes |
|-----------|----------|----------|-------|
| `title`   | string   | no       | Minimum 2 characters. A `null` value is ignored, so a title cannot be cleared through the API. |
| `tags`    | int[]    | no       | Tag IDs to set on the link. |
| `newTags` | string[] | no       | Tag names to create and set, 1–50 characters each. |

> ### ⚠️ Tags are replaced, not merged
>
> Tags are synced **unconditionally** on every request. `tags` and `newTags` together are the
> complete desired set — anything not listed is detached.
>
> **A request that omits both `tags` and `newTags` removes every tag from the link.**
>
> To change only the title, resend the full tag set alongside it:
>
> ```json
> { "title": "New title", "tags": [2, 7, 11] }
> ```
>
> Fetch the current set with [`GET /api/links/find`](#get-apilinksfind) first if you do not
> already have it.

**Response — `200 OK`**

Same shape as `GET /api/links/find`.

**Response — `404 Not Found`** — the link does not exist, **or** belongs to another user.
The two cases are deliberately indistinguishable so a caller cannot probe which IDs exist.

**Response — `422 Unprocessable Entity`** — same envelope as `POST /api/links`.

---

### `DELETE /api/links/{linkId}`

Deletes one of the authenticated user's links.

**Required token ability**: `create`. There is no separate `delete` ability — adding one
would reject every token already issued.

The link is **soft-deleted**, so it lands in the user's trash and can be restored from the
web UI with its tags intact. Group memberships are detached and group link counts recomputed.

Group membership is **not** restored along with the link — only tags survive. A restored link
returns ungrouped and has to be refiled.

**Response — `204 No Content`**

**Response — `404 Not Found`** — the link does not exist, or belongs to another user.

---

### `POST /api/suggest-tags`

Fetches the target page and returns the user's existing tags whose names appear in its text.
Used to pre-select likely tags when saving.

**Required token ability**: `create`

**Request body**

| Field  | Type   | Required | Notes |
|--------|--------|----------|-------|
| `link` | string | yes      | Must be a valid URL. Note this goes in the **body**, unlike `find` which takes a query parameter. |

Matching is whole-word and case-insensitive, against the user's own tags only. At most **10**
suggestions are returned.

**Response — `200 OK`**

```json
[
    { "id": 2, "name": "laravel" },
    { "id": 9, "name": "testing" }
]
```

An empty array is returned when nothing matches, when the user has no tags, **or when the
page could not be fetched.** A fetch failure is not reported as an error — clients cannot
distinguish "no matches" from "could not read the page".

> **This endpoint never creates tags.** It only surfaces tags that already exist. To attach a
> new name, pass it in `newTags` on `POST /api/links` or `PUT /api/links/{id}`.

---

## Error responses

| Status | Meaning |
|--------|---------|
| `401`  | Missing or invalid token |
| `403`  | Token lacks the required ability |
| `404`  | Resource does not exist **or** belongs to another user — deliberately indistinguishable. Also the normal "not saved" answer from `GET /api/links/find`. |
| `422`  | Validation failed (see the error envelope above) |
| `500`  | Server error |

Note that an ownership failure returns `404`, not `403`. Returning `403` would confirm that a
record with that ID exists.

## OpenAPI

A machine-readable spec is available at [`docs/openapi.yaml`](openapi.yaml). Import it into
Postman, Insomnia, or Swagger UI to explore the API interactively. It declares both the
production server and `http://localhost:8000/api` for local development.
