# Local Development

This is the default local evaluation workflow for BOLT and private company repositories adopted from it. It is written for both humans and coding agents, but the agent is expected to perform the setup when one is available.

## Local Defaults

- Application URL: `http://127.0.0.1:8000`
- Login URL: `http://127.0.0.1:8000/login`
- Application database: file-backed SQLite at `database/database.sqlite`
- Mail: written to the local log
- Files: private local storage
- Full development process: Laravel server, queue listener, application log stream, and Vite

SQLite is the zero-configuration local evaluation default. MySQL 8 remains the production database and must be used for production-parity checks, including MySQL-only behavior such as full-text search.

## Agent Responsibility

When a user asks an agent to adopt BOLT, work on a user-facing feature, or let them test the application locally, the agent owns the local bring-up. Do not finish by telling the user to run the server themselves.

The agent must:

1. Inspect the repository instructions and recent journal.
2. Confirm that PHP 8.3+, Composer, Node.js, and npm are available.
3. Install missing project dependencies when authorized and needed.
4. Create the ignored local `.env` from `.env.example` through the setup workflow if it does not exist.
5. Create the ignored SQLite database, application key, Passport keys, schema, seed data, and frontend assets through the setup workflow.
6. Start the complete development process on port `8000`, or reuse it if this project is already listening there.
7. Verify `/up` and `/login` before reporting that the application is ready.
8. Help the user create the first local owner-admin interactively.
9. Leave the development process running for the user unless they ask for it to stop.

Never display or copy the contents of `.env` or private keys. Never invent an admin email or password. Do not put a password in chat, a committed file, or shell arguments.

## First Local Setup

From the repository root:

```bash
composer install
composer run setup
```

`composer run setup` performs the one-time local preparation:

- copies `.env.example` to `.env` only when `.env` is missing;
- creates `database/database.sqlite` only when it is missing;
- generates the application key and Passport keys;
- migrates and seeds the local database;
- installs locked Node dependencies;
- builds frontend assets.

The generated `.env`, SQLite database, keys, dependencies, and build output remain untracked.

If the company deliberately uses local MySQL, configure the ignored `.env` and create that local database before running setup. Do not silently fall back to SQLite when the feature being evaluated depends on MySQL-specific behavior.

## First Local Owner

Start the interactive command in a terminal the user can access:

```bash
php artisan bolt:create-local-admin
```

The agent should keep the terminal attached while the user enters:

- their chosen admin email;
- their chosen display name;
- a temporary local password satisfying the displayed requirements.

The user should enter the password directly into the terminal. The agent must not ask for it in chat or pass it with `--password`. After creation, direct the user to `http://127.0.0.1:8000/login` and remind them not to reuse a production password.

On an existing repository, do not overwrite an owner account automatically. First determine whether the user still needs local access, then run the same interactive command with their approval.

## Start And Verify

Start the full local development process in a persistent terminal:

```bash
composer run dev
```

The server binds to `127.0.0.1:8000`. Keep this process running while the user tests. From another terminal, verify:

```bash
curl --fail --silent --show-error http://127.0.0.1:8000/up
curl --fail --silent --show-error --output /dev/null http://127.0.0.1:8000/login
```

Report the clickable application and login URLs to the user only after verification succeeds.

If port `8000` is already in use:

- reuse it when it is this BOLT repository and it responds correctly;
- do not kill an unrelated process;
- choose another loopback port, start BOLT explicitly on it, and clearly report the changed URL;
- update the ignored local `APP_URL` for the session when URL generation depends on the alternate port.

## During Feature Work

For user-facing changes, the agent should keep or restart the local process, run proportionate automated tests, and exercise the changed path in the browser when browser tooling is available. A passing unit or feature test does not replace giving the user a running application to inspect.

If setup or startup fails, the agent should diagnose the actual failure, fix safe repository-local issues, and explain any external prerequisite it cannot satisfy. Do not claim the application is ready when only the build or tests pass.

## Stop

Stop the persistent development process only when the user asks, the task explicitly requires shutdown, or leaving it running would be unsafe. Report when it has been stopped.
