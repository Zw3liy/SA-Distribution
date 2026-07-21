# Enterprise Technical Specifications — Index

**Phase:** 5 — Enterprise Technical Specifications
**Date:** 21 July 2026
**Status:** Complete. Governs all Phase 5+ implementation work. Supersedes nothing in `DOMAIN_ARCHITECTURE_BLUEPRINT.md` (Phase 4) — this document goes one level deeper, per domain, into the 20 dimensions every module must address before code is written.

## How to use this document set

Each file in `docs/specs/` is the technical specification for one of the 13 business domains defined in the Phase 4 blueprint. Every domain file follows the same 20-section template, in this order:

1. Responsibilities
2. Business Rules
3. Database Tables
4. Entity Definitions
5. Service Interfaces
6. Repository Interfaces
7. Controller Responsibilities
8. Validation Rules
9. Events Published
10. Events Consumed
11. Permissions Required
12. API Endpoints
13. UI Pages
14. Error Handling
15. Logging Requirements
16. Security Requirements
17. Performance Requirements
18. Testing Strategy
19. Migration Strategy
20. Future Enhancements

Section 19 ("Migration Strategy") in each file is the authoritative, scoped definition of exactly what gets built when that domain is implemented — it is deliberately narrower than the domain's full long-term vision (described in sections 1–18 and 20), because building everything a domain could ever need in one pass would violate the standing "never write quick fixes, but also never over-build past what's needed" balance. Implementation work should treat section 19 of each file as its acceptance criteria.

## Domain index

| # | Domain | File | Depends on (Phase 4 §3) |
|---|---|---|---|
| 1 | Identity | [01-identity.md](01-identity.md) | Platform only |
| 2 | Administration | [02-administration.md](02-administration.md) | Identity |
| 3 | Catalog / PIM | [03-catalog.md](03-catalog.md) | Identity, Administration |
| 4 | Customers | [04-customers.md](04-customers.md) | Identity |
| 5 | Inventory | [05-inventory.md](05-inventory.md) | Catalog |
| 6 | Orders | [06-orders.md](06-orders.md) | Catalog, Inventory, Customers, Identity, Finance (TaxCalculator) |
| 7 | Warehouse | [07-warehouse.md](07-warehouse.md) | Inventory, Orders |
| 8 | Suppliers | [08-suppliers.md](08-suppliers.md) | Catalog |
| 9 | CRM | [09-crm.md](09-crm.md) | Customers, Identity, Orders |
| 10 | Finance | [10-finance.md](10-finance.md) | Orders, Customers, Suppliers |
| 11 | API Platform | [11-api-platform.md](11-api-platform.md) | Identity, façades Catalog/Orders/CRM/Customers |
| 12 | Analytics | [12-analytics.md](12-analytics.md) | (reads) Catalog, Orders, CRM, Finance |
| 13 | AI | [13-ai.md](13-ai.md) | (reads) Catalog, Orders, CRM, Analytics |

## Cross-domain conventions (apply to every spec)

- **Namespacing:** `App\Domains\{Domain}\{Models|Repositories|Services|Controllers}`, per the Phase 4 folder structure. Shared kernel stays in `App\Platform\*`.
- **Events:** a lightweight in-process event dispatcher lives in `Platform` (net-new in this phase — does not exist yet). Domains publish plain event objects (`App\Domains\{Domain}\Events\{EventName}`); the Analytics and AI domains are the primary cross-cutting subscribers, per the read-only dependency rule in the Phase 4 dependency diagram. Events are synchronous, in-process, best-effort in this phase — a durable queue is a Future Enhancement, not a Phase 5 requirement.
- **Permissions:** every domain's permissions are rows in the existing `permissions` table (Identity-owned), named `{domain}.{action}` (e.g. `catalog.product.edit`, `orders.order.cancel`). No domain invents its own authorization mechanism.
- **API endpoints:** internal AJAX endpoints (existing pattern) are documented per-domain where they exist; public `/api/v1/...` endpoints are only specified for domains actually fronted by API Platform in this phase (see Phase 4 §4 — not every domain gets a public API immediately).
- **Error handling:** every service throws typed, domain-specific exceptions (e.g. `App\Domains\Identity\Exceptions\InvalidCredentialsException`) rather than generic `Exception`/`InvalidArgumentException` where the current code (Phase 3) does the latter — controllers catch these and map to the correct HTTP status, and the Kernel's existing last-resort `Throwable` handler remains the final safety net, unchanged from Phase 3.
- **Logging:** all domains use the existing `App\Platform\Logging\Logger` (Phase 3) — no per-domain logging framework. Structured context (`domain`, `action`, `actor_id`, `entity_id`) is required on every log call, which is a new convention as of Phase 5 (Phase 3's logger calls did not consistently include this).
- **Testing:** PHPUnit, introduced in this phase (Phase 3 flagged its absence as a risk). Directory convention: `tests/Unit/{Domain}/...`, `tests/Integration/{Domain}/...`. Service-layer unit tests are mandatory per domain; repository/integration tests against a real database are mandatory for anything with non-trivial SQL.
