# BOLT MVP Master Plan

This is the active MVP tracker for BOLT. It is forward-looking and user-centered: what a small business owner, HR/admin user, manager, employee, auditor, API client, or AI agent should be able to rely on in the boilerplate.

`project-journal.md` remains the history log. Update this file when a feature area is completed, re-scoped, or selected as the current focus.

## Philosophy

BOLT should stay minimal, polished, cloneable, and easy for AI agents to extend. The MVP is not a broad enterprise suite; it is a solid base app with the smallest useful version of each core operations workflow.

Core behavior should be policy-protected, API-visible where appropriate, documented, tested, and safe for public open-source reuse.

Public BOLT is the reusable upstream. A company adopts a reviewed release into an independent private repository, owns that downstream as its product, and takes later BOLT changes only after review. `USING-BOLT.md` defines this lifecycle for humans and agents.

## MVP Feature Areas

- [x] Setup and local admin bootstrap: zero-configuration SQLite local evaluation, agent-owned server bring-up on port 8000, interactive local owner-admin creation, seeded roles/permissions, complete `.env.example`, public repo safety docs.
- [x] Authentication and access: session login, password update, owner-admin user/role management, employee-user linking, scoped API tokens.
- [x] Employees and org structure: searchable/filterable employee directory, private employee photos, managed nested departments/positions, department chart, people org chart, manager relationships, polished onboarding, API access.
- [x] Sensitive HR history: compensation and benefit history, compensation packages, restricted private HR details, filters, web/API coverage.
- [x] PTO: policy management, day-based balances, onboarding starting balance/top-off, request submission, manager/HR approval paths, cancellation, filters, API coverage.
- [x] Attachments: private polymorphic uploads/downloads for employees, knowledge, and assets, target authorization, audited access.
- [x] Knowledge base: Markdown articles, secure import/rendering, hierarchical categories, directed article links, indexed search, version history, attachments, and agent-friendly traversal APIs.
- [x] Assets: inventory, managed reusable tags, search/filter, purchase/vendor/warranty data, assignment/return workflow, assignment history, multiple private asset photos, and photo-backed lifecycle history.
- [x] Audit log: policy-protected audit viewer, filters, sensitive event coverage, retention command.
- [x] API, OAuth, and docs: `/api/v1`, Passport scopes/tokens, OAuth2 foundation, Scramble docs, OpenAPI operation IDs, AI API guide.
- [x] System settings: central web UI for operational configuration such as main currency, worker guidance, and webhook history limits.
- [x] Webhooks: signed outbound delivery, diagnostics, retries, replay, health, and retention controls.
- [x] Public repo and deploy readiness: README, MIT license, contributing guide, security policy, optional cPanel runbook with separated key custody and non-secret deployment memory, CI workflow.

## Current Focus: v1 Release Hardening

### Release Completion Tasks

- [x] Restrict personal employee contact fields to employee-management permission in API resources.
- [x] Preserve operational audit visibility while filtering compensation, benefit, and private-HR values according to the caller's permissions; mask private employee values in stored model audits.
- [x] Require a valid supported and policy-checked target for every attachment API upload so orphan files cannot be created.
- [x] Add a BOLT OAuth consent screen, public-client provisioning guidance, and an end-to-end Authorization Code + PKCE test.
- [x] Add a MySQL 8 CI lane alongside fast SQLite tests so production migrations, full-text indexing, and database behavior are continuously verified.
- [x] Expand the production runbook with explicit secure environment values, private-storage checks, database/file backups, queue and scheduler commands, verification, rollback, and restore guidance.
- [x] Add a changelog, repeatable v1 release checklist, supported-version security policy, and core upgrade-recipe convention.
- [x] Align the one-time Composer setup workflow with Passport keys, seeded migrations, locked Node dependencies, and the documented MySQL setup.
- [x] Add an interactive, audited, first-owner-only production bootstrap command that keeps credentials out of shell arguments and refuses subsequent owner creation.
- [x] Add regression and documentation tests for every release-hardening behavior.

## Completed Focus: Knowledge Base

### Already Present

- [x] Article create, edit, and detail views with draft, published, and archived statuses.
- [x] Markdown body storage with category, reusable tags, search, and filters.
- [x] Version counter plus article creator and updater metadata.
- [x] Private policy-checked attachments on knowledge articles.
- [x] Scoped API create, update, list, and detail routes with idempotent creation.
- [x] Article create/update webhooks and agent-oriented search, status, category, and tag filters.

### MVP Completion Tasks

- [x] Add Markdown file import to article creation: accept a UTF-8 `.md` or `text/markdown` file within a documented size limit, prefill an editable body, suggest the title from the first H1 or filename, generate a collision-safe slug, retain the source file as a private attachment after save, and preserve imported content and metadata after validation errors.
- [x] Add an explicit CommonMark dependency and a secure server-side renderer supporting headings, links, lists, tables, task lists, blockquotes, fenced code, and inline code while disabling raw HTML and unsafe URL schemes and keeping tables/code usable on mobile.
- [x] Replace the raw Markdown `<pre>` display with a polished reading view showing rendered content, status, category, tags, version, author, last update, a generated table of contents when multiple subheadings exist, and clearly separated attachments.
- [x] Add Write and Preview modes to article authoring using the same server-side renderer as the final reading view.
- [x] Restrict regular knowledge readers and read-scoped API clients to published articles; allow knowledge managers to inspect and filter all statuses; enforce the same visibility on direct article and attachment access.
- [x] Add immutable article version snapshots for the initial article and every update, recording title, Markdown body, category, tags, editor, version, and timestamp.
- [x] Add a version history view with comparison metadata and a restore action that loads a prior snapshot into the editor without changing the article until it is explicitly saved as a new version; keep attachments article-level rather than duplicating them per version.
- [x] Polish the knowledge index with useful excerpts, visible tags/status metadata, and clear empty and filtered-result states.
- [x] Add `POST /api/v1/knowledge-articles/import` for idempotent multipart Markdown import with `knowledge:write`, and `GET /api/v1/knowledge-articles/{knowledgeArticle}/versions` with `knowledge:read` plus article visibility.
- [x] Keep canonical Markdown in API resources, add attachment and version metadata without rendered HTML, and keep published content as the only result set for ordinary readers and agents.
- [x] Audit Markdown imports, publication and archive status changes, and version restores while continuing article create/update webhook delivery.
- [x] Update `product-definition.md`, `technical-definition.md`, and `ai/api-guide.md` after implementation.
- [x] Add focused tests for Markdown import, H1/filename title fallback, invalid files, collision-safe slugs, safe rendering, preview parity, publication visibility, draft attachment protection, version snapshots, restore workflow, API import, and agent retrieval.
- [x] Run full verification before commit: `./vendor/bin/pint`, `php artisan test`, `npm run build`.
- [x] Perform browser verification across desktop and mobile for import, preview, rendered articles, table/code overflow, attachments, search, and version history.
- [x] After implementation commit, refresh `app-overview.md` and `routes.md`, update `project-journal.md`, and mark completed Knowledge tasks here.

### Smart Knowledge Completion Tasks

- [x] Add optional curated article excerpts with a 300-character web/API limit, a 1,000-character database field, version snapshots, and deterministic Markdown preview fallback.
- [x] Replace string categories with stable hierarchical category records, backfill existing data, preserve legacy API category-name compatibility, prevent cycles, and guard deletion while categories are in use.
- [x] Upgrade Knowledge Setup and article authoring for searchable hierarchical category selection, and add a human category browser with breadcrumbs, children, counts, previews, and direct-article pagination.
- [x] Add `@` article autocomplete to the Markdown editor, insert stable internal links, derive directed article relationships on every save/import, and show outgoing links and backlinks on article pages.
- [x] Add indexed title/excerpt/body search with relevance ordering on MySQL and a portable fallback for SQLite tests and short queries; expose missing-excerpt and relationship filters.
- [x] Add policy-filtered API traversal for article links, category metadata, direct category digests, recursive category indexes with bounded previews, and the reusable knowledge-tag catalog.
- [x] Keep ordinary readers and agents restricted to published articles throughout search, category exploration, digests, links, and backlinks.
- [x] Audit category mutations and continue article audits/webhooks for excerpt, taxonomy, and relationship changes.
- [x] Update `product-definition.md`, `technical-definition.md`, and `ai/api-guide.md` for the smart Knowledge model and API.
- [x] Add focused migration, model, web, API, policy, search, excerpt, category-tree, autocomplete, link-sync, and traversal tests.
- [x] Run `./vendor/bin/pint`, `php artisan test`, and `npm run build`, then verify desktop and mobile Knowledge workflows in the browser.
- [x] After implementation, refresh `app-overview.md` and `routes.md`, update `project-journal.md`, and mark the Smart Knowledge tasks complete.

### MVP Boundaries

- Markdown upload imports content into an editable article and retains the original file as a private attachment.
- Knowledge managers alone can access drafts and archived articles; ordinary readers and agents receive published content only.
- Restoring a version loads its content into the editor and requires an explicit save to create the next version.
- The importer handles one standalone Markdown file and does not unpack archives or rewrite relative image/file references.
- Archiving is the non-destructive removal workflow; permanent article deletion is outside this MVP slice.
- Articles keep one primary category and any number of tags; category digests contain direct articles while category indexes summarize descendants.
- Article relationships are directed backend data exposed as links and backlinks; a visual graph, embeddings, vector search, and autonomous AI curation remain outside MVP.

## Post-v1 Focus Queue

After the verified `v1.0.0` release, prioritize improvements without broadening the core prematurely:

1. Reviewable core upgrade recipes for customized private downstreams.
2. More granular employee self-service permissions.
3. MCP server built on the stable API and OpenAPI contract.
4. Additional operational diagnostics informed by real deployments.

## Completion Standard

A feature area is MVP-complete when a user can complete the core workflow from the web UI, an API client or agent can use the documented API where applicable, sensitive behavior is policy-protected, failure states are understandable, tests cover the main path and one meaningful edge case, and the living docs match the implementation.
