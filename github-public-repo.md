# GitHub Public Upstream Repo

BOLT itself is public open source from the start. A company that adopts BOLT should create an independent private downstream repository and follow `USING-BOLT.md`; it should not put company customizations, operational details, or business data in this public repository.

## Create

```bash
git init
git add .
git commit -m "chore: initial bolt scaffold"
gh repo create [org-or-user]/bolt --public --source=. --remote=origin --push
```

Existing repo:

```bash
git remote add origin git@github.com:[org-or-user]/bolt.git
git push -u origin main
```

## Rules

- Public repo.
- This repository is the reusable upstream, not a company's operational repository.
- `.env` never committed.
- Passport private keys never committed.
- Webhook secrets, deploy keys, API tokens, customer data, and production credentials never committed.
- `.env.example` stays complete but uses placeholders.
- Use conventional commits.
- Run `./vendor/bin/pint` before committing.
- After commits, refresh:

```bash
php artisan about > app-overview.md
php artisan route:list > routes.md
```

## Deploy Keys

For an optional cPanel production deploy, follow `production-runbook.md`. Keep the local cPanel access identity separate from the server's repository-specific read-only GitHub identity. Private keys remain outside the repo and sandbox; a private downstream records only paths and public fingerprints.
