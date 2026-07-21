# Technical Specification — Analytics Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

Cross-domain reporting, dashboards, and KPIs. A deliberately **read-only consumer** of every other domain — per the Phase 4 dependency diagram, Analytics has no solid (hard-dependency) arrows anywhere. It is never a system of record, only a system of insight.

## 2. Business Rules

- Analytics never queries another domain's operational tables directly — it builds its own `analytics_events` store from events published by other domains, and reports are computed from that store. This is non-negotiable per the Phase 4 blueprint's explicit call-out that a direct dependency here is a signal the domain boundary needs rethinking, not a signal to add the dependency.
- Every domain event consumed by Analytics is recorded as an immutable `AnalyticsEvent` row — Analytics does not mutate or delete historical events (it may build materialized aggregates *from* them, but the raw event log is append-only, matching Administration's audit-log posture for the same underlying reason: it's a historical record).
- Dashboards/reports are defined declaratively (a `ReportDefinition` describing which event types + aggregation to compute) rather than as one-off hardcoded queries per report, so new reports can be added without new deploys once the underlying event data exists.
- Materialized aggregate tables (e.g., daily sales totals) are rebuilt on a scheduled task, not computed live on every dashboard page load — protects the platform from an expensive analytical query ever competing with live transactional traffic.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `analytics_events` | new | `id, event_type, subject_type, subject_id, actor_id NULL, payload_json, occurred_at` — append-only |
| `report_definitions` | new | `id, name, event_type_filter, aggregation ENUM('count','sum','avg'), group_by, created_at` |
| `daily_aggregates` | new (example materialized table — others added per report need) | `id, metric_key, date, value` |

## 4. Entity Definitions

`AnalyticsEvent`, `ReportDefinition`, `DailyAggregate` — all new.

## 5. Service Interfaces

```
interface AnalyticsEventRecorderInterface {
    public function record(string $eventType, string $subjectType, $subjectId, ?int $actorId, array $payload): void;
}
interface ReportServiceInterface {
    public function run(int $reportDefinitionId, \DateTimeInterface $from, \DateTimeInterface $to): array;
}
interface AggregationServiceInterface {
    public function rebuildDaily(\DateTimeInterface $date): void; // scheduled task entry point
}
```

## 6. Repository Interfaces

`AnalyticsEventRepositoryInterface` (insert + filtered/paginated query — never update/delete, per §2), `ReportDefinitionRepositoryInterface`, `DailyAggregateRepositoryInterface`.

## 7. Controller Responsibilities

`AdminDashboardWidgetController` (renders KPI widgets on the admin dashboard shell, per Administration §13's note that dashboard widgets pull from Analytics), `AdminReportController` (report definition CRUD + report-running UI) — both staff-only, no customer-facing surface.

## 8. Validation Rules

`ReportDefinition` aggregation type must be one of the supported enum values; date ranges on `ReportServiceInterface::run()` must not exceed a sane maximum window (prevents an accidental unbounded query against a growing event table).

## 9. Events Published

None in this phase — Analytics is a terminal consumer, not a publisher, for now (a future `Analytics\Events\AnomalyDetected` is plausible once AI-assisted analysis exists, see §20).

## 10. Events Consumed

Broadly, by design: `Catalog\Events\*`, `Orders\Events\*`, `Crm\Events\*`, `Finance\Events\*`, `Inventory\Events\LowStockThresholdReached` — every domain's published events are candidate Analytics inputs. This is the one domain, alongside API Platform, explicitly permitted broad event-consumption breadth (per the index's convention note), since aggregating signal across the whole platform is its entire purpose — the constraint that matters is that consumption stays event-based, never a direct table read (§2).

## 11. Permissions Required

`analytics.dashboard.view`, `analytics.report.manage` (create/edit report definitions — distinct from viewing existing dashboards, since defining new queries against the event store has different risk than viewing pre-built ones).

## 12. API Endpoints

No public API in this phase (internal business intelligence only — a partner-facing analytics/reporting API is not an identified requirement).

## 13. UI Pages

`/admin/analytics/dashboard` (KPI widget grid), `/admin/analytics/reports` (report list/run/detail) — both net-new.

## 14. Error Handling

`ReportDefinitionNotFoundException`, `DateRangeTooLargeException`. Because Analytics is read-only over an event log it doesn't own the source of truth for, a missing or incomplete event stream (e.g., a domain that hasn't wired its event-publishing yet) should degrade a dashboard widget to "no data" rather than erroring the whole admin dashboard page — isolate widget failures from each other.

## 15. Logging Requirements

Scheduled aggregate-rebuild runs are logged at `info` with row counts processed; report-definition changes are audit-logged (Administration) since they affect what staff see reported.

## 16. Security Requirements

Report results must respect the same data-access boundaries as the underlying domain would — e.g., a report grouping by customer must still be staff-only (`analytics.dashboard.view`), never exposed in a way that lets one customer see another's aggregated data even indirectly through a report.

## 17. Performance Requirements

Live dashboard queries only ever hit materialized aggregate tables (§2), never the raw `analytics_events` table directly for anything rendered on a page load — raw-event queries are reserved for the scheduled aggregation job and ad hoc staff-run reports (which may be slower and are expected to be).

## 18. Testing Strategy

Unit: `ReportService::run()` against a fixture set of `AnalyticsEvent` rows, asserting correct aggregation math for each supported type (count/sum/avg). Integration: `AggregationServiceInterface::rebuildDaily()` against a real test database, asserting idempotency (running it twice for the same date produces the same result, not double-counted).

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** build `analytics_events`, `report_definitions`, `daily_aggregates` tables and their services; wire event consumers for the domains already implemented by this point in the sequence (Identity through API Platform); build the two admin screens; ship 2–3 example `ReportDefinition`s (e.g., daily order count, daily revenue) as seed data demonstrating the pattern.

**Explicitly out of scope:** a full self-service report-builder UI (query-by-clicking) — this phase's `ReportDefinition` is created via admin form with a fixed set of aggregation options, not an arbitrary query builder; real-time/streaming analytics (scheduled batch aggregation only, matching the "no new infrastructure without justification" principle — a streaming pipeline is not justified by anything in this codebase's current scale).

## 20. Future Enhancements

Self-service report builder, real-time dashboards (would require a streaming/pub-sub infrastructure investment, not justified yet), anomaly detection (candidate AI-domain collaboration), cohort/retention analysis, export-to-CSV/scheduled email reports.
