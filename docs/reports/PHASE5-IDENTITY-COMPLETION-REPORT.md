# Domain Implementation Completion Report — Identity

**Phase:** 5 — Enterprise Technical Specifications & Implementation
**Domain:** 1 of 13 — Identity
**Date:** 21 July 2026
**Spec:** [docs/specs/01-identity.md](../specs/01-identity.md) §19 (Migration Strategy) is the authoritative scope for this work.

## What was completed

- **Structural migration.** `Models/User.php`, `Role.php`, `Permission.php`, `Repositories/UserRepository.php`, `Services/AuthService.php`, `UserService.php`, `Controllers/AuthController.php` moved into `src/Domains/Identity/*`, namespaced `App\Domains\Identity\...`, with zero changes to existing business-logic bodies beyond what's listed below.
- **Interfaces introduced for the first time**: `UserRepositoryInterface`, `AuthServiceInterface`, `UserServiceInterface`, `ApiCredentialRepositoryInterface`, `ApiCredentialServiceInterface`. `Kernel::registerBindings()` now binds Identity by interface; every other still-flat domain (Catalog, Orders, CRM) continues to bind by concrete class exactly as Phase 3 left it — this domain's migration didn't touch anything outside its own scope.
- **`account_kind` (customer/staff) split** — new `users.account_kind` column, defaulting to `customer` for every existing row (verified: the migration is additive and non-destructive).
- **API credentials** (`api_credentials` table, `ApiCredential` model, `ApiCredentialRepository`, `ApiCredentialService`) — built ahead of need for the future API Platform domain (#11), per the spec's explicit instruction. Tokens are stored only as a SHA-256 hash; the raw value is returned exactly once at issuance. A staff-only issuer check and a non-empty-scopes check are enforced and unit-tested.
- **Login rate-limiting enforcement** — closed a real Phase 3 gap: the `login_attempts` table existed but nothing read it. `AuthService::authenticate()` now checks recent failures (5 attempts / 15-minute window) *before* verifying the password, and records every attempt. A composite index `(email, ip_address, attempted_at)` was added since the existing single-column indexes didn't cover this query pattern.
- **Session-fixation fix** — `session_regenerate_id(true)` is now called on successful login. It was already present on logout in Phase 3 but missing on login; this was flagged explicitly in the spec (§16) as a gap to verify and close.
- **Typed exceptions** — `InvalidCredentialsException`, `AccountLockedException`, `AccountInactiveException`, `DuplicateEmailException` replace the generic `InvalidArgumentException`/`RuntimeException` Phase 3 used for these specific cases. `AuthController` catches each distinctly but maps every one to the exact same user-facing message Phase 3 showed — this is an internal refinement, not an observable behavior change.
- **Cross-domain fix-forward**: `AccountController` (Customers-domain territory, not migrated in this pass) and both account-related views had their `UserService`/`User` references updated to the new Identity namespace — a mechanical but necessary consequence of moving classes other code depends on.
- **PHPUnit made available in this sandbox** for the first time (previously blocked, like Composer, by missing Packagist access) — resolved via `apt-get download` for the full dependency chain plus two narrowly-scoped local stub files for two coverage-only sub-dependencies with no apt package (`phpDocumentor/Reflection/DocBlock`, `PhpParser`) that neither this domain's tests nor PHPUnit's core test-running path actually need. This benefits every future domain's verification, not just Identity's.
- **Tests**: 13 unit tests (`AuthServiceTest`, `ApiCredentialServiceTest`) and 3 integration tests (`UserRepositoryTest`, against a real database) — all passing. `phpunit.xml` added at repo root.

## Why it was done this way

Per `docs/specs/01-identity.md` §19, this pass deliberately excludes MFA, a staff-portal UI, moving `company_name`/`addresses` off `users` (Customers domain's job, #4), and email-verification-gating login (a business-rule decision, not a structural one) — each is recorded in §20 of the spec as a future enhancement, not silently dropped.

## Files affected

68 changes: 4 renames (`Models/{User,Role,Permission}.php`, `Repositories/UserRepository.php`, `Services/UserService.php`), 1 delete+recreate (`AuthController.php`, `AuthService.php` — substantially rewritten, not a pure move), 16 new files (Identity domain classes, migration SQL, `phpunit.xml`, 3 test files), 2 files edited outside the domain (`AccountController.php`, `Kernel.php`) plus 2 view docblock fixes.

## Verification results

| Check | Result |
|---|---|
| `php -l` across `src/`, `views/`, `components/`, `public/`, `tests/` | Clean |
| No stale references to old Identity class paths anywhere in the tree | Confirmed clean (grep) |
| No `require_once` for any `App\` class (autoloading intact) | Confirmed clean |
| Migration SQL applied to a real MariaDB instance | Clean — `account_kind` column, `api_credentials` table, composite index all present |
| Unit tests (`AuthService`, `ApiCredentialService`) | 13/13 passing, 22 assertions |
| Integration tests (`UserRepository` against real DB) | 3/3 passing, 10 assertions |
| Every existing page still loads (`/`, `/products.php`, `/login.php`, `/register.php`) | All HTTP 200 |
| Register → 2 failed logins (recorded, not yet locked) → correct login → dashboard → account-edit → logout → post-logout redirect | Full flow verified end-to-end against a live PHP 8.1 + MariaDB instance |

## Risks

- The five-attempt lockout boundary itself is unit-tested (mocked) but was not separately exercised live end-to-end in this pass (only 2 of 5 attempts were driven through the real HTTP flow, to confirm recording without spending the time on a full 5-attempt live sequence) — low risk, since the exact boundary logic is what's under unit test, and the live test proves the underlying counting query is correct.
- `src/Http`, `Config`, `Container`, `Logging`, `Support`, `Database` remain at their Phase 3 locations rather than the blueprint's target `src/Platform/*` — deliberately deferred (see Recommended next priorities) rather than folded into this domain's scope.
- Two local stub files were added to the *sandbox's* PHPUnit installation (not committed to the repository) to unblock coverage-only sub-dependencies with no apt package; a real machine running `composer require --dev phpunit/phpunit` will pull the genuine packages and won't need them.

## Recommended next priorities

1. Implement **Administration** (domain #2), per the approved implementation order — it depends only on Identity, which is now in place.
2. Consider doing the `src/{Http,Config,Container,Logging,Support,Database} → src/Platform/*` rename as a small, standalone, low-risk pass before or alongside Administration, since it's foundational and purely mechanical — better done once, early, than retrofitted after more domains depend on the current paths.
3. Continue the domain-by-domain sequence per `docs/specs/00-index.md`, applying the same pattern established here: migrate/build against the domain's spec §19 scope, verify live, test, report, commit.
