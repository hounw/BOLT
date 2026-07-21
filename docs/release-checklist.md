# BOLT v1 Release Checklist

Use this checklist for `v1.0.0` and adapt it for later releases. Do not tag a release from a dirty working tree.

## Code And Security

- [x] All MVP items in `docs/mvp-plan.md` are complete.
- [x] Sensitive employee and audit API regression tests pass.
- [x] Attachment targets are required and policy checked.
- [x] `composer audit --locked --no-interaction` reports no unresolved advisories.
- [x] `npm audit --omit=dev` reports no unresolved advisories.
- [x] GitHub private vulnerability reporting is enabled.

## Verification

- [x] `./vendor/bin/pint --test`
- [x] `php artisan test` on SQLite.
- [x] Full suite passes on local MySQL 8 and the GitHub MySQL job covers the full-text Knowledge migration and search path.
- [x] `npm run build`
- [x] `composer validate --strict`
- [x] `php artisan route:cache` and `php artisan view:cache` succeed, followed by `php artisan optimize:clear` locally.
- [ ] Desktop and mobile smoke tests cover login, employee permissions, PTO, Knowledge, assets, attachments, webhooks, and OAuth consent.

## Documentation And Operations

- [x] `product-definition.md`, `technical-definition.md`, `ai/api-guide.md`, and OpenAPI match the release.
- [x] `app-overview.md` and `routes.md` are refreshed.
- [ ] `CHANGELOG.md` moves the release candidate notes under `1.0.0` with the release date.
- [ ] Production backup and restore have been tested outside production.
- [ ] Queue worker, scheduler, HTTPS, secure cookies, private storage, mail, and database settings are verified in staging.

## Publish

- [ ] Merge only green CI on `main`.
- [ ] Create signed tag `v1.0.0` from the verified commit.
- [ ] Publish GitHub release notes from `CHANGELOG.md`.
- [ ] Verify the public archive contains no `.env`, private keys, tokens, credentials, or customer data.
- [ ] Keep `1.x` as the supported release line and prepare later core changes as reviewable upgrade recipes.
