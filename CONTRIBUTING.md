# Contributing

BOLT is a public Laravel base app designed to be adopted, customized, and upgraded by humans and AI agents. Public upstream contributions follow this file; companies operating private downstreams follow `USING-BOLT.md` first.

## Before You Start

Read:

- `AGENTS.md`
- `USING-BOLT.md`
- `LOCAL-DEVELOPMENT.md`
- `product-definition.md`
- `technical-definition.md`
- `project-journal.md`
- `ai/api-guide.md` when API behavior changes

Then inspect the current tail:

```bash
tail -n 80 project-journal.md
```

## Development

```bash
composer install
composer run setup
php artisan bolt:create-local-admin
composer run dev
```

The default local URL is `http://127.0.0.1:8000`. Follow `LOCAL-DEVELOPMENT.md`; agents start and verify the process and help the user run local admin creation interactively.

Never commit `.env`, private keys, tokens, secrets, customer data, or generated production credentials.

## Change Rules

- Use conventional commits.
- Keep core reusable and customization-friendly.
- Put business-specific behavior in forks or explicit custom modules, not hidden inside core assumptions.
- Update `product-definition.md` for product behavior changes.
- Update `technical-definition.md` for architecture, auth, API, infrastructure, or security changes.
- Update `ai/api-guide.md` for API behavior changes.
- Add a `project-journal.md` entry for each committed checkpoint.

## Verification

Before committing code changes:

```bash
./vendor/bin/pint
php artisan test
npm run build
```

After feature commits, refresh snapshots:

```bash
php artisan about > app-overview.md
php artisan route:list > routes.md
```
