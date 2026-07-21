# Using BOLT In A Company

This document defines how a company and its agents should adopt BOLT in the real world. It is the bridge between the public BOLT project and the private application a company operates.

## The Model

BOLT is a public starting codebase, not a hosted service, runtime dependency, or finished company product.

A company should create an independent private repository from a reviewed BOLT release. That private repository becomes the company's product and source of truth. The public BOLT repository remains a read-only source of optional fixes and improvements.

Normally, one company product uses one private repository across local, staging, and production. Environments have separate configuration, credentials, databases, and storage; they do not need separate copies of the source repository.

```text
public BOLT upstream
        |
        | adopt a reviewed release once
        v
private company repository (origin)
        |
        +-- company product definition and roadmap
        +-- company-specific code and integrations
        +-- staging and production deployments
        +-- selective, reviewed BOLT upgrades
```

The word "fork" in BOLT documentation means this product lineage. It does not mean using GitHub's **Fork** button. GitHub states that forks of public repositories are public and that a fork's visibility cannot be changed. Use an independent private repository instead. See [GitHub's fork visibility documentation](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/working-with-forks/about-permissions-and-visibility-of-forks).

This separation is intentional:

- BOLT can stay generic, reviewable, and public.
- Company workflows, internal integrations, operational details, and proprietary code stay private.
- A company can diverge when its business requires it.
- Upstream upgrades remain choices, not automatic merges.
- The company owns its deployment, data, security posture, and release decisions.

BOLT's MIT license permits private use and modification. Keep the BOLT copyright and license notice in copies or substantial portions of the software. Private repository visibility does not relax secret-handling rules.

## Repository Roles

Use these Git remote names consistently:

- `origin`: the company's private repository. Company branches, tags, releases, and deployments come from here.
- `upstream`: the public BOLT repository at `https://github.com/hounw/BOLT.git`. Fetch from it; do not push company work to it.

Production and staging servers clone only the private `origin`. They should use a read-only deploy key or an equivalent least-privilege credential.

The private repository should be owned by the company organization rather than an employee's personal account. Company access, continuity, and auditability must not depend on one person's account.

Do not use BOLT as a Git submodule, Composer path dependency, or directory periodically overwritten from upstream. The adopted code is the company's application. Normal Git commits record every company change.

### Existing BOLT Downstreams

BOLT was separated from the Kodifica repository before its first public release. Existing private BOLT-derived projects do not need code or database changes. If a project's `upstream` remote still points to `hounw/kodifica`, update only that remote:

```bash
git remote -v
git remote set-url upstream https://github.com/hounw/BOLT.git
git fetch upstream
```

Do not change the private company `origin`. Review future BOLT changes normally before adopting them.

## Adoption Workflow

### 1. Select A Baseline

Adopt a tagged BOLT release, not an arbitrary moving `main`, unless the company has explicitly reviewed and accepted an unreleased commit.

Before adoption, review:

- `CHANGELOG.md`
- `docs/release-checklist.md`
- `SECURITY.md`
- `production-runbook.md`
- the release's test and build status

Record the BOLT source URL, tag, and commit in a dedicated BOLT baseline section of the company `technical-definition.md` and in the first company journal entry. The technical definition holds the current baseline; the journal preserves its history for later upgrade comparisons.

### 2. Create An Independent Private Repository

One straightforward workflow is:

```bash
git clone https://github.com/hounw/BOLT.git [company-project]
cd [company-project]
git remote rename origin upstream
git switch -C main [reviewed-bolt-tag]
gh repo create [company-org]/[company-project] --private --source=. --remote=origin
git push -u origin main
git remote -v
```

This preserves BOLT history while making the company repository independent. GitHub also documents repository duplication separately from forking in [Duplicating a repository](https://docs.github.com/en/repositories/creating-and-managing-repositories/duplicating-a-repository).

Before adding business information, verify that:

- the new repository is owned by the company organization;
- its visibility is private;
- `origin` points to the private repository;
- `upstream` points to public BOLT;
- branch protection, required CI, access teams, secret scanning, and dependency alerts are configured;
- only approved company members and systems have access.

An agent must not create, publish, transfer, or change repository visibility without explicit company authorization.

### 3. Turn The Base Into The Company's Product

Make an adoption commit before building new features. Adapt the inherited BOLT documentation to the company rather than blindly replacing accurate behavior.

At minimum:

- Rename the application and explain its company purpose in `README.md`.
- Rewrite `product-definition.md` around the company's users, roles, workflows, rules, boundaries, and success criteria. State which BOLT capabilities remain inherited.
- Rewrite `technical-definition.md` with the company's architecture, integrations, environments, data classification, intentional departures from BOLT, and current BOLT source/tag/commit baseline.
- Replace the upstream public-repository rule with a company `github-private-repo.md`, using `templates/github-private-repo.md` as a starting point.
- Adapt `AGENTS.md`, `CLAUDE.md`, and `GEMINI.md` so every agent knows it is operating in a private downstream and reads this file.
- Adapt `production-runbook.md` to the company's hosting model without inserting secrets.
- Turn `docs/mvp-plan.md` into the company's active delivery plan or replace it with an equivalently explicit tracker and update the agent instructions.
- Adapt `CONTRIBUTING.md` and `SECURITY.md` to the company's internal ownership and reporting paths.
- Start `project-journal.md` with the adopted BOLT tag and commit, the private repository decision, and the first known deviations.
- Keep `LICENSE` and the BOLT notice required by the MIT license.
- Keep `.env.example` complete and non-secret; never commit `.env`, keys, credentials, tokens, customer data, or employee data.

The private repository should not continue claiming that the company product itself is a public BOLT repository. Documentation is an operational control, so stale upstream instructions are a security risk.

### 4. Validate Before Customization

Prove that the uncustomized baseline works in the company's local and staging environments. Follow `LOCAL-DEVELOPMENT.md`; when an agent is present, it creates the ignored local environment, prepares the database and dependencies, starts and verifies `http://127.0.0.1:8000`, and helps the user create the first local owner interactively. The user should receive a running application, not only setup commands.

Follow the release verification documented in the repository, including MySQL behavior, queues, scheduled tasks, private storage, OAuth, mail, backups, and restore testing as applicable.

Do not enter production data during this validation. Do not point a production webroot or run production migrations without the human stops required by `production-runbook.md`.

### Optional cPanel Deployment

cPanel is one supported reference path, not the required BOLT hosting model. If the company selects it, adapt `production-runbook.md` before generating keys or connecting to infrastructure.

The agent must keep two identities separate:

- the local cPanel access key at `~/.ssh/deploy/[project]/cpanel_ed25519`, whose private half stays on the authorized workstation;
- the server GitHub deploy key at `~/.ssh/deploy/[project]/github_ed25519`, whose private half stays on the cPanel server and whose public half grants read-only access to the single private company repository.

An agent must obtain authorization before generating either key, refuse to overwrite an existing key, expose only the `.pub` value and public fingerprint during authorization, and stop for the user to authorize the public key in cPanel or GitHub. After creation, it records only the exact paths, public fingerprints, custodian, and verification date in the private downstream's `production-runbook.md` and `technical-definition.md`. The next committed journal checkpoint records the decision and non-secret paths. Private key contents and passphrases never become repository or agent memory.

### 5. Customize As A Normal Product

After adoption, the company `product-definition.md` answers what to build. BOLT's public roadmap does not override it.

Prefer these patterns:

- Keep authorization in policies and API scopes.
- Keep business files private and policy checked.
- Add company workflows as explicit modules or explicit changes, with tests and documentation.
- Use adapters or services around external systems so vendor-specific behavior has a clear boundary.
- Preserve stable API conventions when extending `/api/v1`.
- Record significant product and technical decisions in the living definitions and journal.
- Commit in small conventional changes and keep CI green.

Do not contort the private product merely to keep a zero-conflict relationship with BOLT. Some divergence is healthy. Clear boundaries, tests, and a written deviation are more valuable than superficial merge compatibility.

## Instructions For Agents

Before changing any repository derived from BOLT, an agent must determine which mode it is in.

### Public Upstream Mode

Signals include public-repository instructions, BOLT's generic product definition, and an `origin` that points to the public BOLT project.

In this mode, the agent should:

- keep behavior reusable across companies;
- exclude customer data, company secrets, and proprietary assumptions;
- maintain public documentation, release gates, and upgrade recipes;
- treat company-specific requests as downstream work unless they can be generalized safely;
- never publish information obtained from a private downstream.

### Private Downstream Mode

Signals include a company product name, private-repository instructions, company definitions, a private `origin`, and a public BOLT `upstream`.

In this mode, the agent should:

- treat the company repository and its definitions as authoritative;
- push only to the company `origin`, unless explicitly authorized to prepare a sanitized public contribution;
- never assume that BOLT `main` should be merged;
- protect secrets and business data even though the repository is private;
- preserve company behavior during upgrades;
- update company documentation when product, technical, API, or operational behavior changes.

If repository mode or remote ownership is ambiguous, inspect `git remote -v` and the repository instructions. Stop before any push, repository visibility change, deployment, migration, or public disclosure until a human resolves the ambiguity.

## Taking Updates From BOLT

BOLT is an upgrade source, not a continuously synchronized parent.

Review updates in this order:

1. Security fixes affecting the company's version.
2. Correctness and data-integrity fixes relevant to enabled modules.
3. Framework and dependency compatibility changes.
4. Optional product improvements the company actually wants.

Use a dedicated upgrade branch:

```bash
git fetch upstream --tags
git switch -c chore/bolt-upgrade-[target-version]
```

Then:

1. Read `CHANGELOG.md`, `SECURITY.md`, and the applicable file under `docs/upgrade-recipes/` at the target BOLT tag.
2. Compare the company's baseline and recorded deviations with the target change.
3. Cherry-pick a focused upstream commit or port the change manually. Merge a whole upstream branch only when the diff and conflict surface have been deliberately reviewed.
4. Resolve conflicts in favor of company requirements while retaining the security or correctness intent of the update.
5. Review migrations, configuration, scopes, policies, API behavior, queue effects, and private-file behavior explicitly.
6. Update the company definitions, API guide, changelog, and journal.
7. Run formatting, tests, the production build, MySQL verification when relevant, and staging smoke tests.
8. Release through the company's normal review and deployment process.

Never run an upstream migration directly in production just because it exists upstream. The production stop-and-review rules still apply.

After an upgrade, record:

- old and new BOLT baseline tags or commits;
- upstream commits adopted, skipped, or manually reimplemented;
- company conflicts and decisions;
- migrations and operational actions;
- verification and rollback notes.

Update the current BOLT baseline in `technical-definition.md` only after the upgrade is accepted. The journal keeps the previous baseline and the reasoning for the move.

## Contributing A Change Back

A useful company change may be proposed to public BOLT only with explicit authorization.

Before public work:

- remove company names, domains, data, credentials, internal issue references, and proprietary rules;
- reduce the change to a generic BOLT problem;
- reproduce it against the current public BOLT code in a separate public-safe branch or clone;
- add generic tests and public documentation;
- review the complete Git history and diff for confidential material.

Do not push the private downstream branch or its history to public BOLT. A contribution back is a new, sanitized public change, not synchronization of the company repository.

## Practical Ownership Boundary

The company owns:

- its private repository and access controls;
- its product decisions and custom code;
- environments, secrets, data, backups, monitoring, and incident response;
- validation and timing of every BOLT upgrade;
- compliance obligations arising from the data it stores and how it operates the system.

The BOLT project provides:

- a reusable starting architecture and core workflows;
- public releases, documentation, tests, and upgrade recipes;
- optional fixes and improvements under the MIT license.

There is no promise that a heavily customized company repository can merge every future BOLT release without work. The sustainable contract is smaller: understandable code, explicit boundaries, stable documented APIs where promised, reviewable upstream changes, and company-owned upgrade decisions.
