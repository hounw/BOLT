# GitHub Private Company Repository

Use this template only in a private company application adopted from BOLT. Replace bracketed placeholders and keep secrets out of Markdown.

## Repository

- Owner: `[company organization]`
- Repository: `[private repository]`
- Default branch: `main`
- BOLT upstream: `https://github.com/hounw/BOLT.git`
- Adopted BOLT tag:
- Adopted BOLT commit:

Use `origin` for the private company repository and `upstream` for public BOLT. Deploy only from `origin`.

## Rules

- Repository visibility remains private.
- Never commit `.env`, credentials, tokens, private keys, customer data, or employee data.
- Keep `.env.example` complete and non-secret.
- Protect the default branch and require the project's CI checks.
- Review BOLT upgrades before cherry-picking or porting them.

## Server Deploy Identity

Use a repository-specific read-only identity such as `~/.ssh/deploy/[project]/github_ed25519`.

1. Stop if either the private or public key path already exists; never overwrite or rotate implicitly.
2. Generate the identity on the deployment server with appropriate file permissions.
3. Display only the public key and its public fingerprint.
4. Stop while an authorized user adds the public key as a GitHub deploy key with **Allow write access** disabled.
5. Verify access with `git ls-remote` before cloning or pulling.
6. Record only the exact path, public fingerprint, custodian, and verification date in the private production runbook and technical definition.

Never record private key material or passphrases in the repository.
