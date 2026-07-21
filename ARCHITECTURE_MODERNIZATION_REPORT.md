# Architecture Modernization Report — SA Business Distribution

**Phase:** 3 — Enterprise Architecture Modernization
**Date:** 21 July 2026
**Scope:** Transform the application into an enterprise-grade architecture (autoloading, routing, DI, centralized config, logging) without redesigning business logic or adding new modules. Builds on Phase 2's Enterprise Foundation Recovery.

---

## Architectural changes

**Composer + PSR-4 autoloading.** Added `composer.json` declaring `App\` → `src/` and requiring PHP ≥8.1 with `ext-pdo`/`ext-pdo_mysql`. No third-party packages — every existing dependency was already zero. The old pattern of each page hand-chaining `require_once` for every controller/service/repository/model it needed is gone; classes now resolve automatically by namespace.

**Namespaced application tree.** All business logic moved into `src/` under `App\Models`, `App\Repositories`, `App\Services`, `App\Database`. This was a mechanical relocation: every method body is unchanged from the original `models/`, `repositories/`, `services/`, `config/database.php` files — only a namespace declaration and `use` imports were added. This includes carrying forward the Phase 2 bug fix in `CartService` (array vs. object access on repository results).

**Front controller + router.** `public/index.php` is now the single entry point. `App\Http\Router` matches the exact legacy URLs (`/login.php`, `/cart.php`, `/quote-api.php`, etc.) rather than introducing new route names — this was the key decision that let every existing link, form action, and JS reference in the views keep working with zero changes. `public/.htaccess` rewrites all non-file Apache requests to `index.php` for production; a `PHP_SAPI === 'cli-server'` check in `index.php` itself handles static-asset passthrough for local development via `php -S`.

**Minimal DI container.** `App\Container\Container` is a small id → closure factory with singleton caching. `App\Http\Kernel::registerBindings()` wires it up once at boot — `Config`, `PDO`, the 4 repositories, 5 services, and 7 controllers — mirroring exactly what each original page used to construct by hand (`new CartController(new CartService(new CartRepository($db)))` etc.), just centralized in one place instead of repeated per page.

**Centralized configuration.** `App\Config\Config` wraps `config/app.php` (unchanged file, unchanged keys) behind a dot-notation `get()`/`all()` API. Controllers and views receive `$appConfig` exactly as before; only how it gets loaded changed.

**Kernel-centralized bootstrap, error handling, and logging.** `App\Http\Kernel` replaces `includes/init.php`: session hardening (`ini_set` flags), `session_start()`, security headers (including the existing CSP from config), and CSRF/cart/wishlist session defaults all now happen once, in one place, before any route dispatches. Two new, purely additive safety nets were added: a non-fatal `set_error_handler` that logs to `storage/logs/app.log` without changing PHP's default error behavior (it returns `false`), and a `set_exception_handler` last-resort handler that only ever catches what would previously have been an uncaught fatal. Neither changes behavior for any request that was already working correctly.

**View layer.** `App\Support\View::render()` extracts data into scope and uses output buffering — the same pattern each original page used inline, now reusable. Views live in `views/pages/*.php`, extracted from each original page's HTML with no content changes beyond swapping `__DIR__`-relative includes for an `APP_BASE_PATH` constant (defined once in `public/index.php`). This directly targets the Phase 2 root-cause incident (a file move broke every relative include); an absolute base path can't silently break the same way.

**Controllers as thin route actions.** Each controller preserves 100% of the original page's business methods unchanged (`AuthController::handleLogin()`, `CartController::getCartPageData()`, `QuoteController::handleQuoteSubmit()`, etc.) and adds new action methods (`login()`, `page()`, `api()`, `show()`, ...) that mechanically translate what used to be top-level page-script orchestration — CSRF checks, redirects, view rendering — into methods returning `Response` objects. This is a structural relocation, not a logic rewrite. Two known, pre-existing inconsistencies were deliberately preserved rather than fixed: `cart.php`'s session-based cart and `cart-api.php`'s DB-backed cart remain separate implementations (`CartController::page()` vs. `api()`), and `quote-request.php`'s session-only capture remains unrelated to the DB-backed `quote-api.php` flow (`QuoteController::sessionRequest()` vs. `api()`). Both are flagged again below under Risks.

## Files modified

Full detail is in the commit (`git log`, "Phase 3: Enterprise architecture modernization"). Summary: 68 files changed, 2,141 insertions, 1,058 deletions.

- **Added:** `composer.json`, `public/index.php`, `public/.htaccess`, `src/` (33 files across `Config`, `Container`, `Controllers`, `Database`, `Http`, `Logging`, `Models`, `Repositories`, `Services`, `Support`), `views/pages/*` (12 files) plus 2 new pages with no prior equivalent (`404.php`, `error.php`), `storage/logs/.gitkeep`.
- **Renamed/relocated (content substantially preserved):** `models/*.php` → `src/Models/*.php`, `repositories/*.php` → `src/Repositories/*.php`, `services/*.php` → `src/Services/*.php`, `css/styles.css` → `public/css/styles.css`, `js/main.js` → `public/js/main.js`, all 13 root-level page scripts → `views/pages/*.php` (business logic extracted into corresponding `src/Controllers/*.php` first).
- **Edited:** `components/header.php` (removed its now-obsolete `require_once __DIR__ . '/../includes/init.php'`, since `Kernel::bootSession()` now owns that), `.gitignore` (added `/vendor/`, `composer.phar`), `README.md` (rewritten for the new setup/structure).
- **Removed:** `controllers/`, `services/`, `repositories/`, `models/`, `includes/`, `config/database.php`, and the 13 original root-level page files — all superseded by their `src/`/`views/pages/` equivalents.

## Compatibility assessment

Every legacy URL, form action, and static asset path continues to work unchanged — verified against a real PHP 8.1 + MariaDB 10.6 instance booted via `public/index.php`:

| Check | Result |
|---|---|
| `php -l` across `src/`, `views/`, `components/`, `public/` | Clean — no syntax errors |
| No leftover `require_once` references to deleted legacy paths | Confirmed clean (grep across the tree) |
| `/`, `/index.php`, `/products.php`, `/product-details.php`, `/login.php`, `/register.php`, `/cart.php`, `/wishlist.php` | All HTTP 200, correct byte sizes |
| `/css/styles.css`, `/js/main.js` | HTTP 200 from `public/` under the router, correct byte sizes |
| Registration → login → authenticated dashboard → logout | Full flow works, including CSRF token validation |
| Unauthenticated access to `/account-dashboard.php`; post-logout access | Correctly redirects to login |
| Session-based cart (`/cart.php`) | Add persists, appears on cart page |
| Database-backed cart (`/cart-api.php`) | Add persists to `cart_items`, correct JSON contract |
| Quote submission (`/quote-api.php`, DB-backed) | Creates a `quotes` row with correct VAT math |
| Quote capture (`/quote-request.php`, session-based) | Redirect + flash-message flow preserved |
| Wishlist add/view | Works |
| Unmatched route | Router's own branded `404.php` (not the PHP built-in server's default page) |
| Unknown product slug | Router's own `product-not-found.php` via `Response::notFound()` |
| Autoloading | Zero `require_once` for any `App\` class anywhere in the tree — PSR-4 resolves everything |
| Configuration loading | `Config::get('name')` / `get('base_url')` correctly return `config/app.php`'s values |

One real bug was found and fixed during this verification (not present in the delivered code): the PHP built-in development server sets `$_SERVER['SCRIPT_FILENAME']` inconsistently — an absolute path when a request resolves directly to the router script, but the literal relative command-line path for every other routed request. My first draft of the static-asset passthrough check in `public/index.php` compared this value against `__FILE__` and misfired, causing the built-in server's own default 404 page to shadow every `.php`-suffixed route except `/`. Fixed by checking `REQUEST_URI` against files in the router's own directory instead, and excluding `.php` requests from the passthrough entirely (only real static assets should ever bypass the Kernel). This only affected local `php -S` development serving; it would not have manifested under Apache/`.htaccess`, but is now fixed regardless.

## Risks

- **Secrets already in git history** (carried over from Phase 2, unchanged). Still requires a deliberate history rewrite (`git filter-repo`/BFG) with your sign-off, and rotation of the exposed credentials.
- **Two parallel cart implementations remain**, now formalized as `CartController::page()` (session-based) vs. `CartController::api()` (DB-backed), and **two parallel quote implementations** as `QuoteController::sessionRequest()` vs. `api()`. Both were deliberately preserved, not merged — per your instruction not to redesign business logic this phase. Fixing this is a business-logic decision for a future phase, not a structural one.
- **No automated tests.** Verification remains a manual, scripted smoke test against a real PHP+MariaDB instance, not a regression suite. The new DI-wired structure is now considerably more unit-testable than the old hand-chained-`require_once` pages were, but nothing exercises that yet.
- **The hand-rolled hardening in `Kernel`'s error handlers is deliberately minimal** — it logs and falls through to PHP's default behavior rather than trying to be a full framework-grade error/exception system. That's appropriate for this phase's scope, but the generic `views/pages/error.php` page currently shown for any uncaught exception gives the user no detail — acceptable for production, but worth pairing with proper log monitoring before this matters in practice.
- **`composer.json` requires a real `composer install`** on any machine actually running this app; the sandbox verification in this phase used a hand-written, spec-equivalent `vendor/autoload.php` (gitignored, not committed) because this sandbox has no outbound access to Packagist. This is a testing-environment detail only — `composer.json` itself is correct and complete for a normal `composer install`.
- **Wishlist remains session-only**, not persisted to the database (unchanged from Phase 2's finding — no `wishlist` table exists).

## Recommendations for the next phase

1. **PHPUnit test harness**, starting with the service layer (`AuthService`, `CartService`, `QuoteService`) — these are now cleanly constructor-injected and unit-testable, and would catch regressions like Phase 2's array/object bug automatically going forward.
2. **A deliberate decision on cart and quote architecture** — unify the session-based and DB-backed paths onto one persistence model for each, now that both are clearly isolated in named controller methods rather than duplicated across page files.
3. **CI pipeline** (lint + the new test suite) now that there's a `composer.json` to hang dependency installation off of.
4. **History rewrite to purge committed secrets**, with your sign-off, since the credentials should be treated as compromised regardless.
5. **Only after the above:** begin the Digital Commerce Ecosystem build-out (checkout/orders, admin portal, and the wider 30+ module set) as originally scoped — the codebase is now structured to receive new domains as additional `src/Models|Repositories|Services|Controllers` namespaces and `views/pages/` templates without repeating the fragility that caused Phase 1's original breakage.
