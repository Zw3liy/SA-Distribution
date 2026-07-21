# Technical Specification — Identity Domain

See [00-index.md](00-index.md) for cross-domain conventions this spec inherits (namespacing, events, permissions, error handling, logging, testing).

## 1. Responsibilities

Authenticate actors (who is this) and authorize them (what can they do). Owns credentials, sessions, roles, permissions, and — new in Phase 5 — the distinction between staff and customer identities and machine (API) identities. Does **not** own business profile data (Customers), sales pipeline (CRM), or audit trails of *what happened elsewhere* (Administration) — only who was acting when it happened.

## 2. Business Rules

- Email is the unique login identifier; case-insensitive uniqueness (existing `users.email UNIQUE`, matched case-insensitively at the query layer).
- Passwords: `password_hash()` (bcrypt/argon, PHP default), minimum 8 characters, no maximum-complexity theater rules (existing behavior, unchanged).
- An account is `is_active = 0` → login rejected regardless of correct credentials.
- Email verification (`is_verified`) is tracked but, as of Phase 3, does not yet gate login — this is flagged as a real gap in section 20.
- A user has zero or more roles (`user_roles`); a role has zero or more permissions (`role_permissions`). Effective permission = union across all assigned roles. No permission-deny overrides in this phase (additive-only model, matching what already exists).
- **New:** a user is exactly one of `customer` or `staff` (a new `users.account_kind` column) — determines which portal they can reach (storefront vs. admin) and is checked in addition to, not instead of, roles/permissions. A staff account with zero admin permissions still cannot browse the admin portal's protected areas; a customer account can never reach admin routes regardless of assigned roles, closing off the class of privilege-escalation risk that a single flat `users` table invites.
- **New:** API credentials (`ApiCredential`) belong to a user (typically a staff/service account) but are never sufccient alone to authenticate a *browser session* — token auth and cookie-session auth are separate code paths that never fall back to one another.
- Login attempts are rate-limited per email+IP using the existing `login_attempts` table (already exists; Phase 3 did not yet wire enforcement into `AuthService` — closing that gap is in-scope for Phase 5's migration, see §19).

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `users` | existing, altered | add `account_kind ENUM('customer','staff') NOT NULL DEFAULT 'customer'` |
| `roles` | existing, unchanged | |
| `permissions` | existing, unchanged | |
| `role_permissions` | existing, unchanged | |
| `user_roles` | existing, unchanged | |
| `login_attempts` | existing, unchanged (enforcement added in code) | |
| `password_resets` | existing, unchanged | |
| `remember_tokens` | existing, unchanged | |
| `email_verifications` | existing, unchanged | |
| `api_credentials` | **new** | `id, user_id, name, token_hash, scopes JSON, last_used_at, expires_at, revoked_at, created_at` |

`company_name` remains on `users` in this phase (its removal to `Customers.customer` is scoped into the **Customers** domain's migration, not Identity's, to keep each domain's migration independently revertible).

## 4. Entity Definitions

- **User** (existing `Models\User`, unchanged fields) — id, name, email, passwordHash, isActive, isVerified, accountKind (new), timestamps.
- **Role**, **Permission** — existing, unchanged.
- **ApiCredential** (new) — id, userId, name, tokenHash (never the raw token, only its hash — the raw value is shown once at creation time and never persisted), scopes (array of permission strings), lastUsedAt, expiresAt, revokedAt.

## 5. Service Interfaces

```
interface AuthServiceInterface {
    public function register(array $input): User;
    public function login(string $email, string $password, string $ip): User; // throws InvalidCredentialsException, AccountLockedException, AccountInactiveException
    public function logout(): void;
}
interface UserServiceInterface {
    public function findById(int $id): ?User;
    public function hasPermission(User $user, string $permission): bool;
    public function assignRole(User $user, string $role): void;
}
interface ApiCredentialServiceInterface { // new
    public function issue(User $user, string $name, array $scopes): string; // returns raw token, once
    public function authenticate(string $rawToken): ?User;
    public function revoke(int $credentialId): void;
}
```
`AuthService`/`UserService` are the existing Phase 3 classes, given explicit interfaces for the first time (none existed before — every consumer depended on the concrete class). `ApiCredentialService` is net-new, built ahead of the API Platform domain per the Phase 4 blueprint's note that Identity should be "ready for API Platform later."

## 6. Repository Interfaces

```
interface UserRepositoryInterface {
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): void;
    public function recordLoginAttempt(string $email, string $ip, bool $success): void;
    public function recentFailedAttempts(string $email, string $ip, int $windowSeconds): int;
}
interface ApiCredentialRepositoryInterface {
    public function create(int $userId, string $name, string $tokenHash, array $scopes): ApiCredential;
    public function findByTokenHash(string $tokenHash): ?ApiCredential;
    public function revoke(int $id): void;
}
```

## 7. Controller Responsibilities

- `AuthController` (existing, migrated): `login()`, `register()`, `logout()` route actions — CSRF verification, guest-only guards, redirect-on-success, view-render-on-failure. Unchanged behavior from Phase 3.
- `ApiCredentialController` (new, admin-portal-facing only in this phase — no public self-service UI yet): issue/list/revoke credentials for a given staff user. Gated behind `identity.api_credential.manage` permission.

## 8. Validation Rules

- Registration: email format + uniqueness, password ≥ 8 chars, required name fields — unchanged from Phase 3's existing `filter_input`/`FILTER_VALIDATE_EMAIL` checks.
- Login: email + password non-empty; rate-limit check runs *before* password verification (so a locked-out account doesn't leak whether the password would've been correct).
- API credential issuance: `name` required, `scopes` must be a subset of the issuing user's own effective permissions (a staff member can never mint a token with more access than they themselves have).

## 9. Events Published

- `Identity\Events\UserRegistered` (user)
- `Identity\Events\UserLoggedIn` (user, ip, timestamp)
- `Identity\Events\LoginFailed` (email, ip, timestamp) — consumed by Administration for audit and by a future fraud/anomaly detector
- `Identity\Events\ApiCredentialIssued` / `ApiCredentialRevoked`

## 10. Events Consumed

None. Identity is a foundational domain (Phase 4 §3) — it must not depend on any other domain's events to function.

## 11. Permissions Required

`identity.user.view`, `identity.user.edit`, `identity.role.assign`, `identity.api_credential.manage` — all staff-only; customer self-service (login/register/logout/edit-own-profile) requires no permission, only authentication.

## 12. API Endpoints

No public `/api/v1/...` endpoints in this phase (Identity underpins API Platform's *auth mechanism* for other domains' endpoints, rather than exposing its own business data publicly). Internal: existing `/login.php`, `/register.php`, `/logout.php` (unchanged routes/contracts from Phase 3).

## 13. UI Pages

Existing: login, register (unchanged). New: none in the storefront; a minimal "API Credentials" admin-portal page is part of the Administration domain's UI shell, not a standalone Identity page (Identity supplies the service, Administration supplies the screen — see Administration spec §13).

## 14. Error Handling

New typed exceptions replace the current generic ones: `InvalidCredentialsException`, `AccountLockedException`, `AccountInactiveException`, `DuplicateEmailException`. `AuthController` catches each and maps to the correct user-facing message — this is a genuine behavior refinement over Phase 3 (which used one generic catch-all), not a business-logic redesign, since the *observable* HTTP responses for existing flows are unchanged.

## 15. Logging Requirements

Every login attempt (success or failure) is logged at `info`/`warning` respectively with `{domain: 'identity', actor_id, ip, action: 'login'}`. Role/permission changes logged at `info` with before/after role sets. API credential issuance/revocation logged at `warning` (security-sensitive).

## 16. Security Requirements

- Password hashing unchanged (`password_hash`, PHP default algorithm).
- Login rate-limiting enforcement (closing the Phase 3 gap noted in §2) — lock after 5 failed attempts per email+IP within 15 minutes, using the already-existing `login_attempts` table.
- API tokens: stored only as a hash (SHA-256) at rest; raw token shown exactly once at creation; scoped, revocable, expirable.
- Session fixation/hardening: unchanged from Phase 3's `Kernel::bootSession()` (regenerate session ID on login — verify this is actually happening; if not, add it as part of this domain's migration, since it's a genuine, narrowly-scoped security fix, not scope creep).
- `account_kind` check is enforced at the Kernel/route-group level (admin routes reject non-staff `account_kind` before any permission check even runs), not just at the permission level, per §2.

## 17. Performance Requirements

Login/registration must complete in <300ms under normal load against a properly indexed `users.email` (already unique-indexed). Rate-limit lookups against `login_attempts` must use an index on `(email, ip, created_at)` — add if missing.

## 18. Testing Strategy

- Unit: `AuthService` (register/login/logout) with a mocked `UserRepositoryInterface` — covers happy path, wrong password, inactive account, locked-out account.
- Unit: `ApiCredentialService` — scope-subset validation, token hashing/never-returning-raw-twice.
- Integration: `UserRepository` against a real test database — uniqueness constraint, login-attempt recording/windowing.
- No UI/browser tests in this phase (out of scope — matches Phase 2/3's manual curl-based verification approach; a browser test harness is a Future Enhancement).

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:**
1. Move `Models/User.php`, `Role.php`, `Permission.php` → `Domains/Identity/Models/`; `Repositories/UserRepository.php` → `Domains/Identity/Repositories/`; `Services/AuthService.php`, `UserService.php` → `Domains/Identity/Services/`; `Controllers/AuthController.php` → `Domains/Identity/Controllers/`. Update namespaces, `composer.json` if needed (PSR-4 root unchanged, only sub-namespace changes), and `Kernel::registerBindings()`/`registerRoutes()`.
2. Add `account_kind` column + migration SQL.
3. Add `api_credentials` table + `ApiCredential` model/repository/service (built, but with no consumer yet — API Platform is domain #11; this is intentionally built ahead of need per the blueprint's explicit call-out, not speculative gold-plating).
4. Add typed exceptions (§14) and wire them through `AuthController`.
5. Wire login rate-limiting enforcement into `AuthService::login()` using the existing `login_attempts` table (closes a real Phase 3 gap).
6. Verify session-ID regeneration on login; add if missing.
7. Add unit + integration tests per §18.

**Explicitly out of scope for this pass** (deferred to §20 / later domains): MFA, staff-portal UI (Administration's job), moving `company_name`/`addresses` off `users` (Customers domain's job), email-verification-gates-login enforcement (flagged but not fixed here, since changing existing login behavior for currently-unverified accounts is a business-rule change requiring your sign-off, not a structural migration).

## 20. Future Enhancements

MFA (TOTP), OAuth2/social login, email-verification-gates-login (pending business decision), passwordless/magic-link login, device/session management UI ("log out all other sessions"), anomaly-based login risk scoring (consumed by AI domain later).
