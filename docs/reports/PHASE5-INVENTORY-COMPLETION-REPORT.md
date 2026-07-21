# Domain Implementation Completion Report — Inventory

**Phase:** 5 — Enterprise Technical Specifications & Implementation
**Domain:** 5 of 13 — Inventory (Multi-location Stock, Reservations, Movements)
**Date:** 21 July 2026
**Spec:** [docs/specs/05-inventory.md](../specs/05-inventory.md) §19 (Migration Strategy) is the authoritative scope for this work.

## What was completed

- **New `Inventory` bounded context.** `Warehouse`, `InventoryItem`, `StockReservation`, `StockMovement` models; `WarehouseRepository(Interface)`, `InventoryItemRepository(Interface)`, `StockReservationRepository(Interface)`, `StockMovementRepository(Interface)`; `InventoryService(Interface)`; `AdminInventoryController` — all under `src/Domains/Inventory/*`, bound by interface in the Kernel.
- **Multi-location stock model.** `inventory_items` scoped `(product_id, warehouse_id)` with a unique index doubling as the lookup index (§17); `quantity_available` is always derived (`on_hand - reserved`), never stored, and is the only figure ever exposed past the service layer (§16 — no customer-facing code path can see raw `quantity_on_hand`/`quantity_reserved`).
- **Concurrency-safe reservations (§18).** `InventoryItemRepository::tryReserve()`/`tryAdjustOnHand()` use single atomic conditional `UPDATE ... WHERE (on_hand - reserved) >= :quantity` statements rather than read-then-write pairs, so two simultaneous reservation attempts for the last unit of stock cannot both succeed — verified against a real database, not just mocked. `reserve()` never silently clamps a requested quantity; it throws `InsufficientStockException`.
- **Reservation lifecycle.** `reserve()` creates a 30-minute-window `StockReservation`; `consumeReservation()` converts it into a permanent on-hand deduction (writing a `StockMovement`); `releaseReservation()` returns the hold (idempotent for already-consumed/released reservations, since a caller racing the expiry sweep is expected, not a bug). `expireReservations()` (an addition beyond the spec's minimal 5-method `InventoryServiceInterface` list, needed to make §17's "run by a scheduled task" sweep a real, callable, tested capability) performs a bounded-batch, transactional sweep — implemented with a `GROUP BY`-aggregated subquery rather than a naive multi-row `UPDATE...JOIN`, since MySQL's multi-table `UPDATE` only applies one matching joined row per target row, which would have silently dropped all but one release when an item had multiple expired reservations.
- **Full audit trail (§2/§15).** Every real on-hand quantity change (`adjust()`, `consumeReservation()`) writes a `StockMovement` row. The invariant "no code path updates quantity without an audit row" is enforced by `InventoryItemRepository` never being exposed outside `InventoryService`, rather than by threading a `reason` parameter through the repository's own mutation signature — a deliberate, documented deviation from the spec's literal wording that preserves the same guarantee with a simpler repository layer.
- **`products.stock` compatibility mirror (§2).** `InventoryService` depends directly on Catalog's `ProductRepositoryInterface` (the same precedent already established by `CartService`) and calls `updateFields($productId, ['stock' => $available])` after every operation that changes availability, so Catalog's existing read paths keep working unchanged until Orders (domain #6) is live and confirmed to query Inventory directly.
- **`ProductCreated` event consumption (§10).** `AdminProductController::createProduct()` now calls `InventoryService::initializeForProduct()` immediately after a product is created, wired as a direct synchronous call (no event bus exists anywhere in this platform — same pattern as Customers' `AuthController` → `CustomerService` retrofit). This creates a zero-quantity `InventoryItem` in the default warehouse. **Workflow change, documented not silently absorbed:** the product-create form's legacy `stock` field is now immediately superseded by this zero-quantity initializer; staff must set real initial stock afterward via `/admin/inventory/adjust`, which is audit-trailed (the create form's flash message was updated to say so).
- **`AdminInventoryController`** — `/admin/inventory` (per-warehouse stock list, filterable/searchable by product name or SKU) and `/admin/inventory/adjust` (adjustment form with mandatory reason and an explicit "allow negative" override checkbox), query-param routed like Customers' equivalent screens since the Router has no path-parameter support. Defense in depth: Kernel's `/admin/*` guard plus `inventory.stock.view`/`inventory.stock.adjust` permission checks, the latter gating adjustments specifically (a more sensitive permission than view, per §16).
- **Permission catalog extended** — `inventory.stock.view`, `inventory.stock.adjust`, seeded and granted to the baseline `staff` role via the same idempotent pattern used in prior migrations.
- **Historical backfill built into the migration SQL itself** (not a separate script, unlike Customers) — one `InventoryItem` per existing product against the seeded default `MAIN` warehouse, `quantity_on_hand` set from the existing `products.stock` value (not zero), so the transition preserves every real stock count already in the system rather than making every product instantly appear out-of-stock. Live run: 8 products, 8 inventory items created, values matched exactly.
- **Tests**: 11 new unit tests (`InventoryServiceTest` — sufficient/insufficient/exact-boundary reservation, negative-adjustment rejection and the explicit override path, per §18) and 8 new integration tests (`InventoryItemRepositoryTest` against a real database for the atomic-update boundary cases; `StockReservationExpiryTest` with a real mix of active/expired/consumed reservations, asserting only the correct subset transitions and that quantities release correctly even with multiple expired reservations on the same item) — all passing, alongside the full pre-existing suite.

## Why it was done this way

Per §19, real reservation calls into a checkout flow are explicitly out of scope this pass (Orders, domain #6, doesn't exist yet) — `reserve()`/`consumeReservation()`/`releaseReservation()` are built and fully unit/integration-tested but have no real caller yet, exactly as instructed. The Warehouse domain's richer location model (zones/bins) was left untouched; this domain's `warehouses` table is deliberately minimal, just enough to scope a quantity to a location, per §19's explicit boundary with domain #7.

## Files affected

23 changes: 1 new domain directory (`src/Domains/Inventory/*`, 17 files: 4 models, 4 exceptions, 4 events, 4 repository interfaces + implementations, 1 service interface + implementation, 1 controller), 1 migration (with the backfill embedded, unlike Customers' separate script — chosen here because the backfill is a simple single `INSERT...SELECT` with no per-row correlation problem), 2 new admin views, 3 modifications outside the domain for wiring (`src/Http/Kernel.php`, `components/admin-nav.php`, `src/Domains/Catalog/Controllers/AdminProductController.php` — new `InventoryServiceInterface` dependency for the `ProductCreated` consumption), 4 new test files.

## Verification results

| Check | Result |
|---|---|
| `php -l` across `src/`, `views/`, `components/`, `public/`, `tests/`, `database/` | Clean |
| Migration applied to a real MariaDB instance | Clean — `warehouses`, `inventory_items`, `stock_reservations`, `stock_movements` created; 1 default warehouse seeded; 8/8 products backfilled with matching on-hand quantities; 2 permissions + staff-role grants seeded |
| Unit tests (`InventoryService`) | 11/11 passing |
| Integration tests (`InventoryItemRepository`, `StockReservationExpiry`) | 8/8 passing |
| Full project integration suite (Identity + Administration + Catalog + Customers + Inventory) | 31/31 passing |
| Full project unit suite | 64/64 passing |
| Anonymous / customer request to `/admin/inventory` | 403 |
| Staff request to `/admin/inventory` | 200 |
| Staff stock adjustment (+25, valid) | Confirmed — on-hand increased, `products.stock` mirror synced, exactly 1 `StockMovement` row written, audit log entry correct |
| Staff over-negative adjustment without override | Correctly rejected with `InsufficientStockException`'s message, no DB change |
| Staff over-negative adjustment with `allow_negative` override | Confirmed — on-hand went negative as intended, movement recorded |
| New product created via `/admin/catalog/products` | Confirmed — zero-quantity `InventoryItem` auto-created in the default warehouse regardless of the legacy form's `stock` input |
| Anonymous POST to the adjust endpoint | 403 (defense-in-depth guard holds) |
| Regression: `/`, `/products.php`, `/cart.php`, `/wishlist.php`, `/login.php`, `/register.php`, `/admin`, `/admin/staff`, `/admin/settings`, `/admin/feature-flags`, `/admin/audit-log`, `/admin/catalog/products`, `/admin/customers` | All 200 (staff) / 403 (anonymous, admin routes) as expected |
| Git working tree | Confirmed clean via `rm -f .git/index && git read-tree HEAD` before staging (recurring OneDrive-mount stale-index bug, now routine to check) |
| Test fixtures (e2e users, movements, adjustments) | Cleaned from the database after verification |

## Real bug found and fixed during live e2e verification

`AdminInventoryController::adjust()` read the "allow negative" checkbox via `filter_input(INPUT_POST, 'allow_negative', FILTER_VALIDATE_BOOLEAN)`, which returns `null` — not `false` — when the field is entirely absent from the POST body (the normal state of an unchecked checkbox). Passed directly into `InventoryServiceInterface::adjust()`'s non-nullable `bool $allowNegative` parameter under `strict_types=1`, this threw a `TypeError` on every single ordinary stock adjustment (checkbox unchecked, the overwhelmingly common case), silently caught by the controller's generic `catch (Throwable)` and surfaced as a confusing internal error message instead of the adjustment succeeding. Caught by this domain's own live e2e verification pass (per §18's testing discipline), not found in unit tests (mocks don't reproduce `filter_input`'s real return-value quirks) — a reminder of why live verification remains mandatory even with full unit/integration coverage. Fixed with `?? false`, documented inline, and re-verified end-to-end (normal adjustment, rejection path, and override path all now behave correctly).

## Risks

- The reservation-expiry sweep (`InventoryService::expireReservations()`) is built, unit-testable in principle, and integration-tested directly against the repository, but has no real scheduled-task runner wired to invoke it on a cadence — that's DevOps/Phase-7 infrastructure, out of scope for this domain per §19's own boundary ("run by a scheduled task" describes the intended caller, not a requirement to build a scheduler in this pass).
- `reserve()`/`consumeReservation()`/`releaseReservation()` have no real caller yet, by design (§19) — they will need integration testing against Orders' actual checkout flow once domain #6 exists, beyond what's testable in isolation today.
- The product-create form's `stock` field is now cosmetic/overridden-to-zero the moment a product is saved — a real workflow change for staff, flagged here and in the create-flow's own flash message rather than silently changing behavior. Removing the field from the form entirely is a reasonable follow-up polish but was left as-is to avoid unnecessary UI churn beyond this domain's actual migration scope.
- `reorder_threshold` defaults to 0 for every backfilled item (no low-stock signal fires for existing inventory until staff set real thresholds) — `LowStockThresholdReached` has no real consumer yet regardless (Suppliers, domain #8, doesn't exist), so this is a latent, not active, gap.

## Recommended next priorities

1. Implement **Orders** (domain #6), per the approved implementation order — this is the first real caller for `reserve()`/`consumeReservation()`/`releaseReservation()`, and is the trigger for eventually removing the `products.stock` compatibility mirror once Orders is confirmed to query Inventory directly.
2. Wire a real scheduled-task runner to call `InventoryService::expireReservations()` periodically once the platform has one (Phase 7 / DevOps concern).
3. Consider removing (or making read-only in the UI) the product-create form's legacy `stock` field, now that it has no real effect.
4. Continue the domain-by-domain sequence per `docs/specs/00-index.md`.
