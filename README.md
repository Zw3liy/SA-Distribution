# SA Business Distribution

Enterprise IT hardware, networking, security, and managed-services storefront for South African businesses (B2B/B2C catalog, cart, wishlist, quote requests, and customer accounts).

This README reflects the actual current state of the codebase as of the last foundation-recovery pass. See `PROJECT_AUDIT.md` for the full architecture audit and roadmap.

## What's implemented

- Product catalog with search, category/brand filters, sorting, and pagination
- Product detail pages with related products and recently-viewed tracking
- Shopping cart (session-based) and a session-based wishlist
- Quote request (RFQ) flow with company/VAT details
- Customer registration, login, logout, profile editing
- CSRF protection, hashed passwords, hardened session cookies, security headers (CSP/HSTS/etc.)

## What's not implemented yet

Checkout/payments, order management, an admin portal, and the wider enterprise module set (supplier/distributor portals, ERP/CRM integration, BI, etc.) described in `PROJECT_AUDIT.md`. Those are future-phase work, not part of this foundation.

## Requirements

- PHP 8.x with the `pdo_mysql` extension
- MySQL 8 or MariaDB 10.x
- No Composer dependencies currently (there is no `composer.json` yet)

## Local setup

1. **Install PHP.** On Windows, run `scripts\setup-windows.bat`, which downloads PHP, adds it to your `PATH`, and starts the dev server. (This replaces the old approach of committing a portable PHP runtime into the repo — do not re-add binaries to version control.) On macOS/Linux, install PHP via your package manager instead.
2. **Create the database and load the schema:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE sa_business CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p sa_business < database/schema.sql
   mysql -u root -p sa_business < database/create_auth_account_schema.sql
   mysql -u root -p sa_business < database/create_cart_quote_schema.sql
   mysql -u root -p sa_business < database/seed.sql   # optional sample data
   ```
3. **Configure database credentials.** `config/app.php` reads `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` via `getenv()` — there is currently no `.env` auto-loader in the PHP app, so these must be set as real environment variables before starting PHP (see `.env.example` for the expected names). If you don't set them, it falls back to `127.0.0.1` / `sa_business` / `sa_business_user` / a placeholder password, which will only work if your local database matches those defaults.
4. **Run the app from the repository root** (not from a subfolder):
   ```bash
   php -S localhost:8000
   ```
   Then visit `http://localhost:8000/index.php`.

## Project structure

```
components/    Shared header/footer partials
config/        App settings + PDO database wrapper
controllers/   Request handling (Auth, Account, Cart, Product, Quote)
services/      Business logic
repositories/  Data access (parameterized PDO queries)
models/        Typed DTOs
includes/      Bootstrap (init.php), auth helpers, view helpers
views/         Reusable view partials
database/      SQL schema and seed files
css/, js/      Frontend assets
*.php (root)   Page-level entry points (index.php, login.php, products.php, etc.)
AI_Agentina/   Standalone developer AI CLI tool (Python) — not part of the storefront runtime
```

Every page-level PHP file at the repository root wires up its own controller/service/repository chain via `require_once` (there is no autoloader or router yet — see `PROJECT_AUDIT.md` for planned improvements).

## Known risks

- No automated tests exist yet.
- Wishlist is session-only (not persisted to the database).
- Cart has two parallel implementations — a session-based one (used by `cart.php`) and a database-backed one (used by `cart-api.php`) — that are not unified. This is pre-existing behavior, left as-is in this recovery pass to avoid changing business logic; see `PROJECT_AUDIT.md` for the recommendation to consolidate it in a future phase.
- Secrets were previously committed to git history under `AI_Agentina/`. They have been removed from tracking going forward, but anyone with an existing clone should treat those credentials as compromised, rotate them, and be aware they still exist in old commits until the history is rewritten.
