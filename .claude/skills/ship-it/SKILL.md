---
name: ship-it
description: Finalize a LinkerLee change end-to-end — adversarial review of the whole feature, format, lint, tests, conventional commit, and open a PR. Auto-invoke when the user says "ship it", "wrap this up", "finalize", "ready to commit/PR", "open a PR for this", or otherwise signals a feature/fix is done and should go out. Orchestrates the project's definition-of-done; do not skip steps.
---

# Ship it — LinkerLee definition of done

Take the current change from "code written" to "PR opened", running the
project's full finalize ritual. Work top to bottom. If a step surfaces a real
problem, **stop and report it** rather than pushing broken work.

## 0. Scope the feature
- Make sure you're on a feature branch, not `main`. If on `main`, create a
  branch first (`git switch -c <type>/<short-name>`).
- Determine the **full feature diff**, not just the last edit:
  `git diff main...HEAD` plus any staged/unstaged/untracked changes. Everything
  below reviews and ships the whole feature, not one file.

## 1. Adversarial review of the entire feature (do this first)
Review the complete feature diff with an **adversarial mindset — try to break it**,
not to praise it. Prefer launching review subagents in parallel over the full
diff (e.g. the `pr-review-toolkit:code-reviewer` and
`pr-review-toolkit:silent-failure-hunter` agents, or the `/code-review` skill at
high effort); consolidate their findings. Hunt specifically for:
- **Ownership & authorization** — every link, group, tag and public link is
  scoped to its owner. Check `HasCurrentUserScope` / explicit
  `where('user_id', Auth::id())`, `PermissionHelper`, and route-model bindings
  that could expose another user's record by id. API routes must check token
  abilities (`$user->tokenCan('create')`) as well as auth.
- **Correctness/logic bugs & edge cases** — empty/null titles and metadata,
  duplicate URLs per user (the `Rule::unique` on `links.link` is per-user),
  soft-deleted links leaking back into listings/search/counts, group link
  counts drifting after sync, tag sync wiping tags it shouldn't.
- **Database column limits** — user- and page-supplied strings must fit their
  columns (`links.link` and the URL columns are 2048, `title` 255). A long URL
  or page title must never surface as a 500; see `Link::MAX_URL_LENGTH`.
- **Silent failures** — swallowed exceptions, empty-catch fallbacks, and
  especially queued work (`FetchLinkMetadataJob`) failing where the user never
  sees it. Remote fetches of user-supplied URLs need timeouts and must handle
  non-HTML, redirects, and huge responses.
- **Inertia prop leakage** — page props are shipped to the browser; don't pass
  whole models with fields the UI doesn't need (tokens, other users' data).
- **Performance** — N+1 on links → tags/groups (eager load), unbounded
  listings without pagination, `LIKE %…%` search on large tables.
- **Project-convention violations** — Form Request validation (not inline),
  explicit return types, `casts()` method not `$casts`, Eloquent over `DB::`,
  `config()` not `env()` outside config, API Resources for JSON, queued jobs
  implement `ShouldQueue`, Wayfinder helpers (`@/actions`, `@/routes`) instead
  of hardcoded URLs in TSX, Radix/shadcn primitives reused instead of new
  one-off components, Tailwind v4 utilities following existing patterns.
- **Test gaps** — is the happy path + failure path + a weird path covered?

Fix blocking issues (or surface them clearly if they need a decision). Re-review
if you changed anything substantive. Keep the consolidated findings — they go in
the PR body.

## 2. Format & lint
- PHP: `vendor/bin/pint --dirty --format agent` (never `--test`). Let it fix style.
- If the change touches `resources/js`: `npm run types` (tsc), `npm run lint`
  (eslint --fix) and `npm run format` (prettier). All three must come back clean.

## 3. Tests
- Run the affected tests by filter/path, e.g.
  `php artisan test --compact --filter=<Name>` or
  `php artisan test --compact tests/Feature/<Path>`.
- If nothing covers the change, **write a test first** (Pest v4, feature tests
  by default, model factories, `fake()` for Faker data), then run it.
- Tests run on in-memory SQLite while production is MySQL. Anything that
  depends on MySQL behaviour (column lengths, full-text search, collation)
  cannot be proven by the suite — verify it against a local MySQL database and
  say so in the report.
- **All targeted tests must pass.** If any fail, stop and report the output —
  do not commit. Offer to run the full suite (`php artisan test --compact`)
  when green.

## 4. Docs
If the change touches the public API (routes in `routes/`, the controllers in
`app/Http/Controllers/ApiControllers/`, request/response shapes), update
`docs/API.md` and `docs/openapi.yaml` to match. Skip silently when the change
is internal.

## 5. Commit
Invoke the **`git-commit`** skill (Conventional Commits, project style — only
commit already-staged changes, don't `git add` for the user unless they ask).
Stage the finalized files as appropriate.

## 6. Open the PR
- Push the branch (`git push -u origin <branch>`).
- Create the PR with `gh pr create --base main`.
- **PR body** should contain: a short summary of the feature, and an
  **"Adversarial review" section** with the consolidated findings from step 1
  (what was checked, what was fixed, any residual risks / follow-ups). Call out
  any migration that has to run on deploy.
- Return the PR URL.

## Output
A short report: review verdict (issues found/fixed), pint/lint result, test
result (counts), docs touched or "no API change", commit hash/subject, the PR
URL, and any deploy steps (migrations, `npm run build`).
