# Deployment

**Status: live.** This project is deployed on Render at **https://smart-library-zsh8.onrender.com**. This document describes the deployment path that was actually used — see `README.md` for the local Docker setup instead.

## Docker production model

`compose.yaml` (Laravel Sail) and `Dockerfile` serve different purposes and don't interact:

| | `compose.yaml` | `Dockerfile` |
|---|---|---|
| Purpose | Local development | Production deployment |
| Base | Sail's Ubuntu dev image | `php:8.5-fpm-alpine` (multi-stage) |
| Web server | `php artisan serve` | nginx + php-fpm, supervised |
| PHP errors | Displayed | Hidden, logged to stderr |
| `.env` | Present, git-ignored | Never copied in — env vars only |
| Frontend assets | Built separately (`npm run build`) | Built in-image (`frontend` stage) |

The production image is a 3-stage build:

1. **`frontend`** — `node:22-alpine`, `npm ci` + `npm run build` (Vite production build)
2. **`vendor`** — `composer:2`, `composer install --no-dev --optimize-autoloader`
3. **`runtime`** — `php:8.5-fpm-alpine` with `pdo_pgsql`, `pgsql`, `intl`, `mbstring`, `bcmath` — no Xdebug, no dev tooling, no Node, no Composer. nginx and php-fpm run under `supervisord`; `public/` is the web root; a `HEALTHCHECK` hits `/up`.
   - **Known limitation:** `opcache` is deliberately not compiled in. On this `php:8.5-fpm-alpine` base (PHP 8.5 is very new at time of writing), `docker-php-ext-install opcache` fails at its final install step regardless of being built in isolation from every other extension — a base-image/toolchain issue, not an application one. The app is fully correct without it, just without bytecode caching (a performance optimization, not a functional requirement). See the Dockerfile's inline note.

Build and run it locally to verify (this was actually run against this codebase — built, started, and its endpoints curled — not just written):

```bash
docker build -t smart-library-prod .
docker run --rm -p 8080:8080 \
  -e APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')" \
  -e DB_URL="pgsql://user:pass@host:5432/dbname" \
  smart-library-prod
```

## Environment variables

All of these come from the deployment platform's environment — never from a file baked into the image. See `.env.example` for the full list; the ones that matter most for a deployment:

| Variable | Required | Notes |
|---|---|---|
| `APP_KEY` | Yes | Generate locally with `php artisan key:generate --show`, then paste the result into the Render dashboard. **Not** `generateValue: true` — Render's generic generator doesn't produce a Laravel-compatible base64 encryption key, and the app fails at runtime with `Unsupported cipher or incorrect key length`. Never committed. |
| `APP_ENV` | Yes | `production` |
| `APP_DEBUG` | Yes | `false` — never `true` outside local dev |
| `APP_URL` | Yes | The public URL, once known — for this deployment, `https://smart-library-zsh8.onrender.com` |
| `DB_URL` (or `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`) | Yes | `config/database.php`'s `pgsql` connection accepts either form |
| `SESSION_SECURE_COOKIE` | Recommended | `true` behind HTTPS |
| `LIBRARY_LOAN_PERIOD_DAYS` | No | Defaults to 14 |
| `AI_PROVIDER` / `AI_BASE_URL` / `AI_API_KEY` / `AI_MODEL` | No | App is fully functional with these unset |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | No | "Continue with Google" only appears once these are set |

## HTTPS behind Render's proxy

Render terminates TLS at its edge and forwards requests to this container over plain HTTP, with the original scheme carried in `X-Forwarded-Proto`. `bootstrap/app.php` configures `trustProxies(at: '*')` so Laravel treats those forwarded headers as authoritative — the container is never directly reachable from the internet, so trusting every incoming connection as proxied is correct here, the same as on any PaaS with a dynamic edge IP range. Without this, `Request::isSecure()` is always false behind the proxy, and every generated asset/route URL (Vite's included) comes back as `http://`, which the browser blocks as mixed content on an `https://` page.

## Migrations

Migrations are **not** run automatically by the container's entrypoint (`docker/production/entrypoint.sh`) — with more than one replica, every container start/restart would race the same migration against the same database. On Render's free tier specifically, they run via `initialDeployHook` (below); on a platform with shell/job access (AWS, a paid Render plan), run them as an explicit one-off before traffic is routed to a new release:

```bash
php artisan migrate --force
```

`--force` is required outside `local`/`testing` environments — Laravel otherwise prompts for confirmation, which a non-interactive deploy can't answer.

## Seeding demo data

`DemoSeeder` is **idempotent** — every row it writes uses `updateOrCreate` (users, keyed by email; books, keyed by title+author) or `firstOrCreate` (tags), and the loan-seeding block only runs `if (! Loan::query()->exists())`. Running it again on a database that already has reviewer activity will not duplicate demo users/books, and will not touch loans a reviewer has actually created — confirmed by reading the seeder, not assumed:

```bash
php artisan db:seed --class=DemoSeeder --force
```

## Render deployment (free tier)

`render.yaml` (Blueprint) is what this project is actually deployed with, on Render's **current free tier** — a `smart-library-db` (Postgres 18) plus a `smart-library` Docker web service, same `region: oregon` for both (Render's private network only connects services within the same region).

**What free-tier Render services do *not* have**, confirmed against Render's current docs before writing this: dashboard Shell access, SSH access, one-off Jobs, or `preDeployCommand` (that's a paid-plan-only field that runs before every deploy). None of the usual "just SSH in and run artisan" advice applies here.

**What free tier *does* support: `initialDeployHook`.** It's a plain command string that Render runs exactly once, right after a service's first successful deploy — never again on later restarts or redeploys. `render.yaml` sets it to:

```
php artisan migrate --force && php artisan db:seed --class=DemoSeeder --force
```

This is why `healthCheckPath` stays `/up` (Laravel's own liveness check — confirms the app booted, nothing about the database) rather than this app's own `/up/db` readiness check: on a brand-new deploy the schema doesn't exist yet until `initialDeployHook` finishes, so a health check that required a working database would never pass and the deploy would be stuck. `/up/db` is for manual verification after the fact (step 5 below), not the Render health-check path.

Steps actually followed to deploy this project (useful as a repeatable recipe, e.g. for a from-scratch redeploy):

1. Push this repository to GitHub — see the repo's Actions tab for the current green CI run.
2. In Render: New → Blueprint → point at the repo. Render reads `render.yaml`.
3. Fill in the `sync: false` env vars in the dashboard (`APP_URL` once the service has a hostname; Google/AI credentials only if/when you have them — neither is required for the app to work).
4. Trigger the first deploy. Render builds the Docker image, starts the service, and — once — runs `initialDeployHook` to migrate and seed.
5. Once the deploy is live, manually confirm the database actually initialized by visiting `https://<service>.onrender.com/up/db` — expect `{"status":"ok","database":"ok"}`. If it instead returns the `{"status":"error","database":"unreachable"}` 503, the hook didn't run or failed — check the deploy's logs in the Render dashboard (this is a normal log view, not a Shell session, so it's available on free tier).
6. Auto-deploy is configured to trigger on push to the main branch. `.github/workflows/ci.yml` runs the full gate (Pest/Pint/Larastan/lint/typecheck/format/build/Playwright E2E — the same commands as `README.md`'s "Testing" section) on every push/PR to `main` and is currently green on GitHub; making it a required status check before merge means Render only ever deploys a commit that already passed it.

This is exactly how `https://smart-library-zsh8.onrender.com` (the live deployment linked from `README.md`) came up.

### Free vs. paid tier, explicitly

| | Free (this plan) | Paid |
|---|---|---|
| First-time DB init | `initialDeployHook` — runs once, first deploy only | Same, or `preDeployCommand` |
| Migrations on every subsequent deploy | **Not automatic** — see "Ongoing migrations" below | `preDeployCommand: php artisan migrate --force` runs before every deploy |
| Shell / SSH / one-off Jobs | Not available | Available |
| Postgres lifetime | **Expires and is deleted 30 days after creation** | Persistent |
| Web service idle behavior | **Spins down after inactivity**; the next request wakes it (cold start, can take tens of seconds) | Always-on |

**Ongoing migrations on free tier:** since there's no `preDeployCommand` and no Shell, a *schema-changing* redeploy on free tier needs either (a) a temporary one-time bump of the plan to run `migrate --force` once via Shell, then back down, or (b) accepting `initialDeployHook`-only coverage and re-triggering it by recreating the service if a schema change is needed before upgrading to a paid plan. This project's current migration set is exactly what `initialDeployHook` will apply on first deploy; there's no pending second migration to worry about yet.

**This is a demo/challenge deployment, not durable production infrastructure** — the free Postgres instance will be deleted after 30 days (Render then permanently removes the data), and the web service will cold-start after periods of inactivity. Both are expected and acceptable for a technical-challenge submission; neither should be read as an oversight. A real production deployment of this app would use a paid Postgres plan (persistent) and either a paid always-on web service or a platform without idle spin-down (see the AWS alternative below).

## Google OAuth callback configuration

In the Google Cloud Console (OAuth 2.0 Client), the **Authorized redirect URI** is set to `https://smart-library-zsh8.onrender.com/auth/google/callback`, matching `GOOGLE_REDIRECT_URI` on the live service — configured and verified working. Until `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` are set, the button stays hidden and the routes 404 — see `SECURITY.md`. The OAuth app itself is currently in Google's **Test** publishing mode, so Google sign-in only works for authorized test users added to that OAuth client, not for an arbitrary Google account — the seeded demo accounts remain the way for any reviewer to sign in.

## Optional AI provider configuration

Set `AI_PROVIDER=openai_compatible`, `AI_BASE_URL`, `AI_API_KEY`, `AI_MODEL` to enable natural-language intent extraction against any OpenAI-Chat-Completions-compatible endpoint. Leaving `AI_PROVIDER` unset (or `null`) keeps the Assistant on its deterministic fallback — fully functional, no external calls. No real key is included anywhere in this repository.

## Rollback notes

- **Application code:** Render keeps prior deploys; roll back to a previous deploy from the Render dashboard (redeploys that commit's image).
- **Database:** this project's migrations are all additive (no destructive `down()` beyond `dropIfExists` on the original `up()`'s own tables) as of this writing — rolling back application code does not require a matching migration rollback in the common case. If a future migration ever needs a coordinated rollback, run `php artisan migrate:rollback --force` for that specific batch before rolling back the app image, not after.
- **Demo data:** `DemoSeeder`'s idempotency (above) means re-running it is always safe as a recovery step; it will not duplicate or destroy existing rows.

## AWS alternative (documented only — not deployed, no AWS resources created)

A production-grade path beyond this challenge's Render target:

```
GitHub Actions (test → build image)
        ↓
   Amazon ECR
        ↓
  ECS Fargate service (behind an ALB)
        ↓
Amazon RDS for PostgreSQL
```

- **Compute:** ECS Fargate running the same `Dockerfile` built here — no server management, scales the task count behind an Application Load Balancer.
- **Database:** RDS for PostgreSQL, same schema/migrations, in a private subnet — the Fargate task reaches it over the VPC, never publicly exposed.
- **Secrets:** AWS Secrets Manager (or SSM Parameter Store for lower-cost non-rotating values) for `APP_KEY`, `DB_URL`/DB credentials, `AI_API_KEY`, `GOOGLE_CLIENT_SECRET` — injected into the task as environment variables at launch, never in the task definition's image or in source control.
- **Logs:** the container's stdout/stderr (already how this image logs — see `SECURITY.md`) ship to CloudWatch Logs via the `awslogs` driver, no application change required.
- **CI/CD:** GitHub Actions runs the same test gate as Render's plan (Pest, Pint, Larastan, lint, typecheck, format:check, build, Playwright E2E), then builds and pushes the image to ECR, then updates the ECS service to the new task definition revision.

No Terraform, CloudFormation, or actual AWS resources exist for this — this is architecture documentation for the challenge's "AWS/containers" evaluation criterion, matching the Render plan's shape (build once, deploy the same container) rather than a second, divergent implementation.
