# Technical Specification — Suppliers Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

The supplier directory, procurement, purchase orders, and supplier-side product cost/lead-time data. Feeds Warehouse (goods receipt) and Finance (accounts payable).

## 2. Business Rules

- A `Supplier` can offer zero or more Catalog products via `SupplierProduct`, which carries the supplier's cost price and lead time — distinct from and never conflated with Catalog's customer-facing `price`.
- A `PurchaseOrder` moves through `draft → sent → partially_received → received → closed` (or `cancelled` from `draft`/`sent` only) — a state machine, same discipline as Orders' status machine (§2 of that spec), deliberately mirrored for consistency across the platform.
- `PurchaseOrderItem` quantities received are tracked cumulatively against ordered quantity (via Warehouse's `GoodsReceipt`, which Suppliers does not own but is the eventual consumer of, closing the loop back to `partially_received`/`received` status) — Suppliers listens for `Warehouse\Events\GoodsReceived` rather than Warehouse writing into Suppliers' tables directly.
- A product with no `SupplierProduct` record has no known sourcing — flagged (not blocked) in the admin Catalog product screen, since a product can legitimately exist in the catalog before a supplier relationship is finalized.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `suppliers` | new | `id, name, contact_email, contact_phone, is_active, created_at` |
| `supplier_products` | new | `id, supplier_id, product_id, cost_price, lead_time_days, supplier_sku`, unique `(supplier_id, product_id)` |
| `purchase_orders` | new | `id, supplier_id, warehouse_id, status, created_by_user_id, created_at` |
| `purchase_order_items` | new | `id, purchase_order_id, product_id, quantity_ordered, quantity_received, unit_cost` |

## 4. Entity Definitions

`Supplier`, `SupplierProduct`, `PurchaseOrder`, `PurchaseOrderItem` — all new.

## 5. Service Interfaces

```
interface SupplierServiceInterface {
    public function create(array $data): Supplier;
    public function linkProduct(int $supplierId, int $productId, float $costPrice, int $leadTimeDays): SupplierProduct;
}
interface PurchaseOrderServiceInterface {
    public function create(int $supplierId, int $warehouseId, array $items, int $actorUserId): PurchaseOrder;
    public function send(int $purchaseOrderId): void; // status draft -> sent
    public function applyReceipt(int $purchaseOrderId, array $receivedItems): void; // called from the GoodsReceived event handler
}
```

## 6. Repository Interfaces

`SupplierRepositoryInterface`, `SupplierProductRepositoryInterface`, `PurchaseOrderRepositoryInterface` — standard CRUD shape, consistent with prior domains' repository interfaces.

## 7. Controller Responsibilities

`AdminSupplierController` (supplier directory CRUD, product-linking screen), `AdminPurchaseOrderController` (PO creation/send/detail) — both staff-only, no customer-facing surface.

## 8. Validation Rules

`cost_price >= 0`, `lead_time_days >= 0`. `PurchaseOrder::send()` rejects an empty-items PO. `applyReceipt()` rejects a received quantity that would exceed ordered quantity unless Warehouse's goods-receipt override flag (per Warehouse spec §8) was set on the originating receipt — Suppliers trusts Warehouse's own validation rather than re-implementing it, since the override decision was already made at the point of physical receipt.

## 9. Events Published

`Suppliers\Events\PurchaseOrderSent`, `PurchaseOrderReceived` — consumed by Finance (accounts payable — an incoming supplier invoice should be reconcilable against a received PO) and Analytics.

## 10. Events Consumed

`Warehouse\Events\GoodsReceived`, `Inventory\Events\LowStockThresholdReached` (the latter is a candidate trigger for a future auto-suggested PO — in this phase it's only logged/surfaced in the admin UI as a suggestion, not auto-actioned; see §20).

## 11. Permissions Required

`suppliers.supplier.manage`, `suppliers.purchase_order.create`, `suppliers.purchase_order.send` (kept distinct from `create` since sending a PO is the point of no return — committing spend — and some staff roles may be allowed to draft but not authorize).

## 12. API Endpoints

None public in this phase — no partner-facing supplier portal exists yet (a genuine "Supplier Portal" from the standing project instructions' module list is a larger future initiative, not scoped into Phase 5's Suppliers domain, which is internal procurement tooling only).

## 13. UI Pages

`/admin/suppliers`, `/admin/suppliers/{id}/products`, `/admin/purchase-orders` — all net-new, staff-only.

## 14. Error Handling

`SupplierNotFoundException`, `InvalidPurchaseOrderTransitionException`, `OverReceiptException` (mirrors Warehouse's, since receipt validation is conceptually shared logic even though Suppliers doesn't own the receiving action itself).

## 15. Logging Requirements

PO creation/send/receipt-application audit-logged (financial-commitment-relevant, same bar as Warehouse's blanket audit-logging rule).

## 16. Security Requirements

`suppliers.purchase_order.send` should be treated as a financially sensitive permission in role design (recommendation, not a technical enforcement beyond the standard permission check) — flagged here so Administration's role-setup guidance can call it out explicitly when this domain ships.

## 17. Performance Requirements

No unusual performance requirements — this domain's data volumes (supplier count, PO count) are orders of magnitude smaller than Catalog/Orders and don't need special indexing beyond the standard foreign-key indexes.

## 18. Testing Strategy

Unit: `PurchaseOrderService` state machine transitions. Unit: `applyReceipt()` over-receipt rejection and override-respecting behavior. Integration: full flow against a real test database — create PO, send, simulate a `GoodsReceived` event, assert status moves to `partially_received` then `received` across two partial receipts.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** all tables, services, repositories, and the two admin screens in §3–7/§13; the `GoodsReceived` event consumer wiring. This domain has no existing code to migrate — it is built entirely fresh, following the same architectural conventions (state machine, event-driven cross-domain integration, audit logging) established by the domains implemented before it.

**Explicitly out of scope:** a supplier-facing self-service portal (large future initiative, not this domain's Phase 5 scope); automated PO suggestion from low-stock events (logged as a UI suggestion only, not auto-created).

## 20. Future Enhancements

Supplier self-service portal (PO acknowledgment, ASN submission), automated reorder-point PO suggestions consuming `LowStockThresholdReached`, supplier performance scoring (on-time delivery rate, feeds Analytics), EDI/API integration for large suppliers.
