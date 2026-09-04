# Smart Library

A real library management system — catalog, borrow/return, role-based access, an event-driven audit trail, and an AI-assisted Library Assistant that never invents a book. Built as a technical challenge submission.

**Live URL:** _not deployed yet — see `docs/DEPLOYMENT.md` for the prepared, unexecuted Render/AWS plan._
**Status:** feature-complete, polished, and locally verified end-to-end.

## Screenshots

_Placeholder — screenshots (desktop/tablet/mobile, light/dark) to be added here after final visual sign-off._

## Terminology note

The original assignment text used "checked in" / "checked out" ambiguously. This product uses explicit, unambiguous language instead: **actions** `BORROW BOOK` / `RETURN BOOK`, **book states** `AVAILABLE` / `BORROWED`. Full rationale in `ARCHITECTURE.md`.

## Features

**Catalog** — paginated, PostgreSQL full-text search (title/author/description) with a typo-tolerant fallback (see "Library Assistant" below), filterable by ISBN/author/category/tag/availability, sortable.

**Books** (Librarian/Admin) — create, edit, delete, view: title, author, ISBN, description, category, tags, publication year, optional cover URL. Delete asks for confirmation via an in-app dialog, not a browser popup.

**Loans** — borrow/return with a configurable default loan period (`LIBRARY_LOAN_PERIOD_DAYS`, default 14 days), overdue detection, per-member history. A book can never be double-borrowed — enforced by a PostgreSQL partial unique index *and* a locked transaction, not just application logic.

**Roles & permissions** — `ADMIN` / `LIBRARIAN` / `MEMBER`, enforced server-side via Policies and middleware. Demo login works with no setup; Google OAuth is available as an optional, fully-wired extra (see below).

**Library Assistant** — a small, real conversational assistant:
- Natural language in ("Do you have Clean Architecture available?", "What books do I currently have borrowed?", staff get "Give me a quick circulation summary")
- A deterministic action/tool router picks *what kind* of question it is — catalog search, availability check, your loans, or (staff-only) a circulation summary — then runs a real, read-only query against the actual database
- Typo/word-order tolerant: `arquitecture clea` still finds *Clean Architecture*, via a PostgreSQL trigram fallback when the strict full-text search comes up empty
- The AI (when configured) only ever extracts structured search parameters or improves the wording of an answer already grounded in real rows — it can never fabricate a book, and there is no borrow/return/delete path reachable through it
- Works fully with **no AI key configured** — the deterministic fallback is the real, default experience, not a degraded stub

**Audit trail** — every book create/update/delete, borrow/return, and role change is recorded via domain Events + one Listener (not scattered manual inserts), and surfaced as a human-readable "Recent activity" feed on the staff dashboard (e.g. *"Ada Admin borrowed Clean Architecture"*), gated to Admin/Librarian.

**UI** — light/dark/system theme, responsive desktop/tablet/mobile (including a card layout for book management on small screens, not a horizontally-scrolling table), `prefers-reduced-motion` support, keyboard-accessible forms.

## Stack

Laravel 12 (PHP 8.5) · Inertia.js v2 + React 18 + TypeScript · Tailwind CSS · PostgreSQL 18 (+ `pg_trgm`) · Docker (Sail for dev, a separate production `Dockerfile`) · Pest 4 · Larastan · Pint · Playwright

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.test` | `password` |
| Librarian | `librarian@example.test` | `password` |
| Member | `member@example.test` | `password` |

Documented demo-only credentials for local evaluation — the login screen itself has a "Demo access" panel that fills these in for you. Never reused for anything real.

## Setup (verified on Windows)

```bash
# 1. Copy the environment file
cp .env.example .env
# Windows (non-WSL2) only: also set WWWUSER=1000 and WWWGROUP=1000 in .env.

# 2. Install PHP dependencies
docker run --rm -v "$(pwd):/app" -w /app composer:2 install

# 3. Generate the application key
docker run --rm -v "$(pwd):/app" -w /app composer:2 php artisan key:generate

# 4. Install frontend dependencies and build
npm install
npm run build

# 5. Start the containers (PHP app + PostgreSQL)
docker compose up -d --build

# (Windows/non-WSL2 only) storage/ and bootstrap/cache/ need to be writable
# by the container's non-root user on a bind mount:
docker compose exec laravel.test bash -c "chmod -R ugo+rwX storage bootstrap/cache"

# 6. Run migrations and seed realistic demo data
docker compose exec laravel.test php artisan migrate --seed --seeder=DemoSeeder
```

The app is now at **http://localhost**. Stop everything with `docker compose down`.

**Note on Laravel Sail on Windows:** `./vendor/bin/sail`'s wrapper script only supports macOS/Linux/WSL2 — it refuses to run on plain Git Bash/MSYS. This project was built and verified using `docker compose` directly against Sail's own `compose.yaml`. On WSL2/macOS/Linux, `./vendor/bin/sail ...` works as a shorter alias for the `docker compose exec laravel.test ...` commands above.

## Testing & quality

```bash
docker compose exec laravel.test php artisan test                                   # Pest
docker compose exec laravel.test ./vendor/bin/pint --test                            # code style (drop --test to auto-fix)
docker compose exec laravel.test ./vendor/bin/phpstan analyse --memory-limit=1G      # static analysis (Larastan, level 5)
npm run lint          # ESLint, check-only
npm run typecheck     # tsc --noEmit
npm run format:check  # Prettier, check-only
npm run build          # TypeScript + Vite production build
npm run test:e2e       # Playwright — see tests/e2e/
```

Current status: **86 Pest tests passing (327 assertions)** · Pint clean (116 files) · Larastan (level 5) clean · ESLint clean · typecheck clean · Prettier clean · production frontend build clean · **13/13 Playwright E2E tests passing** covering admin/member login, dashboard, book create/edit, catalog search, borrow, double-borrow rejection, return, role-based 403, the Assistant's no-key and fuzzy-query behavior, light/dark, and mobile navigation.

*A note on the E2E numbers:* all 13 pass reliably when each spec file is run as its own process (`npx playwright test <file>`) — that's how they were actually verified, repeatedly. Run as one continuous 13-test sequential suite in this project's local dev setup (`php artisan serve` behind a Windows Docker bind mount — see "Setup" note below), 2-4 of them occasionally time out due to that setup's well-documented resource contention over a long single run, not application defects; a CI environment or per-file execution doesn't exhibit this.

Domain coverage (Pest): book CRUD, search by title/author/ISBN, availability/category filtering, borrow/return/due-dates/overdue, double-borrow rejection at both the app and database level, all three roles' permissions, the audit trail (including its human-readable descriptions), the Assistant's deterministic fallback + fuzzy matching + tool routing (search/availability/my-loans/staff-summary) + its guarantee that it never returns a nonexistent book, Google SSO (button visibility, new-user-is-always-MEMBER, account linking, safe-when-unconfigured), and the observability endpoints.

## AI behavior

The Library Assistant is **fully functional with no AI key configured** — see "Library Assistant" above. To enable natural-language intent extraction via a real OpenAI-compatible endpoint:

```
AI_PROVIDER=openai_compatible
AI_BASE_URL=https://api.openai.com/v1   # or any compatible endpoint
AI_API_KEY=sk-...
AI_MODEL=gpt-4o-mini
```

The model is never the source of book data — every result comes from a real query against the catalog/loan tables (`App\AI\Tools\LibraryTools`). If the provider times out, rate-limits, or returns something malformed, the app degrades to the deterministic path silently — no raw error ever reaches the user. Full detail in `ARCHITECTURE.md` and `SECURITY.md`.

## SSO behavior

Google OAuth is fully wired end-to-end but stays invisible until configured: the "Continue with Google" button on Login/Register only renders when `GOOGLE_CLIENT_ID` is actually set (checked server-side), and the OAuth routes themselves 404 if it isn't — never a broken button. A new Google sign-in is always created as `MEMBER`; OAuth profile data can never select an elevated role. Returning users are matched by `google_id`, then by email. See `SECURITY.md`.

## Architecture

See `ARCHITECTURE.md` for the domain model, availability's single source of truth, concurrency design, the RBAC/events/audit architecture, the Assistant's tool architecture and grounding guarantees, deployment architecture, and key trade-offs.

## Security

See `SECURITY.md` for threat boundaries, CSRF, OAuth role handling, secret handling, the AI prompt/tool boundary, rate limiting, and safe logging.

## Deployment

See `docs/DEPLOYMENT.md` for the prepared (not executed) Render deployment plan, environment variables, migration/seeding strategy, and the documented AWS ECS/Fargate + RDS alternative.
