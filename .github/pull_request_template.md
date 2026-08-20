## Summary

<!-- What changes, and why. One or two paragraphs. -->

Closes #

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor (no behaviour change)
- [ ] Documentation
- [ ] Chore / dependencies

## What I checked

<!--
Not a formality — this is the project's definition of done (see CONTRIBUTING.md).
Delete the lines that genuinely do not apply, rather than leaving them unticked.
-->

- [ ] **Ownership**: new queries are scoped to the current user; route-model bindings cannot
      surface another user's record; API routes check `tokenCan('create')`
- [ ] **Limits**: user- or page-supplied strings fit their columns (`link` 2048, `title` 255)
- [ ] **Failure paths**: no swallowed exceptions; queued work reports failure somewhere visible
- [ ] **Soft deletes**: trashed links stay out of listings, search and group counts
- [ ] **Queries**: relations eager-loaded, listings paginated

## Quality gates

- [ ] `vendor/bin/pint --dirty` clean
- [ ] `npm run types`, `npm run lint`, `npm run format` clean *(if `resources/js` changed)*
- [ ] Tests added or updated
- [ ] `composer test` passes

## Docs

- [ ] `docs/API.md` and `docs/openapi.yaml` updated *(if routes, API controllers, or
      request/response shapes changed)*
- [ ] No API change — nothing to update

## Deployment notes

<!-- Migrations that must run, a rebuild that is needed, config that must be set. "None" is a fine answer. -->

None.

## Anything you are unsure about

<!-- Optional. Flagging a weak spot gets it reviewed properly instead of missed. -->
