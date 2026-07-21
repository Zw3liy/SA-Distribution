# Domain Implementation Completion Report — Customers

**Phase:** 5 — Enterprise Technical Specifications & Implementation
**Domain:** 4 of 13 — Customers (Accounts, B2B/B2C, Addresses)
**Date:** 21 July 2026
**Spec:** [docs/specs/04-customers.md](../specs/04-customers.md) §19 (Migration Strategy) is the authoritative scope for this work.

## What was completed

- **New `customers` bounded context.** `Customer`/`Address` models, `CustomerRepository(Interface)`, `AddressRepository(Interface)`, `CustomerService(Interface)`, `AddressService(Interface)`, `AccountController`, `AdminCustomerController`, and `WishlistController` (namespace move only) under `src/Domains/Customers/*`, bound by interface in the Kernel. `AccountController` and `Address` were moved from `src/Controllers`/`src/Models` (old files deleted, zero stale references confirmed via grep) and substantially extended; `WishlistController` moved with no logic changes.
- **Account-type model** (`Customer::$accountType` b2c/b2b, `$companyName`, `$parentCustomerId`, `$creditTerms`). `CustomerService::createForUser()` enforces the core business rule: `company_name` is required for b2b, forced null for b2c.
- **Multi-buyer B2B accounts.** `customer_users` link table (customer_id, user_id, is_primary_contact), indexed on `user_id` per §17 so `findByUserId()` is a single indexed lookup. `CustomerService::addBuyer()` rejects adding buyers to a b2c account and rejects duplicate links (`InvalidAccountTypeException`, `DuplicateBuyerException`).
- **Address re-parenting.** `addresses.user_id` made nullable and kept as a documented, deprecated compatibility column; `addresses.customer_id` added as the new owning key; `type` enum extended with `'both'`. `AddressService` validates required fields, a South African 4-digit postal code format (previously unvalidated — addresses were never wired into any controller before this domain), and clears the correct prior default (`clearDefaultForType` treats `'both'`-typed addresses as covering both billing and shipping).
- **Self-registration auto-creates a Customer.** `AuthController::handleRegister()` now calls `CustomerServiceInterface::createForUser($user, ['account_type' => 'b2c'])` after a successful registration — implemented as a direct, synchronous controller-level call (this platform has no event bus anywhere; same pattern as Catalog's undispatched event DTOs) rather than a real "UserRegistered" subscription. B2B accounts remain staff-managed only, created via the admin portal, never through self-registration.
- **`AdminCustomerController`** — staff-facing `/admin/customers` list and `/admin/customers/view?id=` detail (query-param routing, documented in the controller, since the hand-rolled Router only supports exact-path matching with no path parameters), including the B2B buyer-add form. Defense in depth per the established convention: the Kernel's `/admin/*` guard enforces `account_kind=staff`; this controller additionally enforces `customers.account.view` for viewing and `customers.b2b.manage` for adding buyers. Every buyer-add writes an audit log entry.
- **Self-service address UI** added to `account-edit.php` — list of current addresses with default-status badges, a "set as default" action per address, and an add-address form with SA postal code validation surfaced as an inline error.
- **Permission catalog extended** — `customers.account.view`, `customers.account.edit`, `customers.b2b.manage` seeded and granted to the baseline `staff` role, following the exact idempotent seeding pattern established in the Catalog migration.
- **Historical data backfill** — `database/migrations/2026_07_21_customers_backfill.php`, a standalone idempotent PHP script (not part of application runtime) that creates a b2c Customer for every pre-existing `account_kind='customer'` user with no linked Customer yet, links it as primary contact, and re-parents that user's existing addresses. Chosen over a pure SQL `INSERT...SELECT` because linking each new Customer's own auto-increment id back to its source user cannot be done in one bulk statement without a database-specific trick; run inside a transaction with rollback on failure. Run against the live environment: 2 customer accounts created, 0 addresses re-parented (no pre-existing addresses existed).
- **Tests**: 17 new unit tests (`CustomerServiceTest`, `AddressServiceTest` — account-type rules, buyer-linking rules, SA postal code validation, default-clearing behavior) and 12 new integration tests (`CustomerRepositoryTest`, `AddressRepositoryTest`, plus a dedicated `CustomersBackfillTest` per spec §18 proving the user_id→customer_id re-parenting and the backfill's idempotency) — all passing, alongside the full pre-existing suite.

## Why it was done this way

`users.company_name` was deliberately left untouched (§2's compatibility requirement) rather than migrated or removed in the same pass — it remains the Identity domain's own field, still read by the (unchanged) profile-update path, while `Customer.company_name` becomes the new parallel source of truth going forward. This avoided any behavioral risk to Identity's already-verified read paths. Ownership enforcement for self-service actions (§16) is achieved structurally rather than via an explicit actor parameter on every service method: self-service controller code always resolves the acting `Customer` via `CustomerService::findForUser($currentAuthenticatedUser)`, never accepting a client-supplied customer id, while staff-facing paths use the existing `UserServiceInterface::hasPermission()` check instead (staff aren't the record's owner).

## Files affected

18 changes: 3 deletions (`src/Controllers/AccountController.php`, `src/Controllers/WishlistController.php`, `src/Models/Address.php`, superseded by their `Domains/Customers` equivalents), 2 modifications (`components/admin-nav.php` — new Customers link; `public/css/styles.css` — badges/address-list/inline-form styles), 1 modification (`views/pages/account-edit.php` — new addresses section), 4 modifications outside the domain for wiring (`src/Http/Kernel.php`, `src/Domains/Identity/Controllers/AuthController.php`, `src/Domains/Identity/Services/UserService.php`/`UserServiceInterface.php` — new `getUserByEmail()` retrofit), 1 new domain directory (`src/Domains/Customers/*`, 18 files), 1 migration + 1 backfill script, 2 new admin views, 3 new test files.

## Verification results

| Check | Result |
|---|---|
| `php -l` across `src/`, `views/`, `components/`, `public/`, `tests/`, `database/` | Clean |
| No stale references to old `App\Controllers\AccountController`/`WishlistController`/`App\Models\Address` | Confirmed clean (grep) |
| Migration applied to a real MariaDB instance | Clean — `customers`, `customer_users` created; `addresses.customer_id` added, `user_id` made nullable, `type` enum extended with `'both'`; 3 permissions + staff-role grants seeded |
| Backfill script (live run) | 2 customer accounts created, 0 addresses re-parented (idempotent, transactional) |
| Unit tests (`CustomerService`, `AddressService`) | 17/17 passing |
| Integration tests (`CustomerRepository`, `AddressRepository`, `CustomersBackfillTest`) | 12/12 passing |
| Full project integration suite (Identity + Administration + Catalog + Customers) | 23/23 passing |
| Full project unit suite | 53/53 passing |
| Self-registration via `/register.php` | Confirmed — new user immediately has a linked b2c Customer row |
| Self-service add address via `/account-edit.php` | Confirmed — persisted with correct `customer_id`, `user_id` left null (new addresses are customer-owned only) |
| Self-service invalid postal code (non-4-digit) | Correctly rejected with inline validation error, no DB write |
| Anonymous / customer request to `/admin/customers` | 403 |
| Staff request to `/admin/customers` and `/admin/customers/view` | 200 |
| Staff B2B buyer-add (valid) | Confirmed — buyer linked as non-primary, audit log entry correct |
| Staff B2B buyer-add (duplicate) | Correctly rejected with a `DuplicateBuyerException`-derived error, no duplicate row |
| Anonymous POST to buyer-add endpoint | 403 (defense-in-depth guard holds) |
| Regression: `/`, `/products.php`, `/cart.php`, `/wishlist.php`, `/login.php`, `/register.php`, `/account-dashboard.php`, `/account-edit.php`, `/admin`, `/admin/staff`, `/admin/settings`, `/admin/feature-flags`, `/admin/audit-log`, `/admin/catalog/products` | All 200 (or expected 302 for already-authenticated login/register) |
| Git working tree | Stale-index bug recurred (diff/status showed no changes despite real edits) — fixed via the documented `rm -f .git/index && git read-tree HEAD`; also found and committed `PHASE5-CATALOG-COMPLETION-REPORT.md`, which had been written but never added in the prior domain's commit |
| Test fixtures (e2e users, customers, addresses) | Cleaned from the database after verification, confirmed zero leftover rows |

## Risks

- `AdminCustomerController` has no "remove buyer" or "change primary contact" action yet — `CustomerServiceInterface` (§5) only defines `addBuyer()`. Recorded as a real usability gap for a near-term follow-up, not a hidden limitation.
- There is no admin UI to change a customer's `account_type` after creation, or to set `credit_terms` — both fields exist on the model/schema but have no write path yet, since no spec section calls for it explicitly in this pass.
- The backfill script is a manual one-off CLI tool, not invoked automatically by the migration SQL or by application boot — if new `account_kind='customer'` users are ever created directly via SQL (bypassing `AuthService::register()`) outside of self-registration, they would need the backfill re-run. Low risk in practice since the only user-creation paths are registration (which now creates a Customer inline) and admin staff creation (`account_kind='staff'`, out of scope for this backfill).
- `docs/reports/PHASE5-CATALOG-COMPLETION-REPORT.md` was found untracked in git during this domain's verification (written but never `git add`-ed in the Catalog commit) — included in this commit as a correction, not silently dropped.

## Recommended next priorities

1. Implement **Inventory** (domain #5), per the approved implementation order.
2. Add "remove buyer" / "change primary contact" actions to `CustomerServiceInterface` and `AdminCustomerController` once B2B account management needs justify it.
3. Add admin write paths for `account_type` changes and `credit_terms`, if/when Finance (domain #10) needs them.
4. Continue the domain-by-domain sequence per `docs/specs/00-index.md`.
