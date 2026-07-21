# Changelog

All notable BOLT changes are documented here. BOLT follows semantic versioning once `1.0.0` is tagged.

## Unreleased - 1.0.0 Release Candidate

### Added

- Employee and access management, private HR history, organization charts, and managed People reference data.
- Day-based PTO policies, balances, approvals, calendar, history, and manual adjustments.
- Private polymorphic attachments with policy-checked downloads.
- Traversable Markdown knowledge base with import, secure rendering, versions, categories, tags, links, backlinks, and agent APIs.
- Asset inventory, reusable tags, private photos, assignments, and lifecycle history.
- Audit log, scoped Passport API, OAuth2 Authorization Code with PKCE, OpenAPI documentation, and signed webhook delivery infrastructure.
- Platform settings, public-repository guidance, SQLite and MySQL CI, and production deployment/recovery runbooks.
- Interactive first-owner bootstrap for new production deployments without credentials in shell arguments.

### Security

- Sensitive employee contact fields are restricted to employee managers.
- Audit API values honor compensation, benefits, and private-HR permissions.
- Attachment API uploads require a valid policy-checked target.

### Fixed

- Shortened Knowledge relationship index names for MySQL's identifier limit.
- Made audit targets compatible with Passport's string token IDs.
- Combined MySQL relevance search with exact substring recall for consistent web and agent retrieval.

The release checklist in `docs/release-checklist.md` must be completed before creating the `v1.0.0` tag.
