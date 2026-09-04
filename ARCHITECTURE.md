# Smart Library — Architecture

**Status: deployed and live** at `https://smart-library-zsh8.onrender.com`. All core domain features are implemented and verified end-to-end, both locally and in production (see §17 for Phase 2, §18 for Phase 3 — the Assistant's tool architecture, production Docker/deployment, and observability; §19 for the release that took this from "verified locally" to "live and reviewer-ready").

## 1. Purpose & Scope

A Mini Library Management System built to demonstrate strengths relevant to a PHP/Laravel + relational-database + AWS-containers role: clean MVC/domain layering, SOLID-friendly service boundaries, transactional/concurrency-safe business rules, and a scoped, non-overengineered application of hexagonal architecture where it actually earns its keep (the AI provider boundary) rather than everywhere.

## 2. Terminology Decision

The assignment text uses "checked in" / "checked out" ambiguously (their intended meaning is the reverse of common library-system usage — "checked in" = borrowed, "checked out" = returned). **This product does not use that phrasing anywhere** — in the domain model, the API, the UI, or the database. Instead:

- **Actions:** `BORROW BOOK`, `RETURN BOOK`
- **Book states:** `AVAILABLE`, `BORROWED`

This removes a real source of confusion for future maintainers and end users. Documented here and restated briefly in `README.md`.

## 3. Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12, PHP 8.5 |
| Frontend | Inertia.js + React 18 + TypeScript |
| Styling | Tailwind CSS |
| Database | PostgreSQL 18 |
| Dev environment | Docker (Laravel Sail) — no local PHP/Composer required |
| Testing | Pest 4 (+ pest-plugin-laravel) |
| Static analysis | Larastan (PHPStan for Laravel) |
| Code style | Laravel Pint |
| CI | GitHub Actions (`.github/workflows/ci.yml`) — backend/frontend/E2E jobs on every push |

Laravel is the core application — Inertia/React is the view layer rendered by Laravel, not a separate frontend service. This matches the assignment's explicit instruction not to substitute Next.js as the backend.

## 4. Environment Notes (this machine)

No PHP or Composer is installed on the host. All Composer/artisan commands during scaffolding were run through the official `composer:2` Docker image (which bundles PHP 8.5 + Composer) against a bind-mounted project directory. Day-to-day development going forward uses **Laravel Sail** (`./vendor/bin/sail ...`), which is already installed and configured (`compose.yaml`, PHP 8.5 app container + PostgreSQL 18 container).

Real environment issues hit and fixed during scaffolding (all verified resolved — see §16 for the full end-to-end verification run):

1. Breeze's generated `package.json` pinned `@types/node@^18.13.0`, which conflicts with `vite@7`'s peer requirement (`^20.19.0 || >=22.12.0`). Bumped to `^22.12.0` — a real fix, not a `--legacy-peer-deps` workaround.
2. Larastan flagged `VerifyEmailController` passing a nullable, generically-typed `Authenticatable` where `Verified`'s constructor wants `MustVerifyEmail`. Root cause: the scaffolded `App\Models\User` didn't actually implement `MustVerifyEmail` (its import was commented out, Laravel 12's default). Fixed at the source — implemented the interface on the model — plus a local `@var` hint in the controller for clarity.
3. `./vendor/bin/sail` refuses to run on Git Bash/MSYS (only macOS/Linux/WSL2 supported) — worked around by driving `docker compose` directly against Sail's generated `compose.yaml`, which needs no wrapper.
4. First HTTP request 500'd with `tempnam(): file created in the system's temporary directory` — root cause was `storage/` and `bootstrap/cache/` being unwritable by the container's non-root `sail` user on this Windows bind mount. Fixed with `chmod -R ugo+rwX storage bootstrap/cache` (documented in README as a Windows-specific step).
5. First HTTP request also hit `Vite manifest not found` — expected before a `npm run build` (or a running `npm run dev`) has produced `public/build/manifest.json`; not a bug, just a missing setup step, now documented in README.

## 5. Domain Model

### Book
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| title | string | indexed |
| author | string | indexed |
| isbn | string, unique | nullable — not every catalog entry will have a clean ISBN, but no two books share one when present |
| description | text, nullable | |
| category | string, nullable | indexed — single primary category for browsing |
| publication_year | smallint, nullable | |
| cover_path | string, nullable | storage disk path; placeholder cover shown in UI when absent |
| status | enum: `available`, `borrowed` | indexed; see §7 for how this stays consistent with `loans` |
| search_vector | tsvector, generated | Postgres full-text search over title + author + description; GIN index |
| timestamps | | |

### Tag / BookTag (many-to-many)
Normalized tagging (`tags` table: id, name unique; `book_tag` pivot: book_id, tag_id) rather than a denormalized array column — standard relational modeling, keeps tag search/filtering trivial with a plain join, and matches the "relational databases" emphasis in the target role more directly than a Postgres-specific array type would.

### User
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | |
| password | string, nullable | nullable because Google-OAuth-only users may never set one |
| google_id | string, unique, nullable | |
| role | enum: `admin`, `librarian`, `member` | indexed; default `member` |
| timestamps | | |

### Loan
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| book_id | FK → books | indexed |
| user_id | FK → users | indexed |
| borrowed_at | timestamp | |
| due_at | timestamp | set at borrow time (fixed loan period, e.g. 14 days — exact value a config constant, not hardcoded magic) |
| returned_at | timestamp, nullable | null = active loan |
| timestamps | | |

**Critical index:** a **unique partial index** on `loans (book_id) WHERE returned_at IS NULL`. This is the actual source of truth for "a book cannot have multiple active loans" — enforced by PostgreSQL itself, not just application code. Application code (wrapped in a transaction with `lockForUpdate()`) is the first line of defense for a good error message; the DB constraint is what makes it impossible to violate even under real concurrent requests (the classic check-then-act race condition). `books.status` is a denormalized, transactionally-updated read optimization for fast catalog filtering — the partial index is the actual guarantee.

### AuditEvent
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users, nullable | nullable for system-triggered events |
| event_type | string | e.g. `book.created`, `book.updated`, `book.deleted`, `book.borrowed`, `book.returned`, `user.role_changed` |
| subject_type | string | polymorphic-style reference (e.g. `Book`, `Loan`, `User`) |
| subject_id | bigint | |
| metadata | jsonb, nullable | before/after values where relevant |
| created_at | timestamp | indexed, append-only (no updated_at — audit rows are never edited) |

Populated via Laravel **Events + a single Listener** (`RecordAuditEvent`), not scattered manual inserts — every domain action that matters emits a domain event (`BookBorrowed`, `BookReturned`, `BookCreated`, etc.) and one listener turns those into `AuditEvent` rows. Keeps the audit concern in one place instead of duplicated across Actions.

## 6. Folder / Layering Architecture

```
app/
  Actions/                 # one class per real business operation
    Book/CreateBookAction.php
    Book/UpdateBookAction.php
    Book/DeleteBookAction.php
    Loan/BorrowBookAction.php   # transactional, lockForUpdate, emits BookBorrowed
    Loan/ReturnBookAction.php   # transactional, emits BookReturned
  AI/
    Contracts/AiProviderInterface.php
    Providers/NullAiProvider.php     # deterministic fallback, no external call
    Providers/OpenAiProvider.php     # real provider, used only if configured
    LibraryAssistant.php             # orchestrates intent -> tools -> response
    Tools/SearchCatalogTool.php
    Tools/GetBookDetailsTool.php
    Tools/GetAvailabilityTool.php
    Tools/GetMyLoansTool.php
    Data/SearchIntentData.php        # structured intent DTO
  Enums/
    UserRole.php  BookStatus.php  AuditEventType.php
  Events/
    BookCreated.php BookUpdated.php BookDeleted.php
    BookBorrowed.php BookReturned.php
  Listeners/
    RecordAuditEvent.php
  Http/
    Controllers/            # thin: validate (Form Request) -> call Action/query -> Inertia response
    Requests/                # StoreBookRequest, UpdateBookRequest, BorrowBookRequest, ...
    Middleware/
  Models/
    Book.php Tag.php Loan.php User.php AuditEvent.php
  Policies/
    BookPolicy.php LoanPolicy.php UserPolicy.php
  Providers/
    AppServiceProvider.php  # binds AiProviderInterface -> Null or real provider based on config
resources/js/
  Pages/                    # Inertia pages (Catalog, BookDetail, MyLoans, Admin/*, Dashboard, Auth/*)
  Components/
  Layouts/
tests/
  Feature/  Unit/
```

### Why this shape, not a full hexagonal ports-and-adapters everywhere

The assignment explicitly asks to demonstrate SOLID/MVC/DDD/Hexagonal awareness **without overengineering**. The judgment call made here: apply a real port/adapter boundary (`AiProviderInterface` + swappable providers) exactly where one is genuinely needed — the AI provider is a real external dependency that must be swappable and must have a working null-object fallback. Everywhere else (Book/Loan/User CRUD), Laravel's idiomatic stack — Eloquent models as the domain entities, Form Requests for input validation, Policies for authorization, Actions for business logic, Events for side effects — already gives clean separation of concerns without inventing a parallel domain-entity layer that would just mirror Eloquent models 1:1 for no behavioral benefit. That parallel layer is the "ceremony that adds no value" the assignment warns against.

## 7. Concurrency & Transactions

`BorrowBookAction::execute(Book $book, User $user)`:
1. Open a DB transaction.
2. `SELECT ... FOR UPDATE` the book row (`lockForUpdate()`).
3. Check `book.status === AVAILABLE` (fast, friendly error path).
4. Insert the `Loan` row — protected at the DB level by the unique partial index regardless of what the in-memory check above saw.
5. Update `book.status = BORROWED`.
6. Commit; dispatch `BookBorrowed` event (queued listener records the audit event outside the critical path).

If two requests race, one wins the row lock first; the second either sees `status = BORROWED` after acquiring the lock (fails cleanly with a good error message) or, in the pathological edge case, is caught by the unique partial index at insert time (fails with a DB-level integrity error, translated to a clean user-facing message). Both paths are covered by tests (§10).

`ReturnBookAction` is the mirror: transaction, set `returned_at`, set `book.status = AVAILABLE`, dispatch `BookReturned`.

## 8. Auth Model

- Session-based auth via Breeze (login, registration, password reset) — self-registration creates **MEMBER** accounts only. ADMIN/LIBRARIAN accounts are created via seeder (for the demo) or by an existing ADMIN (future admin-invite flow, not required for MVP) — nobody can self-register into a privileged role.
- **Google OAuth (bonus)** via Laravel Socialite: `/auth/google/redirect` → Google → `/auth/google/callback` → find-or-create by `google_id`/email, default role `member`. The "Sign in with Google" button is hidden client-side when `GOOGLE_CLIENT_ID` isn't configured, so the app never shows a button that can't work.
- **Demo/local login (required so reviewers can test without configuring OAuth):** three seeded accounts, one per role, with a documented password in `README.md`. Seeder is idempotent and clearly marked as demo-only data.

## 9. Role / Permission Matrix

| Action | ADMIN | LIBRARIAN | MEMBER |
|---|---|---|---|
| Browse/search catalog | ✅ | ✅ | ✅ |
| View book details/availability | ✅ | ✅ | ✅ |
| Create/edit/delete books | ✅ | ✅ | ❌ |
| Borrow a book (for self) | ✅ | ✅ | ✅ |
| Borrow a book (on behalf of another user) | ✅ | ✅ | ❌ |
| Return a book (own loan) | ✅ | ✅ | ✅ |
| Return a book (any user's loan) | ✅ | ✅ | ❌ |
| View own loan history | ✅ | ✅ | ✅ |
| View all loans / all users' history | ✅ | ✅ | ❌ |
| Manage users (create/edit/assign role/deactivate) | ✅ | ❌ | ❌ |
| Use Library Assistant (read-only) | ✅ | ✅ | ✅ (own loans only, per user) |

Product decision worth stating explicitly: this is a **self-service** smart library — members can borrow/return their own books directly (not only staff-mediated checkout), which is what "smart library" implies for a modern digital product. Librarians/admins retain the ability to act on behalf of any user (handling in-person situations, corrections).

Implementation: a `role` enum column + three Laravel **Policies** (`BookPolicy`, `LoanPolicy`, `UserPolicy`) — no permissions package. Three flat roles don't justify one; if granularity grows later, `spatie/laravel-permission` is the natural upgrade path, noted here rather than adopted prematurely.

## 10. Core Routes / Use Cases

| Method | Route | Who | Purpose |
|---|---|---|---|
| GET/POST | `/login`, `/register` | guest | Breeze auth |
| GET | `/auth/google/redirect`, `/auth/google/callback` | guest | Google SSO (bonus) |
| GET | `/dashboard` | any authenticated | Role-aware stats (admin/librarian: catalog + loan aggregates; member: personal loan summary) |
| GET | `/catalog` | any authenticated | Browse/search/filter (title, author, category, tag, availability) |
| GET | `/catalog/{book}` | any authenticated | Book detail + availability |
| POST | `/catalog/{book}/borrow` | any authenticated | Self-borrow (policy-checked) |
| GET | `/my-loans` | any authenticated | Own loan history |
| POST | `/my-loans/{loan}/return` | owner, or librarian/admin | Self-return |
| GET/POST/PUT/DELETE | `/admin/books...` | librarian, admin | Catalog CRUD |
| GET/POST | `/admin/loans...` | librarian, admin | All-loan visibility, borrow/return on behalf of a user |
| GET/POST/PUT | `/admin/users...` | admin | User management |
| POST | `/assistant/query` | any authenticated | Library Assistant — read-only NL search |

## 11. Library Assistant (AI Feature)

**Goal:** turn a natural-language message into one of a small set of real, read-only tool calls against the actual database — never let the model invent a book, a loan, or a summary figure.

```
User message ("Do you have Clean Architecture available?")
        │
        ▼
App\AI\Intent\ActionClassifier::classify($message, $user->isStaff())
        │  deterministic regex-based routing — runs BEFORE any AI call,
        │  identically whether or not a provider is configured
        ▼
   ┌────────────────┬──────────────────────┬───────────────┬────────────────────┐
   │ search_catalog  │ check_availability   │ get_my_loans   │ get_library_summary │
   │ (default)       │ ("do you have X",    │ ("what do I    │ (staff-only —       │
   │                 │  "is X available")   │  have borrowed")│  circulation counts)│
   └────────────────┴──────────────────────┴───────────────┴────────────────────┘
        │                                                          │
        ▼                                                          ▼
App\AI\Tools\LibraryTools — the Assistant's ENTIRE surface against the data.
Every method is read-only and returns real Eloquent rows or nothing:
   - searchCatalog(SearchIntentData): CatalogSearchResult
   - getBookDetails(string $titleGuess) / checkAvailability(...): BookMatch
   - getMyLoans(User $user)             <- always scoped to the authenticated user
   - getLibrarySummary()                <- caller MUST have already checked isStaff()
        │
        ▼
LibraryAssistant builds a grounded response: a message, real result rows
(books/loans/summary), and — only when there were confident results — a
deterministic "why this matched" reason list (keyword/author/tag/year/
availability). An AI provider, if configured, may only improve the WORDING
of the message for search_catalog/check_availability results it was
explicitly given; it cannot add a book that isn't in that list.
```

**Where `AiProviderInterface` fits in:** only two places, both upstream of the tool call above, never downstream of it.
1. `extractSearchIntent(string $query): SearchIntentData` — turns the free-text query into structured search parameters (keywords/author/isbn/tags/availability/year range) for `search_catalog`. `check_availability`/`get_my_loans`/`get_library_summary` don't call this at all — their "intent" is just the classified action plus (for `check_availability`) a title guess extracted deterministically by `ActionClassifier::extractAvailabilitySubject()`.
2. `summarize(string $query, Collection $books): string` — given ONLY the real, already-retrieved book rows, asked to phrase a short explanation. Called only when there's a non-empty result set and a real (non-Null) provider configured; its output is used as-is for the response message, never merged with anything the model might have added about books outside that list.

**Typo/fuzzy fallback (deterministic, not AI):** when the strict PostgreSQL full-text search (`Book::scopeSearch`, `tsvector`/`plainto_tsquery`) finds nothing for a non-empty keyword term, `LibraryTools::searchCatalog()` falls back to `Book::scopeFuzzy()` — a `pg_trgm` `similarity()` comparison against `title`/`author`. A match scoring ≥ 0.35 is returned as a real result (`usedFuzzy: true`, surfaced in "why this matched"); a lower-but-plausible match (≥ 0.15) is offered as a single "did you mean …" suggestion instead of being silently discarded or silently shown as if confident. This is what makes `arquitecture clea` find *Clean Architecture* — no AI call involved, so it works identically with `AI_PROVIDER=null`.

**Anti-hallucination guarantees:**
- The model is never the source of book, loan, or summary data — only of (a) structured search parameters and (b) an optional wording improvement strictly grounded in real rows passed to it.
- `LibraryAssistant::extractIntent()` and `::summarizeGracefully()` each independently catch `Throwable` — a provider timeout, malformed JSON, non-2xx response, or rate limit all degrade to the deterministic path (`NullAiProvider`'s parser, or the deterministic message builder) rather than surfacing a raw exception to the user.
- **Read-only, by construction, not by convention.** `LibraryTools` has no borrow/return/delete/role-change method at all — there is no code path from the Assistant to a mutation, so there's nothing to accidentally expose later by adding an AI-facing wrapper around an existing action.
- `getMyLoans` is always scoped server-side to `$user->id` — the model/classifier cannot ask for someone else's loans. `getLibrarySummary` is only ever invoked after `$user->isStaff()` is checked in `LibraryAssistant::query()` (and `ActionClassifier` won't even classify a non-staff query as `get_library_summary` in the first place — belt and suspenders).

**Provider abstraction (unchanged shape from Phase 2):**
```php
interface AiProviderInterface {
    public function extractSearchIntent(string $query): SearchIntentData;
    public function summarize(string $query, Collection $books): string;
}
```
- `NullAiProvider`: deterministic parsing (availability/year/author phrases, stopword-filtered keywords), zero external calls. **This is what makes "fully functional without an AI API key" literally true.**
- `OpenAICompatibleAiProvider`: any OpenAI-Chat-Completions-compatible endpoint, configured via `AI_PROVIDER=openai_compatible` / `AI_BASE_URL` / `AI_API_KEY` / `AI_MODEL` — no vendor hard-coded.
- Bound in `AppServiceProvider` based on `config('services.ai.provider')`. No API key ever reaches the frontend (verified: it's read once server-side at the point of the HTTP call, never included in any Inertia prop or JSON response).

## 12. Testing Plan (Pest)

| Area | Tests |
|---|---|
| Book CRUD | admin/librarian can create/update/delete; member forbidden (403); validation rejects missing title / duplicate ISBN |
| Search | search by title/author/category returns expected matches; no-match returns a clean empty result, not an error |
| Borrow | available book → loan created, `due_at` set correctly, status flips to `BORROWED` |
| Cannot double-borrow | second borrow attempt on an already-borrowed book fails cleanly; a concurrency-style test asserts the DB-level unique partial index actually rejects a second active loan row for the same book |
| Return | owning user or librarian/admin can return; `returned_at` set, status flips back to `AVAILABLE`, book becomes borrowable again |
| Role permissions | dataset-driven Pest test matrix across all three roles × protected actions (mirrors §9 table) |
| Audit trail | borrowing/returning produces the expected `AuditEvent` row (correct type, subject, user) |
| Library Assistant | NL query → expected structured intent → correct real results, with `NullAiProvider` (no key configured) and with a faked real provider; asserts no book data appears in output that isn't a real row |

Also: Pint (style), Larastan (static analysis), ESLint, `tsc --noEmit`, and Prettier all run as part of the local quality gate (see `README.md`'s "Testing"), and identically in GitHub Actions CI (`.github/workflows/ci.yml`) on every push. A formal Playwright E2E suite (`tests/e2e/`, `npm run test:e2e`) covers the reviewer-critical browser flows Pest can't — see §18.

## 13. Deployment Architecture (live — see `docs/DEPLOYMENT.md`)

- **Production Docker image** (`Dockerfile`, separate from `compose.yaml`'s local dev environment): a 3-stage build — `frontend` (Vite production build), `vendor` (`composer install --no-dev --optimize-autoloader`), `runtime` (`php:8.5-fpm-alpine` + nginx, supervised, no dev tooling). This has been built and verified locally (`docker build -f Dockerfile .` succeeds against this codebase), not just written.
- **Deployed on Render** — `render.yaml` (Blueprint): a `smart-library` Docker web service + a `smart-library-db` Postgres instance (both `region: oregon`), `healthCheckPath: /up`, `APP_KEY` and all other secrets `sync: false` (generated locally with `php artisan key:generate --show` for `APP_KEY` specifically, then entered once in the dashboard — never in the file; Render's own `generateValue: true` doesn't produce a Laravel-compatible key). Live flow: `GitHub → CI → Docker build → Render Web Service → Render PostgreSQL`, auto-deploying every push to `main` once CI is green. Full walkthrough in `docs/DEPLOYMENT.md`.
- **Documented alternative: AWS ECS Fargate** — same container image, pushed to ECR, run on Fargate behind an ALB, RDS for PostgreSQL, Secrets Manager for env secrets, CloudWatch for logs, GitHub Actions for CI/CD. Architecture only — no Terraform, no AWS resources created. See `docs/DEPLOYMENT.md` §"AWS alternative".

## 14. Security Model

- No secrets in the repo; `.env` gitignored; `.env.example` carries empty placeholders only (already true after scaffolding).
- Every mutation is authorized server-side via a Policy — the frontend's role-aware UI is a convenience, never the actual boundary.
- Form Requests validate all input; Eloquent `$fillable` prevents mass-assignment.
- CSRF protection (default for session-based Inertia requests); React escapes output by default (no `dangerouslySetInnerHTML` planned).
- SQL injection: Eloquent/query builder throughout — no raw string interpolation into queries.
- Borrow transactions: `lockForUpdate()` + the DB unique partial index (§7) — two independent layers, not one.
- Rate limiting (Laravel's `throttle` middleware) on auth routes and on `/assistant/query` specifically, to bound AI-provider cost/abuse exposure.
- AI API key read only server-side from config/env — never sent to the client.

## 15. End-to-End Verification Run (Phase 1 scaffold)

Confirmed working, in this order, on this machine:

1. `docker compose up -d --build` — both containers (`laravel.test`, `pgsql`) start healthy.
2. `docker compose exec laravel.test php artisan migrate` — default Laravel migrations run cleanly against the real PostgreSQL 18 container.
3. `npm run build` — TypeScript compiles, Vite build succeeds (22 assets emitted).
4. `curl http://localhost/` — `200 OK` (after fixes #4 and #5 above).
5. `docker compose exec laravel.test php artisan test` — **25/25 passing**, 61 assertions (Breeze's default auth/profile test suite — nothing domain-specific exists yet).
6. `php vendor/bin/pint` — clean (one pre-existing scaffold style issue found and fixed).
7. `php vendor/bin/phpstan analyse` (Larastan, level 5) — clean (one real type issue found and fixed at the source, not suppressed).

## 16. Open Decisions Deferred to David (not blocking Phase 1)

- Exact loan period (defaulting to 14 days as a config constant unless told otherwise).
- Whether to pursue AWS ECS deployment for the "live deployment" bonus, or a faster PaaS path first.
- Which AI provider to actually configure for the real (non-Null) `AiProviderInterface` implementation.

## 17. Phase 2 Implementation Notes

Two refinements were applied during implementation, both requested up front rather than discovered mid-build:

1. **Availability has exactly one source of truth.** No `book.status` column exists. `Book::$availability` is a computed accessor over the `activeLoan` relation (`loans.returned_at IS NULL`). A PostgreSQL partial unique index (`loans_book_id_active_unique`, `WHERE returned_at IS NULL`) guarantees at most one active loan per book at the database level; `BorrowBookAction` additionally uses a transaction with `lockForUpdate()` so the app-level check-then-create is race-safe even before the database constraint would catch it.
2. **The AI provider is a real port/adapter boundary**, and nowhere else: `AiProviderInterface` with `NullAiProvider` (zero external calls, deterministic keyword/date/author parsing) and `OpenAICompatibleAiProvider` (any OpenAI-Chat-Completions-compatible endpoint) behind it. Configured via `AI_PROVIDER` / `AI_BASE_URL` / `AI_API_KEY` / `AI_MODEL`. The product is fully functional with no key configured.

**A real bug found during visual QA, not by the automated test suite:** `Book` didn't declare `$appends = ['availability']`, so the computed `availability` accessor — despite being correct in isolation — was silently dropped from every JSON/Inertia response. The catalog UI showed nearly every book as "Borrowed" regardless of actual state, because the frontend's `AvailabilityBadge` received `undefined` for every book. None of the 70 Pest tests at the time caught this, because they asserted against the Eloquent model in PHP (where the accessor works fine), not against the actual serialized payload a browser receives. Fixed by adding `$appends` (and `$hidden = ['search_vector']`, which was also leaking the raw tsvector column to the frontend for no reason), and a regression test was added to `SearchTest.php` asserting `books.data.0.availability` directly against the Inertia response — the exact contract that broke. This is the concrete reason the explicit "actually launch the application and click through it" QA step in Phase 2's instructions mattered: it caught something the type checker, Larastan, and the full Pest suite all missed.

**Final verification (Phase 2):**

- `docker compose exec laravel.test php artisan test` — **70/70 passing**
- `./vendor/bin/pint --test` — clean, 105 files
- `./vendor/bin/phpstan analyse` (Larastan, level 5) — clean
- `npm run build` — clean production build
- Manual browser QA (Playwright-driven): admin login, admin book create/edit, catalog search, member login, member borrow, double-borrow correctly blocked, member return, member forbidden from `/admin/*`, and an assistant query with no AI key configured — **11/11 flows passing** against realistic seeded demo data

## 18. Phase 3 Implementation Notes

Assistant rebuilt into the tool-based architecture described in §11 (deterministic action classifier, `LibraryTools`, pg_trgm fuzzy fallback, per-action grounded responses); Google OAuth given a real UI surface (`googleOAuthEnabled` shared prop, "Continue with Google" on Login/Register, gated both client- and server-side); a "Demo access" panel added to the login screen (prefills the form, no auth-bypass route); Dashboard's "Recent activity" changed from a bare event-type label to `AuditEvent::describe()`'s human-readable sentences; Manage Books gained a mobile card layout (was horizontal-scroll-only); book delete now uses the app's own `Modal` instead of `window.confirm()`; a database-readiness health check (`/up/db`, alongside Laravel's own `/up` liveness check) and a request-correlation-ID middleware (`AssignRequestId`) were added; a production `Dockerfile` (separate from Sail's dev `compose.yaml`) was written, built, and run; `render.yaml` and `docs/DEPLOYMENT.md` were prepared for a Render deploy that has **not** been executed; a formal Playwright E2E suite (`tests/e2e/`) replaced the ad-hoc `.qa-*.cjs` scripts from Phase 2 (now gitignored, kept locally only).

**Two real bugs found by actually building and running the production image, not by writing it:**
1. `docker-php-ext-install opcache` fails on the `php:8.5-fpm-alpine` base image at time of writing (`cp: can't stat 'modules/*'`), reproducibly, even built in total isolation from every other extension. Root-caused as a base-image/toolchain issue (PHP 8.5 is very new), not fixable from this Dockerfile alone — opcache was dropped from the image with an inline comment explaining why, rather than silently worked around or left failing.
2. The `/up/db` readiness check returned a raw Laravel 500 page instead of its own clean JSON 503 when the database was actually unreachable — because `SESSION_DRIVER=database` means the session middleware tries to query the DB *before* the route's own try/catch ever runs, on every request including this one. Fixed by excluding the whole `web` middleware group from that specific route (`->withoutMiddleware('web')`) — verified by actually running the built image with no database configured and confirming the response changed from an HTML 500 to `{"status":"error","database":"unreachable"}` at 503.

**Assistant fuzzy-matching calibration (measured, not guessed):** `similarity()` scores against the real seeded catalog — `arquitecture clea` → *Clean Architecture* at 0.542; `php beginners` → *PHP for Beginners* at 0.778 (with *Beginner's Guide to Laravel Testing* correctly ranked lower, 0.195, below the confident threshold); a clearly unrelated query (`xyzzy quantum toaster`) returns zero rows. The 0.35 "confident" / 0.15 "suggestion floor" thresholds in `LibraryTools` were set from these real measurements, not picked arbitrarily.

**Final verification (Phase 3):**

- `docker compose exec laravel.test php artisan test` — **86/86 passing**, 327 assertions
- `./vendor/bin/pint --test` — clean, 116 files
- `./vendor/bin/phpstan analyse` (Larastan, level 5) — clean
- `npm run lint` / `npm run typecheck` / `npm run format:check` — all clean
- `npm run build` — clean production build
- `docker build -f Dockerfile .` — succeeds; the built image was actually run (`docker run`) and its `/up` and `/up/db` endpoints curled and confirmed correct, including the security-relevant details (`expose_php=Off` → no `X-Powered-By` header; `APP_DEBUG=false` → no stack traces; `X-Request-Id` generated and honored)
- Playwright E2E (`tests/e2e/`, 13 tests covering the 14 required reviewer flows — "admin login" and "dashboard loads" share one test): **13/13 passing** when each spec file is run as its own process. Run as one continuous 13-test sequential suite, 2-4 occasionally time out from this local setup's documented resource contention over a long run (not reproducible when isolated per-file, and not an application defect) — see `README.md`'s testing section for the honest caveat.

## 19. Release Notes — GitHub, CI, Production Deployment, and Final Submission

After Phase 3, the project went through: a first Git commit and CI wiring; pushing to a private GitHub repository with all three CI jobs (backend/frontend/E2E) green; a deliberate git history rewrite (via `git commit-tree`, verified tree-identical, `--force-with-lease` push) down to a clean two-commit history before the repo's history became load-bearing; and an actual production deployment to Render, including finding and fixing three real production-only bugs that never appeared in local Docker (a `generateValue: true` `APP_KEY` that isn't a valid Laravel key; missing `trustProxies()` causing mixed-content HTTP asset URLs behind Render's TLS-terminating edge; and a silently-discarded `email_verified_at` on Google sign-up caused by a field correctly excluded from `$fillable`, masking the actually-requested "check Google's own verification claim" fix underneath it). Each fix shipped as its own commit, verified against real GitHub Actions runs and the live deployment, not just locally.

This final submission release captured screenshots directly from that live deployment, brought `README.md`/`SECURITY.md`/`docs/DEPLOYMENT.md` in line with the shipped, verified state (no more "not deployed yet" language), and re-ran the full quality gate one last time:

- `docker compose exec laravel.test php artisan test` — **93/93 passing**, 339 assertions
- `./vendor/bin/pint --test` — clean, 117 files
- `./vendor/bin/phpstan analyse` (Larastan, level 5) — clean
- `npm run lint` / `npm run typecheck` / `npm run format:check` — all clean
- `npm run build` — clean production build
- Playwright E2E — **13/13 passing** (each spec file run as its own process, per the same documented local-resource-contention caveat as §18; CI runs the full suite as one job and has been consistently green)
- Secret scan across the working tree and the full `main` branch git history (diffs and commit messages) — no API keys, database credentials, `APP_KEY` values, or AI attribution trailers found anywhere
