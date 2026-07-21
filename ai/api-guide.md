# AI API Guide

Truth = `/openapi.json`.

## URLs

- Base: `https://[domain]/api/v1`
- Docs: `/docs`
- Spec: `/openapi.json`
- Guide: `ai/api-guide.md`

## Auth

```http
Authorization: Bearer [token]
```

- Do not put tokens in query strings or logs.
- `401` means no/invalid auth.
- `403` means authenticated but not allowed.
- OAuth2 login for separate apps uses Laravel Passport Authorization Code + PKCE.

### Authorization Code With PKCE

Use this flow for browser, desktop, and mobile applications that need a BOLT user to sign in. Public clients have no client secret.

Register each external application on the BOLT server with its exact callback URI:

```bash
php artisan passport:client --public --name="Operations companion" --redirect_uri="https://client.example.com/oauth/callback"
```

Record the client ID. Redirect URI matching is exact. Do not register wildcard or untrusted callback domains.

For each authorization attempt, the client generates a cryptographically random PKCE `code_verifier` of 43-128 URL-safe characters, derives `code_challenge = BASE64URL(SHA256(code_verifier))`, and stores a separate random `state` value in its session. Redirect the browser to:

```text
GET /oauth/authorize
  ?client_id=[client-id]
  &redirect_uri=https%3A%2F%2Fclient.example.com%2Foauth%2Fcallback
  &response_type=code
  &scope=employees%3Aread%20knowledge%3Aread
  &state=[random-state]
  &code_challenge=[base64url-sha256-challenge]
  &code_challenge_method=S256
```

BOLT prompts for login when needed and displays a consent screen listing requested scopes. On success, the callback receives `code` and `state`. The client must reject the callback unless `state` exactly matches the value stored before redirecting. OAuth errors return `error`, and may include `error_description`, on the registered callback.

Exchange the one-time code from the application backend or native client:

```http
POST /oauth/token
Content-Type: application/json

{
  "grant_type": "authorization_code",
  "client_id": "[client-id]",
  "redirect_uri": "https://client.example.com/oauth/callback",
  "code": "[authorization-code]",
  "code_verifier": "[original-code-verifier]"
}
```

Store access and refresh tokens in the platform's secure credential storage, never browser local storage or logs. Refresh with `grant_type=refresh_token`, the public `client_id`, and the issued `refresh_token`. Signing out of the external app should delete its local tokens. An owner-admin can revoke issued tokens in **Platform > Access > API tokens**; BOLT session logout does not implicitly revoke tokens held by other applications.

## Scopes

- `employees:read`, `employees:write`
- `hr:read`, `hr:write`
- `pto:read`, `pto:write`
- `files:read`, `files:write`
- `knowledge:read`, `knowledge:write`
- `assets:read`, `assets:write`
- `audit:read`
- `webhooks:write`

## Versioning

Use `/api/v1/...`. Do not assume compatibility across future major API versions.

Owner-admins can create and filter scoped bearer tokens in the web UI at **Platform > Access > API tokens**. Copy a new token immediately; BOLT only displays it once.

## Operation IDs

Use stable OpenAPI `operationId` values such as `listEmployees`, `createPtoRequest`, and `listWebhookEvents` when generating clients or MCP tools.

Use `GET /api/v1/employees` with `q`, `status`, `department`, and `manager_id` query parameters to retrieve directory records without scanning the full employee list.

Use `GET /api/v1/departments` and `GET /api/v1/positions` with `q` and `is_active` to populate employee form choices. Department resources include `parent_id`, `parent_name`, and `path` for nested department selectors. Use `POST`/`PUT` on those endpoints with `employees:write` only when the user explicitly asks to manage People setup data; department writes may send `parent_id`, but the API rejects hierarchy cycles.

Employee writes may send `department_id` and `position_id`. Legacy `department` and `title` strings are still accepted and will resolve into managed reference records.

Use `GET /api/v1/compensation-packages` with `q` and `is_active` to retrieve reusable compensation defaults for authorized HR users. Compensation package writes accept `amount_basis` (`annual`, `monthly`, `hourly`) and `payment_frequency` (`monthly`, `bimonthly`, `biweekly`); omit them only when the user accepts the defaults of annual amount and monthly pay. BOLT uses the platform main currency for compensation package and compensation history writes, so API clients should not present per-record currency choices for the MVP. Selecting `compensation_package_id` on an employee write also requires `hr:write` and creates a normal compensation history entry using the package's current values.

Employee writes may create a starting PTO balance with `starting_pto_policy_id`, `starting_pto_available_days`, `starting_pto_period_start`, and `starting_pto_period_end`; this requires `pto:write`. Day quantities must be whole or half days.

Personal email, phone, emergency contact, medical notes, tax ID, government ID, and other private HR fields are only returned to users with employee-management permission. Do not assume those fields are absent merely because they are hidden from a lower-privilege token.

Use `GET /api/v1/employees/{employee_id}/compensation-history` with `type`, `effective_from`, and `effective_until`, and `GET /api/v1/employees/{employee_id}/benefit-history` with `type`, `starts_from`, and `starts_until` for authorized HR timeline review.

Use `GET /api/v1/pto-policies` before PTO writes to choose the correct policy. Use `POST /api/v1/pto-policies` and `PUT /api/v1/pto-policies/{pto_policy_id}` only for authorized PTO configuration changes; setting `is_default` to true clears the previous default. Policy writes use `annual_allowance_days`, `carryover_days`, `accumulation_frequency` (`monthly`, `bimonthly` for twice monthly, or `biweekly` for every other week), `working_days`, `holidays`, and `allow_negative_balance`.

Policy calendars are day-first: `working_days` is an array of lowercase weekday names and `holidays` is an array of `YYYY-MM-DD` dates. Web PTO requests calculate request days from these settings and half-day flags; API PTO requests still send `days` directly, so API clients should apply the same policy calendar before submitting.

Use `GET /api/v1/pto-balances` to inspect available, pending, used, and remaining PTO days before creating a request.

Use `POST /api/v1/pto-requests` with `days`, not hours. Valid requests use whole or half days only. Requests beyond remaining balance are rejected unless the selected policy has `allow_negative_balance` enabled.

Use `GET /api/v1/pto-requests` with `status`, `employee_id`, `pto_policy_id`, `starts_from`, and `starts_until` query parameters to review request queues and decision history.

PTO queue visibility is permission scoped: employees see their own records, managers see direct reports, and HR/admin users with PTO management permissions see all employees.

Use `GET /api/v1/knowledge-articles` with indexed `q` and optional `category_id`, legacy `category`, `tag`, `missing_excerpt`, `linked_from`, or `linked_to` query parameters to retrieve relevant SOPs and operational notes. Tokens with ordinary knowledge-read permission only receive published articles, even when a non-published `status` filter is sent. Knowledge managers may also filter `draft` and `archived` content.

Knowledge article resources return canonical `body_markdown`, nullable curated `excerpt`, deterministic `excerpt_preview`, `excerpt_missing`, category ID/name/path, link counts, attachment counts, and version counts. Use `excerpt_missing=true` to identify articles needing editorial curation. Detail and import responses include private attachment metadata but never public file URLs or rendered HTML.

Import one UTF-8 `.md` or `.markdown` file with:

```http
POST /api/v1/knowledge-articles/import
Authorization: Bearer [token]
Idempotency-Key: [stable-unique-key]
Content-Type: multipart/form-data
```

Send the file in `file` and optionally send `title`, `slug`, `status`, `excerpt`, `category_id`, legacy `category`, and `tags[]`. The first H1 becomes the suggested title when `title` is omitted; the filename is the fallback. BOLT generates a collision-safe slug, stores the Markdown as the canonical article body, and retains the uploaded source as a private attachment. Files are limited to 1 MB, must use UTF-8, and must end in `.md` or `.markdown`.

Use `GET /api/v1/knowledge-articles/{knowledge_article_id}/versions` to retrieve immutable content snapshots visible under the same article policy. Normal API updates create the next snapshot. Restoring old content is deliberately a review-first web workflow; agents should read a version and then issue a normal update only after the user explicitly approves the change.

Use `GET /api/v1/knowledge-articles/{knowledge_article_id}/links?direction=outgoing` to follow directed links and `direction=incoming` for backlinks. Results are paginated and policy filtered, so read-only agents cannot discover drafts or archived articles through graph traversal.

Use `GET /api/v1/knowledge-categories` to search the category tree and inspect stable IDs, parent IDs, paths, child counts, and direct article counts. `GET /api/v1/knowledge-categories/{category_id}/digest` returns paginated summaries for direct articles only. `GET /api/v1/knowledge-categories/{category_id}/index` returns paginated descendants with paths, direct counts, and up to three direct article previews per category. Use the digest for complete category contents and the index for branch orientation.

Category create, update, and delete endpoints require `knowledge:write` plus knowledge-management permission. Parent updates reject hierarchy cycles, and deletion is blocked until child categories and assigned articles are moved. `GET /api/v1/knowledge-tags` provides a searchable paginated catalog of reusable labels before agents patch article tags.

Use `GET /api/v1/assets` with `q`, `status`, `tag`, legacy `category`, and `assigned_to` query parameters to locate equipment, lifecycle state, and current assignment records. Asset writes may send `tags` as an array or comma-separated string; if `asset_tag` is omitted BOLT generates an internal system ID. Asset resources include `tags`, `current_holder`, and `photo.has_photo`; photos remain private and are not exposed as public URLs. The web UI includes Operations > Setup > Asset tags for managing reusable tag suggestions. Web uploads can attach multiple asset photos, with the first uploaded image used as the primary display photo. Asset purchase costs use the platform main currency.

Use `GET /api/v1/assets/{asset_id}/history` to inspect asset lifecycle events and `POST /api/v1/assets/{asset_id}/history` with `type`, `notes`, optional `employee_id`, `condition`, `occurred_at`, and `metadata` to add a timeline entry. Use `asset_events` as the attachment target type when uploading photos/files for a specific history entry.

Use `POST /api/v1/assets/{asset_id}/assign` and `POST /api/v1/assets/{asset_id}/return` for holder changes. These endpoints preserve assignment history and create asset history entries with from/to holder, actor, notes, and condition when provided.

Use `GET /api/v1/audit-logs` with `event`, `actor_id`, `auditable_type`, `auditable_id`, `occurred_from`, and `occurred_until` query parameters to investigate operational history without scanning the full log. Audit events remain discoverable to authorized auditors, but compensation, benefit, and private employee values are filtered according to the caller's domain permissions. Check `sensitive_values_redacted`; when true, do not infer that null or omitted values were absent from the underlying business change.

Use `GET /api/v1/webhook-endpoints` with `q`, `is_active`, and `event` query parameters to find subscriptions, `GET /api/v1/webhook-endpoints/{webhook_endpoint_id}/deliveries` with `status`, `event`, `created_from`, and `created_until` to triage delivery history, and `GET /api/v1/webhook-deliveries/{webhook_delivery_id}` to inspect one delivery.

## Error

```json
{"error":{"code":"validation_failed","message":"The given data was invalid.","fields":{}}}
```

Read `error.code`.

## Idempotency

Risky POST endpoints require:

```http
Idempotency-Key: [stable-unique-key]
```

Same key and same payload returns the stored JSON response. Same key and different payload returns `409`.

For file uploads, the idempotency fingerprint includes file name, MIME type, size, and content hash.

Attachment uploads require both `attachable_type` and `attachable_id` and are target-policy checked before the file is stored. A token with `files:write` also needs the authenticated user to be allowed to view the target employee, knowledge article, asset, or asset history event. BOLT rejects targetless uploads rather than creating orphan files.

## Retry

Retry `429`, `503`, and network timeouts only when the endpoint is safe or the request has an idempotency key.

Do not retry destructive updates blindly.

## Rate Limit

Read standard Laravel rate-limit headers:

```http
X-RateLimit-Limit
X-RateLimit-Remaining
Retry-After
```

## Destructive Or Sensitive Actions

- Confirm resource IDs.
- Prefer read-before-write.
- Do not bulk modify without explicit user confirmation.
- Report IDs changed.

## Webhooks

Read the supported event catalog with:

```http
GET /api/v1/webhook-events
```

Webhook endpoint subscriptions must use catalog event names or `*` for every event.

Queue a test delivery for one endpoint with:

```http
POST /api/v1/webhook-endpoints/{webhook_endpoint_id}/test
Idempotency-Key: [unique-key]
```

Replay an existing delivery with:

```http
POST /api/v1/webhook-deliveries/{webhook_delivery_id}/replay
Idempotency-Key: [unique-key]
```

Inspect an existing delivery with:

```http
GET /api/v1/webhook-deliveries/{webhook_delivery_id}
```

Delivery detail includes payload, response body, error, attempts, response status, `next_attempt_at`, `delivered_at`, and timestamps.

Test and replay calls return `422` when the endpoint is disabled. Reactivate the endpoint first so disabled integrations do not quietly keep producing traffic.

Failed deliveries are retried by the scheduled `bolt:retry-webhooks` command when `next_attempt_at` is due and the endpoint is still active.

Webhook deliveries include:

- `X-BOLT-Event`
- `X-BOLT-Delivery`
- `X-BOLT-Timestamp`
- `X-BOLT-Signature: sha256=[hmac]`

Signature base string is `[timestamp].[raw_json_body]` using the endpoint secret.
