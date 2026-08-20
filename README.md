<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="public/linkerlee-logo-dark.svg">
    <img src="public/linkerlee-logo-light.svg" alt="LinkerLee" width="280">
  </picture>
</p>

<p align="center">
  <strong>Save a link. Find it again.</strong><br>
  A self-hostable bookmark manager built with Laravel 12 and React 19.
</p>

<p align="center">
  <a href="https://github.com/linkerlee-app/linkerlee/actions/workflows/tests.yml"><img alt="Tests" src="https://github.com/linkerlee-app/linkerlee/actions/workflows/tests.yml/badge.svg"></a>
  <a href="https://github.com/linkerlee-app/linkerlee/actions/workflows/lint.yml"><img alt="Linter" src="https://github.com/linkerlee-app/linkerlee/actions/workflows/lint.yml/badge.svg"></a>
  <a href="LICENSE"><img alt="License: MIT" src="https://img.shields.io/badge/license-MIT-blue.svg"></a>
  <img alt="PHP ^8.4" src="https://img.shields.io/badge/php-%5E8.4-777bb4.svg">
  <img alt="Laravel 12" src="https://img.shields.io/badge/laravel-12-ff2d20.svg">
</p>

---

## What LinkerLee is

Browser bookmark managers are good at saving and bad at retrieval. Everything goes into a
folder tree, and a year later you cannot remember which folder.

LinkerLee makes a different bet. Paste a URL and it fetches the title, description, favicon
and preview image in the background, so you never file a bare link. You tag it once, and
**collections** assemble views of your links from tag rules — a link can belong to "Reading",
"Rust" and "This week" at the same time without being copied into three folders.

No social feed, no recommendations, no algorithm. Your links, organised the way you organise.

A hosted instance runs at **[linkerlee.com](https://linkerlee.com)**. The code here is MIT
licensed and self-hostable — see [Quick start](#quick-start).

## Screenshots

| | |
|---|---|
| [![Links](docs/screenshots/links.png)](docs/screenshots/links.png)<br>**Your links** — search across title, URL and page content, then narrow by tag, favourite, unread or untagged. | [![Dashboard](docs/screenshots/dashboard.png)](docs/screenshots/dashboard.png)<br>**Dashboard** — totals, your most-used tags, what you saved recently. |
| [![Link detail](docs/screenshots/link-detail.png)](docs/screenshots/link-detail.png)<br>**A link up close** — rate it, mark it read, favourite it, archive it. | [![Editing a link](docs/screenshots/link-edit.png)](docs/screenshots/link-edit.png)<br>**Tagging** — pick from every tag you have, or type a new one. |

## Browser extension

LinkerLee has an official Chrome extension, developed in its own repository:

### → **[linkerlee-app/linkerlee-browser-extension](https://github.com/linkerlee-app/linkerlee-browser-extension)**

Click the toolbar icon on any page and the popup pre-fills the URL and title, shows tags that
look relevant to the page content, and saves in one click. It also recognises pages you have
already bookmarked, so you edit instead of duplicating.

Pairing it with an instance:

1. In LinkerLee, go to **Settings → API tokens** and create a token.
2. In the extension's **Options**, set the **Base URL** (your instance, or `https://linkerlee.com`)
   and paste the token.
3. Click **Test connection**.

[![Settings → API tokens](docs/screenshots/api-tokens.png)](docs/screenshots/api-tokens.png)

The token value is shown once, at creation. Revoke any you no longer use — a token grants
write **and delete** access to your links.

The extension talks to the endpoints described in [docs/API.md](docs/API.md) — chiefly
`GET /api/links/find` to detect an already-saved page and `POST /api/suggest-tags` for the
tag suggestions. `config/cors.php` already allows `chrome-extension://` and `moz-extension://`
origins, so a self-hosted instance works with it out of the box.

## Features

**Saving**
- Title, URL and description, with per-user duplicate detection
- Background metadata enrichment — title, description, favicon, preview image, page text —
  via a queued job *(needs a queue worker running; see [Configuration](#configuration))*
- URLs up to 2048 characters
- Missing scheme is filled in, so `example.com` saves as `https://example.com`

**Organising**
- Tags, with filtering from the links, dashboard and tags views
- Tag suggestions derived from the page's own text
- Collections: nested into a hierarchy, **and** definable as saved tag queries using
  and / or / not rules *(the code and the API call these `groups`)*
- An Inbox view for links that still need filing

**Working through**
- Search across links and collections (full-text on MySQL, `LIKE` elsewhere)
- Favourites, 1–5 star ratings, read/unread state
- Bulk editing across many links at once

**Lifecycle**
- Archive, soft-delete to trash, restore, force delete

**In and out**
- Import and export Netscape-format bookmark files — the format Chrome, Firefox and Safari
  all export
- Save by email: forward any link to your private inbox address and it becomes a bookmark
- A REST API, and the browser extension above

**Account**
- Registration, email verification, password reset
- Two-factor authentication (TOTP) with recovery codes
- Light and dark themes
- Delete all your data

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12, PHP ^8.4 |
| Bridge | Inertia.js v2 |
| Frontend | React 19, TypeScript |
| Styling | Tailwind CSS v4, Radix UI, shadcn/ui |
| Build | Vite 7, Laravel Wayfinder |
| Auth | Laravel Fortify (sessions + 2FA), Laravel Sanctum (API tokens) |
| Database | SQLite by default; MySQL 8 supported |
| Tests | Pest 4 |

## Quick start

**Prerequisites:** PHP 8.4 or newer, [Composer](https://getcomposer.org), and Node.js 22+.

You do **not** need MySQL. LinkerLee defaults to SQLite, which needs no server.

```bash
git clone https://github.com/linkerlee-app/linkerlee.git
cd linkerlee
composer setup
composer dev
```

`composer setup` installs PHP dependencies, copies `.env.example` to `.env`, generates an
app key, runs the migrations, installs npm packages and builds the frontend.

`composer dev` then starts four processes together — the PHP server, a queue worker, the log
tailer and Vite — and the app is at **<http://localhost:8000>**.

Register an account at `/register`. Email verification is enabled, and with the default
`MAIL_MAILER` the verification link is written to `storage/logs/laravel.log` rather than sent.

## Configuration

Most of `.env` can stay as it ships. The settings that actually matter:

| Variable | Why it matters |
|---|---|
| `DB_CONNECTION` | `sqlite` by default. Set to `mysql` (plus the `DB_*` credentials) for production — MySQL also enables the full-text search index, which SQLite does not get. |
| `QUEUE_CONNECTION` | Defaults to `database`. **A queue worker must be running** or link metadata is never fetched and saved links stay untitled. `composer dev` runs one for you; in production use `php artisan queue:work` under supervisor or systemd. |
| `MAILGUN_*` | Optional. Only needed for save-by-email. `MAILGUN_WEBHOOK_SIGNING_KEY` must be set or the inbound webhook rejects everything. |
| `LOG_VIEWER_ALLOWED_EMAILS` | Comma-separated emails allowed to open `/log-viewer`. Empty means nobody — set it deliberately. |

## Development

```bash
composer dev        # server + queue worker + logs + Vite, all at once
composer dev:ssr    # the same, with server-side rendering
npm run dev         # just Vite, if you are running the PHP server yourself
```

**One gotcha on a fresh clone:** Wayfinder generates the TypeScript route helpers that
components import from `@/actions` and `@/routes`, and its output is gitignored. `composer
setup` produces it as part of the build, but if you ever see unresolved imports, run
`php artisan wayfinder:generate`.

## Testing

```bash
composer test                                    # Pint check + the full suite
php artisan test --compact --filter=LinkFilter   # one test
php artisan test --compact tests/Feature/Api     # one directory
```

Tests run against in-memory SQLite while production runs MySQL. Anything depending on MySQL
specifically — the full-text index, column-length enforcement, collation — cannot be proven
by the suite; verify those against a real MySQL database.

## Code quality

```bash
composer lint         # Pint, fixes in place
composer test:lint    # Pint, check only (what CI runs)
npm run lint          # ESLint, fixes in place
npm run lint:check    # ESLint, check only (what CI runs)
npm run format        # Prettier, writes
npm run format:check  # Prettier, check only (what CI runs)
npm run types         # tsc --noEmit
```

## API

LinkerLee exposes a small REST API under `/api`, authenticated with a Sanctum personal access
token sent as `Authorization: Bearer <token>`. Tokens are created in the web UI at
**Settings → API tokens** — there is no endpoint that mints them.

> **Careful with `PUT /api/links/{id}`:** it replaces tags wholesale. A request that omits
> `tags` and `newTags` removes every tag on the link. To change only the title, resend the
> full tag set.

Full reference: **[docs/API.md](docs/API.md)**. There is also an OpenAPI 3 spec at
[docs/openapi.yaml](docs/openapi.yaml) you can import into Postman, Insomnia or Swagger UI.

The API is **unversioned and pre-1.0** — it may change. The browser extension is the reference
consumer; breaking changes will be coordinated with it.

## Deployment

No Dockerfile or compose file is provided yet. A conventional PHP deployment:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```

Then run `php artisan queue:work` as a supervised long-running process. Without it, metadata
enrichment silently never happens — this is the single most common self-hosting mistake.

## Roadmap

Not built, but intended. Listed here so nobody mistakes intent for reality:

- Comments and discussion threads on links
- Version history and change tracking
- Scheduling and reminders for links to revisit
- Outbound webhooks for link events
- An analytics dashboard for activity and popular tags
- Firefox and Safari extensions
- Mobile apps

## Contributing

Contributions are welcome. [CONTRIBUTING.md](CONTRIBUTING.md) covers setup, the definition of
done the project holds itself to, and the PR workflow. Please also read the
[Code of Conduct](CODE_OF_CONDUCT.md).

Bugs in the extension belong in the
[extension repository](https://github.com/linkerlee-app/linkerlee-browser-extension/issues).

## Security

Please do not open a public issue for a security problem. See [SECURITY.md](SECURITY.md) for
how to report one privately.

## License

MIT — see [LICENSE](LICENSE). You are free to self-host, modify and redistribute it,
commercially included.

## Acknowledgements

Built on the [Laravel React starter kit](https://github.com/laravel/react-starter-kit), and
standing on [spatie/laravel-tags](https://github.com/spatie/laravel-tags),
[spatie/laravel-searchable](https://github.com/spatie/laravel-searchable),
[Inertia.js](https://inertiajs.com), [Radix UI](https://www.radix-ui.com) and
[shadcn/ui](https://ui.shadcn.com).
