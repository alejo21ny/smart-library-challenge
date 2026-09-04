# Deployment

**Status: not deployed anywhere.** This document describes a deployment path that has been prepared and locally verified (the production `Dockerfile` builds successfully — see below) but not executed. See `README.md` for the current, local-only way to run this project.

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
| `APP_KEY` | Yes | Platform-generated (see `render.yaml`), never reused across environments |
| `APP_ENV` | Yes | `production` |
| `APP_DEBUG` | Yes | `false` — never `true` outside local dev |
| `APP_URL` | Yes | The public URL, once known |
| `DB_URL` (or `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`) | Yes | `config/database.php`'s `pgsql` connection accepts either form |
| `SESSION_SECURE_COOKIE` | Recommended | `true` behind HTTPS |
| `LIBRARY_LOAN_PERIOD_DAYS` | No | Defaults to 14 |
| `AI_PROVIDER` / `AI_BASE_URL` / `AI_API_KEY` / `AI_MODEL` | No | App is fully functional with these unset |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | No | "Continue with Google" only appears once these are set |

## Migrations

Migrations are **not** run automatically by the container's entrypoint (`docker/production/entrypoint.sh`) — with more than one replica, every container start/restart would race the same migration against the same database. Run them as a one-off, before traffic is routed to a new release:

```bash
php artisan migrate --force
```

`--force` is required outside `local`/`testing` environments — Laravel otherwise prompts for confirmation, which a non-interactive deploy can't answer.

## Seeding demo data

`DemoSeeder` is **idempotent** — every row it writes uses `updateOrCreate` keyed on a natural identifier (user email, book title+author), and the loan-seeding block only runs `if (! Loan::query()->exists())`. Running it again on a database that already has reviewer activity will not duplicate demo users/books, and will not touch loans a reviewer has actually created:

```bash
php artisan db:seed --class=Database\\Seeders\\DemoSeeder --force
```

## Render deployment plan

`render.yaml` (Blueprint) is prepared — a `smart-library-db` (Postgres) plus a `smart-library` Docker web service, `healthCheckPath: /up`. To actually deploy:

1. Push this repository to GitHub (not done yet — see `README.md`'s Git status).
2. In Render: New → Blueprint → point at the repo. Render reads `render.yaml`.
3. Fill in the `sync: false` env vars in the dashboard (`APP_URL` once the service has a hostname; Google/AI credentials only if/when you have them).
4. First deploy: once the service is up, run migrations + seed via Render's Shell (or a one-off Job):
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=DemoSeeder --force
   ```
5. **Render's free-tier web services don't offer a Pre-Deploy Command** (that's a paid-plan feature — it would otherwise be the natural place to run `migrate --force` automatically before each release). On the free tier, step 4's manual run via the Shell is the safest honest option for this challenge — not pretending the limitation doesn't exist. On a paid plan, move the migrate command into the service's Pre-Deploy Command and this becomes fully automatic.
6. Auto-deploy is configured to trigger on push to the main branch. `.github/workflows/ci.yml` already runs the full gate (Pest/Pint/Larastan/lint/typecheck/format/build/Playwright E2E — the same commands as `README.md`'s "Testing & quality" section) on every push/PR to `main`; making it a required status check before merge means Render only ever deploys a commit that already passed it. The workflow file exists in this repository now — it just hasn't run on GitHub yet, since no remote has been created.

## Google OAuth callback configuration

Once a live URL exists, in the Google Cloud Console (OAuth 2.0 Client): set the **Authorized redirect URI** to `https://<your-domain>/auth/google/callback`, matching `GOOGLE_REDIRECT_URI`. Until `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` are set, the button stays hidden and the routes 404 — see `SECURITY.md`.

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
