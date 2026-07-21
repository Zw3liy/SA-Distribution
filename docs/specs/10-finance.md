# Technical Specification — Finance Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

Invoicing, payment records, tax/VAT calculation, credit notes, and the integration boundary for a future accounting/ERP system. Owns "what is owed and what's been paid" — not payment gateway orchestration (Orders owns the `Payment` orchestration record; Finance owns the `Invoice` it settles against).

## 2. Business Rules

- **Resolves the Phase 4-flagged Orders↔Finance cycle:** `TaxCalculator` is a stateless service with zero dependency on `Order` — it takes a list of `(product, quantity, unit_price)` tuples and a tax jurisdiction and returns amounts. Orders calls it during checkout. `InvoiceService`, separately, depends on a completed `Order` to generate an `Invoice` from it. These are two distinct services in this domain; nothing in `TaxCalculator` ever references `Order`.
- VAT rate defaults to South Africa's standard rate (15%, matching the existing verified `QuoteService` math from Phase 2 — R24,999 → R28,748.85), sourced from `tax_rates`, not hardcoded, so a future rate change is a data update, not a deploy.
- An `Invoice` is generated automatically on `Orders\Events\OrderPlaced` (one invoice per order in this phase — split/partial invoicing is a Future Enhancement).
- A `Payment` (Orders' orchestration record) reaching a `succeeded` gateway status triggers Finance to mark the corresponding `Invoice` as `paid` — Finance listens for this via an event, it does not poll or directly read Orders' `payments` table.
- `CreditNote` reduces an invoice's outstanding balance and must reference a reason (return, goodwill, pricing error) — never a bare number with no justification.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `tax_rates` | new | `id, region, rate, effective_from, effective_to NULL` |
| `invoices` | new | `id, order_id, invoice_number, customer_id, status ENUM('draft','issued','paid','overdue','void'), subtotal, tax_total, grand_total, due_date, issued_at` |
| `credit_notes` | new | `id, invoice_id, amount, reason, issued_by_user_id, issued_at` |

## 4. Entity Definitions

`TaxRate`, `Invoice`, `CreditNote` — all new. The VAT-calculation *logic* itself is not a new invention — it's extracted, unchanged, from the existing `QuoteService` (Phase 2/3's verified 15% VAT math) into this domain's `TaxCalculator`.

## 5. Service Interfaces

```
interface TaxCalculatorInterface { // stateless, no Order dependency — see §2
    public function calculate(array $lineItems, string $region = 'ZA'): TaxCalculationResult; // { subtotal, taxTotal, grandTotal }
}
interface InvoiceServiceInterface {
    public function generateFor(Order $order): Invoice;
    public function markPaid(int $invoiceId): void;
    public function issueCreditNote(int $invoiceId, float $amount, string $reason, int $actorUserId): CreditNote;
}
```

## 6. Repository Interfaces

`TaxRateRepositoryInterface`, `InvoiceRepositoryInterface`, `CreditNoteRepositoryInterface` — standard CRUD shape, consistent with prior domains.

## 7. Controller Responsibilities

`AdminInvoiceController` — invoice list/detail, manual credit-note issuance, staff-only. No customer-facing controller beyond a read-only invoice view mounted under the Customers domain's account area (Finance supplies the data via its service interface; the account-area page itself belongs to Customers, matching the same cross-domain-controller pattern already used for `AccountController` in that spec).

## 8. Validation Rules

`TaxCalculator::calculate()` rejects an empty line-item list or any negative unit price. `issueCreditNote()` rejects an amount exceeding the invoice's remaining outstanding balance.

## 9. Events Published

`Finance\Events\InvoiceGenerated`, `InvoicePaid`, `CreditNoteIssued` — consumed by Analytics and, eventually, an external accounting-system sync (per the standing project instructions' "ERP Integration" goal — the integration boundary itself, not a specific ERP connector, per §20).

## 10. Events Consumed

`Orders\Events\OrderPlaced` (generates the invoice), a payment-succeeded signal from Orders' payment orchestration (marks the invoice paid — the exact event name depends on how Orders' `Payment` domain finalizes gateway integration, flagged as a dependency to confirm when Orders' payment gateway is actually selected, per that domain's §20).

## 11. Permissions Required

`finance.invoice.view`, `finance.invoice.manage`, `finance.credit_note.issue` (kept distinct and more sensitive than general invoice management, same pattern as other domains' "the action that commits money" permissions).

## 12. API Endpoints

Public, via API Platform (limited): `GET /api/v1/customers/me/invoices` (own invoices only). No write endpoints exposed publicly in this phase.

## 13. UI Pages

`/admin/finance/invoices`, `/admin/finance/tax-rates` — new, staff-only. `/account/invoices` — new, customer-facing, mounted under Customers' account area.

## 14. Error Handling

`InvalidTaxRegionException` (no matching `tax_rates` row for the requested region — fails loudly rather than silently defaulting to 0% tax, since that would be a serious financial-correctness bug), `CreditNoteExceedsBalanceException`, `InvoiceNotFoundException`.

## 15. Logging Requirements

Every invoice generation, payment-marked, and credit-note issuance is audit-logged — this entire domain is financial-record-relevant by nature, matching Warehouse's blanket-audit-logging posture for a different reason (custody of money vs. custody of stock).

## 16. Security Requirements

`TaxCalculator` must never be reachable by an unauthenticated or unscoped caller with an attacker-controlled region/rate override — region is validated against a known allowlist (`tax_rates.region` values), not accepted as free text passed through to a query. Credit note issuance (`finance.credit_note.issue`) is a high-sensitivity permission by design.

## 17. Performance Requirements

`TaxCalculator::calculate()` is called synchronously during Orders' checkout critical path (per Orders §17's <2s budget) — must be a pure in-memory calculation against a small, cacheable `tax_rates` lookup, never a slow query or external call.

## 18. Testing Strategy

Unit: `TaxCalculator` — the exact Phase 2-verified VAT math (R24,999 → R28,748.85) re-asserted as a regression test in its new home, plus edge cases (zero-amount, multiple line items, unknown region). Unit: `InvoiceService` credit-note balance validation. Integration: `OrderPlaced` → `InvoiceGenerated` event flow against a real test database.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** extract the VAT calculation logic currently embedded in `Domains\Crm\Services\QuoteService` into the new, standalone `TaxCalculator` (both `QuoteService` and, later, Orders' `CheckoutService` call this same shared implementation going forward — a genuine deduplication, not a behavior change, since the extracted logic is byte-for-byte the same verified math); build `tax_rates`, `invoices`, `credit_notes` tables and their services; build the two admin screens and the customer invoice view.

**Explicitly out of scope:** real accounting-system/ERP sync (integration boundary is defined — an event stream Finance publishes — but no actual connector is built, since no specific ERP has been selected); split/partial invoicing; multi-currency (ZAR only, matching the existing platform's single-currency assumption throughout).

## 20. Future Enhancements

ERP/accounting-system connector (Xero, Sage, or similar — vendor decision needed), multi-currency support, split/partial invoicing, automated dunning (overdue-invoice reminder sequences, candidate CRM/AI integration), customer-facing online invoice payment.
