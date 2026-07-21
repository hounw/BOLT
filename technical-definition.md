# Technical Definition

Living document. Change architecture, data, auth, API, integrations, dependencies, infrastructure, logging, security, or deploy details here.

## Stack

- Laravel 13, PHP 8.3+, MySQL 8, Livewire, Flux UI, Passport, Scramble, Spatie Permission, League CommonMark, Pint.
- Tests use SQLite in memory; the zero-configuration local evaluation environment uses ignored file-backed SQLite at `database/database.sqlite`.
- Production uses private local storage or S3-compatible private storage.

## Architecture

- Pattern: conventional Laravel modular core using Eloquent models, policies, form requests, resources, services, jobs, REST controllers, and Blade operations screens.
- Modules: identity/access, employees/HR, People setup reference data, PTO, attachments, knowledge base, assets, audit, webhooks, system settings, API docs.
- Boundaries: custom business logic belongs in an independent private company repository, outside core modules where practical or as explicit downstream changes; no plugin system in MVP.

## Data

- User: login identity, Passport tokens, roles, optional employee record.
- Department and Position: lightweight active/inactive People reference records used by employee onboarding and directory filters; departments can nest through `parent_id` and render in a web department chart.
- Employee: business person record, optional login-user link, private photo path, manager hierarchy rendered in a web people org chart, managed department/position IDs plus backward-compatible department/title strings, encrypted private HR data, emergency contact, HR metadata, and directory search/filter scopes.
- CompensationPackage: reusable active/inactive compensation defaults for onboarding; package edits do not rewrite employee history.
- CompensationHistory and BenefitHistory: sensitive HR timelines with web/API filter scopes; compensation packages carry amount basis (`annual`, `monthly`, `hourly`) and payment frequency (`monthly`, `bimonthly`, `biweekly`) before snapshotting into normal compensation history rows.
- PtoPolicy, PtoBalance, PtoRequest, PtoAdjustment: web/API PTO configuration, default-policy selection, monthly/twice-monthly/every-other-week accumulation frequency, working-day and holiday calendars, optional negative-balance requests, scoped balance/request visibility, pending/used/remaining days, approval state, and audited manual balance adjustments.
- Attachment: private polymorphic file metadata and storage path, exposed through policy-checked API and web upload/download routes; upload targets must pass target-record view policy, and employee-file downloads are limited to the employee, their manager, or file/employee managers. Employee photos are stored on the private local disk and served through a policy-checked web route.
- KnowledgeArticle and KnowledgeArticleVersion: canonical Markdown body, optional 300-character curated excerpt stored in a 1,000-character field, deterministic preview fallback, publication status, one stable hierarchical category ID with a backward-compatible category-name snapshot, reusable individual tags, creator/updater metadata, private source attachments, and immutable content snapshots. KnowledgeCategory uses an adjacency-list `parent_id` tree with cycle prevention and guarded deletion. KnowledgeArticleLink stores unique directed source/target edges derived from stable internal Markdown links on every save/import. MySQL full-text search covers title, excerpt, and Markdown body and is combined with a bounded `LIKE` fallback for uncommitted, stop-word, substring, SQLite, and short-query recall. A shared CommonMark service renders GitHub-flavored Markdown with raw HTML stripped, unsafe links disabled, stable heading IDs, and table-of-contents metadata.
- Asset and AssetAssignment: equipment lifecycle, internal system asset ID, managed reusable tag catalog plus asset tag array with legacy category compatibility, current-assignee filtering, search scopes, and assignment history.
- AuditLog: actor, event, string-compatible auditable target ID for integer and Passport token models, old/new values, metadata, timestamp, and operational filter scopes.
- WebhookEndpoint and WebhookDelivery: endpoint config, encrypted secret, delivery lifecycle, subscription filters, endpoint health state, delivery detail inspection, and delivery triage filters.
- SystemSetting: owner-admin-managed operational configuration for main currency, worker guidance, webhook delivery history retention, asset tags, and the reusable knowledge tag catalog; hierarchical knowledge categories are first-class records.
- IdempotencyKey: request fingerprint and stored JSON response for risky POST retries.

## Auth

- Web login: Laravel session auth with authenticated self-service password update.
- Local owner-admin creation: `php artisan bolt:create-local-admin` only runs in `local`, validates interactive user-chosen credentials, seeds access roles, and assigns `owner-admin`; agents do not pass passwords through chat or shell arguments.
- Initial production owner creation: `php artisan bolt:bootstrap-owner --confirm-production` runs only outside `local`, accepts credentials interactively rather than as shell options, requires a strong temporary password, audits creation, and refuses when an owner-admin already exists.
- API auth: Passport `auth:api` bearer tokens; owner-admins can create and revoke scoped personal access tokens from the Access UI.
- Reusable login: Passport OAuth2 Authorization Code + PKCE for separate apps, with a BOLT consent screen, public-client provisioning command, exact redirect URI validation, and an end-to-end authorization/token test.
- Roles/permissions: Spatie Permission with owner-admin, hr-manager, manager, employee, auditor, api-client; owner-admin access UI filters and creates login users, manages web roles, maintains employee links, and filters/provisions/revokes Passport personal access tokens.
- Policies/gates: all sensitive module actions route through policies; API routes additionally require Passport scopes.

## API

- Base: `/api/v1`
- Docs: `/docs`
- Spec: `/openapi.json`
- Agent guide: `ai/api-guide.md`
- Auth: `Authorization: Bearer [token]`
- Scopes: employees, HR, PTO, files, knowledge, assets, audit, webhooks.
- Idempotency: risky POST routes require `Idempotency-Key`; fingerprints include normalized input and uploaded file metadata/content hashes.
- Rate limits: `BOLT_API_RATE_LIMIT`, default `120,1`.
- OpenAPI operation IDs: stable camelCase names such as `listEmployees`, `createPtoRequest`, and `listWebhookEvents` for agents and generated clients.
- Knowledge retrieval: `/api/v1/knowledge-articles` supports indexed `q` plus `status`, legacy `category`, `category_id`, `tag`, `missing_excerpt`, `linked_from`, and `linked_to` filters. Article resources expose canonical Markdown, curated/fallback excerpt metadata, stable category metadata, and link counts. Category list/detail, direct digest, recursive descendant index, tag catalog, and directed link traversal endpoints use `knowledge:read`; category mutations use `knowledge:write`. Ordinary readers and agents only receive published articles throughout search and traversal; knowledge managers can retrieve every status. Markdown import and immutable versions remain supported.
- Asset retrieval: `/api/v1/assets` supports `q`, `status`, `tag`, legacy `category`, and `assigned_to` filters using the same scopes as the web UI; asset resources include reusable `tags`, current-holder metadata, and private-photo presence without exposing public file URLs. Web asset forms accept multiple private photos, store them as asset attachments, and use the first uploaded image as the primary display photo.
- Asset history: `/api/v1/assets/{asset}/history` lists and creates policy-checked timeline events for condition notes, delivery notes, transfers, returns, repairs, audits, and observations. Assignment and return workflows create matching history events, and event photos/files use the private attachment system.
- Employee retrieval: `/api/v1/employees` supports `q`, `status`, `department`, and `manager_id` filters using the same scopes as the web UI. Employee write APIs accept `department_id`/`position_id` and still accept legacy `department`/`title` strings, resolving those strings into reference records.
- People reference APIs: `/api/v1/departments` and `/api/v1/positions` support `q` and `is_active` filters with employee scopes; department writes accept `parent_id` and reject hierarchy cycles; `/api/v1/compensation-packages` supports `q` and `is_active` filters with HR scopes.
- HR history retrieval: employee compensation history supports `type`, `effective_from`, and `effective_until`; benefit history supports `type`, `starts_from`, and `starts_until`.
- Employee onboarding extras: employee create/update can optionally seed a compensation history row from an active compensation package, including amount basis and payment-frequency context in the snapshot notes, when the token has `hr:write`; it can create a starting day-based PTO balance when the token has `pto:write`.
- PTO policies: `/api/v1/pto-policies` supports listing, detail retrieval, creation, and updates with day allowance/carryover, accumulation frequency, working days, holiday dates, and negative-balance behavior; writes require `pto:write` scope plus PTO management permission and keep at most one default policy.
- PTO request retrieval: `/api/v1/pto-requests` supports `status`, `employee_id`, `pto_policy_id`, `starts_from`, and `starts_until` filters using the same scopes as the web UI; employees see self-service records, managers see direct reports, and HR/admin users with PTO management see the full queue.
- Audit retrieval: `/api/v1/audit-logs` supports `event`, `actor_id`, `auditable_type`, `auditable_id`, `occurred_from`, and `occurred_until` filters using the same scopes as the web UI. Compensation and benefit values are returned only to callers with their corresponding HR permission; private employee fields are omitted without employee-management permission, while the operational event remains visible with `sensitive_values_redacted=true`.
- Webhook retrieval: `/api/v1/webhook-endpoints` supports `q`, `is_active`, and `event`; `/api/v1/webhook-endpoints/{id}/deliveries` supports `status`, `event`, `created_from`, and `created_until`; `/api/v1/webhook-deliveries/{id}` returns a single delivery with payload, response body, error, timestamps, and endpoint-policy authorization.
- Standard API error responses preserve relevant HTTP headers, including throttle `Retry-After`.
- Error shape:

```json
{"error":{"code":"validation_failed","message":"The given data was invalid.","fields":{}}}
```

## Integrations

- Webhooks: explicit event catalog in `config/bolt.php`, catalog API at `/api/v1/webhook-events`, signed JSON POST with `X-BOLT-*` headers and HMAC SHA-256 signature, scheduled `bolt:retry-webhooks` retries for due failed deliveries, API and web UI test/replay actions through `WebhookDispatcher`, and matching event dispatch from API and web workflows including PTO policy changes. Test and replay actions require an active endpoint; HTTP and exception failures increment endpoint failure counts and disable endpoints after the configured max attempts.
- OAuth2 apps: external apps register Passport clients and use Authorization Code + PKCE.
- Future MCP server: should consume `/openapi.json` and `ai/api-guide.md`.

## Jobs/Files

- Commands: `bolt:retry-webhooks`, `bolt:prune-operational-logs`, and `bolt:prune-webhook-deliveries`.
- Jobs: `DeliverWebhook` handles signed delivery attempts.
- Queues: database queue by default.
- Storage: private `local` disk by default; public disk only for app assets.
- Public/private: uploaded business files and asset photos are private and served only through policy-checked routes; additional asset photos are retained as private attachments even when the primary display photo changes.
- Retention: `bolt:prune-operational-logs` runs daily and prunes audit logs and webhook delivery logs using `BOLT_AUDIT_RETENTION_DAYS` and `BOLT_WEBHOOK_DELIVERY_RETENTION_DAYS`; `bolt:prune-webhook-deliveries` runs daily and deletes oldest webhook deliveries beyond the configured total-history limit. The default total limit is `10,000` records.
- Settings UI: owner-admins can manage the deployment main currency, webhook delivery history limit, and record queue worker guidance under Platform > Settings. Web monetary inputs use a shared Blade money-input component that renders the main currency as a fixed left-aligned prefix.

## Logging

- Log: errors, critical business events, irreversible actions, integrations, webhook failures.
- Do not log: secrets, private key material, full sensitive payloads, routine successful requests.
- Default: `daily`; production `TELESCOPE_ENABLED=false`.

## Security

- Secrets: `.env`, Passport private keys, webhook secrets, and deploy keys are never committed.
- Sensitive data: compensation, benefits, personal employee contact details, private HR details, emergency contacts, tax IDs, government IDs, and medical/accommodation notes require HR/employee-management permissions; auditors do not receive pay/benefits/private-HR permissions by default. Private employee attributes are masked in stored model audits and filtered from API resources.
- Validation: API writes use Form Requests.
- Authorization: policies plus Passport scopes.
- Audit: model changes, auth, file uploads/downloads, reference-data changes, PTO balance creation/adjustment, Markdown imports, knowledge publication/archive transitions, version restores, and webhook attempts are recorded.
- UI: authenticated Blade screens use policies; navigation items declare permission hints and render only accessible sections; compensation and benefits panels/actions are gated separately from general employee profile access; PTO self-service is scoped to the linked employee, manager PTO queues and manual adjustments are scoped to direct reports, HR/admin PTO users can review and adjust the full queue, and non-published knowledge articles and their attachments require knowledge-management permission.
- Risks: customized downstreams must review core upgrade recipes before cherry-picking.

## Deploy

- Guided cPanel + SSH runbook in `production-runbook.md`, including secure production environment values, pre-migration database/private-file backups, worker and scheduler commands, activation verification, and reviewed rollback/restore procedures.
- cPanel is optional. Its SSH design separates the local workstation-to-cPanel access key from the server-to-GitHub repository-specific read-only deploy key, refuses implicit overwrite, and records only paths and public fingerprints in private downstream operational memory.
- CI runs the full suite against SQLite and MySQL 8; MySQL coverage executes production-only migrations and full-text Knowledge behavior.
- Release gates and supported-version packaging are tracked in `docs/release-checklist.md`, `CHANGELOG.md`, and `SECURITY.md`.
- Public repo does not include secrets; deployment pulls code and creates `.env` on server.
- Company deployments pull from the company's independent private `origin`; public BOLT remains a read-only `upstream` used only for reviewed upgrades.
- Stop before production migrations and before pointing webroot to `/public`.

## Local Development

- `composer run setup` creates a missing ignored `.env` and SQLite database before generating keys, migrating/seeding, installing Node dependencies, and building assets.
- `composer run dev` binds Laravel explicitly to `127.0.0.1:8000` and runs the queue listener, Pail, and Vite alongside it.
- `LOCAL-DEVELOPMENT.md` requires an agent to start, verify, and leave the local process running for user evaluation and to facilitate interactive first-admin creation.

## Decisions

- 2026-07-04 public OSS repo: BOLT is meant to be adopted and customized.
- 2026-07-04 single-business deployment: no tenant layer in MVP.
- 2026-07-04 Passport OAuth2: reusable login for separate apps and scoped agent/API clients.
- 2026-07-14 private downstream model: companies adopt a reviewed release into an independent private repository, retain BOLT as a read-only upstream, and select upgrades explicitly.
- 2026-07-14 cPanel key custody: optional cPanel deployments use distinct project-scoped access/deploy identities; downstream docs remember paths and public fingerprints, never private material.

## Assumptions

- Laravel 13 satisfies the Laravel 11+ requirement.
- Livewire/Flux dependencies are installed now; MVP UI starts with policy-protected Blade screens.
