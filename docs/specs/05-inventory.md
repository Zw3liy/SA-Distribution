# Technical Specification — Inventory Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

How many of a product exist, and where, right now. Owns on-hand quantity, reservations, adjustments, and the audit trail of stock movement. Does not own the physical act of moving stock (Warehouse) or what's sellable (Catalog).

## 2. Business Rules

- Every active `Product` has exactly one `InventoryItem` per `Warehouse` location it's stocked in; a product with zero locations is effectively out-of-stock everywhere regardless of any legacy `products.stock` value.
- `quantity_available = quantity_on_hand - quantity_reserved`. Only `quantity_available` is ever shown to customers or checked at checkout.
- A `StockReservation` is created when an order is placed and is either consumed (converted to a real deduction on fulfillment) or released (order cancelled/reservation expired) — never left dangling. Reservations expire automatically after a configurable window (default 30 minutes) if checkout doesn't complete, to avoid permanently locking stock behind an abandoned cart.
- Every change to `quantity_on_hand` must produce a `StockMovement` row — there is no code path that updates the quantity column without also writing the audit trail (enforced by having the repository's only mutation method require a reason/reference).
- **Compatibility rule during transition:** `products.stock` (Catalog) is kept in sync as a read-only mirror of the sum of `quantity_available` across all locations, updated whenever Inventory changes, so any code not yet migrated to query Inventory directly still sees a correct number. This mirror is removed once Orders (domain #6) is live and confirmed to query Inventory directly.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `warehouses` | new (minimal — full detail owned by Warehouse domain) | `id, name, code, is_active` — Inventory needs to reference a location even before the Warehouse domain builds its own richer model |
| `inventory_items` | new | `id, product_id, warehouse_id, quantity_on_hand, quantity_reserved, reorder_threshold, updated_at`, unique `(product_id, warehouse_id)` |
| `stock_reservations` | new | `id, inventory_item_id, order_reference, quantity, expires_at, status ENUM('active','consumed','released'), created_at` |
| `stock_movements` | new | `id, inventory_item_id, delta, reason, reference_type, reference_id, actor_user_id, created_at` |

## 4. Entity Definitions

`InventoryItem`, `StockReservation`, `StockMovement` — all new, no prior equivalents. `order_reference` on `StockReservation` is a string, not yet a real FK to an `orders` table, since Orders (domain #6) doesn't exist until after Inventory in the implementation order — it becomes a proper FK when Orders is built.

## 5. Service Interfaces

```
interface InventoryServiceInterface {
    public function availableQuantity(int $productId, ?int $warehouseId = null): int; // null = sum across all locations
    public function reserve(int $productId, int $warehouseId, int $quantity, string $orderReference): StockReservation; // throws InsufficientStockException
    public function consumeReservation(int $reservationId): void;
    public function releaseReservation(int $reservationId): void;
    public function adjust(int $productId, int $warehouseId, int $delta, string $reason, int $actorUserId): void;
}
```

## 6. Repository Interfaces

```
interface InventoryItemRepositoryInterface {
    public function findFor(int $productId, int $warehouseId): ?InventoryItem;
    public function sumAvailable(int $productId): int;
    public function upsertOnHand(int $productId, int $warehouseId, int $quantityOnHand): void;
}
interface StockReservationRepositoryInterface {
    public function create(array $data): StockReservation;
    public function expireOlderThan(\DateTimeInterface $cutoff): int; // returns count released, run by a scheduled task
}
interface StockMovementRepositoryInterface {
    public function record(array $data): void;
    public function historyFor(int $inventoryItemId, int $limit): array;
}
```

## 7. Controller Responsibilities

`AdminInventoryController` (new) — stock level view/adjustment screen per warehouse, permission-gated, every adjustment routed through `InventoryService::adjust()` (never a direct SQL update from the controller) so the audit trail rule in §2 can't be bypassed.

## 8. Validation Rules

`reserve()` rejects if `quantity > quantity_available` (throws `InsufficientStockException`, does not silently clamp). `adjust()` rejects a delta that would take `quantity_on_hand` negative unless an explicit `allowNegative` flag is passed (for known reconciliation edge cases like a prior over-sell) — default is to reject, per "never write quick fixes" (silently allowing negative stock hides real operational problems).

## 9. Events Published

`Inventory\Events\StockReserved`, `StockConsumed`, `StockReleased`, `LowStockThresholdReached` (fires when `quantity_available` crosses below `reorder_threshold` — consumed by Suppliers, for future auto-PO-suggestion, and Administration/Analytics for alerting).

## 10. Events Consumed

`Catalog\Events\ProductCreated` — auto-creates a zero-quantity `InventoryItem` for the default warehouse so every product has a location record from day one, rather than needing a null-check everywhere downstream.

## 11. Permissions Required

`inventory.stock.view`, `inventory.stock.adjust` (a more sensitive permission than view, since adjustments directly affect what's sellable).

## 12. API Endpoints

No public API in this phase (stock levels are operationally sensitive and not yet a partner-integration requirement). Internal admin-portal AJAX for the adjustment screen only.

## 13. UI Pages

`/admin/inventory` (per-warehouse stock list, filterable/searchable), `/admin/inventory/{product}/adjust` (adjustment form with mandatory reason).

## 14. Error Handling

`InsufficientStockException`, `InventoryItemNotFoundException`, `ReservationExpiredException` (attempting to consume a reservation that already auto-expired — a real race condition worth a named exception rather than a generic failure, since Orders' checkout flow needs to handle it distinctly: re-check availability and either re-reserve or fail the checkout with a clear message).

## 15. Logging Requirements

Every `StockMovement` is itself a durable audit trail (§2) — no separate logging requirement duplicates it. Reservation expiry (automated) is logged at `info` with a count, not per-reservation, to avoid log spam from a scheduled sweep.

## 16. Security Requirements

Stock adjustment requires `inventory.stock.adjust` (a distinct, more sensitive permission than view-only staff access). No customer-facing endpoint ever exposes `quantity_on_hand` or `quantity_reserved` — only the derived `quantity_available`, and only as an in-stock/low-stock/out-of-stock signal, not an exact number (prevents competitors from scraping precise stock levels).

## 17. Performance Requirements

`availableQuantity()` is called on every product-detail page view and at checkout — must be a single indexed query (`inventory_items(product_id, warehouse_id)` unique index doubles as the lookup index). Reservation expiry sweep must run as a scheduled task (not on the request path) and process in bounded batches.

## 18. Testing Strategy

Unit: `InventoryService::reserve()` — sufficient stock, insufficient stock, exactly-at-boundary quantity. Unit: `adjust()` — negative-result rejection and the explicit override path. Integration: reservation expiry sweep against a real test database with a mix of expired/active/consumed rows, asserting only the correct subset transitions. Concurrency note (documented, not necessarily fully tested in this phase): two simultaneous `reserve()` calls for the last unit of stock must not both succeed — requires a row-level lock or atomic conditional update in the repository implementation; called out explicitly here so it isn't missed during implementation.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** create all four tables in §3; build `InventoryService` + repositories; build the `products.stock` compatibility mirror (§2) so Catalog's existing read paths keep working unchanged; build the admin adjustment screen; wire the `ProductCreated` event consumer. Do **not** yet wire real reservation calls into a checkout flow, since Orders (domain #6) doesn't exist yet — `reserve()`/`consumeReservation()` are built and unit-tested but have no real caller until Orders is implemented.

**Explicitly out of scope:** the Warehouse domain's richer location model (zones/bins) — Inventory's `warehouses` table here is intentionally minimal, just enough to scope a quantity to a location; Warehouse (domain #7) either extends this table or references it, decided when that domain is actually implemented. Multi-warehouse transfer logic (Warehouse's job).

## 20. Future Enhancements

Automatic low-stock purchase-order suggestions (feeds Suppliers), demand forecasting (AI domain, once real sales history exists via Orders), removal of the `products.stock` compatibility mirror once all consumers migrate to querying Inventory directly.
