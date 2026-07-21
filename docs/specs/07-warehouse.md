# Technical Specification — Warehouse Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

The physical execution of inventory movement: receiving, picking, packing, shipping, and inter-location transfers. Inventory (domain #5) answers "how many, where"; Warehouse answers "who does what, physically, to make that number true."

## 2. Business Rules

- A `PickList` is generated automatically when an `Order` transitions to `paid` (consuming the `Orders\Events\OrderStatusChanged` event, per Phase 4 §3's `Orders → Warehouse` direction being read via events, not a direct call from Orders into Warehouse).
- Picking a `PickList` line consumes the corresponding `StockReservation` in Inventory (calls `InventoryServiceInterface::consumeReservation()`) — Warehouse is a caller of Inventory's service interface, never a direct writer of `inventory_items`.
- A `Shipment` cannot be created until all lines on its `PickList` are marked picked and packed.
- Inter-warehouse `StockTransfer` requires a `StockMovement` (negative) at the source location and one (positive) at the destination, both referencing the same transfer ID — an atomic pair, never one without the other.
- `GoodsReceipt` (inbound from a Supplier's `PurchaseOrder`) increases `quantity_on_hand` at the receiving warehouse via `InventoryServiceInterface::adjust()`, with `reason = 'goods_receipt'` and `reference` pointing at the `PurchaseOrder`.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `warehouses` | shared with Inventory (owned there, extended here) | add `address_id`, `zone_count` or similar operational fields as needed |
| `pick_lists` | new | `id, order_id, warehouse_id, status, created_at` |
| `pick_list_items` | new | `id, pick_list_id, product_id, quantity, picked_at NULL` |
| `packing_slips` | new | `id, pick_list_id, packed_by_user_id, packed_at` |
| `goods_receipts` | new | `id, purchase_order_id, warehouse_id, received_by_user_id, received_at` |
| `goods_receipt_items` | new | `id, goods_receipt_id, product_id, quantity` |
| `stock_transfers` | new | `id, from_warehouse_id, to_warehouse_id, status, initiated_by_user_id, created_at` |
| `shipments` | new | `id, order_id, pick_list_id, carrier, tracking_number, shipped_at` |

## 4. Entity Definitions

`PickList`, `PickListItem`, `PackingSlip`, `GoodsReceipt`, `StockTransfer`, `Shipment` — all new. `Warehouse` is jointly defined with Inventory (Inventory owns the minimal version needed to scope stock; Warehouse extends it with operational fields) rather than duplicated as two separate entities — a deliberate exception to strict one-table-one-domain-owner, justified because the two domains' needs for this specific entity are additive, not conflicting.

## 5. Service Interfaces

```
interface PickListServiceInterface {
    public function generateFor(int $orderId): PickList; // triggered by OrderStatusChanged event
    public function markPicked(int $pickListItemId, int $actorUserId): void;
}
interface ShipmentServiceInterface {
    public function createFor(int $pickListId, string $carrier, string $trackingNumber): Shipment;
}
interface GoodsReceiptServiceInterface {
    public function receive(int $purchaseOrderId, int $warehouseId, array $items, int $actorUserId): GoodsReceipt;
}
interface StockTransferServiceInterface {
    public function initiate(int $fromWarehouseId, int $toWarehouseId, array $items, int $actorUserId): StockTransfer;
    public function complete(int $transferId): void;
}
```

## 6. Repository Interfaces

Standard CRUD-shaped repository interfaces for each entity in §4 (`PickListRepositoryInterface`, `ShipmentRepositoryInterface`, `GoodsReceiptRepositoryInterface`, `StockTransferRepositoryInterface`), each following the same `findById`/`create`/`updateStatus` shape already established by Orders' repositories — no new repository pattern introduced.

## 7. Controller Responsibilities

`AdminWarehouseController` — pick-list queue, packing confirmation, shipment creation, goods-receipt entry, transfer initiation/completion — all staff-facing, permission-gated, no customer-facing controller in this domain (customers see `Shipment` tracking info via Orders' order-detail page, not directly via Warehouse).

## 8. Validation Rules

`markPicked` rejects if the pick list is already fully picked/cancelled. `receive()` quantities must be > 0 and, if the receipt is against a `PurchaseOrder`, should not (by default) exceed the ordered quantity — over-receipt is allowed only with an explicit staff override flag, logged distinctly.

## 9. Events Published

`Warehouse\Events\PickListGenerated`, `OrderPicked`, `OrderShipped`, `GoodsReceived`, `StockTransferCompleted` — `OrderShipped` is consumed by Orders (transitions the order to `shipped`, completing the loop back per the dotted/event-driven relationship, not a direct call).

## 10. Events Consumed

`Orders\Events\OrderStatusChanged` (specifically the `paid`→`fulfilling` transition triggers `PickListServiceInterface::generateFor()`).

## 11. Permissions Required

`warehouse.pick.manage`, `warehouse.ship.manage`, `warehouse.receive.manage`, `warehouse.transfer.manage` — deliberately split into separate permissions per operational role (a picker doesn't necessarily need receiving access), matching how warehouse staff roles are typically segmented in practice.

## 12. API Endpoints

None public in this phase — Warehouse is an internal operations domain with no partner-facing surface identified yet (a future carrier-integration webhook receiver, e.g. for tracking updates, is a Future Enhancement, not a Phase 5 requirement).

## 13. UI Pages

`/admin/warehouse/pick-lists`, `/admin/warehouse/receiving`, `/admin/warehouse/transfers` — all net-new, all staff-only.

## 14. Error Handling

`PickListAlreadyCompleteException`, `OverReceiptException` (when the override flag isn't set), `TransferAlreadyCompletedException`. A failure partway through `StockTransferServiceInterface::complete()` (the atomic pair in §2) must not leave one leg of the transfer applied without the other — wrap in a database transaction, called out explicitly since it's the most correctness-critical operation in this domain.

## 15. Logging Requirements

Every pick/pack/receive/transfer action is logged with the acting staff user — this is inherently an audit-relevant domain (physical stock custody), so every write path calls `AuditLoggerInterface::record()`, not just the sensitive subset some other domains reserve it for.

## 16. Security Requirements

All Warehouse actions require staff `account_kind` (Identity) plus the specific operational permission (§11) — no customer-facing access surface exists at all in this domain, simplifying its security posture relative to customer-facing domains.

## 17. Performance Requirements

Pick-list generation (triggered synchronously by the order-status-change event in this phase, per the "events are synchronous, in-process" convention in the index) should remain fast (single order's line items, not a batch) — batch/overnight wave-picking optimization is a Future Enhancement once real order volume justifies it.

## 18. Testing Strategy

Unit: `StockTransferService` atomicity (mocked repository asserting both legs are called or neither is, on a simulated failure). Integration: `PickListService::generateFor()` against a real test database, asserting correct line-item generation from a real `Order`+`OrderItem` fixture and correct `StockReservation` consumption via Inventory's real service.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** build all tables and services in §3–6; wire the `OrderStatusChanged`/`OrderShipped` event flow end-to-end (this is the first domain in the implementation order where the event system gets real, non-trivial bidirectional use with Orders — a good integration test of the event dispatcher itself, not just this domain's logic). Build the three admin screens in §13.

**Explicitly out of scope:** carrier API integration for live tracking updates (no carrier has been selected — vendor decision, same caveat as Orders' payment gateway); wave-picking/batch optimization; barcode-scanner hardware integration (mentioned in no requirement yet, flagged only as a plausible future need given the domain).

## 20. Future Enhancements

Carrier API integration (tracking webhooks), barcode/RFID-assisted picking, wave/batch picking optimization, warehouse capacity/zone-utilization reporting (feeds Analytics), automated re-order suggestions crossing into Suppliers when goods-receipt patterns indicate a reliable reorder point.
