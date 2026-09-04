# Security

How Smart Library handles authorization, secrets, and the AI boundary. See `ARCHITECTURE.md` for the domain design these decisions sit on top of, and `docs/DEPLOYMENT.md` for how secrets are supplied in a real deployment.

## Threat boundaries

- **The browser is never trusted.** The frontend hides actions a role can't perform (a Member never sees a "Delete book" button), but every one of those actions is independently re-checked server-side. Trying the underlying route directly (`DELETE /admin/books/{id}` as a Member, for example) returns a real `403`, not a UI-only restriction.
- **Server-side enforcement is via Laravel Policies** (`BookPolicy`, `LoanPolicy`, `UserPolicy`) for model-scoped abilities, plus route middleware (`staff`, `admin`) for the `/admin/*` route group. Both layers are exercised by the Pest suite (`RolePermissionsTest`) and by the Playwright E2E suite (a Member hitting `/admin/books` gets a real `403`).
- **CSRF** is Laravel's default `VerifyCsrfToken` middleware, active on every state-changing request. Session cookies are `SESSION_DRIVER=database`-backed, `httpOnly`, and (in production, see `docs/DEPLOYMENT.md`) `SESSION_SECURE_COOKIE=true` so they're never sent over plain HTTP.
- **Passwords** are bcrypt-hashed (`password` cast as `hashed` on the `User` model). Login is rate-limited by Laravel Breeze's default throttle.

## Google OAuth and role handling

- The "Continue with Google" button is only ever rendered when `GOOGLE_CLIENT_ID` is actually configured (`googleOAuthEnabled`, shared server-side via `HandleInertiaRequests`) — a reviewer without that env var configured never sees a button that would 404.
- The OAuth routes themselves (`/auth/google/redirect`, `/auth/google/callback`) independently `abort(404)` if Google isn't configured, regardless of what the frontend shows — the UI check is a convenience, not the actual guard.
- **A brand-new Google sign-in is always created as `MEMBER`.** OAuth profile data (name, email, provider ID) is never used to select or elevate a role — see `GoogleController::callback()` and `tests/Feature/Auth/GoogleSsoTest.php`. Promoting a user to `LIBRARIAN`/`ADMIN` is only ever done explicitly by an existing Admin, through `/admin/users`.
- Google's `email_verified` OIDC claim is read from the raw userinfo payload (`GoogleController::googleEmailIsVerified()`) and only ever stamps `email_verified_at` when it is strictly `true` **and** the Google account's email matches the exact local account being linked — it never verifies a different, unconfirmed email address on that account.
- Returning users are matched first by `google_id`, then by email (to link an existing password-based account to Google) — never by name, which isn't a stable or unique identifier.
- In production, this app's Google OAuth client is currently in Google's **Test** publishing mode — Google sign-in works only for authorized test users added to that OAuth app, not for an arbitrary Google account. The seeded demo accounts (`README.md`) remain available to every reviewer regardless.

## Secret handling

- No secret is ever committed. `.env` is git-ignored; `.env.example` documents every variable with empty placeholders, and the application is fully functional with all of them left empty (AI provider, Google OAuth).
- The production Docker image (`Dockerfile`) never copies a `.env` file in. All runtime configuration — `APP_KEY`, `DB_URL`, `AI_API_KEY`, `GOOGLE_CLIENT_SECRET`, etc. — comes from real environment variables supplied by the deployment platform at container start.
- `APP_KEY` is generated locally with `php artisan key:generate --show` and entered once into the Render dashboard (`render.yaml` marks it `sync: false`) — never committed, never Render's own generic `generateValue: true` generator, which doesn't produce a Laravel-compatible base64 key.

## The AI boundary — what the model can and cannot do

This is the app's most important trust boundary, so it's enforced structurally, not by prompting:

- An AI provider (`OpenAICompatibleAiProvider`, when configured) is only ever asked to do two things: turn free text into a `SearchIntentData` (structured query parameters — keywords, author, tags, a year range) and, separately, write a short explanation grounded in book rows it was explicitly given. Neither call path lets the model hand back something that is rendered as a book.
- Every book, loan, and summary figure the Assistant shows comes from a real Eloquent query run by `App\AI\Tools\LibraryTools` against the actual `books`/`loans` tables — see `ARCHITECTURE.md`'s Assistant section for the full request flow.
- The Assistant's tool surface is **read-only**. There is no borrow, return, delete, or role-change path reachable through the Assistant, by design — those remain explicit, individually-authorized product actions with their own server-side checks.
- If the configured AI provider times out, returns malformed JSON, rate-limits, or is simply unreachable, `LibraryAssistant` catches it and falls back to the deterministic parser — the user sees a normal (if less nuanced) answer, never a raw provider exception or stack trace.
- The Assistant endpoint (`POST /assistant/query`) is rate-limited (`throttle:assistant`, 10/minute per user) independently of any per-provider rate limiting, bounding cost/abuse exposure even with a real key configured.

## Rate limiting

- Login: Laravel Breeze's default throttle (by email + IP).
- The Library Assistant: `RateLimiter::for('assistant', ...)`, 10 requests/minute per authenticated user (falls back to IP for edge cases). See `AppServiceProvider::boot()`.

## Safe logging

- `APP_DEBUG=false` in every non-local environment (enforced in `render.yaml` and the production `Dockerfile`'s `ENV`) — Laravel's debug error pages, which can include request details and stack traces, are never shown to an end user in production.
- `docker/production/php.ini` disables `display_errors` and sends PHP-level errors to stderr rather than into a response body.
- Nothing in this codebase logs request bodies, passwords, API keys, or OAuth tokens. `AI_API_KEY` and `GOOGLE_CLIENT_SECRET` are read once from config at the point they're used (`Http::withToken(...)`, Socialite's driver config) and never written to `Log::` calls.
- `AssignRequestId` middleware attaches a request-correlation ID to the response (`X-Request-Id` header) and to the log context for that request — useful for tracing an issue a reviewer reports, without logging anything sensitive.

## Responsible disclosure (demo project note)

This is a technical-challenge submission, not a production service handling real user data. The seeded demo accounts (`admin@example.test`, `librarian@example.test`, `member@example.test`) use an intentionally simple, publicly-documented password (see `README.md`) — this is by design for reviewer convenience, not an oversight, and these credentials must never be reused for anything real. If this project is ever deployed somewhere genuinely public with real user accounts, the demo seeder and its documented credentials should be removed first.
