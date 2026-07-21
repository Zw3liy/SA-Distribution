# Technical Specification — CRM Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

The sales pipeline: leads, opportunities, activity/interaction history, and — the key existing-code inheritance — the Quote/RFQ system. Owns the pre-sale negotiation lifecycle; hands off to Orders the moment a quote is accepted.

## 2. Business Rules

- **Resolves the Phase 3-flagged inconsistency:** the current two disconnected quote paths (`quote-request.php` session-only capture vs. `quote-api.php` DB-backed submission) are unified in this domain onto the single DB-backed `Quote`/`QuoteItem` model. The session-only capture form becomes a lightweight `Lead` creation (a product-interest signal, not a real quote) rather than a parallel, disconnected quote record — this resolves the inconsistency by giving the session-based form a *correct*, distinct purpose (lead capture) instead of trying to force it to be a second quote-submission path.
- A `Quote` moves through `draft → sent → accepted|rejected|expired`. Acceptance publishes `QuoteAccepted`, which Orders consumes to create a draft order (per Orders §10) — CRM never creates an `Order` row itself, preserving the dotted/event-only dependency from Phase 4 §3.
- An `Opportunity` is qualified from a `Lead` (or created directly by staff for an existing `Customer`) and tracks a pipeline `stage` (new, qualified, quoted, won, lost) — `Quote` records attach to an `Opportunity`, not the reverse, since one opportunity may go through multiple quote revisions.
- `Activity` entries (calls, emails, notes) are append-only, timestamped, attributed to a staff `actor_user_id` — never edited after creation (correcting a mistaken activity note means adding a new one, not silently rewriting history, matching the audit-log-adjacent nature of a sales activity trail).

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `quotes` | existing, altered | add `opportunity_id NULL`, `status` values extended to include `accepted`/`rejected`/`expired` if not already present |
| `quote_items` | existing, unchanged | |
| `leads` | new | `id, source, product_slug NULL, name, company, email, phone, message, status ENUM('new','contacted','qualified','disqualified'), created_at` |
| `opportunities` | new | `id, lead_id NULL, customer_id NULL, owner_user_id, stage, estimated_value, created_at, updated_at` |
| `activities` | new | `id, opportunity_id NULL, customer_id NULL, actor_user_id, type, note, created_at` |

## 4. Entity Definitions

`Quote`, `QuoteItem` (existing `Models\Quote`/`QuoteItem`, migrated, `Quote` gains `opportunityId`). `Lead`, `Opportunity`, `Activity` — new.

## 5. Service Interfaces

```
interface QuoteServiceInterface {
    // existing methods unchanged: createQuote, getQuoteHistory
    public function accept(int $quoteId): void;  // new — publishes QuoteAccepted
    public function reject(int $quoteId, string $reason): void; // new
}
interface LeadServiceInterface {
    public function captureFromProductInterest(array $formData): Lead; // replaces the old session-only quote-request path
    public function qualify(int $leadId, int $ownerUserId): Opportunity;
}
interface OpportunityServiceInterface {
    public function advance(int $opportunityId, string $toStage): void;
    public function logActivity(int $opportunityId, int $actorUserId, string $type, string $note): void;
}
```

## 6. Repository Interfaces

`QuoteRepositoryInterface` (existing, unchanged), `LeadRepositoryInterface`, `OpportunityRepositoryInterface`, `ActivityRepositoryInterface` — new, standard CRUD shape.

## 7. Controller Responsibilities

`QuoteController` (existing, migrated) — `api()` (DB-backed submission, unchanged) retained; `sessionRequest()` is **replaced**, not just moved — its route now calls `LeadServiceInterface::captureFromProductInterest()` instead of writing to `$_SESSION['quote_requests']`, which is the concrete fix for the Phase 3-flagged inconsistency. `AdminOpportunityController` (new) — pipeline board/list, activity logging, quote-accept/reject actions.

## 8. Validation Rules

Lead capture: name/email/product reference required (same fields as the old session form, now validated the same way but persisted properly). Quote acceptance: only a `sent`-status quote can be accepted or rejected (state-machine guard, same discipline as Orders/Suppliers).

## 9. Events Published

`Crm\Events\LeadCaptured`, `OpportunityStageChanged`, `QuoteAccepted`, `QuoteRejected` — `QuoteAccepted` is the critical cross-domain event consumed by Orders (§10 of that spec).

## 10. Events Consumed

`Customers\Events\CustomerCreated` — CRM listens to auto-link any existing `Lead` with a matching email to the newly created `Customer` record, closing the lead-to-customer loop without CRM needing to query Customers' tables directly.

## 11. Permissions Required

`crm.lead.view`, `crm.lead.qualify`, `crm.opportunity.manage`, `crm.quote.accept` (kept distinct from general quote management since accepting a quote has a real downstream effect — an order gets created).

## 12. API Endpoints

No public API in this phase (CRM is an internal sales tool). Internal: existing `/quote-request.php` (behavior changed per §7, route unchanged), `/quote-api.php` (unchanged).

## 13. UI Pages

Existing: none dedicated (quote submission was form-embedded in product-details). New: `/admin/crm/leads`, `/admin/crm/opportunities` (pipeline view), `/admin/crm/opportunities/{id}` (detail + activity log + quotes).

## 14. Error Handling

`InvalidQuoteTransitionException`, `LeadNotFoundException`. The lead-capture replacement (§7) must fail as gracefully as the old session-based form did — if `LeadServiceInterface::captureFromProductInterest()` throws, the controller still redirects back to the product page with a flash message (matching the existing user-facing behavior exactly), even though the underlying implementation changed completely.

## 15. Logging Requirements

Opportunity stage changes and quote accept/reject are audit-logged (staff-attributed, business-significant). Lead capture (customer-initiated, high-volume, low-individual-significance) is logged at `info` only, not audit-logged, to avoid flooding the audit log with routine form submissions.

## 16. Security Requirements

Quote acceptance (`crm.quote.accept`) should be treated as carrying real financial/operational weight in role design (it triggers order creation) — same recommendation pattern as Suppliers' PO-send permission. Lead-capture endpoint remains public/unauthenticated (matching the existing product-interest form's accessibility) but should be rate-limited per IP to prevent spam, a gap that existed in the original session-based form too and is worth closing here rather than carrying forward silently.

## 17. Performance Requirements

No unusual requirements beyond standard indexed lookups (`opportunities.owner_user_id`, `activities.opportunity_id`) for the pipeline board view to stay fast as data grows.

## 18. Testing Strategy

Unit: `QuoteService::accept()`/`reject()` state-machine guards. Unit: `LeadService::captureFromProductInterest()` — this is the domain's highest-risk regression point (replacing working session-based behavior), so its test explicitly asserts the resulting user-facing redirect/flash-message behavior matches Phase 3's original `sessionRequest()` behavior byte-for-byte, even though the underlying persistence completely changed. Integration: full lead→opportunity→quote→accept→`QuoteAccepted`-event flow against a real test database.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** move `Models/Quote.php`, `QuoteItem.php`, `Repositories/QuoteRepository.php`, `Services/QuoteService.php`, `Controllers/QuoteController.php` → `Domains/Crm/*`; add `accept()`/`reject()` to `QuoteService`; build `leads`, `opportunities`, `activities` tables and their services/repositories; **replace** `QuoteController::sessionRequest()`'s implementation to call the new `LeadService` (this is the one deliberate behavior change in this migration, explicitly called for by resolving the Phase 3-flagged inconsistency — not an accidental scope change); build the two admin screens in §13.

**Explicitly out of scope:** a full drag-and-drop pipeline board UI (a simple filterable list view is sufficient for this phase — richer UI is a Future Enhancement); lead-scoring/qualification automation.

## 20. Future Enhancements

Lead scoring/auto-qualification (candidate AI-domain integration), email/calendar integration for activity auto-logging, a richer pipeline board UI, quote PDF generation and e-signature (depends on Finance's invoice-PDF tooling existing first, for shared document infrastructure).
