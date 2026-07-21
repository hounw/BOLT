# BOLT

Business Operations Low-code Toolkit is a public Laravel base app for the internal operations layer small businesses usually lack: employees, roles, HR records, PTO, files, Markdown knowledge, assets, audit, API, and webhooks.

The app is meant to be adopted and customized. Core stays clean, policy-protected, API-first, and friendly to AI agents that need to understand and extend a private company downstream.

Companies should not use GitHub's public Fork workflow for their operational application. Adopt a reviewed BOLT release into an independent private company repository, keep public BOLT as a read-only `upstream`, and deploy from the private `origin`. The complete human and agent workflow is in `USING-BOLT.md`.

## MVP Surface

- Employees, manager relationships, roles, and permissions
- Compensation and benefit history with restricted visibility
- PTO policies, balances, requests, scoped approvals, and cancellation
- Private polymorphic attachments
- Markdown knowledge base with tags, categories, versions, and attachments
- Asset inventory, assignment history, purchase data, and warranty data
- Audit log, webhooks, retries, signed delivery, and replay
- Passport OAuth2 and scoped API tokens
- `/api/v1`, `/docs`, `/openapi.json`, and `ai/api-guide.md`

## Stack

- Laravel 13 on PHP 8.3+
- MySQL 8 for production
- Livewire and Flux UI dependencies for the web UI layer
- Laravel Passport for OAuth2 and reusable login
- Scramble for `/docs` and `/openapi.json`
- Spatie Laravel Permission for roles and permissions

## Local Setup

```bash
composer install
composer run setup
```

The setup command creates the ignored local `.env`, file-backed SQLite database, application and Passport keys, schema, seed data, Node dependencies, and built assets. MySQL 8 remains required for production and production-parity verification.

Create the first local owner-admin interactively, then start the complete development process:

```bash
php artisan bolt:create-local-admin
composer run dev
```

Open `http://127.0.0.1:8000`. Agents must perform this setup, start and verify the server, help the user through interactive admin creation, and leave the process running for testing. See `LOCAL-DEVELOPMENT.md`.

Do not commit `.env`, the SQLite database, Passport private keys, production credentials, or customer data. Do not pass the admin password in shell arguments or chat.

Fresh non-local deployments use the interactive, first-owner-only command documented in `production-runbook.md`; never use local bootstrap credentials in production.

## Agent Workflow

Before working, read `AGENTS.md`, `USING-BOLT.md`, and `LOCAL-DEVELOPMENT.md`, then `tail -n 80 project-journal.md`. Use `docs/mvp-plan.md` as the active MVP tracker and `project-journal.md` as the history log. Keep `product-definition.md`, `technical-definition.md`, and `ai/api-guide.md` current as the app changes.

## Public Repo

- License: MIT
- CI: `.github/workflows/ci.yml`
- MVP tracker: `docs/mvp-plan.md`
- Contributing: `CONTRIBUTING.md`
- Security: `SECURITY.md`
- Optional cPanel production deploy: `production-runbook.md`
- Release checklist: `docs/release-checklist.md`
- Changelog: `CHANGELOG.md`
- Core upgrade recipes: `docs/upgrade-recipes/`
- Company adoption model: `USING-BOLT.md`
- Local evaluation workflow: `LOCAL-DEVELOPMENT.md`
- Public repo rules: `github-public-repo.md`

External applications use Passport Authorization Code with PKCE. Provisioning and callback/token examples are documented in `ai/api-guide.md`.
