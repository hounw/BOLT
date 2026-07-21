# Project Journal

Memory. Read before work:

```bash
tail -n 80 project-journal.md
```

Rules: add an entry after each commit. Keep it short. No secrets.

## Format

```md
## YYYY-MM-DD - type: summary

Commit: [hash|pending]
Change:
-
Reason:
-
Decision:
-
Impact:
- Product:
- Technical:
- Operation:
Pending:
-
```

## Entries

## 2026-07-04 - scaffold: BOLT MVP core

Commit: 12347df
Change:
- Scaffolded Laravel app and added BOLT MVP core modules, API, auth, audit, webhooks, docs, and tests.
Reason:
- Implement public open-source base app for small-business operations and AI-agent customization.
Decision:
- Public OSS repo posture, single-business deployment, Passport OAuth2, no plugin system in MVP.
Impact:
- Product: MVP core entities and workflows are available through `/api/v1`.
- Technical: core uses policies, scopes, Form Requests, Resources, audit logs, idempotency, and webhooks.
- Operation: production setup remains guided and secret-free.
Pending:
- Create local admin credentials only after the user provides them.

## 2026-07-04 - feature: operations UI and local admin command

Commit: 12347df
Change:
- Added local-only owner-admin creation command.
- Added authenticated web UI for dashboard, employees, PTO, knowledge, assets, audit, and webhooks.
- Added web UI feature tests.
Reason:
- Make the backend core usable through the app, not only through API endpoints.
Decision:
- Keep local admin credentials out of repo and require one-off local input.
Impact:
- Product: operators can manage core records from the browser.
- Technical: UI routes reuse existing policies and models.
- Operation: local setup now has a safe admin command.
Pending:
- Ask user for actual local admin credentials before creating an account.

## 2026-07-07 - checkpoint: shell polish and navigation rules

Commit: 12347df
Change:
- Added BOLT logo/icon assets to the authenticated shell.
- Reworked primary navigation into config-driven section groups with three-level support.
- Added Lucide icons for submenu indicators and removed submenu hover gaps.
- Removed action links from navigation; actions remain buttons inside views.
Reason:
- Make the public base app feel solid enough for forked/customized deployments.
Decision:
- Users and employees remain separate concepts; employees may link to users when appropriate.
- Navigation is for sections/views only, not actions.
Impact:
- Product: dashboard shell is clearer and ready for larger module menus.
- Technical: custom modules can extend `config/navigation.php` with nested section/view items.
- Operation: generated app and route snapshots were refreshed before checkpoint.
Pending:
- Continue core MVP hardening from the implementation plan.

## 2026-07-07 - feature: webhook event catalog

Commit: 42da754
Change:
- Added a descriptive webhook event catalog in `config/bolt.php`.
- Added `/api/v1/webhook-events` for API clients and agents.
- Updated webhook endpoint validation and web form event descriptions, including `*` wildcard subscriptions.
- Added API coverage for catalog exposure and invalid event validation.
Reason:
- Make webhook subscriptions explicit and discoverable for customized forks, MCP clients, and external integrations.
Decision:
- Keep event names centralized in config and derive validation from the same catalog.
Impact:
- Product: admins and API clients can see supported webhook events before subscribing.
- Technical: webhook event additions now have one canonical place plus route/docs/test coverage.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue webhook retry/backoff and core API hardening from the implementation plan.

## 2026-07-07 - feature: scheduled webhook retries

Commit: ed5b0d6
Change:
- Added `bolt:retry-webhooks` to dispatch due failed webhook deliveries.
- Scheduled the retry command every minute through Laravel's scheduler.
- Added tests for active endpoint, due time, and max-attempt eligibility.
- Documented scheduler/queue requirements in the production runbook and API/technical docs.
Reason:
- Complete the webhook retry/backoff loop beyond recording `next_attempt_at`.
Decision:
- Retry only failed deliveries that are due, under the max attempt count, and attached to an active endpoint.
Impact:
- Product: admins can rely on automatic webhook recovery instead of manual replay only.
- Technical: retry eligibility is covered by feature tests and centralized command behavior.
- Operation: production deploys must run both queue worker and scheduler.
Pending:
- Continue API and permission hardening from the implementation plan.

## 2026-07-07 - feature: employee login linkage UI

Commit: e86a1e3
Change:
- Added login user selection to employee create/edit screens.
- Displayed linked login identity on employee index and show screens.
- Enforced one employee per linked user in web validation.
- Added web UI tests for linking and duplicate-link validation.
Reason:
- Bring the operations UI up to parity with the core `employees.user_id` model/API capability.
Decision:
- Users remain login identities and employees remain business records; the link is optional and unique.
Impact:
- Product: admins can connect human login accounts to employee records when appropriate.
- Technical: web validation now mirrors the database uniqueness constraint for employee-user links.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue HR/PTO/API hardening from the implementation plan.

## 2026-07-08 - feature: HR history web UI

Commit: e1c0c82
Change:
- Added compensation and benefits/bonus history panels to employee detail pages.
- Added HR-gated web forms for creating compensation and benefits history entries.
- Reused existing policy gates and webhook events for web-created HR history.
- Added web UI coverage for compensation and benefit entry creation.
Reason:
- Make sensitive HR history manageable from the operations UI, not only the API.
Decision:
- Keep compensation and benefits visibility/actions separately gated from general employee profile access.
Impact:
- Product: HR/admin users can manage employee pay and benefits history from the browser.
- Technical: web routes now mirror the API history write behavior and dispatch the same events.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue PTO and file attachment hardening from the implementation plan.

## 2026-07-08 - feature: private attachment web UI

Commit: 48d8b67
Change:
- Added generic web attachment upload and download controller.
- Added reusable attachments panel for employees, assets, and knowledge articles.
- Loaded attachment metadata/uploader on detail screens.
- Added web UI coverage for upload, private storage, download, and audit logging.
Reason:
- Make core private file attachments usable from the operations UI, not only the API.
Decision:
- Keep attachments generic/polymorphic and authorize both file permissions and attachable visibility.
Impact:
- Product: operators can attach and retrieve private files on key core records.
- Technical: web downloads reuse the same private storage and audit behavior as API downloads.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue PTO and asset workflow hardening from the implementation plan.

## 2026-07-08 - feature: PTO self-service web workflow

Commit: 9e7e1be
Change:
- Added PTO request submission from the web UI.
- Added pending PTO cancellation from the web UI.
- Scoped web PTO listings to the linked employee unless the user can manage or approve PTO.
- Web approval/rejection now dispatches the same webhook events as the API path.
- Added web UI coverage for self-service submission, forbidden cross-employee submission, visibility, and cancellation.
Reason:
- Complete the PTO tracking flow in the browser instead of leaving creation/cancel paths API-only.
Decision:
- Employee self-service requires an employee record linked to the login user; PTO managers can submit for any employee.
Impact:
- Product: employees can submit and cancel PTO requests from BOLT.
- Technical: web PTO behavior now mirrors API balance tracking and event dispatch.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue asset workflow and API hardening from the implementation plan.

## 2026-07-08 - feature: asset web event parity

Commit: 12d6a7b
Change:
- Web asset create/update/assign/return now dispatch the same webhook events as API asset workflows.
- Added web workflow coverage for asset lifecycle event deliveries.
Reason:
- Keep core webhook behavior consistent regardless of whether work happens through the API or operations UI.
Decision:
- Treat public web workflows as first-class core event producers, not UI-only shortcuts.
Impact:
- Product: external integrations receive asset lifecycle events from browser actions.
- Technical: asset event parity is covered by feature tests.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue API hardening and remaining operations workflow coverage from the implementation plan.

## 2026-07-08 - feature: employee and knowledge web event parity

Commit: 46467be
Change:
- Web employee create/update now dispatch `employee.created` and `employee.updated`.
- Web knowledge article create/update now dispatch `knowledge_article.created` and `knowledge_article.updated`.
- Fixed optional slug handling in web knowledge article creation/update.
- Added web workflow coverage for employee and knowledge event deliveries.
Reason:
- Keep webhook behavior consistent across API and operations UI writes.
Decision:
- All core web writes should produce the same catalog events as equivalent API writes.
Impact:
- Product: integrations receive employee and knowledge changes made from the browser.
- Technical: event parity is covered by tests and the optional slug bug is fixed.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue API hardening and remaining workflow coverage from the implementation plan.

## 2026-07-08 - hardening: stable OpenAPI operation IDs

Commit: abeb190
Change:
- Added centralized Scramble/OpenAPI operation ID mapping.
- Replaced route-name operation IDs with stable camelCase names for agents and generated clients.
- Added documentation coverage for representative IDs and uniqueness.
- Documented operation ID guidance in `ai/api-guide.md` and `technical-definition.md`.
Reason:
- The implementation plan requires agent-friendly operation IDs such as `listEmployees`.
Decision:
- Keep operation IDs centralized in `AppServiceProvider` rather than scattering controller docblocks.
Impact:
- Product: MCP clients and API consumers get predictable OpenAPI operation names.
- Technical: tests guard all operations from missing or duplicate IDs.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue API error/rate-limit hardening and remaining workflow coverage from the implementation plan.

## 2026-07-08 - hardening: API scope and throttle errors

Commit: 987538e
Change:
- Added API coverage for missing Passport scope failures.
- Added API coverage for rate-limit headers and standard `rate_limited` error shape.
- Preserved HTTP exception headers in the JSON error renderer so throttled responses include `Retry-After`.
Reason:
- The implementation plan calls for consistent API errors, scope behavior, and rate-limit headers for agents/MCP clients.
Decision:
- Keep the standard BOLT error envelope while forwarding framework-provided HTTP headers.
Impact:
- Product: API clients receive actionable throttle timing while still parsing `error.code`.
- Technical: scope and rate-limit behavior is now regression-tested.
- Operation: no route changes.
Pending:
- Continue remaining API and workflow hardening from the implementation plan.

## 2026-07-08 - hardening: upload-aware idempotency fingerprints

Commit: cac926a
Change:
- Replaced ad hoc idempotency request hashing with normalized input and file fingerprints.
- File upload fingerprints now include original name, MIME type, size, and SHA-256 content hash.
- Added API coverage for same-key/different-file idempotency conflicts.
- Updated API and technical docs for upload-aware idempotency behavior.
Reason:
- Risky file-upload POST endpoints need reliable conflict detection for agents and API clients.
Decision:
- Keep idempotency replay behavior unchanged, but make payload fingerprints deterministic and explicit.
Impact:
- Product: API clients get safer retry semantics for attachment uploads.
- Technical: multipart upload idempotency behavior is now tested.
- Operation: no route changes.
Pending:
- Continue remaining API and workflow hardening from the implementation plan.

## 2026-07-08 - hardening: sensitive HR and file access boundaries

Commit: d911efa
Change:
- Removed compensation and benefits permissions from the default auditor role.
- Tightened attachment downloads so employee files are limited to the employee, their manager, or managers with file/employee permissions.
- Added API coverage for auditor HR denial and employee-file download boundaries.
Reason:
- Sensitive HR and private files need clearer default boundaries before forks build on the core.
Decision:
- Auditors remain operational/audit readers, not pay/benefits readers.
- Generic file permissions are necessary but not sufficient for employee-file downloads.
Impact:
- Product: default roles better match the intended sensitive-data philosophy.
- Technical: attachment authorization now considers the attached record.
- Operation: projects should rerun `CoreAccessSeeder` when adopting this role-permission change.
Pending:
- Continue remaining permission and workflow hardening from the implementation plan.

## 2026-07-08 - feature: webhook test and replay UI

Commit: d7cf71d
Change:
- Added web action to queue endpoint-specific `webhook.test` deliveries.
- Added web replay action for existing webhook deliveries.
- Expanded webhook endpoint delivery table with response details and replay controls.
- Added web UI coverage for test and replay controls.
Reason:
- Webhook operations should be diagnosable from the browser, not API-only.
Decision:
- Test deliveries target the selected endpoint directly, even if it is not subscribed to `webhook.test`.
- Replay is treated as a webhook management action and requires endpoint update permission.
Impact:
- Product: admins can test and replay webhooks from endpoint detail pages.
- Technical: web UI reuses `WebhookDispatcher` delivery creation and replay behavior.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue remaining API and workflow hardening from the implementation plan.

## 2026-07-08 - feature: webhook operations API parity

Commit: 7461e11
Change:
- Added API action to queue endpoint-specific `webhook.test` deliveries.
- Required idempotency keys for webhook test deliveries and replay requests.
- Aligned API replay authorization with webhook management/update permission.
- Added stable OpenAPI operation ID and API guide coverage for webhook test and replay actions.
Reason:
- Agents and MCP clients need the same operational webhook controls available in the browser.
Decision:
- API test deliveries reuse `WebhookDispatcher::test` and target the selected endpoint directly.
- Replay remains a side-effecting POST and must keep idempotency protection.
Impact:
- Product: API clients can verify webhook endpoint configuration without waiting for a business event.
- Technical: webhook operational actions now share dispatcher behavior across API and web UI.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue remaining API and workflow hardening from the implementation plan.

## 2026-07-08 - feature: owner access management UI

Commit: fd759ab
Change:
- Added owner-admin web UI for reviewing login users, assigning system roles, and linking users to employee records.
- Added audit logging for access changes.
- Prevented demoting the final owner-admin.
- Made primary navigation permission-aware while preserving three-level section hooks.
Reason:
- A polished base app needs a browser control plane for roles and user-to-employee links.
Decision:
- Keep MVP access management scoped to existing login users; user invitation and password reset workflows remain outside this slice.
- Gate the access console with the existing `api.clients.manage` system-configuration permission.
Impact:
- Product: owner-admins can manage core access without direct database edits.
- Technical: navigation now hides inaccessible sections instead of exposing dead-end links.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: owner-created login users

Commit: eddcd5c
Change:
- Added owner-admin create-user screen under Users & roles.
- Required unique email, strong confirmed temporary password, and at least one role.
- Supported employee linking during user creation.
- Audited user creation without recording password material.
Reason:
- A polished MVP should not require Tinker or SQL to create ordinary login users after the local owner-admin bootstrap.
Decision:
- Keep creation manual and explicit; invitation email and password reset workflows are still outside MVP.
Impact:
- Product: owner-admins can bootstrap user access entirely from the browser.
- Technical: access creation reuses the same role and employee-linking rules as access edits.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: account password self-service

Commit: 78a16ff
Change:
- Added authenticated account password screen.
- Required current password plus strong confirmed replacement password.
- Regenerated the session after password changes.
- Audited password updates without recording password material.
Reason:
- Owner-created temporary passwords need an in-app path for users to take ownership of their credentials.
Decision:
- Keep this as direct password self-service; email-based resets and forced password rotation remain outside MVP.
Impact:
- Product: users can update their own login password from the browser.
- Technical: auth audit coverage now includes self-service password changes.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - hardening: PTO remaining balance enforcement

Commit: 1e03cbb
Change:
- Extracted PTO balance updates into `PtoBalanceService`.
- Initialized annual balances from the PTO policy allowance.
- Used the request start year for PTO balance periods.
- Blocked PTO requests that exceed remaining available minus pending hours.
Reason:
- PTO balances need trustworthy remaining-hours behavior for a polished HR MVP.
Decision:
- Pending requests reserve capacity without decrementing available hours; approvals decrement available and increment used.
Impact:
- Product: employees get validation before over-requesting PTO.
- Technical: API and web PTO workflows share one balance service.
- Operation: generated app and route snapshots were refreshed.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - hardening: PTO overdraw validation without residue

Commit: c53a217
Change:
- Adjusted PTO overdraw checks so rejected requests do not create balance rows.
- Added regression coverage that overdraw attempts leave both requests and balances untouched.
Reason:
- Validation failures should not leave operational records behind.
Decision:
- Estimate remaining balance from the policy allowance when no balance row exists.
Impact:
- Product: failed PTO submissions keep the operations ledger clean.
- Technical: `PtoBalanceService` now separates balance lookup from balance creation.
- Operation: no route or snapshot changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: API token management UI

Commit: a770d64
Change:
- Added owner-admin API token screen under Platform > Access.
- Added scoped Passport personal access token creation with one-time token display.
- Added token revocation from the browser.
- Audited API token creation and revocation without storing token material.
- Added end-to-end coverage proving created tokens can call `/api/v1` and revoked tokens are denied.
Reason:
- A polished agent-ready MVP needs browser provisioning for MCP/client bearer tokens.
Decision:
- Use Passport personal access tokens for agent/API clients and auto-create the BOLT personal access client when missing.
Impact:
- Product: owner-admins can provision and revoke scoped API tokens without artisan commands or database access.
- Technical: token operations are gated by `api.clients.manage` and share the Access navigation section.
- Operation: generated route snapshot and API guide were refreshed.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: PTO balance visibility

Commit: e4ce0de
Change:
- Added PTO balance table to the web PTO screen.
- Added `/api/v1/pto-balances` with scoped visibility for employees versus PTO managers/approvers.
- Added `PtoBalanceResource` with available, pending, used, remaining, and period fields.
- Added OpenAPI operation ID and API guide coverage for balance reads.
Reason:
- Enforced PTO balances need to be visible to people and agents before requests are submitted.
Decision:
- Reuse PTO request visibility rules for balance access.
Impact:
- Product: employees and managers can inspect PTO balances from the browser and API.
- Technical: PTO balance data is now an agent-readable API resource.
- Operation: generated route snapshot was refreshed.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - hardening: operational log retention

Commit: 0bca1a5
Change:
- Added `bolt:prune-operational-logs` with `--dry-run`.
- Added configurable audit and webhook delivery retention windows.
- Scheduled daily pruning alongside webhook retry scheduling.
- Added regression coverage for dry-run preservation and old-record pruning.
Reason:
- A polished deployable MVP needs bounded operational log growth.
Decision:
- Prune only audit logs and webhook delivery logs; keep endpoint configuration and newer operational records intact.
Impact:
- Product: long-running installs have safer default operational hygiene.
- Technical: retention is configurable through `.env` and covered by tests.
- Operation: production scheduler now handles retries and retention pruning.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: searchable knowledge base

Commit: bf848e5
Change:
- Added shared knowledge article search and filter scopes.
- Added web filters for query, status, category, and tag.
- Added API query parameters `q`, `status`, `category`, and `tag` for agent retrieval.
- Added web/API coverage for filtered knowledge results.
Reason:
- The knowledge base is a core agent-facing surface and needs precise retrieval, not just pagination.
Decision:
- Reuse the same model scopes for web and API filtering.
Impact:
- Product: people and agents can quickly find SOPs by content and metadata.
- Technical: knowledge retrieval now has tested filter semantics across browser and API.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: searchable asset inventory

Commit: 0cf7a37
Change:
- Added shared asset search and filter scopes.
- Added web filters for query, status, category, and current assignee.
- Added API query parameters `q`, `status`, `category`, and `assigned_to` for inventory retrieval.
- Added web/API coverage for filtered asset results.
- Added a reusable employee `full_name` accessor for assignment labels.
Reason:
- Asset inventory needs fast lookup by lifecycle state, metadata, and current assignment as deployments grow.
Decision:
- Treat current assignee filtering as an open assignment where `returned_at` is null.
Impact:
- Product: admins can quickly locate assigned equipment in the browser and agents can retrieve inventory through the API.
- Technical: asset retrieval now shares tested scopes across web and API.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: searchable employee directory

Commit: 98c57d5
Change:
- Added shared employee search and filter scopes.
- Added web filters for query, status, department, and manager.
- Added API query parameters `q`, `status`, `department`, and `manager_id` for directory retrieval.
- Added web/API coverage for filtered employee results.
Reason:
- Employee records are the center of BOLT operations and need fast retrieval as a business grows.
Decision:
- Use the same model scopes for web and API employee directory filtering.
Impact:
- Product: admins and HR can quickly find people by role, department, status, and reporting line.
- Technical: employee retrieval now has tested filter semantics across browser and API.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: filterable PTO requests

Commit: a662f6d
Change:
- Added shared PTO request filter scopes.
- Added web filters for status, employee, policy, and start-date window.
- Added API query parameters `status`, `employee_id`, `pto_policy_id`, `starts_from`, and `starts_until`.
- Scoped PTO employee filter options to the current self-service user unless the user can review all PTO.
- Added web/API coverage for filtered PTO request results.
Reason:
- PTO queues need fast review by status, person, policy, and date range as the request history grows.
Decision:
- Preserve existing PTO visibility rules first, then apply request filters within that authorized result set.
Impact:
- Product: managers and HR can review PTO queues faster, while employees do not see other employees in self-service filters.
- Technical: PTO request retrieval now has tested filter semantics across browser and API.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: filterable audit log

Commit: 0d1f7ce
Change:
- Added shared audit log filter scopes.
- Added web filters for event, actor, auditable type/id, and occurrence date window.
- Added API query parameters `event`, `actor_id`, `auditable_type`, `auditable_id`, `occurred_from`, and `occurred_until`.
- Added web/API coverage for filtered audit log results.
Reason:
- Operational diagnostics need targeted audit review as event volume grows.
Decision:
- Keep audit filtering generic around event and auditable targets so core and custom modules can share the same tool.
Impact:
- Product: admins and auditors can investigate operational history without scanning the full log.
- Technical: audit retrieval now has tested filter semantics across browser and API.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: filterable webhook operations

Commit: 87310ff
Change:
- Added shared webhook endpoint search/subscription filters.
- Added shared webhook delivery status/event/date filters.
- Added web filters for endpoint discovery and delivery triage.
- Added API query parameters for endpoint and delivery filtering.
- Added web/API coverage for filtered webhook results.
Reason:
- Webhook operations need fast subscription lookup and failed delivery triage as integrations grow.
Decision:
- Filter endpoints separately from per-endpoint deliveries to match the existing operational workflow.
Impact:
- Product: admins can locate subscriptions and investigate delivery history without scanning full lists.
- Technical: webhook retrieval now has tested filter semantics across browser and API.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: filterable access management

Commit: 789f77e
Change:
- Added owner-admin filters for login users by search, role, and employee-link state.
- Added owner-admin filters for API tokens by search, acting user, active/revoked status, and scope.
- Removed the token list dependency on Passport's brittle token-user relationship by using an explicit user map.
- Added access UI coverage for filtered user and token results.
Reason:
- Access management is security-sensitive and needs fast review as users and agent tokens grow.
Decision:
- Keep filtering in the owner-admin web UI because BOLT does not currently expose access-management API endpoints.
Impact:
- Product: owner-admins can audit users and tokens without scanning full lists.
- Technical: token listing is more robust in local/test environments where Passport token provider resolution can be brittle.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: filterable HR history

Commit: 4f6aa43
Change:
- Added compensation history filters by type and effective-date window.
- Added benefit history filters by type and start-date window.
- Added matching API query parameters for authorized HR history reads.
- Added employee detail filter controls for compensation and benefits panels.
- Added web/API coverage for filtered HR history results.
Reason:
- Sensitive HR timelines need targeted review as compensation, benefit, and bonus history grows.
Decision:
- Reuse existing employee HR policies and apply filters only inside authorized compensation/benefit result sets.
Impact:
- Product: HR/admins can inspect pay and benefit timelines without scanning full histories.
- Technical: HR history retrieval now has tested filter semantics across browser and API.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: manage PTO policies

Commit: 7d0d819
Change:
- Added policy-protected web management for PTO policies.
- Added API listing, detail, create, and update routes for PTO policies.
- Added request/resource/controller coverage and OpenAPI operation IDs for PTO policy endpoints.
- Added webhook catalog events for PTO policy creation and updates.
- Added tests for PTO policy permissions and default-policy handoff in web and API flows.
Reason:
- PTO configuration is an MVP core surface and should not depend only on seeded records.
Decision:
- Reuse the existing PTO management permission and keep one default policy by clearing older defaults when a new default is saved.
Impact:
- Product: HR/admin users can configure PTO policies from the UI and API.
- Technical: PTO policy management now uses the same policies, scopes, audit, webhook, and idempotency patterns as other core modules.
- Operation: adds PTO policy web/API routes and two webhook event names.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - feature: enforce PTO manager scope

Commit: 0cae843
Change:
- Added shared PTO request and balance visibility scopes.
- Limited manager PTO queues to direct reports while preserving full HR/admin review access.
- Enforced PTO approval strategy so manager approvals apply only to direct reports and HR-only policies require PTO management permission.
- Added API and web coverage for manager visibility and approval boundaries.
Reason:
- PTO approval paths should reflect org structure instead of allowing any manager to review any employee.
Decision:
- Treat `pto.manage` as the HR/admin override and `pto.approve` as manager approval only for direct reports unless the policy is HR-only.
Impact:
- Product: employees, managers, and HR/admin users now see PTO queues that match their role in the approval process.
- Technical: web and API PTO listings share the same visibility scopes.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - docs: add OSS repo readiness files

Commit: f303456
Change:
- Added MIT license text for the public base app.
- Added contributing guidance that points humans and agents to living docs and verification commands.
- Added a security policy covering private reporting, supported line, secret handling, and deploy safety.
- Expanded README with MVP scope and public repo links.
Reason:
- A polished public open-source base needs clear entry points for adopters, contributors, and security-sensitive usage.
Decision:
- Keep the docs concise and aligned with the existing public repo/no-secrets guidance.
Impact:
- Product: adopters can understand the MVP surface and customization posture from the README.
- Technical: contributors have a single checklist for docs, tests, snapshots, and journal updates.
- Operation: no route or runtime changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - ci: add public repo verification workflow

Commit: d97e7fa
Change:
- Added a GitHub Actions CI workflow for Composer install, npm install, environment prep, Pint, PHPUnit, and Vite build.
- Linked the CI workflow from the public repo section in the README.
Reason:
- A public base app needs an automated quality gate for contributors, customized forks, and agent-authored patches.
Decision:
- Use SQLite-backed PHPUnit configuration and avoid requiring external database services in CI.
Impact:
- Product: adopters can see the expected verification bar for changes.
- Technical: pull requests and main pushes can run the same checks used locally.
- Operation: CI generates local app and Passport keys only inside the GitHub runner.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - fix: authorize API attachment targets

Commit: aaf73e5
Change:
- Added target-record policy authorization to API attachment uploads.
- Added regression coverage for a file manager attempting to attach to an employee they cannot view.
- Documented target-policy checks for attachment uploads.
Reason:
- Generic polymorphic upload endpoints must not allow users to attach files to records they cannot access.
Decision:
- Match the existing web upload behavior by authorizing the resolved attachable before storing the file.
Impact:
- Product: private business files remain tied to the same access boundaries as their parent records.
- Technical: API and web attachment upload behavior now share the same target authorization rule.
- Operation: no route changes.
Pending:
- Continue polishing remaining MVP workflow gaps.

## 2026-07-08 - docs: add MVP master plan

Commit: dab2875
Change:
- Added `docs/mvp-plan.md` as the active user-perspective MVP checklist.
- Documented current MVP feature areas, the webhook focus, existing webhook functionality, and remaining webhook MVP tasks.
- Linked the tracker from README and agent guidance files.
Reason:
- The project needs a stable planning document that keeps humans and agents oriented after rapid implementation passes.
Decision:
- Keep `project-journal.md` as the history log and use checkbox tracking in the new plan document for forward work.
Impact:
- Product: current and remaining MVP work is easier to review from a user workflow perspective.
- Technical: agents now have a clear next-focus document before editing implementation.
- Operation: no runtime changes.
Pending:
- Implement the webhook MVP completion tasks next.

## 2026-07-08 - docs: add settings and webhook retention tasks

Commit: 62e59f8
Change:
- Added system settings/configuration as an MVP feature area.
- Added webhook retention tasks for a default 10,000 total historical delivery cap and cleanup job/command.
- Added worker configuration guidance as part of the settings surface.
- Updated product and technical definitions to reflect settings and webhook history cap pruning.
Reason:
- Webhook delivery history can grow quickly and needs a minimal, polished operational control in the boilerplate.
Decision:
- Track this as part of the webhook MVP completion work while keeping implementation for a later coding pass.
Impact:
- Product: owner-admins will have a clear settings destination for operational configuration.
- Technical: webhook cleanup requirements now include max-count pruning in addition to existing date-based retention.
- Operation: no runtime changes in this docs-only checkpoint.
Pending:
- Implement settings UI and webhook max-history pruning during the webhook MVP work.

## 2026-07-08 - feat: polish webhook MVP

Commit: 61fac84
Change:
- Added webhook delivery detail views in the web UI and API.
- Added endpoint health indicators, disabled endpoint guards for test/replay actions, and clearer secret preservation/rotation copy.
- Made exception-based delivery failures increment endpoint failure counts and disable endpoints after the configured max attempts.
- Added Settings UI for webhook history limit and queue worker guidance.
- Added `bolt:prune-webhook-deliveries` and daily scheduling to prune oldest deliveries beyond the configured total cap.
- Marked webhook MVP completion tasks done in `docs/mvp-plan.md` and updated webhook API/technical docs.
Reason:
- Webhooks needed the final minimum polish pass: inspectability, clear failure state, safer disabled behavior, and bounded history growth.
Decision:
- Keep the MVP focused on one endpoint health surface, one delivery detail page, one retention setting, and one pruning command instead of adding broader integration-management features.
Impact:
- Product: admins can understand endpoint health, inspect individual deliveries, and control delivery history growth.
- Technical: API clients can fetch delivery detail, disabled endpoint behavior is enforced in web/API/service paths, and retention has focused tests.
- Operation: run queue workers for delivery jobs and `php artisan schedule:run` for retry/prune automation; snapshots were refreshed after the implementation commit.
Pending:
- Move to the next MVP focus area after reviewing audit/diagnostics polish.

## 2026-07-08 - feat: polish employee onboarding

Commit: 1854c5d
Change:
- Added managed Departments, Positions, and Compensation Packages under the People menu with web/API surfaces.
- Converted employee Department and Position fields to reference-backed dropdowns with quick-create modals.
- Added employee onboarding support for creating or linking login users, seeding compensation history from a package, and creating starting PTO balances.
- Added optional encrypted private HR details and emergency contact fields with HR/admin-only visibility.
- Kept employee API compatibility for legacy `department` and `title` strings while exposing `department_id` and `position_id`.
- Updated MVP, product, technical, and AI API docs for the People setup slice.
Reason:
- Employee onboarding was still too thin for a polished boilerplate and relied on free-text organization data.
Decision:
- Keep departments, positions, and compensation packages lightweight reference records; snapshot compensation package values into normal employee history instead of linking history to mutable packages.
Impact:
- Product: admins can complete a richer but still skippable employee onboarding workflow from one screen.
- Technical: employee APIs now support managed references and optional onboarding side effects while preserving old string payloads.
- Operation: run the new migration to create People reference tables and backfill existing employee department/title strings.
Pending:
- Move to the next MVP focus area after reviewing audit/diagnostics polish.

## 2026-07-08 - fix: center quick-create modals

Commit: 1726b7d
Change:
- Added a reusable centered modal dialog class with backdrop styling.
- Applied it to the employee Department and Position quick-create dialogs.
Reason:
- Quick-create modals must behave like polished base-framework UI, not appear anchored near the page edge.
Decision:
- Keep the fix small and reusable for future native dialog modals instead of introducing a broader modal component.
Impact:
- Product: employee setup quick-create dialogs now open centered and feel consistent.
- Technical: future native dialogs can use the shared `modal-dialog` class.
- Operation: no route or app snapshot changes were produced after regeneration.
Pending:
- Continue the Employee/People MVP polish queue.

## 2026-07-08 - feat: polish employee onboarding form

Commit: 3ee23d9
Change:
- Derived create-login-user name and email from the employee name and work email instead of asking twice.
- Added employee-form quick-create support for compensation packages.
- Changed employee onboarding starting PTO input to whole or half days and derived the hidden balance period from the employee start date.
- Updated the MVP tracker and focused web UI tests for the refined onboarding behavior.
Reason:
- The employee create screen should stay minimal and obvious: no duplicate identity fields, no raw PTO hour math, and no extra period dates during onboarding.
Decision:
- Keep the existing PTO storage/API hour model for now, but make the web onboarding shortcut day-based and convert to hours internally.
Impact:
- Product: admins can create an employee, login user, compensation package, and starting PTO top-off with less duplicated or confusing input.
- Technical: quick-create selects now support richer option labels, compensation package create can return JSON, and starting PTO derives its balance period from `start_date`.
- Operation: no route or app snapshot changes were produced after regeneration.
Pending:
- Continue Employee/People review, then move to PTO final polish for the broader hour-vs-day domain decision.

## 2026-07-08 - feat: clarify compensation package pay terms

Commit: 75b1ee8
Change:
- Added amount basis (`annual`, `monthly`, `hourly`) and payment frequency (`monthly`, `bimonthly`, `biweekly`) to compensation packages.
- Updated compensation package web/API create and edit flows, employee onboarding package labels, and quick-create modal fields.
- Included amount basis and payment frequency in API resources and compensation-history snapshot notes.
- Updated MVP/product/technical/API docs and focused tests.
Reason:
- Compensation package amounts were ambiguous without saying whether the amount was yearly, monthly, or hourly and how often the employee is paid.
Decision:
- Default existing/legacy packages to annual amount and monthly pay, while requiring explicit choices in the web UI.
Impact:
- Product: HR admins can understand and edit compensation packages without guessing pay terms.
- Technical: package metadata is persisted, API-visible, validated, and included in onboarding snapshots.
- Operation: run `php artisan migrate` to add the new package fields; local dev migration was applied and snapshots did not change.
Pending:
- Continue Employee/People review, then revisit broader PTO day/hour semantics.

## 2026-07-08 - feat: convert PTO to day-based tracking

Commit: 2115613
Change:
- Converted PTO policies, balances, requests, onboarding top-offs, web UI, API resources, and API write validation from hours to days.
- Added PTO accumulation frequency with monthly, twice-monthly, and every-other-week options.
- Updated generated balance periods and balance math to use day buckets based on the selected accumulation frequency.
- Updated seed defaults, MVP/product/technical/API docs, and focused web/API tests for day-based PTO.
Reason:
- PTO should be simple for small businesses to reason about: full-day and half-day increments, not raw hour math.
Decision:
- Break the MVP API contract now so PTO is day-first before the public boilerplate hardens.
- Keep the existing accrual type field but add accumulation frequency as the period selector for generated balances.
Impact:
- Product: employees, managers, HR, API clients, and agents now request and inspect PTO in days only.
- Technical: final PTO schema uses day-based columns and exposes `days` fields; existing local hour values are migrated by dividing by eight.
- Operation: run `php artisan migrate` to convert existing PTO data; local dev migration was applied.
Pending:
- Move to the next MVP focus area after reviewing the remaining queue.

## 2026-07-08 - feat: add employee photos and org charts

Commit: 3eabdbb
Change:
- Added private employee photo upload/removal with policy-checked display in employee profiles, directory rows, and charts.
- Split People navigation into a people org chart for manager reporting lines and a department chart for nested department hierarchy.
- Reworked both charts to render node-and-line org chart structures instead of indented lists.
- Updated MVP/product/technical docs and focused web UI tests for photos and both chart pages.
Reason:
- Employee records need recognizable photos, and the People area needs separate visualizations for department hierarchy and person-to-person reporting.
Decision:
- Keep employee photos on the private local disk and serve them through the existing employee `view` policy rather than exposing public storage URLs.
- Use lightweight nested-list chart markup and shared CSS connector lines instead of adding a broad charting dependency for the MVP.
Impact:
- Product: admins can view a clearer department chart, a distinct people org chart, and employee avatars throughout core People screens.
- Technical: employees now store `photo_path`, expose recursive reports for chart rendering, and add web routes for people chart and policy-gated photos.
- Operation: run `php artisan migrate` to add `employees.photo_path`; local dev migration was applied and app/route snapshots were refreshed.
Pending:
- Continue Employee/People review, then revisit broader PTO day/hour semantics.

## 2026-07-08 - feat: add department hierarchy and org chart

Commit: 6cc59d1
Change:
- Added parent-child department relationships with cycle prevention in web and API writes.
- Added a Department org chart view under People > Setup that renders nested departments and assigned employees.
- Updated department management, employee onboarding department selectors, quick-create department labels, API resources, docs, and tests.
Reason:
- Flat departments could not represent real structures such as Digital Marketing inside Marketing inside Sales.
Decision:
- Keep this as lightweight hierarchy on the existing Department model instead of introducing a separate organization-unit model.
Impact:
- Product: admins can manage nested departments and view the department org chart.
- Technical: departments expose `parent_id`, `parent_name`, and `path`; web/API writes reject cycles.
- Operation: run `php artisan migrate` to add `departments.parent_id`; local dev migration was applied and `routes.md` was refreshed.
Pending:
- Continue Employee/People review, then revisit broader PTO day/hour semantics.

## 2026-07-09 - feat: polish PTO workflow

Commit: this commit
Change:
- Added PTO policy working days, holiday exclusions, and explicit negative-balance behavior.
- Split PTO request submission into its own page and changed web requests to calculate days from policy calendars plus half-day flags.
- Reordered the PTO dashboard around pending approvals, a three-month approved-absence calendar, request history, balances, and audited manual adjustments.
- Added manager/admin manual PTO adjustments, API-visible policy calendar fields, MVP/product/technical/API docs, and focused regression tests.
Reason:
- PTO should be simple and trustworthy for small businesses: users should not type PTO math by hand, and over-balance behavior must be explicit.
Decision:
- Keep API PTO requests day-first with direct `days` submission for now, while documenting that API clients should apply the same policy calendar rules before posting.
- Scope manager manual adjustments to direct reports and keep HR/admin users able to adjust any visible employee balance.
Impact:
- Product: employees submit PTO from dates, managers see a clearer approval queue and calendar, and authorized users can correct balances without database access.
- Technical: policies now store calendar/negative-balance settings, balance math supports allowed negative balances, and adjustments are auditable model records.
- Operation: run `php artisan migrate` to add PTO policy calendar fields and `pto_adjustments`; local dev migration was applied and app/route snapshots were refreshed.
Pending:
- Move to the next MVP focus area from `docs/mvp-plan.md`.

## 2026-07-09 - feat: complete asset lifecycle MVP

Commit: this commit
Change:
- Added private primary asset photos with policy-checked display in asset list/detail views.
- Added asset lifecycle history events for notes, condition, delivery, assignment, return, repairs, audits, and observations.
- Connected assignment and return workflows to timeline events with from/to holder, actor, timestamp, condition, notes, and optional files.
- Added asset history API listing/creation, current-holder/photo metadata on asset resources, docs, and focused web/API tests.
Reason:
- Asset tracking needs a polished handoff and condition record, not only an inventory row and assignment table.
Decision:
- Keep asset photos on the private local disk and expose only photo metadata through the API.
- Reuse private attachments for asset history photos/files by allowing `asset_events` as an attachment target.
Impact:
- Product: admins can recognize assets visually, record photo-backed handoffs, and audit an asset's lifecycle from one detail page.
- Technical: assets now have `photo_path`, lifecycle events are first-class audited records, and API clients can manage asset history.
- Operation: run `php artisan migrate` to add `assets.photo_path` and `asset_events`; local dev migration was applied and app/route snapshots were refreshed.
Pending:
- Select the next MVP focus area from `docs/mvp-plan.md`.

## 2026-07-09 - fix: allow multiple asset photos

Commit: this commit
Change:
- Changed asset create/edit photo uploads from one image to multiple private images.
- Store each uploaded asset photo as a private attachment while using the first uploaded image as the primary display photo.
- Updated asset MVP/product/technical/API docs and the asset web workflow test.
Reason:
- Asset records often need more than one recognition or condition photo, especially for equipment with multiple sides or accessories.
Decision:
- Keep the primary `photo_path` for fast list/detail display and reuse private attachments for the full set of uploaded asset photos.
Impact:
- Product: admins can upload several photos during asset create/edit without leaving the asset form.
- Technical: asset photos remain policy-checked private files and are not exposed as public URLs.
- Operation: no migration required.
Pending:
- Continue the next MVP focus selection from `docs/mvp-plan.md`.

## 2026-07-10 - fix: add searchable knowledge taxonomy fields

Commit: this commit
Change:
- Replaced the knowledge category select with a searchable single-value combobox.
- Replaced the fixed tag checkbox grid with searchable multi-select suggestions and removable tag chips.
Reason:
- Knowledge bases can accumulate hundreds of categories and tags, making fixed lists slow to scan and difficult to manage.
Decision:
- Keep standard hidden form values as the server contract while progressively enhancing selection in the browser.
Impact:
- Product: authors can quickly find categories and add or remove tags without scrolling through large catalogs.
- Technical: the knowledge form now has reusable accessible combobox behavior with keyboard filtering and duplicate prevention.
- Operation: no migration required.
Verification:
- `./vendor/bin/pint`
- `php artisan test` (97 tests, 783 assertions)
- `npm run build`
- Browser verification confirmed category filtering, tag filtering, add/remove behavior, canonical form values, and no desktop overflow.
Pending:
- Continue the next MVP focus selection from `docs/mvp-plan.md`.

## 2026-07-10 - feat: add smart knowledge traversal

Commit: this commit
Change:
- Added optional curated excerpts with deterministic Markdown previews and immutable version snapshots.
- Promoted knowledge categories to hierarchical records with migration backfill, guarded management, human browsing, and category digest/index APIs.
- Added `@` article lookup, stable internal Markdown links, directed link synchronization, backlinks, indexed search, and policy-filtered traversal APIs.
Reason:
- The knowledge base needs to be searchable and explorable by humans and agents, not only a collection of tagged Markdown documents.
Decision:
- Keep one primary category per article, derive directed edges from internal Markdown links, and use MySQL full-text search without adding embeddings or a vector database.
Impact:
- Product: users can curate summaries, browse a category tree, link related knowledge, follow backlinks, and search the same indexed corpus agents use.
- Technical: run `php artisan migrate` to create/backfill knowledge categories, excerpts, the full-text index, version metadata, and article links.
- API: agents gain category list/detail/digest/index, tag catalog, missing-excerpt filters, relationship filters, and link traversal under existing knowledge scopes.
Verification:
- `./vendor/bin/pint`
- `php artisan test` (102 tests, 828 assertions)
- `npm run build`
- Local MySQL migration/backfill and full-text search verified; browser verification covered category setup/browsing, excerpt counter, `@` lookup/insertion, indexed search, cleanup, no console errors, and no horizontal overflow.
Pending:
- Select the next MVP focus from `docs/mvp-plan.md`.

## 2026-07-10 - feat: manage knowledge categories and tags

Commit: this commit
Change:
- Added Knowledge > Setup > Categories & tags with create, rename, delete, article usage counts, and audit events.
- Replaced the category autocomplete input with the same select control used elsewhere in BOLT.
- Replaced comma-group tag autocomplete with a reusable multi-tag checkbox picker.
- Normalized legacy comma-grouped tags into individual values and propagated taxonomy rename/delete changes to existing articles.
Reason:
- Browser-native autocomplete looked inconsistent and could suggest entire comma-separated tag groups instead of reusable individual tags.
Impact:
- Product: knowledge managers can curate category/tag choices centrally and editors select consistent reusable values.
- Technical: catalogs use system settings while article records remain the source of usage counts; no migration or API change is required.
Verification:
- `./vendor/bin/pint`
- `php artisan test` (97 tests, 781 assertions)
- `npm run build`
- Browser verification confirmed matching category/status controls, unique tag values, and no desktop overflow.
Pending:
- Select the next MVP focus from `docs/mvp-plan.md`.

## 2026-07-10 - fix: add Markdown editor cheatsheet

Commit: this commit
Change:
- Added an accessible question-mark help action beside the Markdown editor label.
- Added a centered, responsive cheatsheet dialog covering every supported Markdown syntax family with copy-ready examples.
- Added reusable dialog open/close behavior and Lucide help/close icons.
Reason:
- Knowledge editors need a quick syntax reference without leaving or losing their article draft.
Impact:
- Product: editors can review supported headings, emphasis, lists, tasks, links, quotes, code, and tables in context.
- Technical: no migration or API change required.
Verification:
- `./vendor/bin/pint`
- `php artisan test` (94 tests, 754 assertions)
- `npm run build`
- Desktop and mobile browser verification for centering, overflow, and both close controls.
Pending:
- Select the next MVP focus from `docs/mvp-plan.md`.

## 2026-07-09 - feat: complete knowledge base MVP

Commit: this commit
Change:
- Added UTF-8 Markdown import with retained private source files, collision-safe slugs, and matching web/API workflows.
- Added secure GitHub-flavored Markdown rendering, Write/Preview authoring, polished reading/index layouts, and responsive table/code handling.
- Added published-only reader visibility, draft attachment protection, immutable article snapshots, version history, and review-first restoration.
- Added knowledge import/version API routes, attachment/version metadata, explicit audits, and focused end-to-end tests.
- Corrected shared mobile header and closed-menu overflow discovered during responsive browser verification.
Reason:
- The existing knowledge base stored useful Markdown but displayed raw source and did not protect drafts or preserve real revision history.
Decision:
- Keep Markdown as the canonical API format and render HTML only in the web UI through one hardened CommonMark service.
- Retain imported source files as article-level private attachments and require an explicit save before restored content becomes current.
Impact:
- Product: teams can import, preview, publish, read, search, and safely revise operational knowledge from polished web surfaces.
- Technical: run `php artisan migrate` to create and backfill `knowledge_article_versions`; API clients gain import and version-list routes.
- Security: ordinary readers and agents only receive published articles, including through direct attachment access.
Verification:
- `./vendor/bin/pint`
- `php artisan test` (93 tests, 749 assertions)
- `npm run build`
- Browser verification at desktop and mobile widths with no console errors or horizontal overflow.
Pending:
- Select the next MVP focus from `docs/mvp-plan.md`.

## 2026-07-09 - feat: add asset tag management

Commit: this commit
Change:
- Added Operations > Setup > Asset tags for creating, renaming, and deleting reusable asset labels.
- Asset form suggestions now include managed tags, active asset tags, and legacy categories.
- Renaming or deleting a tag updates matching existing assets so inventory filters stay coherent.
Reason:
- Once asset tags are visible reusable choices, admins need a deliberate place to curate them.
Decision:
- Store the lightweight tag catalog in system settings and keep asset records as the source of assignment/use counts.
Impact:
- Product: admins can manage asset tags without editing individual assets one by one.
- Technical: managed tags live in `SystemSetting::ASSET_TAGS`; asset records keep their per-record tag arrays.
- Operation: no migration required.
Pending:
- Continue the next MVP focus selection from `docs/mvp-plan.md`.

## 2026-07-09 - fix: clarify asset photo and tag inputs

Commit: this commit
Change:
- Replaced the native multiple-photo picker with repeatable asset photo inputs and an explicit "Add another photo" action.
- Made asset tag suggestions visible as clickable chips and loaded suggestions from both new `tags` data and legacy categories.
Reason:
- Browser file inputs can make multi-file selection feel hidden, and migrated category data should still appear as reusable tag suggestions.
Decision:
- Keep the backend `photos[]` contract and improve the web form interaction rather than adding a larger media manager.
Impact:
- Product: admins can clearly add several asset photos and reuse existing asset labels from the create/edit form.
- Technical: asset tag suggestion loading remains backward-compatible with pre-tags asset records.
- Operation: no migration required.
Pending:
- Continue the next MVP focus selection from `docs/mvp-plan.md`.

## 2026-07-09 - fix: show currency in money fields

Commit: this commit
Change:
- Added a shared money input component with a fixed left-aligned currency prefix.
- Replaced separate currency displays in asset cost and compensation amount forms with the prefixed amount control.
Reason:
- The MVP uses one platform currency, so monetary forms should feel like single amount fields instead of asking users to reason about currency per record.
Decision:
- Keep Platform > Settings as the only place to manage the main currency and derive the visible prefix from that setting.
Impact:
- Product: asset cost and compensation amount entry is cleaner and more consistent.
- Technical: monetary form markup now reuses `x-money-input`.
- Operation: no migration required.
Pending:
- Continue the next MVP focus selection from `docs/mvp-plan.md`.

## 2026-07-09 - feat: simplify asset tags and currency

Commit: this commit
Change:
- Replaced visible asset tag/category inputs with reusable comma-separated asset tags while keeping an internal generated system asset ID.
- Added a Platform > Settings main currency and applied it to asset costs, compensation packages, and compensation history writes.
- Updated asset API resources/filters to expose `tags` while preserving legacy `category` filtering compatibility.
Reason:
- The MVP should avoid per-record currency complexity and should treat asset labels as reusable tags rather than separate tag/category concepts.
Decision:
- Keep `asset_tag` as an internal stable identifier for compatibility, but stop asking users to manually enter it in the web asset form.
- Keep existing currency columns as stored snapshots, but write them from the platform main currency setting.
Impact:
- Product: asset creation is simpler, asset inventory filters by tag, and money fields use one deployment currency.
- Technical: assets now store a `tags` JSON array and settings include `main_currency`.
- Operation: run `php artisan migrate` to add `assets.tags`.
Pending:
- Continue the next MVP focus selection from `docs/mvp-plan.md`.

## 2026-07-10 - fix: harden BOLT for v1 release

Commit: this commit
Change:
- Closed employee and audit API privacy gaps and rejected orphan attachment uploads.
- Added a BOLT OAuth consent screen, full public-client PKCE verification, and interactive first-production-owner bootstrap.
- Added MySQL 8 CI, production backup/rollback/worker guidance, release packaging, and regression/documentation coverage.
Reason:
- The feature-complete MVP still needed security-boundary and operational proof before it could be shipped as a public v1 boilerplate.
Decision:
- Keep audit events visible to auditors while filtering values they are not permitted to read.
- Keep production bootstrap interactive and one-time so credentials never appear in shell arguments or the repository.
Impact:
- Product: external apps can complete reusable login and new deployments can safely create their first owner.
- Technical: SQLite and MySQL CI now cover the app, including production-only Knowledge full-text behavior.
- Operation: releases now have explicit security, backup, recovery, verification, and versioning gates.
Verification:
- `./vendor/bin/pint --test`
- `php artisan test` (106 tests, 882 assertions)
- Full `php artisan test` on local MySQL 8.4.9 (106 tests, 882 assertions), including clean migrations and Knowledge full-text search
- `npm run build`
- `composer validate --strict`, Composer advisory audit, and npm production audit
- Route and Blade production caches, workflow YAML, documentation checks, and GitHub private vulnerability reporting
Pending:
- Run the v1 release checklist, merge green CI, and tag `v1.0.0` only after staging verification.

## 2026-07-10 - ci: preserve empty unit test suite

Commit: this commit
Change:
- Added a tracked placeholder in `tests/Unit` so PHPUnit's declared unit suite exists in clean GitHub Actions checkouts.
Reason:
- Local worktrees retained the empty directory, but Git did not; both CI jobs exited before running tests.
Impact:
- Operation: clean clones now execute the same SQLite and MySQL suites that pass locally.
Pending:
- Confirm the follow-up GitHub Actions run is green.

## 2026-07-10 - ci: isolate tests from built assets

Commit: this commit
Change:
- Disabled Vite asset resolution in the shared test case while retaining the independent production build job.
Reason:
- Local tests found an ignored Vite manifest, but clean SQLite and MySQL CI checkouts correctly had no build output before tests ran.
Impact:
- Technical: application tests no longer depend on generated frontend artifacts; `npm run build` remains the production asset gate.
Pending:
- None. GitHub Actions run `29132328081` passed SQLite, Pint, the production build, and MySQL 8.

## 2026-07-10 - ci: update GitHub action runtimes

Commit: this commit
Change:
- Updated checkout and Node setup actions to their Node 24-compatible major versions.
Reason:
- The green release-candidate run still emitted Node 20 action-runtime deprecation warnings.
Impact:
- Operation: CI remains warning-free on the current GitHub-hosted runner runtime.
Pending:
- Confirm the action-runtime-only follow-up remains green.

## 2026-07-21 - chore: establish independent BOLT repository

Commit: this commit
Change:
- Separated BOLT from the Kodifica prompt/template repository and established BOLT as its own public project with a clean Git history.
- Removed Kodifica-owned prompt and generic template files from BOLT and changed the Composer package identity to `hounw/bolt`.
- Preserved the latest BOLT local-development, private-downstream adoption, deployment, and documentation improvements in the new repository baseline.
Reason:
- BOLT is an independent open-source operations toolkit, while Kodifica is a standalone vibe-coding framework; sharing one repository incorrectly coupled their products and release histories.
Impact:
- Product: BOLT and Kodifica can now evolve and release independently.
- Technical: BOLT starts from a clean root commit without Kodifica history or framework files.
- Operation: existing private BOLT-derived projects remain valid downstreams and can repoint their upstream remote to the new BOLT repository when adopting future upgrades.
Pending:
- None. BOLT is available at `https://github.com/hounw/BOLT` with green CI; Kodifica was restored to framework-only commit `06e2728`.
