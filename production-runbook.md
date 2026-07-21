# Production Runbook - Optional cPanel And SSH

No secrets. No passwords, tokens, private keys, `.env`, Passport private keys, or customer data belong in this document, shell history, or the repository.

This is the public BOLT reference for companies that choose cPanel hosting. cPanel is optional, not a BOLT requirement. A company downstream must first select its hosting model, adapt the non-secret topology and repository names, and deploy from its private `origin` as defined in `USING-BOLT.md`. If it selects another platform, replace this runbook with an equivalent reviewed procedure.

## Key Roles

The cPanel path uses two different SSH keypairs:

1. A local cPanel access key lets an authorized person or agent connect from its workstation to the cPanel server. Its private key stays on that workstation.
2. A server GitHub deploy key lets the cPanel server clone and pull one private company repository. Its private key stays on the server and the GitHub key remains read-only.

Never reuse one keypair for both roles. Never copy either private key into the repository, Markdown, chat, `/tmp`, `/private/tmp`, or an agent sandbox.

## Data To Record

- Host:
- User:
- SSH port:
- Domain/subdomain:
- Project path:
- Backup path outside the webroot:
- Repo/tag:
- Previous known-good tag:
- PHP version:
- DB name/user:
- Queue supervision method:
- Local cPanel access key path:
- Local cPanel access public fingerprint:
- Local key custodian/device:
- Server GitHub deploy key path:
- Server GitHub deploy public fingerprint:
- Key paths/fingerprints last verified:

DNS and HTTPS should be ready before deployment.

The exact paths and public fingerprints are non-secret operational memory. After key creation, record them in this private downstream runbook and in the deployment section of `technical-definition.md`. Record the decision in `project-journal.md` at the next committed checkpoint. Never record private key contents, a passphrase, `.env` values, or credentials.

## 1. Confirm cPanel And Key Locations

Do not generate deployment keys merely because BOLT contains a cPanel runbook. Confirm that the company selected cPanel, the private repository exists, and the user authorized key creation.

Use stable project-specific paths:

```text
Local workstation: ~/.ssh/deploy/bolt/cpanel_ed25519
cPanel server:     ~/.ssh/deploy/bolt/github_ed25519
```

In a private company downstream, replace `bolt` with the stable project slug and write the exact paths into **Data To Record** before continuing. An agent must check for existing files and stop rather than overwrite or rotate a key implicitly.

## 2. Local cPanel Access Key

Never place keys in `/tmp`, `/private/tmp`, the repo, or sandbox.

```bash
umask 077
install -d -m 700 ~/.ssh
install -d -m 700 ~/.ssh/deploy
install -d -m 700 ~/.ssh/deploy/bolt
if [ -e ~/.ssh/deploy/bolt/cpanel_ed25519 ] || [ -e ~/.ssh/deploy/bolt/cpanel_ed25519.pub ]; then
    echo "STOP: cPanel access key already exists; inspect it instead of overwriting it."
    exit 1
fi
ssh-keygen -t ed25519 -a 100 -C "bolt-cpanel-access" -f ~/.ssh/deploy/bolt/cpanel_ed25519
chmod 600 ~/.ssh/deploy/bolt/cpanel_ed25519
chmod 644 ~/.ssh/deploy/bolt/cpanel_ed25519.pub
ssh-keygen -lf ~/.ssh/deploy/bolt/cpanel_ed25519.pub
cat ~/.ssh/deploy/bolt/cpanel_ed25519.pub
```

`ssh-keygen` prompts for a passphrase. A passphrase is recommended for a local human-operated access key; the user enters it directly in the terminal and may load it into the operating system's SSH agent or credential store. The agent must not request or store it in chat.

Immediately record the exact private-key path, public fingerprint, and custodian/device in the private downstream's **Data To Record** and `technical-definition.md`. The fingerprint identifies the public key without exposing private material.

## 3. Authorize The Public Key In cPanel

STOP. Give the user only the `.pub` contents and public fingerprint. In cPanel, open **Security > SSH Access > Manage SSH Keys**, import the public key, and choose **Authorize**. Never upload or paste the private key into cPanel. See cPanel's [public-key authorization procedure](https://support.cpanel.net/hc/en-us/articles/360053423234-How-to-Manage-a-Public-Key-via-the-cPanel-Interface) and general [SSH Access documentation](https://docs.cpanel.net/cpanel/security/ssh-access/).

Obtain the SSH host-key fingerprint from the hosting provider or cPanel administrator and verify it during the first connection. Do not blindly accept an unexpected host identity.

Then test server access using the provider's SSH port:

```bash
ssh -p [ssh-port] -i ~/.ssh/deploy/bolt/cpanel_ed25519 [user]@[host]
```

If access fails, diagnose cPanel authorization, the SSH port, username, host-key verification, permissions, and provider SSH availability. Do not weaken SSH verification or upload the private key as a workaround.

## 4. Server GitHub Deploy Key

On the cPanel server, create a separate repository-specific identity. Check first and never overwrite an existing key:

```bash
umask 077
install -d -m 700 ~/.ssh
install -d -m 700 ~/.ssh/deploy
install -d -m 700 ~/.ssh/deploy/bolt
if [ -e ~/.ssh/deploy/bolt/github_ed25519 ] || [ -e ~/.ssh/deploy/bolt/github_ed25519.pub ]; then
    echo "STOP: GitHub deploy key already exists; inspect it instead of overwriting it."
    exit 1
fi
ssh-keygen -t ed25519 -N '' -C "bolt-server-github-readonly" -f ~/.ssh/deploy/bolt/github_ed25519
chmod 600 ~/.ssh/deploy/bolt/github_ed25519
chmod 644 ~/.ssh/deploy/bolt/github_ed25519.pub
ssh-keygen -lf ~/.ssh/deploy/bolt/github_ed25519.pub
cat ~/.ssh/deploy/bolt/github_ed25519.pub
```

The command creates a passphrase-less server key for unattended pulls. This is limited to one repository and read-only access. If company policy requires a passphrase, remove `-N ''` and use an approved SSH agent or secret manager rather than recording the passphrase. The private key never leaves the server.

STOP. Add only the public key to the private company repository under **Settings > Deploy keys > Add deploy key**. Leave **Allow write access** disabled. GitHub deploy keys grant access to a single repository and are read-only by default; see [Managing deploy keys](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys) and the [deploy key API documentation](https://docs.github.com/en/rest/deploy-keys/deploy-keys).

If organization policy disables deploy keys, stop and use an organization-approved GitHub App or machine-user design. Do not substitute a developer's personal SSH key.

For multi-repository deployments, expiring credentials, or finer centralized control, prefer a company GitHub App. GitHub documents deploy keys as single-repository credentials and recommends GitHub Apps for enhanced security and permission control.

Record the server private-key path and public fingerprint in the private downstream runbook and `technical-definition.md`. Never copy the private key into BOLT or onto the local workstation.

Before the first GitHub connection, compare the presented `github.com` host-key fingerprint with [GitHub's published SSH key fingerprints](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints). Do not accept an unexpected host identity.

Then verify repository access from the server before cloning:

```bash
GIT_SSH_COMMAND='ssh -i $HOME/.ssh/deploy/bolt/github_ed25519 -o IdentitiesOnly=yes' git ls-remote git@github.com:[org]/[private-repo].git HEAD
```

## 5. Database, Domain, And Runtime

Use cPanel UI or UAPI to create the MySQL 8 database and user. Do not put the database password in Markdown or a command that will remain in shell history.

Confirm before continuing:

```bash
php -v
php -m | grep -E 'pdo_mysql|mbstring|openssl|fileinfo'
node --version
npm --version
composer --version
```

The domain must use HTTPS and its document root must eventually point to `[project-path]/public`.

## 6. Code And Production Environment

```bash
GIT_SSH_COMMAND='ssh -i $HOME/.ssh/deploy/bolt/github_ed25519 -o IdentitiesOnly=yes' git clone git@github.com:[org]/[private-repo].git [project-path]
cd [project-path]
git config core.sshCommand "ssh -i $HOME/.ssh/deploy/bolt/github_ed25519 -o IdentitiesOnly=yes"
git checkout [release-tag]
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.example .env
php artisan key:generate --force
php artisan passport:keys --force
```

Edit `.env` on the server only. Do not cache configuration until every value has been reviewed.

Required production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://[domain]
LOG_LEVEL=warning
DB_CONNECTION=mysql
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=[configured-production-mailer]
```

Also set the real database, mail, cache, session, and optional S3 values. Use `SESSION_DOMAIN=null` unless subdomain sharing is deliberate. If TLS terminates at a trusted reverse proxy, configure Laravel's trusted proxies explicitly before deployment.

Confirm that `storage/app/private` is not under the document root and is not symlinked into `public`. `php artisan storage:link` links only `storage/app/public`; BOLT business attachments remain on private storage.

## 7. Pre-Migration Backup

STOP. Confirm the backup destination is outside the repository and webroot. Create both database and private-file backups before every migration deploy.

```bash
mkdir -p [backup-path]
mysqldump --single-transaction --routines --triggers -u [db-user] -p [db-name] > [backup-path]/bolt-before-[release-tag].sql
tar -C [project-path] -czf [backup-path]/bolt-private-before-[release-tag].tar.gz storage/app/private
test -s [backup-path]/bolt-before-[release-tag].sql
test -s [backup-path]/bolt-private-before-[release-tag].tar.gz
```

Copy backups to a second protected location and periodically perform a restore drill on a non-production database. A backup that has never been restored is not proven.

## 8. Migrate And Activate

STOP. Ask before production migrations.

```bash
php artisan down
php artisan migrate --force
php artisan db:seed --class=CoreAccessSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Do not seed local admin credentials in production. If this is a new installation with no owner-admin, run the interactive one-time bootstrap. Credentials cannot be passed as command options, which keeps them out of shell history:

```bash
php artisan bolt:bootstrap-owner --confirm-production
```

The command refuses to create a second owner. Sign in over HTTPS, change the temporary password immediately, and manage later owners through **Platform > Access**.

## 9. Scheduler And Queues

Add one cPanel cron entry for the scheduler:

```cron
* * * * * cd [project-path] && php artisan schedule:run >> /dev/null 2>&1
```

Use Supervisor, systemd, or the host's process manager for a continuously restarted worker:

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-time=3600
```

If persistent processes are unavailable, use a non-overlapping cron fallback:

```cron
* * * * * cd [project-path] && flock -n storage/framework/bolt-queue.lock php artisan queue:work database --stop-when-empty --tries=3 --timeout=90
```

Verify both:

```bash
php artisan schedule:list
php artisan queue:monitor database:default --max=100
```

## 10. Webroot

STOP. Point the domain document root to:

```text
[project-path]/public
```

Never point the webroot at the repository root or `storage/app/private`.

## 11. Verification

```bash
php artisan about
php artisan migrate:status
php artisan route:list
php artisan schedule:list
```

Verify `/up`, `/`, `/login`, `/docs`, and `/openapi.json`. Sign in as a non-production test user and confirm one policy-protected API request, one queued webhook test, and one private attachment download. Confirm `APP_DEBUG` is false by requesting a nonexistent route and ensuring no stack trace is rendered.

## 12. Rotation And Revocation

Rotate a key when its private material may be exposed, its custodian or server changes, company policy requires rotation, or access is no longer needed.

Do not overwrite a working key in place. Create a new project-specific key at a new temporary name, authorize its public half, verify access, update the recorded path and fingerprint, then revoke the old public key and securely remove the old private key from its original machine. For the GitHub key, remove the old deploy key from the repository. For the cPanel access key, deauthorize the old public key in cPanel.

After rotation or revocation, update the private downstream runbook, `technical-definition.md`, and the next `project-journal.md` checkpoint with paths, public fingerprints, reason, and verification date only.

## 13. Rollback And Restore

If code fails before migrations, return to the previous known-good tag, reinstall locked dependencies, rebuild assets, clear/cache configuration, restart queues, and verify again:

```bash
php artisan down
git checkout [previous-known-good-tag]
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

STOP before attempting to reverse a production migration. Do not run `migrate:rollback` blindly. Review the migration and release notes first. When schema or data cannot be safely reversed, restore the pre-deploy database and matching private-file backup into a controlled maintenance window, then verify record and file counts before reopening BOLT.

Record the failed tag, error, rollback decision, and verification result in the approved operational system, without including secrets or customer data in the repository.
