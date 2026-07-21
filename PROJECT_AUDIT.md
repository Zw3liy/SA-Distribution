# SA Business Distribution — Project Audit
**Prepared by:** Lead Enterprise Software Architect (Claude)
**Date:** 21 July 2026
**Scope:** Full repository inspection — no files modified during this phase.

---

## 1. Executive Summary

SA Business Distribution is, today, a **partially-built PHP storefront skeleton with a critical structural regression that likely makes it non-functional as checked out**, plus a bundled AI-agent development toolkit ("AI_Agentina") that has accumulated debug scripts, a portable PHP runtime, a Python virtual environment, and — most seriously — **live credentials and API keys committed to git history**.

The good news: the code that *does* exist is well-written. The MVC-style layers (controllers, services, repositories, models) at the repository root follow clean architecture, use parameterized PDO queries throughout (no SQL injection found), hash passwords with `password_hash()`, apply CSRF tokens, and set solid security headers (CSP, HSTS, X-Frame-Options, secure cookies). This is a legitimate, professional starting point — not a toy.

The bad news: git history shows that on the last commit, **all of the application's entry-point pages (`index.php`, `login.php`, `cart.php`, `products.php`, `account-dashboard.php`, etc.) were moved from the repository root into a subfolder called `AI_Agentina/`**, without updating their internal paths. Those pages reference `includes/init.php`, `components/header.php`, `config/app.php`, `services/`, `repositories/`, and `controllers/` — none of which exist inside `AI_Agentina/`. The diagnostic scripts left behind (`diag_test.php`, `check_paths.php`, `phpdiag.php`, `one_drive_path_test.php`) are direct evidence that someone was already trying, unsuccessfully, to debug this exact breakage. **The site will not run in its current state without either moving those files back or rewiring their include paths.**

Beyond that regression, this is fundamentally still a **single-tenant B2B/B2C storefront MVP** (catalog, cart, quotes, accounts) — none of the 30+ enterprise modules named in the platform vision (supplier portal, distributor portal, WMS, ERP integration, CRM, BI, workflow engine, etc.) exist yet. There is no router, no admin portal, no test suite, no CI/CD, no dependency manager (no `composer.json`), and no environment separation. This is early Phase 0 of an enterprise platform, not a platform.

**Immediate priority, before any feature work:** (1) rotate every exposed credential and strip secrets from git history, (2) restore the application to a runnable state, (3) remove ~150MB of unrelated binaries from version control, (4) establish a dependency manager, router, and test harness as the foundation for everything the enterprise vision requires.

---

## 2. Technology Stack

| Layer | Technology | Notes |
|---|---|---|
| Language | PHP 7.x (portable binaries bundled are PHP 7, `declare(strict_types=1)` used throughout) | No `composer.json` — stack is unmanaged/vanilla PHP |
| Database | MySQL/MariaDB via PDO | `utf8mb4`, InnoDB, foreign keys, transactions used correctly |
| Frontend CSS | Tailwind CSS (via `@tailwind` directives) + hand-written CSS (907 lines in `css/styles.css`) | No visible Tailwind build config (`tailwind.config.js`) or build pipeline — unclear how Tailwind is compiled |
| Frontend JS | Vanilla JavaScript (`js/main.js`, 116 lines) | No framework, no bundler |
| Icons | Bootstrap Icons via jsDelivr CDN | External dependency, whitelisted in CSP |
| AI tooling | Python 3 (`AI_Agentina/main.py`, `agent_core.py`) using `anthropic`, `openai`, `ollama` SDKs | A standalone CLI chat agent, not integrated into the storefront |
| Session/Auth | Native PHP sessions, `password_hash()`/`password_verify()` | No JWT/OAuth, no MFA |
| Web server | None configured — presumably PHP built-in server (`php -S`) for dev | No Apache/Nginx config, no `.htaccess` found |

There is no Node.js toolchain, no package manager of any kind (PHP or JS), and no containerization.

---

## 3. Project Architecture

The root of the repository implements a clean **layered MVC-ish architecture**:

```
Controller → Service → Repository → PDO/MySQL
                ↓
              Model (typed DTO)
```

- **Controllers** (`controllers/`): thin, handle request input (`filter_input`), delegate to services, throw exceptions on validation failure. No direct DB or HTML.
- **Services** (`services/`): business logic — password hashing, VAT calculation (15%), quote numbering, cart quantity clamping against stock.
- **Repositories** (`repositories/`): all data access, 100% parameterized PDO statements, transactions used for multi-table writes (cart save, quote save).
- **Models** (`models/`): plain typed classes (`Product`, `User`, `CartItem`, `Quote`, `QuoteItem`, `Role`, `Permission`, `Address`) — no ORM, manual mapping in services.
- **Views** (`views/`, `components/`): only one view partial exists (`views/products/product-card.php`) plus shared `header.php`/`footer.php`. No page-level views live at root.
- **`includes/init.php`**: bootstraps session hardening, security headers, CSRF token, DB connection, and default session containers (cart/wishlist/quote_requests). This is the application's composition root.

**Missing entirely:** a router/front controller, a dependency-injection container (services are wired manually per-page), an autoloader (every file uses manual `require_once`), and — critically — the page-level entry points that actually invoke this stack (see §21 Bugs Found).

---

## 4. Folder Structure

```
SA Distribution/
├── components/          Shared header/footer partials
├── config/               app.php (settings), database.php (PDO wrapper)
├── controllers/          5 controllers (Auth, Account, Cart, Product, Quote)
├── css/                  styles.css (Tailwind + custom, 907 lines)
├── database/             schema.sql, create_auth_account_schema.sql,
│                         create_cart_quote_schema.sql, seed.sql
├── includes/             init.php, auth.php, helpers.php
├── js/                   main.js
├── models/               7 typed DTOs
├── repositories/         4 repositories (User, Product, Cart, Quote)
├── services/             5 services (Auth, User, Product, Cart, Quote)
├── views/products/       product-card.php (only view partial in repo)
├── AI_Agentina/          ⚠ See §4a — misplaced app pages + AI CLI tool + secrets + PHP binary distro + Python venv
├── ext/, sasl2/, dev/,
│   lib/, extras/         ⚠ ~150MB of PHP runtime DLLs/binaries — should never be in git
├── tasks                 VS Code task definition (launches AI_Agentina/main.py)
├── New Microsoft Word Document.docx   ⚠ Empty stray file, should be removed
└── New Text Document.txt              ⚠ Empty stray file, should be removed
```

### 4a. `AI_Agentina/` — what it actually is

Git history proves this folder is **not** a legitimate architectural subsystem. In the first commit ("Initial release v1.0.0"), files like `index.php`, `login.php`, `cart.php`, `account-dashboard.php`, `products.php`, `register.php`, `wishlist.php`, `product-details.php`, `cart-api.php`, `quote-api.php`, and `quote-request.php` lived at the **repository root**, alongside `components/`, `config/`, `includes/`, `services/`, etc. — where they'd resolve correctly.

The second (current) commit, "Initial AI_Agentina + SA Business Distribution", **renamed/moved every one of those files into `AI_Agentina/`** and added the AI agent tooling. Nothing about their internal `require_once __DIR__ . '/includes/init.php'` paths was updated. `AI_Agentina/` has no `includes/`, `config/`, `components/`, `css/`, or `services/` subdirectories of its own — confirmed by direct inspection. The debugging scripts left inside (`diag_test.php`, `phpdiag.php`, `check_paths.php`, `one_drive_path_test.php`, `path_test_short.php`) are literally probing `file_exists()`/`realpath()` on the very paths that broke — someone was mid-troubleshoot when this was committed.

`AI_Agentina/` additionally bundles:
- A full **portable PHP 7 runtime** (`php.exe`, `php-cgi.exe`, `phpdbg.exe`, 30+ `ext/*.dll`, `sasl2/*.dll`, ICU libraries, OpenSSL libs) — likely pulled in to run PHP locally without a system install.
- A **Python virtual environment** (`venv/`) and CLI chat agent (`main.py`, `agent_core.py`, `plugins_general.py`) that talks to Claude/OpenAI/Ollama — a developer productivity tool, not a storefront feature.
- An **agent persona spec** (`.agent.md`) that explicitly describes its own purpose: *"Perform a full repository audit. Find and repair broken dependencies, paths, routes, includes, configuration issues... Refactor and standardize architecture without adding new business features."* This confirms `AI_Agentina/` was built to fix exactly the kind of breakage it's currently suffering from — the repair was apparently never completed or committed.
- **Committed secrets**: `.env`, `API key.md`, `Anthropic Key.txt` are tracked in git despite `.env` being listed in `.gitignore` (the ignore rule was added after these were already committed, or the files were force-added). See §19 Security Assessment.
- An untracked SSH private key file (`ssh-key-2026-07-19 (1).key`) sitting in the working directory — not committed, but present on disk and should be moved outside the repo immediately.

---

## 5. Database Design

Three schema files define a clean, normalized MySQL 8/MariaDB InnoDB schema (utf8mb4, proper foreign keys, sensible indexes):

**Catalog** (`schema.sql`): `categories`, `brands`, `products` (SKU, slug, price, sale_price, stock, feature flags), `product_images` (with primary-image flag and sort order).

**Auth & Accounts** (`create_auth_account_schema.sql`): `users`, `roles`, `permissions`, `role_permissions` (RBAC join table — schema supports RBAC, but no application code uses roles/permissions yet beyond a hardcoded `'customer'` string in session), `password_resets`, `email_verifications`, `remember_tokens`, `login_attempts` (brute-force tracking table — exists but nothing writes to it), `addresses` (billing/shipping typed).

**Cart & Quotes** (`create_cart_quote_schema.sql`): `cart`/`cart_items` (session-based, not user-based — carts aren't tied to logged-in users), `quotes`/`quote_items` (quote numbering, VAT calc, snapshotted product data at time of quote — good practice for auditability).

**Seed data** (`seed.sql`): 4 categories, 6 brands, 8 realistic South African enterprise IT products (Dell, HPE, Cisco, Fortinet, Lenovo, APC) with ZAR pricing.

**Gaps:** no `orders`/`order_items` tables (checkout doesn't exist — quotes are the only conversion path), no `suppliers`, `warehouses`, `inventory_locations`, `audit_log`, `companies` (B2B is user-level only, no company/organization entity despite `company_name` on users), no migrations tooling (raw `.sql` files, no versioning framework like Phinx/Doctrine Migrations).

---

## 6. Frontend Status

- Tailwind CSS is referenced via `@tailwind` directives but there's no visible build step, config file, or compiled output strategy — it's unclear whether `css/styles.css` is meant to be Tailwind-compiled or if the directives are stale/unprocessed.
- Design tokens (colors, spacing) are hand-coded inline in CSS rather than centralized (e.g., `#0B2E59`, `#0F172A` hardcoded rather than CSS custom properties consistently used).
- Semantic HTML and accessibility basics are present: `aria-label` on nav, `loading="lazy"` on images, `prefers-reduced-motion` respected in JS, proper `<label for>` on form fields.
- No dark mode implementation despite it being a stated platform requirement.
- No responsive breakpoints verified (would need to render-test; CSS wasn't fully read for media queries).
- JS is minimal and unobtrusive: smooth-scroll, product image gallery, clipboard-copy share button, a finance/monthly-payment calculator. No JS framework, no state management, no build tooling (Webpack/Vite) — fine for current scope, insufficient for the platform's stated ambitions (portals, dashboards, real-time features).
- Only one reusable view component exists (`product-card.php`). No admin UI, no dashboard UI, no cart/checkout UI at the root level (they exist only in the broken `AI_Agentina/` copies).

---

## 7. Backend Status

The backend pattern (controller → service → repository → PDO) is consistently applied across the five domains implemented: Auth, Account, Cart, Product, Quote. Type declarations (`declare(strict_types=1)`) are used in every file. Exceptions are used for control flow (`InvalidArgumentException`, `RuntimeException`) and caught at the page level (per the `AI_Agentina/login.php` example) to render flash messages.

No autoloading (PSR-4) — every file manually `require_once`s its dependencies, which is brittle and was almost certainly a contributing factor to the path-breakage incident in §4a. No dependency injection container — objects are constructed by hand on each page (`new UserRepository($db)`, etc.), which works at this scale but won't scale to 30+ enterprise modules.

No API layer beyond two ad hoc endpoints referenced in `AI_Agentina/` (`cart-api.php`, `quote-api.php`) — not REST, not versioned, not documented.

---

## 8. Authentication & Security

**What's solid:**
- `password_hash()`/`password_verify()` (bcrypt via `PASSWORD_DEFAULT`) — correct.
- CSRF tokens generated via `random_bytes(32)`, verified with `hash_equals()` — correct, timing-safe.
- Session hardening in `init.php`: `use_strict_mode`, `cookie_httponly`, `cookie_samesite=Lax`, `cookie_secure`.
- Security headers: CSP, HSTS (with `preload`), `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`.
- `session_regenerate_id(true)` on logout.
- All queries parameterized — no SQL injection identified anywhere in the codebase.

**What's missing or broken:**
- Login/register controllers (`AuthController`) never call `verify_csrf_token()` themselves — the CSRF check is done by the *calling page* (confirmed in `AI_Agentina/login.php`), meaning CSRF protection is only as strong as whoever remembers to add it. This is a footgun, not a fix-now bug, but worth flagging.
- `login_attempts` table exists in the schema but nothing in the codebase inserts into it — no brute-force detection is actually active despite the schema supporting it.
- No rate limiting anywhere (login, register, quote submission).
- No RBAC enforcement — `roles`/`permissions`/`role_permissions` tables exist but `currentUserRole()` just returns a hardcoded `'customer'` string; there is no admin role, no permission checks anywhere in code.
- No email verification flow is wired up despite `email_verifications` table and `is_verified` column existing.
- **Secrets committed to git** (`.env`, API keys) — see §19, this is the most severe finding in the audit.
- `FILTER_SANITIZE_FULL_SPECIAL_CHARS` used throughout — this filter is deprecated as of PHP 8.1. Since the bundled runtime is PHP 7, it works today but is a forward-compatibility risk if the team upgrades PHP.
- Debug/diagnostic scripts (`diag_test.php`, `phpdiag.php`, `check_paths.php`) are committed and would leak filesystem paths, `open_basedir`, and directory structure if ever deployed to a public webroot.

---

## 9. APIs

No formal API platform exists. Two informally-named endpoint files (`cart-api.php`, `quote-api.php`) live inside the broken `AI_Agentina/` folder — not reviewed in depth here since they're currently unreachable, but their naming suggests simple form-post/AJAX handlers rather than a REST or GraphQL API. There is no OpenAPI/Swagger spec, no API versioning, no authentication scheme for programmatic access (API keys, OAuth), and no rate limiting. This is a ground-up build item for the "API Platform" and "Developer Portal" goals in the project vision.

---

## 10. Admin Portal

**Does not exist.** No admin views, no admin controller, no admin routes, no admin authentication tier, no CMS/PIM UI for managing products/categories/brands (currently only editable via raw SQL). This is a Phase 1 gap against the stated goals.

---

## 11. Customer Features

Implemented (once the entry-point regression is fixed): registration, login/logout, profile editing (`AccountController::updateProfile`), session-based cart with add/update/remove, wishlist (session-only, not persisted to DB — no `wishlist` table exists), product browsing with search/filter/sort/pagination, product detail view with recently-viewed tracking, quote request submission with company/VAT/registration fields (B2B-oriented), quote history lookup by session.

Not implemented: checkout/payment, order history/tracking, saved addresses UI (model/table exist, no controller/service), account deletion/GDPR-POPIA data export, multi-address shipping selection at checkout, loyalty/rewards, reviews/ratings, notifications beyond a single flash-message pattern.

---

## 12. Product Management

Single flat product table with category and brand (one each, not many-to-many), single-currency ZAR pricing, one sale price field (no scheduled promotions, no tiered/B2B pricing, no quantity breaks), product images support multiple images with a primary flag. No variants/options (size, color, configuration), no bundles/kits, no digital assets beyond a single `file_name`, no rich PIM attributes (specs tables, comparison data) despite these being enterprise IT products where spec sheets matter a lot to buyers.

---

## 13. Inventory Management

A single `stock` integer column on `products`. No warehouse/location concept, no stock reservations, no low-stock alerts, no stock movement history/audit trail, no supplier-linked replenishment. Cart logic does clamp requested quantity to available stock (`min($product->stock, ...)`), which is a good defensive touch, but there's no true inventory management system here — just a stock counter.

---

## 14. Order Management

**Does not exist.** There is no `orders` table, no checkout flow, no payment gateway integration, no order status workflow, no invoicing. The only "conversion" mechanism is the Quote system (RFQ), which produces a quote record but nothing downstream converts a quote into an order. This is a major gap for a platform whose stated goal includes Order Management, Warehouse Management, and ERP Integration.

---

## 15. Business Modules

None of the enterprise modules listed in the project vision exist yet: Supplier Portal, Distributor Portal, Vendor Portal, Sales Portal, Procurement, CRM, ERP Integration, Finance, Analytics/Reporting, Marketing/Promotions, Content Management (beyond static header/footer), Customer Support/Knowledge Base, Returns/Warranty Management, Asset Management, BI, Automation/Workflow Engine, Notification System (beyond session flash messages), Document Management, Security Monitoring/Audit Logs, Multi-location/Multi-company support, Developer Portal, Marketplace capabilities. This is expected at this stage — the codebase is roughly a B2C/B2B storefront MVP skeleton, not yet a platform — but it means essentially the entire platform vision is still greenfield.

---

## 16. AI Integration Readiness

There is an AI capability in the repo, but it is a **standalone developer CLI tool** (`AI_Agentina/main.py`), not a storefront-integrated feature. It supports pluggable providers (Anthropic Claude, OpenAI, Ollama) via environment variables, has a tiny plugin command system (`:help`, `:provider`, `:system`), and is meant to assist *developers* auditing/repairing the codebase (per `.agent.md`) — not to power AI Customer Assistant, AI Product Recommendations, AI Search, etc. as envisioned in the platform goals. There is currently zero AI surfaced to end customers or business users. `agent_core.py` also contains a second, older/duplicate implementation of the same idea using a deprecated OpenAI SDK call style (`openai.ChatCompletion.create`, pre-1.0 SDK), suggesting it's a leftover draft rather than active code.

Architecturally, the clean service-layer separation in the main app (Product/Cart/Quote services) is a reasonable foundation to eventually hang an AI layer on (e.g., an `AIRecommendationService` alongside `ProductService`), but no such integration point exists yet.

---

## 17. Code Quality Assessment

Where code exists, quality is genuinely good for what it is: consistent `declare(strict_types=1)`, consistent naming, single-responsibility classes, no obvious copy-paste duplication within the root MVC layers, parameterized SQL everywhere, sensible use of exceptions. This reads like it was written carefully and deliberately (possibly AI-assisted, given the `.agent.md` context), not hacked together.

Weaknesses: no PSR-4 autoloading (manual `require_once` chains are fragile — directly implicated in the §4a regression), no static analysis config (no PHPStan/Psalm), no coding-standard enforcement (no PHP-CS-Fixer/PHPCS config), no `composer.json` so there's no formal PHP version constraint, no interfaces/contracts between layers (services depend on concrete repository classes, not interfaces — makes mocking/testing harder), inconsistent type hints on constructor params in some classes (`ProductService`, `ProductRepository` accept untyped `$repository`/`$db` while `CartService`/`CartRepository` do type them), and the duplicate/half-finished AI agent scripts (`agent_core.py` vs `main.py`) represent dead or confused code that should be resolved to one canonical version.

---

## 18. Performance Assessment

Too early in the build to assess real-world performance, but structurally: product queries use proper indexes (unique slug/SKU, foreign keys), pagination is implemented via LIMIT/OFFSET (fine at current scale, will need keyset pagination at large catalog sizes), no caching layer anywhere (no Redis/Memcached, no HTTP caching headers on product pages), no image optimization pipeline visible (images referenced as `.webp` in seed data, which is good, but no evidence of a build step that generates them), no CDN configuration, no query result caching, N+1 risk is low because product listing already JOINs category/brand/primary-image in one query. The bundled ~150MB of PHP binaries in git will slow every clone/pull for every future developer — a performance problem for the *team*, not the app.

---

## 19. Security Assessment

**Critical:**
1. **Live secrets committed to git**: `AI_Agentina/.env`, `AI_Agentina/API key.md`, and `AI_Agentina/Anthropic Key.txt` are tracked in version control and present in git history (history rewriting will be needed, not just deletion, since git retains old blobs). Any API keys/credentials in these files must be treated as compromised and rotated immediately, regardless of whether this repo is ever pushed to a shared remote — local git history itself is a durable secret-leak vector (backups, future clones, accidental pushes).
2. An SSH private key file (`ssh-key-2026-07-19 (1).key`) is sitting in the working tree (untracked, thankfully, but one `git add .` away from being committed). It should be moved outside the repository entirely and the associated key pair rotated if there's any doubt about exposure.

**High:**
3. Default DB credentials fall back to weak values in `config/app.php` (`'pass' => getenv('DB_PASS') ?: 'change_this_securely'`) and `AI_Agentina/includes_db.php` hardcodes `root`/empty password — fine for local dev, dangerous if ever deployed without overriding env vars.
4. Debug scripts (`diag_test.php`, `phpdiag.php`, `check_paths.php`, `one_drive_path_test.php`) disclose filesystem paths and `open_basedir` settings — must not ship to any publicly reachable environment.
5. No RBAC enforcement despite schema support — anyone authenticated is implicitly a "customer"; there's no tier for staff/admin, meaning any future admin routes would need this built from scratch with care.

**Medium:**
6. No brute-force protection despite a `login_attempts` table existing unused.
7. No rate limiting on registration or quote submission (spam/abuse risk).
8. `.gitignore` covers `.env` going forward but doesn't retroactively help — the files were force-added or added before the ignore rule existed.

**Good practices already in place** (worth preserving as the codebase grows): parameterized queries throughout, bcrypt password hashing, CSRF tokens with timing-safe comparison, secure session cookie flags, CSP/HSTS/X-Frame-Options headers, SQL foreign-key constraints with sensible cascade rules.

---

## 20. Documentation Assessment

There is **no README at the repository root** — only `AI_Agentina/README.md`, which documents the storefront (not the AI tooling it sits alongside) and gives generic install instructions that don't account for the current broken file layout. There's no architecture doc, no ER diagram, no API documentation, no CONTRIBUTING guide, no LICENSE file, no changelog. `AI_Agentina/administration.md` and `.agent.md` document the AI agent's *own* operating instructions, not the product. Inline code comments are minimal but the code is largely self-explanatory (short methods, clear names) which partially compensates.

---

## 21. Testing Coverage

**Zero automated tests exist.** No PHPUnit, no Pest, no test directory, no `phpunit.xml`, no CI pipeline to run tests even if they existed. The files matching "test" in the repo (`diag_test.php`, `test_claude.py`, `test_key.py`, `path_test_short.php`, `tmp_test.php`) are all ad hoc manual debug scripts, not a test suite. This is a significant gap for a codebase aiming at enterprise-grade reliability — the well-structured service/repository layers are actually quite testable (constructor injection, no static calls, no global state beyond `$_SESSION` which could be abstracted) — this is low-hanging fruit once secrets/structure are fixed.

---

## 22. Bugs Found

1. **[Critical] Application is non-functional as checked out.** Every page-level entry point (`index.php`, `login.php`, `register.php`, `logout.php`, `cart.php`, `cart-api.php`, `products.php`, `product-details.php`, `account-dashboard.php`, `account-edit.php`, `wishlist.php`, `quote-request.php`, `quote-api.php`) lives in `AI_Agentina/` and references `includes/init.php`, `components/header.php`, `config/app.php`, `css/styles.css`, `services/*`, `repositories/*`, `controllers/*` via `__DIR__`-relative paths that don't exist inside `AI_Agentina/`. Confirmed via git history: these files lived correctly at the repository root in the prior commit and were moved without updating paths.
2. **`views/products/product-card.php`** is referenced by product listing logic but there is no page in the current tree that actually includes it (the page that would, `products.php`, is one of the misplaced files above).
3. **`tasks`** (VS Code task config) still points at `AI_Agentina/main.py` (the AI CLI) — harmless, but confirms the working assumption at commit time was that `AI_Agentina/` is the active working directory, not the repo root.
4. **CSRF verification is inconsistently applied** — it's the calling page's responsibility, not the controller's, so any new page author could forget it (observed: `AuthController::handleLogin()` performs no CSRF check itself; `AI_Agentina/login.php` adds it externally).
5. **Wishlist has no database persistence** — `getWishlistItems()`/`addToWishlist()` operate purely on `$_SESSION`, so wishlists vanish when the session ends, despite the platform being described as needing durable customer features. No `wishlist` table exists in any schema file.
6. **Empty stray files** committed at root: `New Microsoft Word Document.docx` (0 bytes), `New Text Document.txt` (0 bytes) — accidental desktop artifacts.
7. **`__pycache__/agent_core.cpython-314.pyc`** is committed — compiled bytecode should never be in version control (also proves `__pycache__/` wasn't gitignored at the time despite `.gitignore` now covering it going forward — it's a historical leftover that needs `git rm --cached`).

---

## 23. Technical Debt

- No dependency manager (Composer) — every third-party need (if any arise) will be manually vendored, and there's no way to declare/enforce a minimum PHP version.
- No autoloader — manual `require_once` chains, the direct cause of bug #1.
- No router/front controller — each "page" is its own PHP file with duplicated `<head>`/meta boilerplate (visible even in the two files reviewed, `index.php` and `login.php`, both hand-roll the same SEO meta tags).
- Duplicate/dead AI agent code (`agent_core.py` vs `main.py`, overlapping functionality, `agent_core.py` uses a deprecated OpenAI SDK pattern).
- ~150MB of PHP runtime binaries and a Python venv committed to git — bloats every clone, has nothing to do with application logic, and will only grow if not addressed now.
- RBAC schema exists with zero enforcing code — either build it out or drop it until it's needed; a half-modeled permission system is worse than none because it implies safety that isn't there.
- `login_attempts` table exists with zero writers — same "modeled but unused" pattern.
- Mixed type-hint discipline between newer (`CartService`) and older-feeling (`ProductService`) constructors.

---

## 24. Missing Features

Relative to the stated platform vision, essentially everything beyond core storefront browsing/cart/quote is missing: checkout & payments, order management, admin portal, supplier/distributor/vendor portals, CRM, ERP integration, finance, analytics/BI/reporting, marketing/promotions engine, PIM beyond flat fields, CMS beyond static partials, support/knowledge base, returns/warranty, asset management, workflow/automation/notification engines beyond session flash messages, document management, audit logging, multi-location/multi-company support, a real API platform, a developer portal, marketplace capabilities, and customer-facing AI features. This is not a criticism of what exists — it's simply the honest gap between "storefront MVP skeleton" (roughly where the code is) and "South Africa's most advanced Digital Commerce Ecosystem" (the stated goal).

---

## 25. Enterprise Readiness Score

**Overall: 2 / 10** for enterprise production readiness in its *current, broken* state; **4 / 10** for the underlying architecture's *potential* once the regression is fixed and secrets are rotated.

| Dimension | Score /10 | Rationale |
|---|---|---|
| Runs at all | 0 | Entry points are structurally broken |
| Security posture | 3 | Good primitives (hashing, CSRF, headers, parameterized SQL) undermined by committed secrets and an SSH key in the working tree |
| Architecture/maintainability | 6 | Clean layering where it exists; no autoload/DI/router limits scale |
| Feature completeness vs. vision | 1 | Storefront MVP only; 90%+ of named modules don't exist |
| Testing | 0 | No automated tests |
| Documentation | 1 | No root README, no architecture docs |
| DevOps/deployability | 0 | No Composer, no CI, no containerization, no env separation |
| Data model | 6 | Well-normalized for what it covers; missing orders, suppliers, warehouses, companies |

---

## 26. Estimated Completion Percentage

Against the full enterprise platform vision described in the project brief: **roughly 3–5% complete.** Against a much narrower "basic B2B/B2C storefront MVP" scope (catalog, cart, accounts, RFQ): **roughly 55–60% complete architecturally**, but **0% functional today** until the entry-point regression is fixed.

---

## 27. Prioritized Development Roadmap

**P0 — Stop the bleeding (do first, before anything else):**
1. Rotate every credential/API key found in `AI_Agentina/.env`, `API key.md`, `Anthropic Key.txt`, and the loose SSH key file. Treat all as compromised.
2. Remove secrets from git history (not just delete the files — history contains the old blobs) and move the SSH key file outside the repo.
3. Fix the broken entry points: either move `AI_Agentina/index.php`, `login.php`, `register.php`, `logout.php`, `cart.php`, `cart-api.php`, `products.php`, `product-details.php`, `account-dashboard.php`, `account-edit.php`, `wishlist.php`, `quote-request.php`, `quote-api.php` back to repository root, or rewrite their include paths to point correctly at the root `includes/`, `components/`, `config/`, `services/`, `repositories/`, `controllers/`. Verify each page actually renders end-to-end afterward.
4. Delete the ~150MB of committed PHP runtime binaries (`ext/`, `sasl2/`, `dev/`, `lib/`, `extras/`, `AI_Agentina/*.exe`, `AI_Agentina/*.dll`) and the Python `venv/` from git, add proper `.gitignore` rules, and document the actual local dev setup (system PHP + MySQL) in a real README.
5. Remove debug/diagnostic scripts (`diag_test.php`, `phpdiag.php`, `check_paths.php`, `one_drive_path_test.php`, `path_test_short.php`, `tmp_test.php`) or move them well outside any web-servable path.
6. Delete stray empty files (`New Microsoft Word Document.docx`, `New Text Document.txt`) and the committed `.pyc`.

**P1 — Foundation for everything else:**
7. Introduce Composer, PSR-4 autoloading, and a PHP version constraint.
8. Introduce a minimal front controller/router so pages stop hand-duplicating `<head>` boilerplate and path-fragility (root cause of the P0 regression) can't recur.
9. Stand up a test harness (PHPUnit) — the service/repository layers are already testable; start with `AuthService`, `CartService`, `QuoteService`.
10. Write a root-level README covering setup, environment variables, and architecture, plus a lightweight architecture doc / ER diagram.
11. Resolve the duplicate AI agent scripts (`agent_core.py` vs `main.py`) down to one, and decide deliberately whether `AI_Agentina/` stays as a developer tool (fine) or gets removed from this repo entirely and lives elsewhere (cleaner).

**P2 — Complete the storefront MVP:**
12. Build checkout: `orders`/`order_items` schema, order status workflow, at least one payment gateway integration.
13. Persist wishlist to the database (table + repository) instead of session-only.
14. Wire up email verification and password reset flows (schema already supports both).
15. Build a minimal Admin Portal: product/category/brand CRUD, order and quote management views, gated by real RBAC enforcement using the existing `roles`/`permissions` tables.
16. Add brute-force protection using the existing (currently unused) `login_attempts` table, plus basic rate limiting on auth/quote endpoints.

**P3 — Begin the platform build-out** (only after P0–P2 are solid): company/organization entity for true B2B multi-user accounts, supplier and inventory/warehouse modules, CRM and reporting, and a deliberate integration point for AI features (recommendations, search, assistant) built on top of the existing service layer rather than as a bolted-on side tool.

---

*No files were modified during this audit. All findings above are based on direct inspection of the repository, its git history, and file contents as of 21 July 2026.*
