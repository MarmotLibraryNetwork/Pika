# Pika CI/CD Pipeline

GitHub Actions based continuous integration and deployment for Pika, deploying
to the test and production Pika servers.

## Overview

```
 PR / push to release branch
        │
        ▼
 ┌─────────────────┐   GitHub-hosted runners
 │  CI (ci.yml)    │   • PHP 8.4 syntax lint (vufind/web)
 │                 │   • PHPStan static analysis (phpstan.neon)
 │                 │   • composer validate + audit
 │                 │   • ShellCheck (vufind/bash)
 └────────┬────────┘
          │ push to ACTIVE_RELEASE_BRANCH        push tag v20xx.xx.x
          ▼                                             ▼
 ┌──────────────────────┐               ┌────────────────────────────┐
 │ Deploy to TEST       │               │ Deploy to PRODUCTION       │
 │ (deploy.yml)         │               │ (deploy.yml)               │
 │ automatic            │               │ gated by required-reviewer │
 │                      │               │ approval on the            │
 │ marmot.test          │               │ "production" environment   │
 │ clearview.test  ...  │               │ marmot.production ...      │
 └──────────────────────┘               └────────────────────────────┘
```

Deploys run **locally on each Pika server** through a self-hosted GitHub
Actions runner installed on that server. There are no SSH keys or inbound
connections: each runner picks up its own deploy job and updates its server
in place (git pull), the same way `settings/bash/code_deploy.sh` does
interactively today. The pre-compiled Java jars committed to the repo deploy
as-is; CI does not build them.

## Release flow

1. Development happens on the release branch (e.g. `2026.02.0`). Every push
   runs CI; pushes to the branch named in the `ACTIVE_RELEASE_BRANCH`
   repository variable also auto-deploy to all test servers.
2. When the release is ready for production, tag it and push the tag:

   ```bash
   git tag v2026.02.0 && git push origin v2026.02.0
   ```

3. The production deploy starts, then **pauses for approval** (required
   reviewers on the `production` GitHub Environment). Once approved, every
   production server deploys in parallel.
4. Patches repeat the cycle: fix on the release branch (auto test deploy),
   then tag `v2026.02.1`.

### Manual deploys and rollback

`Actions → Deploy → Run workflow` lets you deploy **any ref to either
environment**: pick the branch or tag in the "Use workflow from" selector and
choose the target environment. To roll production back, run the workflow from
the previous good tag (production approval still applies).

### What a deploy does on each server

`.github/scripts/deploy.sh <PikaServer> <ref>` — the non-interactive
equivalent of `code_deploy.sh`:

1. `git fetch` + checkout of the deployed ref in `/usr/local/pika`
   (branches fast-forward, falling back to the historical `-X theirs` merge;
   tags check out detached)
2. `git pull` of the settings repository (`/usr/share/php/pika/settings`,
   branch `ubuntu`)
3. `composer install` in `install/`, then copy of the patched PEAR `DB`
   package into the composer vendor directory
4. Clear Smarty compile and cache folders
5. `systemctl reload apache2` (graceful; via sudo)
6. Flush memcache
7. Run pending database maintenance updates
   (`php vufind/web/runDatabaseUpdates.php <PikaServer>`)
8. Smoke test: home page and a search results page must return HTTP 200
   (site URL read from `sites/<PikaServer>/conf/config.ini`)

A failure at any step fails the job for that server; other servers in the
matrix continue (`fail-fast: false`), and per-server concurrency groups keep
two deploys from overlapping on the same host.

### Database updates from the command line

`vufind/web/runDatabaseUpdates.php` applies the same updates as the
Admin → Database Maintenance page, in the same release order:

```bash
php vufind/web/runDatabaseUpdates.php marmot.test --list   # show pending
php vufind/web/runDatabaseUpdates.php marmot.test          # apply pending
```

**Caveat:** the pipeline applies *all* pending updates in order. A few
historical updates are annotated "RUN THIS STEP BY ITSELF" or "RUN AFTER …" —
ordering is honored automatically (release + releaseStep sort), but if a
future update genuinely must not run unattended, apply it manually via the
admin page *before* tagging the release, so the automated run finds it
already marked as run.

## One-time setup

### 1. Repository settings (GitHub)

**Environments** (`Settings → Environments`):

| Environment  | Protection |
|--------------|------------|
| `test`       | none (deploys automatically) |
| `production` | **Required reviewers** — the staff who approve production releases; optionally restrict to `v20*` tags |

**Repository variables** (`Settings → Secrets and variables → Actions → Variables`):

| Variable | Example | Purpose |
|----------|---------|---------|
| `ACTIVE_RELEASE_BRANCH` | `2026.02.0` | Only this branch auto-deploys to test. Update it when a new release branch opens. |
| `TEST_INSTANCES` | `["marmot","clearview","flatirons","lion","wcpl"]` | JSON array; one test deploy job per entry |
| `PRODUCTION_INSTANCES` | `["marmot","clearview","flatirons","lion","wcpl"]` | JSON array; one production deploy job per entry |

No secrets are required — runners authenticate to GitHub outbound, and all
deploy work is local to each server.

### 2. Self-hosted runner on each Pika server

Install one runner per server (`Settings → Actions → Runners → New
self-hosted runner`, run as a systemd service via `./svc.sh install <user>`).
Register it with labels identifying the machine:

```
self-hosted, pika, <environment>, <instance>
```

Examples: `pika,test,marmot` on the marmot test server;
`pika,production,clearview` on Clearview's production server. The deploy
matrix targets jobs with exactly these labels, so a mislabeled runner simply
never receives jobs.

The runner's service account needs:

- write access to `/usr/local/pika` and `/usr/share/php/pika/settings`
  (including the `.git` directories — it performs the pulls)
- git credentials the servers already use to pull both repositories
- a sudoers entry for the Apache reload, and nothing broader:

  ```
  # /etc/sudoers.d/pika-deploy
  runner-user ALL=(root) NOPASSWD: /usr/bin/systemctl reload apache2
  ```

### 3. Actions security (important — public repository)

Pika is a public repo, and GitHub self-hosted runners must never execute
untrusted code. The workflows are structured so self-hosted runners only run
on `push`/`workflow_dispatch` events (never `pull_request`), but also set,
under `Settings → Actions → General`:

- **Fork pull request workflows:** "Require approval for all outside
  collaborators"
- Consider a runner group restricted to this repository if the organization
  adds more runners later.

CI jobs (`ci.yml`) always run on GitHub-hosted `ubuntu-latest` runners, so PR
code never touches Pika servers.

## Files

| File | Purpose |
|------|---------|
| `.github/workflows/ci.yml` | Lint/static-analysis on PRs and release branches; also called by deploy.yml as a gate |
| `.github/workflows/deploy.yml` | Test + production deployment orchestration |
| `.github/scripts/deploy.sh` | The per-server deploy procedure |
| `.github/dependabot.yml` | Weekly GitHub Actions version updates; composer security alerts |
| `phpstan.neon` | PHPStan configuration (level 1, legacy paths excluded) |
| `vufind/web/runDatabaseUpdates.php` | CLI runner for Database Maintenance updates |
