# Domain Implementation Completion Report — Catalog / PIM

**Phase:** 5 — Enterprise Technical Specifications & Implementation
**Domain:** 3 of 13 — Catalog / Product Information Management
**Date:** 21 July 2026
**Spec:** [docs/specs/03-catalog.md](../specs/03-catalog.md) §19 (Migration Strategy) is the authoritative scope for this work.

## What was completed

- **Structural migration.** `Models/Product.php`, `Repositories/ProductRepository.php`, `Services/ProductService.php`, `Controllers/ProductController.php` moved into `src/Domains/Catalog/*`, namespaced `App\Domains\Catalog\...`, bound by interface in the Kernel. `CartController`/`CartService` (Cart domain, not yet migrated) updated to depend on `ProductServiceInterface`/`ProductRepositoryInterface` rather than the old concrete classes — a cross-domain interface dependency, the same pattern already used for `AccountController` → Identity.
- **`attributes_json` + `tax_classes`** (`Product::$attributesJson`, `TaxClass` model/repository/service) — JSON attribute bag chosen over a full EAV schema per spec §2, since no real attribute requirements exist yet in this codebase beyond flat fields.
- **`product_variants`** — schema-only, per spec §19: no service or UI logic populates or reads it yet, deliberately, since Inventory's per-location stock design (domain #5) needs to exist before variant-vs-product stock tracking can be decided correctly.
- **Product write path** (`create`, `update`, `deactivate` on `ProductServiceInterface`) — Phase 3 had no product-management write path at all; the storefront was read-only against seed data. Validates `sale_price < price`, non-negative pricing, and `attributes_json` shape at the service layer before it ever reaches the database.
- **Slug immutability rule** (§2) — once a slug has appeared in a customer-facing transactional document, it can't be changed. No `orders` table exists yet (Orders is domain #6), so `slugHasTransactionHistory()` checks `quote_items` — the closest real transactional history that exists today — and is documented to be extended to also check `order_items` once Orders lands. Named accordingly (`slugHasTransactionHistory`, not the spec's literal `slugHasOrderHistory`) so the method name doesn't overstate what it currently checks.
- **`AdminProductController`** — admin-portal product CRUD at `/admin/catalog/products`, defense-in-depth per the Identity/Administration convention: the Kernel's `/admin/*` guard enforces `account_kind = staff`; this controller additionally enforces the specific permission (`catalog.product.view/create/edit/deactivate`) via `UserServiceInterface::hasPermission()`. Every write calls `AuditLoggerInterface::record()`.
- **Permission catalog seeded for real** — the migration inserts `catalog.*` permissions plus the four Administration permissions that were flagged as a deferred gap in `PHASE5-ADMINISTRATION-COMPLETION-REPORT.md`, creates a baseline `staff` role granted all of them, and backfills any pre-existing `account_kind='staff'` user into that role. This is the first domain where the second defense-in-depth layer is actually enforced, not just documented.
- **New staff accounts auto-get the `staff` role** — `UserRepositoryInterface::assignRole()` (new) is called from `AuthService::register()` whenever `account_kind === 'staff'`, so a staff account created through `/admin/staff` has working permissions immediately, with no separate manual step. Verified live (see below).
- **Admin listing includes deactivated products** — a real gap caught during implementation: reusing the storefront's `getProducts()` (which always filters `is_active = 1`) for the admin screen would have made deactivated products invisible to staff, contradicting spec §2 ("remain fully readable by staff"). Added `getProductsForAdmin()`/`countProductsForAdmin()` on the repository and service, and an `isActive` field on the `Product` model, so the admin screen shows true status.
- **Real pre-existing bug found and fixed**: the storefront search query reused the same named placeholder (`:search`) three times in one SQL statement. This throws `SQLSTATE[HY093]: Invalid parameter number` under PDO native prepares (`PDO::ATTR_EMULATE_PREPARES => false`, set in `Database.php` since Phase 3) — present since Phase 3, never caught because search was never exercised live end-to-end until this domain's regression pass (spec §18 explicitly calls for it). Fixed by using three distinct placeholders bound to the same value.
- **Tests**: 11 new unit tests (`ProductServiceTest`, mocked repository, covering every validation rule and exception path) and 5 new integration tests (`ProductRepositoryTest`, against a real database, including the admin-vs-storefront visibility split and the `quote_items`-based transaction-history check) — all passing, alongside the full pre-existing suite.

## Why it was done this way

Per §19, product variants and a full EAV attribute system are explicitly out of scope for this phase — building either without real catalog/variant requirements would be guessing, which the spec itself warns against. Category and brand admin-management screens (implied by §13's UI Pages list but absent from §7's Controller Responsibilities and §19's explicit in-scope bullets) were treated as a spec inconsistency and deferred rather than silently built beyond the authoritative migration-scope section — recorded below, not silently dropped.

## Files affected

34 changes: 4 renames/moves into `src/Domains/Catalog/*` (`Product.php`, `ProductRepository.php`, `ProductService.php`, `ProductController.php`, each substantially extended, not pure moves), 15 new files (interfaces, `TaxClass`/`ProductVariant` models, exceptions, event DTOs, `AdminProductController`, migration, 2 test files, 1 admin view), 6 files edited outside the domain (`Kernel.php`, `CartController.php`, `CartService.php`, `product-details.php` docblock, `admin-nav.php`, and Identity's `AuthService.php`/`UserRepository(Interface).php` for the role-assignment retrofit).

## Verification results

| Check | Result |
|---|---|
| `php -l` across `src/`, `views/`, `components/`, `public/`, `tests/` | Clean |
| No stale references to old Catalog class paths anywhere in the tree | Confirmed clean (grep) |
| Migration applied to a real MariaDB instance | Clean — `tax_classes`, `product_variants` created; `products.attributes_json`/`tax_class_id` added; composite index present; 10 permissions + `staff` role + role_permissions seeded |
| Unit tests (`ProductService`) | 11/11 passing |
| Integration tests (`ProductRepository`) | 5/5 passing |
| Full project test suite (Identity + Administration + Catalog) | 47/47 passing (36 unit, 11 integration) |
| Anonymous / customer request to `/admin/catalog/products` | Both 403 |
| Staff request to `/admin/catalog/products` | 200 |
| Brand-new staff account (created via `/admin/staff`) can reach `/admin/catalog/products` immediately after first login | Confirmed — validates the `assignRole` retrofit end-to-end |
| Live product create → appears on storefront listing and detail page | Confirmed |
| Live product update (valid pricing) | Confirmed, audit log entry correct |
| Live product update (invalid pricing, sale ≥ price) | Correctly rejected with the validation error, no audit entry, no DB change |
| Live product deactivate | Confirmed — storefront detail page now 404s, admin listing still shows it as Inactive, audit log entry correct |
| Storefront search (previously broken) | Now returns 200 and correct results, confirmed against a real inserted product |
| Storefront sort / category filter regression | All 200 |
| Regression: `/`, `/products.php`, `/product-details.php` (real seeded slug), `/cart.php`, `/wishlist.php`, `/admin`, `/admin/staff`, `/admin/settings`, `/admin/feature-flags`, `/admin/audit-log` | All 200 |
| Test fixtures (e2e users, products, categories, brands, audit rows) | Cleaned from the database after verification |

## Risks

- Category and brand admin-management screens (`/admin/catalog/categories`, `/admin/catalog/brands`) are referenced in this domain's own spec §13 but were not built this pass — §7 (Controller Responsibilities) and §19 (explicit migration scope) never called for them, and building them would have been scope creep beyond what the authoritative section of the spec asked for. Flagged here as a spec inconsistency to resolve, not a silently dropped feature.
- There is no "reactivate" action for a deactivated product — `ProductServiceInterface` (§5) only defines `deactivate()`. A deactivated product currently has no UI path back to active without a direct database update. Recorded as a real usability gap for a near-term follow-up, not a hidden limitation.
- `slugHasTransactionHistory()` checks `quote_items` only, not a real `orders` table (Orders domain #6 doesn't exist yet) — documented in the method's own docblock and interface, with an explicit plan to extend it once Orders lands.
- The search-placeholder bug fix and the admin-visibility gap were both found and fixed *during* this pass, not before — a reminder that this codebase's storefront read paths hadn't been exercised as thoroughly as their "unchanged since Phase 3" label implied.

## Recommended next priorities

1. Implement **Customers** (domain #4), per the approved implementation order.
2. Resolve the `/admin/catalog/categories` and `/admin/catalog/brands` spec inconsistency noted above — either build the screens or formally amend §13.
3. Add a `reactivate()` method to `ProductServiceInterface` and a corresponding admin action once product lifecycle needs justify it.
4. Continue the domain-by-domain sequence per `docs/specs/00-index.md`.
