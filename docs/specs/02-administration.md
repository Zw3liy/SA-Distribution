# Technical Specification — Administration Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

The control plane: runtime-editable system configuration, feature flags, audit logging, and admin-user/staff management (built on top of Identity's `account_kind='staff'`). Provides the admin portal shell that every other domain's back-office screens are mounted into.

## 2. Business Rules

- Only `account_kind = 'staff'` users (Identity) can ever reach `/admin/*` routes — enforced at the route-group level before any permission check.
- `SystemSetting` values that were previously static (`config/app.php`) become DB-backed and hot-reloadable; `config/app.php` becomes the *seed/default* for settings not yet overridden in the database, not the sole source of truth. This is an additive change — if the settings table is empty, behavior is byte-identical to Phase 3.
- Every mutating action taken by a staff user anywhere in the platform must produce an `AuditLogEntry` — Administration owns the audit log table and a shared `AuditLogger` service that other domains call into (a solid dependency: `* → Administration` for audit writes only, matching Phase 4 §3).
- Feature flags default to `false` (off) unless explicitly enabled — new functionality is opt-in, never silently activated.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `audit_log_entries` | new | `id, actor_user_id, domain, action, entity_type, entity_id, before_json, after_json, ip, created_at` |
| `system_settings` | new | `id, key UNIQUE, value, value_type, is_editable, updated_by, updated_at` |
| `feature_flags` | new | `id, key UNIQUE, is_enabled, rollout_rules_json, updated_by, updated_at` |

## 4. Entity Definitions

- **AuditLogEntry** — immutable once written (no update/delete service method exposed; only `create`/`query`).
- **SystemSetting** — key/value with a declared type (`string|int|bool|json`) for safe casting.
- **FeatureFlag** — boolean gate, optionally with simple rollout rules (e.g., `{"staff_only": true}`) — no percentage-rollout engine in this phase (Future Enhancement).

## 5. Service Interfaces

```
interface AuditLoggerInterface {
    public function record(string $domain, string $action, string $entityType, $entityId, array $before, array $after): void;
}
interface SettingsServiceInterface {
    public function get(string $key, $default = null);
    public function set(string $key, $value, int $updatedByUserId): void;
}
interface FeatureFlagServiceInterface {
    public function isEnabled(string $key, ?User $context = null): bool;
}
```
`AuditLoggerInterface` is the one interface every other domain is expected to take a dependency on (via the container) — this is the "solid arrow to Administration" from Phase 4 §3, kept deliberately narrow (one method) so it can never become a backdoor for other domains to reach into Administration's other concerns.

## 6. Repository Interfaces

```
interface AuditLogRepositoryInterface {
    public function insert(array $entry): void;
    public function query(array $filters, int $limit, int $offset): array;
}
interface SystemSettingRepositoryInterface {
    public function get(string $key): ?array;
    public function upsert(string $key, $value, string $type, int $updatedBy): void;
}
interface FeatureFlagRepositoryInterface {
    public function get(string $key): ?array;
    public function upsert(string $key, bool $isEnabled, array $rules, int $updatedBy): void;
}
```

## 7. Controller Responsibilities

- `AdminDashboardController` — admin portal landing page/shell (nav, cross-domain summary widgets — reads from other domains, writes nothing).
- `AuditLogController` — searchable/filterable audit log view (by actor, domain, date range).
- `SystemSettingController`, `FeatureFlagController` — CRUD screens, permission-gated.

## 8. Validation Rules

- Setting `value_type` must match the actual value on write (reject a non-numeric string for an `int`-typed setting).
- Feature flag `key` and setting `key` are immutable once created (rename = delete + recreate, to avoid silently orphaning code that still checks the old key).

## 9. Events Published

`Administration\Events\SettingChanged`, `FeatureFlagToggled` — consumed by Analytics (change tracking) and potentially by domains that need to react live to a flag flip (e.g., cache invalidation), though no such consumer exists yet in this phase.

## 10. Events Consumed

None directly required for Administration's own logic (it's a foundational domain), but `AuditLoggerInterface::record()` is effectively invoked synchronously by every other domain as part of their own write paths — that's a direct call, not an event, by design (audit writes must not be best-effort/droppable the way events are).

## 11. Permissions Required

`administration.settings.view`, `administration.settings.edit`, `administration.audit_log.view`, `administration.feature_flag.manage`, `administration.staff.manage` (staff account CRUD, delegates to Identity's `UserService` for the actual user record).

## 12. API Endpoints

None public. Internal admin-portal AJAX only, session-authenticated, staff-only — no new pattern beyond what Identity/Phase 3 already established.

## 13. UI Pages

`/admin` (dashboard shell), `/admin/settings`, `/admin/feature-flags`, `/admin/audit-log`, `/admin/staff` — all net-new; this is the first domain to introduce the admin portal's visual shell (nav, layout) that every subsequent domain's admin screens will be mounted into.

## 14. Error Handling

`SettingNotFoundException`, `InvalidSettingTypeException`. Audit log writes must never throw in a way that blocks the primary action they're auditing — `AuditLoggerInterface::record()` catches its own persistence failures internally and logs them via `Logger::error()` rather than propagating, so a broken audit table can't take down checkout.

## 15. Logging Requirements

Every setting/flag change is both an audit log entry (business record) and a `Logger::info()` call (operational log) — intentionally duplicated across the two systems, since they serve different consumers (audit = compliance/who-did-what; log = ops/debugging).

## 16. Security Requirements

Admin portal routes require `account_kind = 'staff'` (Identity) *and* the specific permission for the screen — defense in depth, not either/or. Audit log is append-only at the application layer (no `update`/`delete` repository methods exist at all, not just permission-gated) — the only way to remove audit history is a manual DBA action outside the app, which is the correct posture for a compliance-relevant log.

## 17. Performance Requirements

Audit log inserts must not add meaningfully to request latency (<10ms) — synchronous but minimal (single-row insert, no joins). Audit log queries (admin UI) are paginated, never full-table.

## 18. Testing Strategy

Unit: `SettingsService` get/set with mocked repository, including type-casting behavior. Unit: `FeatureFlagService::isEnabled()` with rollout-rule variations. Integration: `AuditLogRepository` insert + filtered query against a real test database. No test should ever assert that audit logging *blocks* the audited action on failure (per §14).

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** create `audit_log_entries`, `system_settings`, `feature_flags` tables; build `AuditLogger`/`SettingsService`/`FeatureFlagService` and their repositories; build the admin portal shell (`/admin` layout, nav) and the four screens in §13; wire `Kernel` to check `account_kind = 'staff'` for any `/admin/*` route before dispatch. Retrofit at least one existing write path (Identity's login/role changes) to call `AuditLoggerInterface::record()` as a working example other domains can copy.

**Explicitly out of scope:** migrating every existing write path to call the audit logger (that happens incrementally as each domain is itself implemented, not retroactively in this pass); percentage-based feature flag rollout; a full RBAC-editing UI beyond basic role assignment (Identity already has the data model — a rich UI for it is a Future Enhancement).

## 20. Future Enhancements

Percentage/segment-based feature flag rollout, exportable audit reports for compliance (SOX/POPIA), configurable audit retention policy, admin-portal dashboard widgets pulling live KPIs from Analytics once that domain exists.
