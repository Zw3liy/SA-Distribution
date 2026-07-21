# Technical Specification — Orders Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

The checkout and order lifecycle: cart (pre-checkout), order placement, payment orchestration, fulfillment status, returns. The single largest gap identified across Phases 1–4 — no `Order` entity exists anywhere in the current codebase.

## 2. Business Rules

- A `Cart` converts to an `Order` at checkout completion; the cart is not deleted but marked converted (kept for the customer's own order-history-adjacent reference and for abuse/fraud review), and a new empty cart is created for the session/user going forward.
- Checkout requires: a valid shipping address (Customers), sufficient stock for every line item (Inventory `reserve()`, per domain #5), and a successful tax calculation (Finance's `TaxCalculator`, per the Phase 4 §3 cycle resolution — a stateless call, not a dependency on Finance's invoicing).
- Order total = sum of line items (Catalog price at time of order — **prices are snapshotted onto `OrderItem` at checkout, never recalculated later from live Catalog data**, so a subsequent price change never retroactively alters a placed order) + tax (Finance) − any applied discount (none in this phase, no promotions engine yet).
- Order status is a strict forward state machine: `pending_payment → paid → fulfilling → shipped → delivered`, with `cancelled` reachable from `pending_payment`/`paid` only (not after fulfillment has started) and `returned` reachable only from `delivered`. No status transition skips a state or moves backward except the explicit cancel/return paths.
- Stock is reserved (Inventory) at order placement and consumed (deducted for real) only on the transition to `fulfilling` — if payment fails and the order never reaches `paid`, the reservation is released, not consumed.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `cart` / `cart_items` | existing, migrated | unchanged structure; gains `converted_to_order_id NULL` |
| `orders` | new | `id, order_number, customer_id, user_id, status, subtotal, tax_total, grand_total, shipping_address_id, billing_address_id, placed_at, updated_at` |
| `order_items` | new | `id, order_id, product_id, sku, name_snapshot, quantity, unit_price_snapshot, line_total` |
| `order_status_history` | new | `id, order_id, from_status, to_status, actor_user_id NULL, note, created_at` |
| `payments` | new (orchestration record only — see §16) | `id, order_id, method, status, amount, gateway_reference, created_at` |

## 4. Entity Definitions

`Order`, `OrderItem`, `OrderStatusHistory`, `Payment` — all new. `CartItem` (existing `Models\CartItem`, migrated unchanged).

## 5. Service Interfaces

```
interface CartServiceInterface {
    // existing methods (getCartItems, addProductToCart, updateCartItemQuantity, ...) unchanged
}
interface CheckoutServiceInterface {
    public function checkout(string $sessionId, int $customerId, int $shippingAddressId, int $billingAddressId): Order;
    // throws InsufficientStockException, InvalidAddressException, PaymentFailedException
}
interface OrderServiceInterface {
    public function findById(int $id): ?Order;
    public function transition(int $orderId, string $toStatus, ?int $actorUserId, ?string $note): void; // enforces the state machine in §2
    public function cancel(int $orderId, string $reason): void;
}
```

## 6. Repository Interfaces

```
interface CartRepositoryInterface { /* existing, unchanged */ }
interface OrderRepositoryInterface {
    public function create(array $data, array $items): Order;
    public function findById(int $id): ?Order;
    public function findByCustomer(int $customerId, int $limit, int $offset): array;
    public function updateStatus(int $id, string $status): void;
}
interface OrderStatusHistoryRepositoryInterface {
    public function record(int $orderId, string $from, string $to, ?int $actorUserId, ?string $note): void;
}
```

## 7. Controller Responsibilities

`CartController` (existing, migrated, both `page()` session-based and `api()` DB-backed methods carried over unchanged — unifying them is explicitly out of scope for this pass, see §19). `CheckoutController` (new) — multi-step or single-page checkout flow, orchestrates `CheckoutServiceInterface::checkout()`, never touches Inventory/Finance/Customers repositories directly (goes through their service interfaces only). `AdminOrderController` (new) — order list/detail/status-transition screen for staff.

## 8. Validation Rules

Checkout: shipping/billing address must belong to the checking-out customer (Customers domain ownership check, re-verified here — defense in depth); cart must be non-empty; every line item must pass an Inventory availability re-check at the moment of checkout (not just when it was added to cart — stock may have changed). Status transitions: only the exact from→to pairs in §2's state machine are accepted; anything else throws `InvalidOrderTransitionException`.

## 9. Events Published

`Orders\Events\OrderPlaced`, `OrderStatusChanged`, `OrderCancelled` — consumed by Inventory (reservation consume/release), Warehouse (fulfillment triggers on `paid`→`fulfilling`), Finance (invoice generation on `OrderPlaced`), CRM (quote-to-order linkage when applicable), Analytics.

## 10. Events Consumed

`Crm\Events\QuoteAccepted` — Orders listens for this to create a draft `Order` pre-populated from the accepted quote's line items, implementing the CRM→Orders handoff defined as a dotted (soft) dependency in the Phase 4 dependency diagram: Orders reacts to an event, it never reaches into CRM's tables directly.

## 11. Permissions Required

`orders.order.view` (staff), `orders.order.manage` (status transitions, cancellation). Customer self-service (viewing/placing their own orders) requires authentication + ownership, not a permission.

## 12. API Endpoints

Public, via API Platform: `POST /api/v1/orders/checkout`, `GET /api/v1/orders/{id}` (own orders only, or staff with permission) — this is the first genuinely transactional public endpoint in the platform and should be built with the standardized envelope from Phase 4 §4 from day one (no legacy ad hoc shape to carry forward here, unlike Cart/Quote). Internal: existing `/cart.php`, `/cart-api.php` (unchanged); new `/checkout.php`.

## 13. UI Pages

Existing: cart (unchanged). New: `/checkout` (address selection, review, place order), `/account/orders` (customer order history — depends on Customers domain's account dashboard area), `/admin/orders` (staff list/detail/status management).

## 14. Error Handling

`InsufficientStockException` (surfaced from Inventory, caught and turned into a specific checkout-page error, not a generic 500), `InvalidOrderTransitionException`, `PaymentFailedException`, `InvalidAddressException`. Checkout failures must leave the cart intact and any partial reservation released — no operation in `CheckoutServiceInterface::checkout()` may leave the system in a state where stock is reserved but no order exists and the customer has no way to complete or retry (this is called out explicitly because it's the single easiest correctness bug to introduce in a checkout flow).

## 15. Logging Requirements

Every status transition is both an `OrderStatusHistory` row (business record, queryable in the admin UI) and an `AuditLoggerInterface` call when staff-initiated (compliance record) — customer-initiated transitions (e.g., a customer-triggered cancellation, if enabled later) are logged at `info` without the audit-log duplication, since audit logging per Administration's spec is specifically for staff/system actions.

## 16. Security Requirements

`payments` in this phase is an **orchestration record only** — no card/payment-instrument data is ever stored in this table or anywhere in this domain. Actual payment processing is delegated to a third-party gateway (not yet selected/integrated — see §20); this table only stores the gateway's own reference ID and a status, keeping the platform outside PCI-DSS card-data scope entirely. Order access is strictly ownership-checked (a customer can never view another customer's order via ID guessing — verified with a dedicated test, not just code review).

## 17. Performance Requirements

Checkout must complete within a bounded time budget (target <2s end-to-end including stock check + tax calc) since it's synchronous from the customer's perspective — no long-running or externally-blocking calls inside the critical path in this phase (a real payment gateway call, once integrated, will need timeout/retry handling, flagged for that future work). Order history listing is paginated and indexed on `(customer_id, placed_at)`.

## 18. Testing Strategy

Unit: `CheckoutService` — happy path, insufficient stock, invalid address ownership, mocked Inventory/Finance/Customers service interfaces (this is the domain where mocking cross-domain service interfaces matters most, since Orders has the most inbound dependencies of any domain per Phase 4 §3). Unit: `OrderService::transition()` — every valid and invalid state pair from the machine in §2. Integration: full checkout against a real test database including an actual Inventory reservation and release. Regression: re-verify Cart's existing Phase 2/3 behavior (both `cart.php` and `cart-api.php` paths) is unchanged after migration.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** move `Models/CartItem.php`, `Repositories/CartRepository.php`, `Services/CartService.php`, `Controllers/CartController.php` → `Domains/Orders/*` unchanged (namespace-only migration, preserving both existing cart implementations exactly as Phase 3 left them). Build the entire net-new order lifecycle: tables in §3, `CheckoutService`, `OrderService`, `CheckoutController`, `AdminOrderController`, the events in §9/§10, and the customer order-history page.

**Explicitly out of scope:** unifying the session-based (`cart.php`) and DB-backed (`cart-api.php`) cart implementations — this was flagged as a risk in Phase 3 and remains a deliberate, separate business decision, not resolved by simply building checkout on top of one arbitrarily; **this phase builds checkout against the DB-backed cart path** (`cart-api.php`'s underlying `CartService`) since it's the one with real persistence, and leaves the session-only path as-is for whatever legacy UI still depends on it. Real payment gateway integration (no gateway has been selected — this is explicitly a business/vendor decision outside this technical spec's scope, not a technical gap). Promotions/discounts (no such requirement exists yet).

## 20. Future Enhancements

Real payment gateway integration (PayFast, Peach Payments, or similar SA-market gateway — vendor selection needed), promotions/discount codes, unifying the two cart implementations, guest checkout (currently implicitly requires a `Customer` record — B2C guest flow is a real future requirement worth scoping separately), order editing/amendment before fulfillment starts, partial shipments.
