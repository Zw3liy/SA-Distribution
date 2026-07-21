# Domain Implementation Completion Report — Administration

**Phase:** 5 — Enterprise Technical Specifications & Implementation
**Domain:** 2 of 13 — Administration
**Date:** 21 July 2026
**Spec:** [docs/specs/02-administration.md](../specs/02-administration.md) §19 (Migration Strategy) is the authoritative scope for this work.

## What was completed

- **New bounded context**, `src/Domains/Administration/*`, built from scratch against Identity's now-established pattern (interface-bound repositories/services, typed models, typed exceptions).
- **Audit logging** (`AuditLogRepositoryInterface`/`AuditLogRepository`, `AuditLoggerInterface`/`AuditLogger`, `audit_log_entries` table) — the one interface every other domain is expected to depend on. `AuditLogger::record()` deliberately swallows its own persistence failures (logged, never thrown) so a broken audit table can never block the write path it's auditing — unit-tested directly (`AuditLoggerTest::testRecordSwallowsRepositoryFailuresAndLogsThemInstead`).
- **System settings** (`SystemSettingRepositoryInterface`/`SystemSettingRepository`, `SettingsServiceInterface`/`SettingsService`, `system_settings` table) — type-cast storage (string/int/bool/json) with a real `InvalidSettingTypeException` for unsupported PHP types.
- **Feature flags** (`FeatureFlagRepositoryInterface`/`FeatureFlagRepository`, `FeatureFlagServiceInterface`/`FeatureFlagService`, `feature_flags` table) — default OFF for any flag with no row (opt-in only, never silently activated), with a `staff_only` rollout rule checked against `User::isStaff()`.
- **Staff management** (`StaffController`) — deliberately owns none of the `users` table; delegates entirely to Identity's `AuthServiceInterface::register()` (with `account_kind: 'staff'`) and `UserServiceInterface::listByAccountKind()`/`setActive()`.
- **Admin portal screens**: dashboard, staff, settings, feature flags, audit log — five new views plus a dedicated `components/admin-nav.php`, reusing every existing CSS class from the storefront rather than introducing a parallel design system (two new badge classes added for active/inactive status).
- **Staff-only route guard**, implemented in `Kernel::handle()` (the Router has no middleware/group concept, so this is the one place every request funnels through): any `/admin` or `/admin/*` path is checked against `isAuthenticated() && isStaffAccount()` before the controller is invoked; failing requests get a real `403` response and view, not a redirect that leaks the page's existence.
- **Retrofit of an existing write path**, per spec §19's explicit instruction: `AuthController` (Identity domain) now takes `AuditLoggerInterface` as a constructor dependency and calls `record()` on both registration and login — a working, copyable example for every other domain's controllers.
- **Session-kind plumbing** (`currentAccountKind()`, `isStaffAccount()` added to `src/Support/auth.php`) built during Identity but only load-bearing now that Administration is the first consumer.
- **Tests**: 12 unit tests (`AuditLoggerTest`, `SettingsServiceTest`, `FeatureFlagServiceTest`) and 3 integration tests (`AdministrationRepositoryTest`, against a real database, using a self-contained fixture user to satisfy the real foreign-key constraints on `updated_by`) — all passing, alongside the full pre-existing suite (31 tests total across the project, all green).

## Why it was done this way

Per `docs/specs/02-administration.md` §16, staff-only access is deliberately layered as defense in depth: `account_kind = 'staff'` is the coarse gate enforced centrally in the Kernel; per-screen permission checks (`staff.manage`, `settings.manage`, etc.) are the second layer, planned to be enforced via `UserServiceInterface::hasPermission()` (already built in Identity) once the permission catalog itself is seeded — recorded below as a near-term follow-up rather than silently skipped or faked.

Feature flags and settings default to safe/inert states (flags off, no fallback value beyond what the caller supplies) so Administration can never be the thing that silently turns on unintended behavior elsewhere in the platform.

## Files affected

44 changes: 23 new files under `src/Domains/Administration/*`, 1 new migration, 6 new admin views + 1 new nav component, 4 new test files, 1 new `403` view, plus 8 files edited outside the new domain (`Kernel.php` for bindings/routes/guard, `Response.php` for a `forbidden()` factory, `AuthController.php`/`UserRepository(Interface).php`/`UserService(Interface).php` for the RBAC/audit retrofit points, `auth.php` for the two new session helpers, `styles.css` for two badge classes).

## Verification results

| Check | Result |
|---|---|
| `php -l` across `src/`, `views/`, `components/`, `public/`, `tests/` | Clean |
| Migration (`2026_07_21_administration_domain.sql`) applied to a real MariaDB instance | Clean — `audit_log_entries`, `system_settings`, `feature_flags` all present with correct FKs |
| Unit tests (`AuditLogger`, `SettingsService`, `FeatureFlagService`) | 12/12 passing, 17 assertions |
| Integration tests (`AdministrationRepositoryTest` against real DB) | 3/3 passing, 5 assertions |
| Full project test suite (Identity + Administration combined) | 31/31 passing (25 unit, 6 integration) |
| Anonymous request to `/admin` | 403, correct view |
| Authenticated customer request to `/admin` | 403 (account_kind check correctly rejects non-staff) |
| Authenticated staff request to `/admin`, `/admin/staff`, `/admin/settings`, `/admin/feature-flags`, `/admin/audit-log` | All 200 |
| Live write-path verification: update a setting, toggle a feature flag, create a staff account, deactivate a staff account | All succeeded; each produced the correct row in `audit_log_entries` (`setting.updated`, `feature_flag.toggled`, `staff.created`, `staff.status_changed`) |
| Live login audit retrofit | Both staff and customer logins produced `identity` / `user.logged_in` audit rows with correct `account_kind` |
| Regression: `/`, `/products.php`, `/login.php`, `/register.php`, `/account-dashboard.php` (pre-existing pages) | All still HTTP 200 |
| Test fixtures (e2e users, settings, flags, audit rows) | Cleaned from the database after verification |

## Risks

- The permission-catalog side of the "defense in depth" requirement (§16) is not yet seeded — the coarse `account_kind = 'staff'` gate is fully enforced and tested, but per-screen fine-grained permission checks are not yet wired into the four admin controllers. Low risk today (there is currently only one staff role in practice), but should be closed before more than one staff role exists.
- No admin user is seeded by the migration itself (by design — seeding real staff credentials doesn't belong in a schema migration); the first staff account must currently be created by inserting a row directly, or by temporarily relaxing the guard. Documented here rather than worked around with a shortcut.
- The sandbox's MariaDB `root` user has no password, which caused the app's real default DB credentials (`sa_business_user` / config default password) to be unusable until a matching user was created for this verification pass — a sandbox-only setup gap, not a code issue; noted so it isn't mistaken for one in a future session.

## Recommended next priorities

1. Implement **Catalog / Product Information Management** (domain #3), per the approved implementation order — no dependency on anything not already in place.
2. Seed the permission catalog (`permissions`, `role_permissions`, `user_roles` rows for at least `staff.manage`, `settings.manage`, `feature_flags.manage`, `audit_log.view`) and wire the second defense-in-depth layer into the four Administration controllers — small, well-scoped, and explicitly flagged as deferred rather than dropped.
3. Continue the domain-by-domain sequence per `docs/specs/00-index.md`, applying the same pattern established across both completed domains: build against the domain's spec §19 scope, verify live, test, report, commit.
