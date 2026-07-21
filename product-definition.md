# Product Definition

Living document. Change product scope, users, roles, permissions, flows, rules, success criteria, or operations here.

## Base

- Name: BOLT - Business Operations Low-code Toolkit
- Objective: provide a clean open-source Laravel base app that small businesses can adopt into an independent private repository and customize with semi-technical users and AI agents.
- Users: business owners, admins, HR managers, people managers, employees, auditors, API clients, and AI/MCP agents.
- Problem: small businesses often have accounting, CRM, POS, or ERP tools, but not a flexible internal operations layer for HR files, PTO, knowledge, assets, and custom micro apps.
- Success: a company can establish a private operational product from a reviewed BOLT release, deploy it, manage core operations records, expose a documented API, configure webhooks, customize it without obscuring core patterns, and selectively review later BOLT upgrades.

## Roles

- owner-admin: all permissions and system configuration.
- hr-manager: employees, compensation, benefits, PTO, files, knowledge, assets, and audit visibility.
- manager: employee visibility, PTO review, knowledge, files, and assets.
- employee: own PTO, knowledge, and allowed files.
- auditor: read-only access to operational and audit records, excluding sensitive compensation and benefits history.
- api-client: scoped integration/agent access through Passport tokens.

## MVP

- Employees with search, optional user login creation/linkage, private photos, managed hierarchical departments, positions, department chart, people org chart, status/department filters, private HR details, and manager relationships.
- Roles and permissions enforced by policies and API token scopes.
- Filterable compensation history, compensation packages with amount basis and payment frequency, benefits history, day-based PTO policies with working-day calendars and holiday exclusions, balances, employee starting PTO balance/top-off, filtered requests, approval, rejection, cancellation, and manual balance adjustments.
- Generic private attachments for employees, knowledge articles, and assets.
- Markdown knowledge base with optional curated excerpts, secure file import, rendered reading and preview modes, hierarchical categories, reusable tags, directed article links/backlinks, indexed search, publication controls, immutable versions, private attachments, and agent-friendly traversal APIs.
- Asset management with search, managed reusable tags, multiple private photos, primary photo display, assignment history, photo-backed lifecycle history, purchase/vendor/warranty data in the platform currency, and lifecycle status.
- Filterable audit log for auth, sensitive writes, file downloads, webhook delivery, and core model changes.
- API under `/api/v1`, OpenAPI at `/openapi.json`, docs at `/docs`, and guide at `ai/api-guide.md`.
- Webhook endpoints, event catalog, signed delivery, filterable logs, retries, disabling, testing, and replay.
- Web operations UI for dashboard, employees, People setup reference data, department chart, people org chart, HR history entries, private attachments, PTO policy management, calculated PTO submission, approval queues, PTO calendar/history, manual adjustments, knowledge articles, asset photos and lifecycle timelines, audit logs, webhook endpoints, and filterable owner-admin user/API-token access management.
- System settings UI for operational configuration, including main currency, worker guidance, and webhook delivery history retention; monetary forms show the main currency as a fixed left-aligned prefix instead of a separate field.

## No Now

- Multi-tenant SaaS hosting.
- Plugin marketplace or runtime plugin system.
- Payroll processing.
- Native mobile apps.
- Production secret or credential generation in repo.

## Flows

- Employee management: admin or HR searches/filters the directory, manages nested departments and positions, reviews the department chart and people org chart, creates/updates employee records with optional private photos, optionally links or creates login users, records optional private HR details, and system audits and emits employee webhook events.
- Sensitive HR history: HR manages compensation packages with clear annual/monthly/hourly amount basis and payroll frequency, records and filters compensation/benefits changes, and can seed initial compensation history during onboarding; only authorized roles can read/write sensitive HR details.
- PTO: HR/admin configures day-based policies and one default policy with monthly, twice-monthly, or every-other-week accumulation, working days, holiday exclusions, and optional negative-balance requests; employee submits a request from dates and half-day flags while BOLT calculates policy days; direct manager or HR/admin sees pending approvals first, reviews the next three months of approved absences on a calendar, filters history, approves/rejects/cancels according to policy strategy, and can make audited manual balance adjustments when authorized.
- File attachment: authorized user uploads private file to a supported entity; download is policy checked and audited.
- Knowledge base: authorized editor manages a directional category tree and reusable tags, writes or imports a UTF-8 Markdown article with an optional curated excerpt, links related articles through `@` autocomplete, previews the same safe rendering used by the reading view, publishes or archives it, reviews immutable versions, and can load an earlier version into the editor before explicitly saving it as a new version. Readers and agents search indexed title/excerpt/body content, browse categories, retrieve paginated category digests/indexes, and traverse outgoing links and backlinks under publication policy. Imported source files remain private article attachments.
- Asset assignment: authorized user manages reusable asset tags, creates assets with comma-separated tags and optional private photos, searches/filters inventory, assigns/returns equipment for an employee, records condition and handoff notes with optional photos/files, and reviews a timeline of transfers, returns, delivery notes, repairs, audits, and observations.
- Webhooks: admin creates and filters endpoints, sends test deliveries, filters delivery logs, and replays deliveries; matching core events create signed delivery jobs and failures are logged.
- Settings: owner-admin reviews and updates operational configuration, including the deployment main currency and total webhook delivery history retained; default webhook delivery history cap is 10,000 records.
- Access management: owner-admin filters users/tokens, creates login users, assigns system roles, links users to employee records, issues or revokes scoped API tokens, and cannot remove the final owner-admin; authenticated users can update their own passwords; separate apps use a public-client Authorization Code + PKCE flow with explicit BOLT consent.
- Local evaluation: a fresh clone has a zero-configuration SQLite path; the agent creates the ignored `.env` and database through `composer run setup`, starts the full development process on `127.0.0.1:8000`, verifies it, and leaves it available for user testing.
- Local admin setup: the agent starts `php artisan bolt:create-local-admin` in an interactive terminal and the user enters their chosen credentials directly; the agent never invents credentials or places the password in chat or shell arguments.
- Production owner bootstrap: a fresh non-local deployment runs `php artisan bolt:bootstrap-owner --confirm-production` interactively once; the command accepts no credential options, refuses when an owner already exists, and subsequent owners are managed through Platform > Access.

## Operation

- Environments: local, staging, production.
- Admin: no default admin credentials are committed. Use the interactive `php artisan bolt:create-local-admin` locally after choosing credentials.
- Manual: cPanel is an optional production path; when selected, deploy follows `production-runbook.md`, uses separate local cPanel and server GitHub read-only SSH identities, records their non-secret paths/public fingerprints as downstream operational memory, and pauses before migrations and webroot changes.
- Auto: queues process webhook delivery, scheduled retries, operational log pruning, webhook history cap pruning, and future background tasks.
- Support: filtered audit logs and webhook deliveries are first-stop operational diagnostics; scheduled retention keeps old operational logs bounded.

## API

BOLT exposes an API in the MVP. See `technical-definition.md`, `/openapi.json`, and `ai/api-guide.md`.

## Roadmap

- Core upgrade recipes for customized private downstreams.
- UI-rich CRUD screens for all modules.
- More granular employee self-service policies.
- MCP server using the API as source of truth.

## Assumptions

- BOLT has a public open-source upstream posture; operational company derivatives are independent private repositories by default.
- A company derivative may intentionally diverge and adopts later BOLT changes selectively rather than continuously synchronizing with upstream.
- Single-business deployment, not multi-tenant.
- Passport OAuth2 is the reusable login and API token foundation.
