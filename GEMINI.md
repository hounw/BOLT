# BOLT

## Read First

```bash
tail -n 80 project-journal.md
```

- `product-definition.md` = what to build.
- `technical-definition.md` = how it works.
- `USING-BOLT.md` = how public BOLT becomes a private company product and how agents operate in each mode.
- `LOCAL-DEVELOPMENT.md` = how agents prepare, start, verify, and hand off the local evaluation environment.
- `docs/mvp-plan.md` = active MVP checklist and current focus.
- `project-journal.md` = what changed and why.
- `production-runbook.md` = guided production deploy.
- `docs/release-checklist.md` + `CHANGELOG.md` = release gates and notes.
- `github-public-repo.md` = public upstream and no-secrets rules.
- `app-overview.md` = `php artisan about` snapshot.
- `routes.md` = `php artisan route:list` snapshot.
- `ai/api-guide.md` + `/openapi.json` = API truth for agents.

## Rules

- Stack: Laravel 13, PHP 8.3+, MySQL 8, Livewire, Flux UI, Passport, Scramble.
- Public GitHub repo; never commit secrets or customer data.
- Conventional commits.
- Plan with tool. Do not maintain `implementation-plan.md`.
- Before commit: `./vendor/bin/pint`.
- After commit:

```bash
php artisan about > app-overview.md
php artisan route:list > routes.md
```

- After commit: add a short entry to `project-journal.md`.
- Product changes update `product-definition.md`.
- Technical changes update `technical-definition.md`.
- API changes update `ai/api-guide.md`.
- `.env` never repo. `.env.example` stays complete.
- Private keys never in `/tmp`, `/private/tmp`, repo, or sandbox.
- Local admin setup uses `php artisan bolt:create-local-admin`; never invent or commit credentials.
- Never ask for or pass an admin password in chat or shell arguments; start the interactive local admin command for the user.
- Non-local first-owner setup uses interactive `php artisan bolt:bootstrap-owner --confirm-production`; never pass credentials in shell arguments.

## Local Development

- Default URL: `http://127.0.0.1:8000`.
- On a fresh clone, run `composer install` and `composer run setup`; setup creates the missing ignored `.env` and local SQLite database.
- When the user needs to evaluate the app or a user-facing change, start `composer run dev` in a persistent terminal, verify `/up` and `/login`, report the URL, and leave it running unless asked to stop.
- Help with the first local owner by starting `php artisan bolt:create-local-admin` interactively so the user enters their own credentials in the terminal.
- Do not merely tell the user how to start the server when the agent can start it.

## Production

- Follow `production-runbook.md`.
- cPanel is optional; do not generate or configure cPanel keys unless the company selected cPanel and the user authorized setup.
- Use separate project-specific identities for local-to-cPanel access and server-to-GitHub read-only deploy access; never overwrite or reuse an existing key.
- Private keys stay outside the repo, `/tmp`, `/private/tmp`, and sandbox: local cPanel key at `~/.ssh/deploy/[project]/cpanel_ed25519`, server GitHub key at `~/.ssh/deploy/[project]/github_ed25519`.
- After key creation, record only the exact paths, public fingerprints, custodian, and verification date in the private downstream's `production-runbook.md` and `technical-definition.md`; never record private key material or passphrases.
- Stop before migrations.
- Stop before webroot changes.

## API

- `/api/v1/...`
- `/docs`, `/openapi.json`, `ai/api-guide.md`
- Bearer auth, Passport scopes, rate limits, idempotency, and standard error envelope.
