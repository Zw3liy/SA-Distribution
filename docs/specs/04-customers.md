# Technical Specification — Customers Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

The business relationship: who the customer is as an account (company or individual), their addresses, preferences, and — for B2B — the account hierarchy of a parent company and its buyers. Distinct from Identity (login credentials) and from CRM (pre-sale pipeline/opportunities).

## 2. Business Rules

- A `Customer` is either `account_type = 'b2c'` (maps 1:1 to a single Identity `User`) or `'b2b'` (maps 1:many — a parent company with multiple buyer logins).
- `company_name` moves from `users` to `customers` in this migration (the Phase 4 blueprint's flagged conflation) — a compatibility read is provided (`User::getCompanyName()` proxies to the linked `Customer` for one release) so nothing else breaks mid-migration.
- Every `Address` belongs to exactly one `Customer` (re-parented from `User` in this migration) and is typed `billing`, `shipping`, or `both`.
- A B2B `Customer` may have `credit_terms` (net-30, net-60, etc.) — informational in this phase (Finance, domain #10, is what actually enforces credit limits at invoicing time; Customers only stores the term).
- Wishlist items belong to a `User`, not a `Customer` (it's a personal shopping-list feature, not a business-account feature — even a B2B buyer's wishlist is personal to them) — unchanged from Phase 3's session-based model, just relocated into this domain.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `customers` | new | `id, account_type ENUM('b2c','b2b'), company_name, parent_customer_id NULL, credit_terms, created_at, updated_at` |
| `customer_users` | new (join) | `customer_id, user_id, is_primary_contact` — supports B2B multi-user accounts |
| `addresses` | existing, altered | add `customer_id`, re-parent from `user_id`; keep `user_id` temporarily nullable for a one-release compatibility window |

## 4. Entity Definitions

`Customer` (new), `Address` (existing `Models\Address`, gains `customerId`). Wishlist remains session-array-backed (`$_SESSION['wishlist']`) — not promoted to a database table in this phase (see §19; it's flagged, not silently dropped, as a real gap already noted in the Phase 2/3 reports).

## 5. Service Interfaces

```
interface CustomerServiceInterface {
    public function createForUser(User $user, array $data): Customer;
    public function findForUser(User $user): ?Customer;
    public function addBuyer(Customer $b2bAccount, User $user): void; // B2B account growth
}
interface AddressServiceInterface {
    public function listFor(Customer $customer): array;
    public function add(Customer $customer, array $data): Address;
    public function setDefault(int $addressId, string $type): void;
}
```

## 6. Repository Interfaces

```
interface CustomerRepositoryInterface {
    public function findById(int $id): ?Customer;
    public function findByUserId(int $userId): ?Customer;
    public function create(array $data): Customer;
    public function linkUser(int $customerId, int $userId, bool $isPrimary): void;
}
interface AddressRepositoryInterface {
    // existing methods, re-scoped from user_id to customer_id
    public function findByCustomerId(int $customerId): array;
    public function create(array $data): Address;
}
```

## 7. Controller Responsibilities

`AccountController` (existing, migrated) — "My Account" dashboard/edit, now reads/writes through `CustomerService` for company/address data and `UserService`/`AuthService` (Identity) for login/security data — a controller that legitimately spans two domains' services, which is fine (controllers may depend on multiple domains' services; it's *services* that must not cross-call each other's repositories directly). `WishlistController` (existing, migrated, unchanged behavior).

## 8. Validation Rules

`company_name` required for `account_type='b2b'`, forbidden (null) for `'b2c'`. Address: standard required-field validation (existing, unchanged) plus a South African postal-code format check (new — the storefront is SA-specific per the standing project brief, and this was previously unvalidated).

## 9. Events Published

`Customers\Events\CustomerCreated`, `BuyerAddedToAccount`, `AddressAdded` — consumed by CRM (new customer → potential lead-to-customer conversion tracking) and Analytics.

## 10. Events Consumed

`Identity\Events\UserRegistered` — Customers listens for this to auto-create a `b2c` `Customer` record for every new self-registering user (B2B accounts are created explicitly by staff, not auto-created from registration).

## 11. Permissions Required

`customers.account.view` (staff), `customers.account.edit`, `customers.b2b.manage` (add/remove buyers from a B2B account). Self-service (a logged-in user managing their own profile/addresses) requires no permission, only authentication + ownership check.

## 12. API Endpoints

Public, via API Platform: `GET /api/v1/customers/me`, `GET /api/v1/customers/me/addresses` (token-scoped to the authenticated customer only — no cross-customer access, ever, regardless of token scope). Internal: existing `/account-dashboard.php`, `/account-edit.php`, `/wishlist.php` (unchanged).

## 13. UI Pages

Existing: account dashboard, account edit, wishlist (unchanged). New: `/admin/customers` (staff-facing customer list/detail, including B2B account hierarchy view).

## 14. Error Handling

`CustomerNotFoundException`, `DuplicateBuyerException` (adding a user to a B2B account they're already on), `InvalidAccountTypeException`.

## 15. Logging Requirements

B2B account changes (buyer added/removed, credit terms changed) are audit-logged (Administration) since they have financial/access implications. Address changes are logged at `info` level only (lower sensitivity).

## 16. Security Requirements

A user may only view/edit their own `Customer` record and its addresses, or — for B2B — records they're linked to via `customer_users`, enforced at the service layer (not just the controller) so no future controller can accidentally skip the check. Staff access to any customer record requires `customers.account.view`.

## 17. Performance Requirements

`findByUserId` (called on nearly every authenticated request to resolve "which customer is this user acting for") must be a single indexed lookup — index `customer_users.user_id`.

## 18. Testing Strategy

Unit: `CustomerService` account-type validation, B2B buyer-linking rules. Integration: `CustomerRepository`/`AddressRepository` against a real test database, including the `user_id`→`customer_id` re-parenting migration itself (a dedicated migration test, since this is a genuine schema migration of existing data, not a fresh table).

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** create `customers`, `customer_users` tables; migrate `Models/Address.php` → `Domains/Customers/Models/`, add `Customer` model/repository/service; write and run a data migration that (a) creates one `b2c` `Customer` row per existing `users` row, copying `company_name`, (b) links each via `customer_users`, (c) re-parents existing `addresses` rows from `user_id` to the new `customer_id`; move `Controllers/AccountController.php`, `Controllers/WishlistController.php` → `Domains/Customers/Controllers/`. Keep `users.company_name` and `addresses.user_id` in place but deprecated (read-only compatibility) for this release rather than dropping them immediately — a hard cutover on live customer data is exactly the kind of change that needs a verified, reversible path, not a big-bang drop.

**Explicitly out of scope:** promoting wishlist from session storage to a database table (real gap, deliberately deferred — no B2B/session-portability requirement has been specified yet to justify the schema work); customer segmentation/tagging; self-service B2B buyer invitation flow (staff-managed only in this phase).

## 20. Future Enhancements

Database-backed wishlist (multi-device persistence), self-service B2B buyer invitations, customer segments (feeds Catalog's future tiered pricing and Analytics), credit-limit enforcement integration with Finance, customer-facing order history (depends on Orders domain existing).
