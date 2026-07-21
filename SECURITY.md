# Security Policy

BOLT is intended for single-business deployments that may hold sensitive HR, files, assets, audit, API token, and webhook data.

## Reporting

Do not open public issues that include exploit details, secrets, private data, or customer records. Use **Security > Report a vulnerability** in the GitHub repository to open a private security advisory with the maintainers. Repository maintainers must enable GitHub private vulnerability reporting before publishing a release.

## Supported Versions

| Version | Supported |
| --- | --- |
| Latest `1.x` release | Yes |
| `main` before the next release | Best effort |
| Older major versions | No |

Customized forks are responsible for reviewing security and core upgrade recipes before cherry-picking or merging them.

## Secret Handling

Never commit:

- `.env` files
- Passport private keys
- webhook signing secrets
- deploy keys
- API tokens
- production credentials
- customer or employee data

Use `.env.example` for placeholders only.

## Deployment Safety

Follow `production-runbook.md`. Stop for human review before production migrations and before changing the webroot.

For optional cPanel deployments, keep the local cPanel access key and server GitHub read-only deploy key separate. Private material remains on its originating machine. Private downstream documentation may record exact paths and public fingerprints, but never private key contents or passphrases.
