# SA Business Distribution

Enterprise IT hardware, networking, security, and managed-services storefront for South African businesses (B2B/B2C catalog, cart, wishlist, quote requests, and customer accounts).

This README reflects the actual current state of the codebase after the Phase 3 architecture modernization pass. See `PROJECT_AUDIT.md` for the original full audit and `ENTERPRISE_FOUNDATION_REPORT.md` / `ARCHITECTURE_MODERNIZATION_REPORT.md` for what changed in each recovery/modernization phase.

## What's implemented

- Product catalog with search, category/brand filters, sorting, and pagination
- Product detail pages with related products and recently-viewed tracking
- Shopping cart (session-based) and a session-based wishlist
- Quote request (RFQ) flow with company/VAT details
- Customer registration, login, logout, profile editing
- CSRF protection, hashed passwords, hardened session cookies, security headers (CSP/HSTS/etc.)

## What's not implemented yet

Checkout/payments, order management, an admin portal, and the wider enterprise module set (supplier/distributor portals, ERP/CRM integration, BI, etc.) described in `PROJECT_AUDIT.md`. Those are future-phase work.

## Requirements

- PHP 8.1+ with the `pdo_mysql` extension
- MySQL 8 or MariaDB 10.x
- [Composer](https://getcomposer.org) (no third-party packages are required yet, but Composer generates the class autoloader the app now depends on)

## Local setup

1. **Install PHP.** On Windows, run `scripts\setup-windows.bat`, which downloads PHP, adds it to your `PATH`, and starts the dev server. On macOS/Linux, install PHP via your package manager instead.
2. **Install Composer** if you don't already have it, then from the repository root run:
   ```bash
   composer install
   ```
   This generates `vendor/autoload.php`, which the app requires to run. `vendor/` is intentionally not committed to git (standard practice) — this is a one-time step per clone/machine.
3. **Create the database and load the schema:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE sa_business CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p sa_business < database/schema.sql
   mysql -u root -p sa_business < database/create_auth_account_schema.sql
   mysql -u root -p sa_business < database/create_cart_quote_schema.sql
   mysql -u root -p sa_business < database/seed.sql   # optional sample data
   ```
4. **Configure database credentials.** `config/app.php` reads `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` via `getenv()` — set these as real environment variables before starting PHP (see `.env.example` for the expected names). If unset, it falls back to `127.0.0.1` / `sa_business` / `sa_business_user` / a placeholder password.
5. **Run the app.** The application now uses a front-controller pattern — `public/` is the web root, not the repository root:
   ```bash
   php -S localhost:8000 public/index.php
   ```
   Then visit `http://localhost:8000/`. For Apache in production, point the vhost's document root at `public/` — `public/.htaccess` handles routing everything through `public/index.php`.

## Project structure

```
public/            Web root — index.php (front controller), .htaccess, css/, js/
src/               Namespaced application code (PSR-4, autoloaded via Composer)
  Http/            Kernel (bootstraps session/config/DI, dispatches routes), Router, Request, Response
  Controllers/      One per feature area (Auth, Account, Cart, Product, Quote, Wishlist, Home)
  Services/         Business logic
  Repositories/      Data access (parameterized PDO queries)
  Models/            Typed DTOs
  Config/            Centralized config loader
  Container/         Minimal dependency-injection container
  Logging/           File-based logger
  Support/           View renderer + global helper functions (esc(), csrf_token(), auth helpers, etc.)
views/
  pages/             One template per route, rendered by the matching controller
  products/          Shared product-card partial
components/         Shared header/footer partials, included by view templates
config/             app.php — plain settings array (unchanged shape)
database/           SQL schema and seed files
storage/logs/       app.log (gitignored; directory tracked via .gitkeep)
composer.json       PSR-4 autoload map (App\ -> src/) + helper-function file autoload
AI_Agentina/        Standalone developer AI CLI tool (Python) — not part of the storefront runtime
```

Every route (`/`, `/products.php`, `/login.php`, `/cart-api.php`, etc.) is registered once in `src/Http/Kernel.php` and dispatched to a controller action through `public/index.php`. There is no more per-page `require_once` chain — classes are resolved via Composer's PSR-4 autoloader, and each controller's dependencies (services, repositories, config) are wired once in the Kernel's dependency-injection container instead of being constructed by hand on every page.

## Known risks

- No automated tests exist yet.
- Wishlist is session-only (not persisted to the database).
- Cart has two parallel implementations — a session-based one (`CartController::page()`, backing `/cart.php`) and a database-backed one (`CartController::api()`, backing `/cart-api.php`) — that are not unified. This is pre-existing behavior, deliberately preserved rather than fixed; see `PROJECT_AUDIT.md` for the recommendation to consolidate it in a future phase.
- Secrets were previously committed to git history under `AI_Agentina/`. They have been removed from tracking going forward, but anyone with an existing clone should treat those credentials as compromised, rotate them, and be aware they still exist in old commits until the history is rewritten.
