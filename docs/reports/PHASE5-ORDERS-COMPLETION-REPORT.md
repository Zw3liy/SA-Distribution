# Domain Implementation Completion Report — Orders

**Phase:** 5 — Enterprise Technical Specifications & Implementation (formal closure)
**Domain:** 6 of 13 — Orders (Cart → Checkout → Order lifecycle, Payments, Tax)
**Date:** 7 August 2026
**Spec:** [docs/specs/06-orders.md](../specs/06-orders.md) §19 (Migration Strategy) is the authoritative scope for the domain; this report closes out the **test/verification gap** that remained after the domain's implementation commit (`3d73e27`).

## What was completed in this pass

The Orders domain itself was implemented in `3d73e27` (32 files under `src/Domains/Orders/*`, the `2026_07_21_orders_domain.sql` migration, admin views, Kernel wiring). What this pass adds is the missing **test coverage and formal closure** the domain never received — the only Phase 5 domain without tests or a completion report:

### New unit tests — `tests/Unit/Orders/` (48 tests, 118 assertions)

| File | Tests | Covers |
|---|---|---|
| `OrderServiceTest.php` | 15 | Full state machine per §2: every legal forward transition, cancel from `pending_payment`/`paid`, `returned` only from `delivered`, terminal-state immutability; every illegal path throws `InvalidOrderTransitionException` (skip, backward, terminal); `OrderNotFoundException` for unknown ids; reservation lifecycle wiring — `consumeReservation()` on `paid→fulfilling` (per line item, null-reservation lines skipped), `releaseReservation()` on cancellation; history row recorded per transition with actor/note; `cancel()` convenience wrapper (reason + null actor); all §5 query methods |
| `TaxCalculatorTest.php` | 9 | The 15% VAT math extracted verbatim from Phase 2/3's QuoteService (regression case R24,999 → R28,748.85), multi-line accumulation, 2-dp rounding, default ZA region, zero-quantity lines, and the three `InvalidArgumentException` paths (empty list, unsupported region, negative unit price) |
| `CartServiceTest.php` | 10 | DB-backed cart path (§19): row→`CartItem` mapping incl. sale-price precedence, summary math (subtotal/VAT/grand total), stock clamping on add and update, quantity floor of 1, zero-quantity removal, unknown-slug rejection, and delegation of remove/clear/mark-converted |
| `CheckoutServiceTest.php` | 11 | The end-to-end orchestration (§2/§7/§14): empty-cart and unknown-customer rejection, both address-ownership re-checks (§8) throwing `InvalidAddressException`, checkout-time availability re-check throwing `InsufficientStockException`, missing-default-warehouse `RuntimeException`, the full happy path (order persists with tax totals and per-line `inventory_reservation_id`s, order starts `pending_payment`, pending orchestration payment with `method='unassigned'` per §16, cart marked converted per §2), the two §14 rollback invariants (mid-reservation failure releases prior reservations; order-create failure releases all), tax-calculator input shape, and the `SDO-{ts}-{rand}` order-number format |
| `OrderEventsTest.php` | 3 | The immutable event DTOs (`OrderPlaced`, `OrderStatusChanged`, `OrderCancelled`) — the subscription contract Warehouse/Finance/CRM/Analytics will consume (§9) |

### New integration tests — `tests/Integration/Orders/` (21 tests, 71 assertions) — run against a real MySQL server

| File | Tests | Covers |
|---|---|---|
| `OrderRepositoryTest.php` | 8 | Transactional order+items create (reload verifies snapshots), full rollback on item FK failure (no partial order row), `findById` null, per-customer pagination with `placed_at DESC` ordering, per-customer counts, admin list/count, `updateStatus`, and the `inventory_reservation_id` write/read round-trip against a real `stock_reservations` row |
| `CartRepositoryTest.php` | 5 | First-use cart-row creation with the `products.stock` join, replace-not-append re-saves, per-slug removal, clear, and the converted-cart semantics (§2/§13): `markConverted()` links the order, `getCartIdBySession()` excludes the converted cart, and the next save creates a **fresh** cart row while the historical cart keeps its order link |
| `PaymentRepositoryTest.php` | 4 | Create/find round-trip, latest-payment-wins ordering, status update, null when absent |
| `OrderStatusHistoryRepositoryTest.php` | 4 | Record/history round-trip with actor + note, oldest-first ordering, null actor/note preservation, empty history for unknown orders |

### Defects found and fixed during verification

1. **Real schema bug — cart conversion is impossible under the Phase 3 unique key.** `cart.session_id` carried `UNIQUE KEY uq_cart_session` from Phase 3 (correct when one row per session was the invariant). The Orders design (§2/§13 + `CartRepository` docs) requires the post-checkout add-to-cart to create a **fresh** cart row for the same session while the converted row is preserved — impossible with the unique key: the first post-checkout save dies with MySQL error 1062. Caught by `CartRepositoryTest::testMarkConvertedExcludesCartAndNextSaveCreatesFreshCart`. Fixed with a new, clearly documented migration, `2026_08_07_cart_session_conversion_fix.sql`, which demotes the unique key to a plain lookup index (no code path relies on the uniqueness — every access filters `converted_to_order_id IS NULL` with `LIMIT 1` or joins `cart_items` by `cart_id`). Environments that already applied the Orders migration need only this one ALTER.
2. **Test portability fix — `ProductRepositoryTest::testAttributesJsonRoundTripsThroughInsertAndRead`.** The assertion compared JSON key order, which is not part of the round-trip contract: MySQL 5.7's JSON binary format sorts object keys by length, while MySQL 8.0+/MariaDB preserve insertion order. Changed to a strict but order-independent comparison (`ksort` + `assertSame`), so the same test passes on all three engines. Data semantics unchanged.

### Tooling note (verification environment)

The sandbox had no PHP, Composer, or MySQL and no access to apt/packagist mirrors. The full suite was executed with an equivalent stack assembled from reachable sources: **PHP 8.3.32** (WordPress-Playground WebAssembly build via `@php-wasm/node`, JSPI mode, host filesystem mounted, `pdo_mysql`/`mysqlnd`/`dom`/`mbstring`/`tokenizer`/`xml`/`xmlwriter` present), **PHPUnit 10.5.64** (source install resolved and fetched from GitHub), and a real **MySQL 5.7.29** server (prebuilt community-server binaries; `libaio` compiled locally). The only SQL adjustment was applied to the *loader*, not the repo: two `ADD COLUMN IF NOT EXISTS` statements in the catalog migration were stripped for 5.7 (a MySQL 8.0.29+/MariaDB construct, redundant on a fresh database). App SQL itself is exercised unchanged. `docs/specs/00-index.md` requires verification against a real database — that discipline was honored; the dev environment (PHP 8.1+ native + MariaDB 10.x) remains the canonical run target per the README.

## Why it was done this way

- **Follow the house standard exactly.** Unit tests mock domain interfaces only (`createMock(Interface::class)`) and cite the spec section under test; integration tests connect via env-var PDO with the same fallbacks (`DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`), create prefixed fixtures (`REPO-TEST-ORD-*`), and clean up in dependency order — identical to Identity/Administration/Catalog/Customers/Inventory suites.
- **The §14 rollback invariants got their own tests because they are the domain's sharpest edge.** "No reservation may be left dangling with no order behind it" is called out in the spec as the single easiest correctness bug in a checkout flow; both halves (reserve-failure and order-create-failure) are pinned.
- **Integration tests were written to find real problems, not to bless the code.** They did — the `uq_cart_session` defect above would have broken every real checkout's follow-up purchase and could not be seen from unit tests alone.
- **No production code was modified** in this pass (only the new migration + the test portability fix). `src/Domains/Orders/*` stands exactly as shipped in `3d73e27`.

## Files affected

**Added — tests (9 files):**
- `tests/Unit/Orders/OrderServiceTest.php`
- `tests/Unit/Orders/TaxCalculatorTest.php`
- `tests/Unit/Orders/CartServiceTest.php`
- `tests/Unit/Orders/CheckoutServiceTest.php`
- `tests/Unit/Orders/OrderEventsTest.php`
- `tests/Integration/Orders/OrderRepositoryTest.php`
- `tests/Integration/Orders/CartRepositoryTest.php`
- `tests/Integration/Orders/PaymentRepositoryTest.php`
- `tests/Integration/Orders/OrderStatusHistoryRepositoryTest.php`

**Added — migration (1 file):**
- `database/migrations/2026_08_07_cart_session_conversion_fix.sql`

**Modified (1 file, 8 lines):**
- `tests/Integration/Catalog/ProductRepositoryTest.php` — JSON key-order portability fix (documented above)

**Unchanged by design:** all of `src/Domains/Orders/*`, the Orders migration as shipped, `src/Http/Kernel.php`, views.

## Test coverage summary

| Layer | Phase 5 baseline | Orders added | Total |
|---|---|---|---|
| Unit (`tests/Unit/`) | 64 tests | 48 tests | **112 tests, 237 assertions** |
| Integration (`tests/Integration/`, real DB) | 31 tests | 21 tests | **52 tests, 158 assertions** |
| **Full suite** | 95 tests, 206 assertions | 69 tests, 189 assertions | **164 tests, 395 assertions** |

User-requested coverage checklist: order creation ✓, lifecycle/status transitions ✓, cart→checkout flow ✓, order-item handling ✓, payment handling ✓, tax calculation ✓, repository behaviour ✓, validation rules ✓ (stock clamps, address ownership, state machine, tax inputs), exception handling ✓ (all five Orders exceptions + cross-domain `InsufficientStockException`), domain events ✓, service-layer contracts ✓ (every interface method of `OrderServiceInterface`/`CartServiceInterface`/`CheckoutServiceInterface`/`TaxCalculatorInterface` is exercised, plus the cross-domain interfaces they depend on).

## Test execution results

Environment: PHP 8.3.32 (wasm, JSPI) · PHPUnit 10.5.64 · MySQL 5.7.29 (fresh `sa_business` schema: all 9 base + migration files applied, including the new cart fix; integration fixtures cleaned after every run).

| Check | Result |
|---|---|
| `php -l` across `src/` (152 files), `tests/` (26 files), `views/` (25), `components/` (3), `public/` (2), `database/` (1) | Clean — 208/208 files |
| Existing Phase 5 unit suite (Identity + Administration + Catalog + Customers + Inventory) | Passing — included in the 112-test unit suite |
| Existing Phase 5 integration suite (same domains, real DB) | Passing — included in the 52-test integration suite |
| New Orders unit suite (`tests/Unit/Orders`) | 48/48 passing, 118 assertions |
| New Orders integration suite (`tests/Integration/Orders`, real DB) | 21/21 passing, 71 assertions |
| Full project unit suite | 112/112 passing, 237 assertions |
| Full project integration suite | 52/52 passing, 158 assertions |
| **Complete suite (`phpunit.xml`)** | **164/164 passing, 395 assertions — OK** |
| Repeatability (full suite run twice consecutively) | Both runs identical — OK (fixture cleanup is leak-free) |
| Orders migration + cart-fix migration applied to a real DB | Clean — `orders`, `order_items`, `order_status_history`, `payments` created; `cart` now `idx_cart_session` (unique demoted) |
| Regression: `git status` | Only the 11 intended files (9 tests + 1 migration + 1 test fix); `src/Domains/Orders/*` untouched |

## Architectural notes

- **The suite now locks in the cross-domain seams Orders depends on**: `InventoryServiceInterface` (reserve/consume/release/available), `CustomerServiceInterface::getById()`, `AddressServiceInterface::listFor()`, `WarehouseRepositoryInterface::findDefault()`, `ProductRepositoryInterface::getProductBySlug()`. Warehouse (domain #7) builds directly on the transition semantics these tests pin down (`paid→fulfilling` = consume reservations; cancel = release).
- **`TaxCalculator` remains Orders' temporary custodian** of Finance's contract; its tests encode the exact `{subtotal, taxTotal, grandTotal}` shape and the QuoteService-verified math so the planned move to `App\Domains\Finance\Services\*` is a pure relocation, verified by an unchanged test file after the move.
- **Events are documented contracts, not dispatches** — consistent with Catalog; the DTO tests exist so future subscribers (Analytics/AI) can rely on the shape.
- **The `uq_cart_session` fix is the schema half of the conversion design.** The code half (`getCartIdBySession()` exclusion, `markConverted()`) was already correct; the constraint was the missing piece. Any environment that applied the Orders migration should apply `2026_08_07_cart_session_conversion_fix.sql` before checkout is exercised for real.

## Risks

- **Verification ran on MySQL 5.7, dev targets MariaDB 10.x/MySQL 8.** The suite is written to be engine-neutral (the one engine-sensitive assertion was fixed), and the sandbox-only DDL shim (`ADD COLUMN IF NOT EXISTS`) touches no app SQL, but a final green run on the dev MariaDB instance is still the canonical sign-off.
- **The customers backfill script** (`2026_07_21_customers_backfill.php`) could not be executed in the sandbox (the app's `Database` connection uses `PDO::MYSQL_ATTR_INIT_COMMAND`, which hits an unimplemented mysqlnd path in the wasm PHP build). It is data-only, idempotent, and a no-op without seed users; dev environments run it under native PHP as documented in its header.
- **No real payment gateway is integrated** — by design (§19/§20). The `payments` orchestration-record lifecycle (`pending` → `succeeded`/`failed`) is repository-tested, but gateway webhook handling remains future work.
- **Checkout's HTTP layer** (`CheckoutController`/`AdminOrderController` CSRF, permission checks, flash/redirect flow) follows the established controller conventions but has no browser-level e2e tests in this pass — consistent with every other Phase 5 domain, whose controllers were verified by live e2e walks in their own completion passes.

## Phase 5 closure status

**CLOSED.** With this pass, all six Phase 5 domains (Identity, Administration, Catalog/PIM, Customers, Inventory, Orders) have: implementation, migration, admin/storefront views, per-domain unit tests, per-domain integration tests against a real database, a completion report in `docs/reports/`, and a green full suite (164 tests, 395 assertions). Phase 5 is formally complete.

**Recommended next priorities** (per `DOMAIN_ARCHITECTURE_BLUEPRINT.md` §6):
1. Begin **Phase 6 — Warehouse (domain #7)**: fulfillment execution over Orders + Inventory (picking/packing/shipping, `OrderStatusChanged` consumption), per `docs/specs/07-warehouse.md` §19. The Orders transition tests in this pass are its contract.
2. **Suppliers (domain #8)** is explicitly parallelizable with Warehouse.
3. Apply `2026_08_07_cart_session_conversion_fix.sql` to any pre-existing dev/staging databases before checkout is used for real.
4. When Finance (domain #10) lands, relocate `TaxCalculator`/`TaxCalculationResult`/`TaxCalculatorInterface` into `App\Domains\Finance\Services\*` and repoint consumers — the relocation is behavior-neutral by design and the tests move with it unchanged.
