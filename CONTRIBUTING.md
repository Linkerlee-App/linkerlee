# Contributing to LinkerLee

Thanks for considering it. This document describes what the project actually enforces, so
you can get a change merged without guessing.

By participating you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

- **Bugs and small fixes** — open a PR directly. No need to ask first.
- **Features and anything structural** — open an issue first so we can agree on the shape
  before you spend time on it.
- **Bugs in the browser extension** belong in
  [its own repository](https://github.com/linkerlee-app/linkerlee-browser-extension/issues).
- **Security problems** — do not open an issue. See [SECURITY.md](SECURITY.md).

## Getting set up

Prerequisites: PHP 8.4+, Composer, Node.js 22+.

The 8.4 floor is not arbitrary — `symfony/http-client` and `symfony/mailgun-mailer` at `^8.0`
both require PHP `>=8.4`, so `composer install` cannot resolve on 8.3. Please do not lower the
constraint without downgrading those first.

```bash
git clone https://github.com/linkerlee-app/linkerlee.git
cd linkerlee
composer setup
composer dev
```

The app runs at <http://localhost:8000>.

**You do not need MySQL.** Local development defaults to SQLite and the test suite runs on
in-memory SQLite (`phpunit.xml`). Install MySQL only if you are working on something
MySQL-specific — the full-text search index, column lengths, collation.

If TypeScript reports unresolved `@/actions` or `@/routes` imports, the gitignored Wayfinder
output is missing: run `php artisan wayfinder:generate`.

## Branching and commits

Branch off `main`. Never commit to `main` directly.

```bash
git switch -c fix/link-title-truncation
```

Use `<type>/<short-name>`, where type is `feat`, `fix`, `chore`, `docs`, `refactor` or `test`.

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org), matching
the existing history:

```
feat(links): filter links by tag from the links, dashboard and tags views
fix(api): keep tags through a soft delete so a restore brings them back
chore(deps): bump vite to 7.1
```

## Definition of done

This is the bar the project holds itself to — the same one described in
`.claude/skills/ship-it/SKILL.md`. Work through it before opening a PR.

### 1. Review your own diff, adversarially

Read `git diff main...HEAD` as a whole and try to break it. In this codebase the recurring
problems are:

- **Ownership.** Every link, group, tag and public link belongs to one user. Check
  `HasCurrentUserScope` / explicit `where('user_id', Auth::id())`, and route-model bindings
  that could expose another user's record by id. API routes must check the token ability
  (`$user->tokenCan('create')`) as well as authentication.
- **Column limits.** User- and page-supplied strings have to fit: `links.link` is 2048
  (`Link::MAX_URL_LENGTH`), `title` is 255. A long URL or page title must never become a 500.
- **Silent failures.** Swallowed exceptions and empty catches, especially in queued work —
  `FetchLinkMetadataJob` fails where the user never sees it. Remote fetches of user-supplied
  URLs need timeouts and must survive non-HTML, redirects and huge responses.
- **Soft deletes.** Trashed links leaking back into listings, search or group counts.
- **Tag syncing** wiping tags it should not.
- **Inertia prop leakage.** Page props ship to the browser — do not pass whole models with
  fields the UI does not need.
- **N+1 queries.** Eager-load links → tags/groups; paginate unbounded listings.

### 2. Format and lint

```bash
vendor/bin/pint --dirty
```

If you touched `resources/js`, all three must come back clean:

```bash
npm run types
npm run lint
npm run format
```

### 3. Tests

Write one. Pest 4, feature tests by default, model factories (check for existing states
before building models by hand), `fake()` for Faker data.

```bash
php artisan test --compact --filter=YourTest
composer test                                  # full suite before you push
```

All tests must pass. Do not delete or skip an existing test to make a change fit — if a test
is genuinely wrong, say so in the PR and explain why.

Remember the SQLite/MySQL split: if your change depends on MySQL behaviour, the suite cannot
prove it. Verify against MySQL and say so in the PR.

### 4. Docs

If your change touches `routes/`, the controllers in `app/Http/Controllers/ApiControllers/`,
or any request/response shape, update **both** [docs/API.md](docs/API.md) and
[docs/openapi.yaml](docs/openapi.yaml). They are the contract the browser extension is built
against.

### 5. Open the PR

Target `main`. Fill in the template: what changed, what you checked, and anything a
deployment needs (migrations, a rebuild). CI runs Pint, ESLint, Prettier, `tsc` and the Pest
suite across PHP 8.4 and 8.5.

## Project conventions

Most of these come from the Laravel Boost guidelines in [CLAUDE.md](CLAUDE.md):

- **Validation** in Form Request classes, not inline in controllers.
- **Explicit return types** on every method; type-hint parameters.
- Casts in a `casts()` method, not a `$casts` property.
- Eloquent relationships over raw queries; avoid `DB::`.
- `config('...')` outside config files, never `env('...')`.
- API Resources for JSON responses.
- Queued jobs implement `ShouldQueue`.
- In TSX, import Wayfinder helpers from `@/actions` and `@/routes` — never hardcode a URL.
- Reuse the Radix/shadcn primitives in `resources/js/components/ui` before writing a new one.
- Prefer PHPDoc blocks over inline comments.

## What not to do

- Do not add or upgrade a dependency without raising it first.
- Do not commit generated Wayfinder output — it is gitignored on purpose.
- Do not commit `.env`, real credentials, or screenshots containing personal data.
- Do not reformat files your change does not touch; it buries the actual diff.

## Questions

Open an issue. A question that turns out to be a documentation gap is a useful bug report.
