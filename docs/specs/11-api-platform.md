# Technical Specification — API Platform Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

The external/partner-facing API surface: versioned public endpoints, API keys/tokens, rate limiting, webhooks, and (eventually) a developer portal. A façade over the domains that need external exposure — owns no core business data itself.

## 2. Business Rules

- Every public endpoint lives under `/api/v1/...` and returns the standardized envelope defined in the Phase 4 blueprint §4 (`{"data":...,"meta":...}` / `{"error":{"code","message","detail"}}`) — no exceptions, even for a simple read endpoint.
- Authentication is exclusively via `Identity\ApiCredential` bearer tokens (domain #1, built ahead of need specifically for this) — session-cookie auth is never accepted on `/api/v1/*` routes, keeping the two auth mechanisms fully separated (Identity §2).
- Every `ApiClient`'s tokens are scoped to specific permissions (drawn from the existing Identity `Role`/`Permission` model) — an endpoint checks the token's scopes, not just "is this token valid," before executing.
- Rate limits are enforced per `ApiClient`, not globally — a documented default (e.g., 60 requests/minute) applies unless a specific client has a negotiated override.
- Webhook delivery is at-least-once, with the receiving endpoint expected to be idempotent (each webhook payload includes an event ID) — API Platform does not guarantee exactly-once delivery in this phase (a durable, retrying delivery queue is a Future Enhancement; this phase does best-effort synchronous delivery with one retry).

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `api_credentials` | owned by Identity (see Identity spec §3) | API Platform reads/validates, does not own |
| `webhook_subscriptions` | new | `id, api_client_id, event_type, target_url, secret, is_active, created_at` |
| `webhook_deliveries` | new | `id, subscription_id, event_id, payload_json, status, attempted_at, response_code` |
| `rate_limit_policies` | new | `id, api_client_id NULL (NULL = default), requests_per_minute` |

## 4. Entity Definitions

`WebhookSubscription`, `WebhookDelivery`, `RateLimitPolicy` — new. `ApiClient` is effectively `Identity\ApiCredential` (no separate entity — API Platform reuses Identity's credential model rather than inventing a parallel one, per the Phase 4 blueprint's explicit instruction that this domain "does not invent a parallel authorization system").

## 5. Service Interfaces

```
interface ApiAuthMiddlewareInterface {
    public function authenticate(Request $request): ?User; // validates bearer token via Identity\ApiCredentialServiceInterface
    public function authorize(User $user, string $requiredScope): bool;
}
interface RateLimiterInterface {
    public function checkAndIncrement(int $apiClientId): bool; // false = over limit
}
interface WebhookDispatcherInterface {
    public function publish(string $eventType, array $payload): void; // fans out to all matching active subscriptions
}
```

## 6. Repository Interfaces

`WebhookSubscriptionRepositoryInterface`, `WebhookDeliveryRepositoryInterface`, `RateLimitPolicyRepositoryInterface` — standard CRUD shape.

## 7. Controller Responsibilities

Versioned resource controllers per exposed domain (`Api\V1\ProductController`, `Api\V1\OrderController`, `Api\V1\CustomerController`) — thin façades that call the owning domain's existing service interface and wrap the result in the standard envelope; **no business logic lives in this domain's controllers**, only translation/authorization/formatting. `AdminApiClientController`, `AdminWebhookController` — staff-facing management screens.

## 8. Validation Rules

Every incoming public request is validated against the same rules the underlying domain's own internal validation already enforces (API Platform does not duplicate business validation — it delegates to the domain service and translates any thrown domain exception into the standard error envelope with an appropriate HTTP status).

## 9. Events Published

`ApiPlatform\Events\WebhookDeliveryFailed` (consumed by Administration/Analytics for operational alerting).

## 10. Events Consumed

Every event this phase's webhook system exposes externally: `Orders\Events\OrderPlaced`/`OrderStatusChanged`, `Crm\Events\QuoteAccepted`, `Inventory\Events\LowStockThresholdReached` — `WebhookDispatcherInterface::publish()` is called by a thin adapter subscribed to these internal events, translating them into external webhook deliveries. This is the one domain explicitly permitted to have broad event-consumption breadth, since fan-out to external subscribers is its entire purpose.

## 11. Permissions Required

`api_platform.client.manage` (create/revoke `ApiClient`s — actually delegates to Identity's `ApiCredentialService`), `api_platform.webhook.manage`.

## 12. API Endpoints

This domain *is* the API endpoint surface. Confirmed in-scope for Phase 5, per the domains already built by this point in the implementation order: `GET /api/v1/catalog/products`, `GET /api/v1/catalog/products/{slug}`, `POST /api/v1/orders/checkout`, `GET /api/v1/orders/{id}`, `GET /api/v1/customers/me`, `GET /api/v1/customers/me/addresses`, `GET /api/v1/customers/me/invoices`.

## 13. UI Pages

`/admin/api-clients`, `/admin/webhooks` — staff-only. A full public developer portal (self-service key issuance, interactive docs) is explicitly a Future Enhancement (§20) — this phase's admin screens are staff-managed only.

## 14. Error Handling

Every domain exception surfaced through a public endpoint is mapped to the standard envelope with an explicit, stable `error.code` (not just an HTTP status) — e.g. `Orders\InsufficientStockException` → `{"error":{"code":"insufficient_stock",...}}` — so partner integrators can branch on `code` reliably across API versions.

## 15. Logging Requirements

Every public API request is logged with `{api_client_id, endpoint, status_code, latency_ms}` — this is the domain's primary operational signal and should be structured for easy aggregation (feeds Analytics' future API-usage dashboards).

## 16. Security Requirements

Bearer tokens are validated on every request (no caching of "this token was valid 5 minutes ago" across requests, to respect revocation immediately). Webhook payloads are signed (HMAC using the subscription's `secret`) so receivers can verify authenticity. Rate limiting is enforced *before* the request reaches any domain service, to protect the whole platform, not just the API layer, from an abusive client.

## 17. Performance Requirements

Rate-limit checks must be fast (<5ms) — an in-memory or Redis-backed counter is preferable to a database row-increment under load; this phase may ship with a simple DB-backed counter (per the "no new infrastructure without justification" principle) with a documented note that Redis is the first upgrade if request volume ever makes the DB approach a bottleneck.

## 18. Testing Strategy

Unit: `ApiAuthMiddleware` — valid token, expired token, insufficient scope. Unit: `RateLimiter` — under/at/over limit boundary behavior. Integration: at least one full façade endpoint (`GET /api/v1/catalog/products`) exercised end-to-end against a real test database, asserting the standard envelope shape exactly. Contract test: webhook payload signature verification.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** build `webhook_subscriptions`, `webhook_deliveries`, `rate_limit_policies` tables; `ApiAuthMiddleware`, `RateLimiter`, `WebhookDispatcher`; the seven endpoints listed in §12 (façading Catalog, Orders, Customers — the three domains with the most mature, stable contracts by this point in the implementation order); the two admin screens.

**Explicitly out of scope:** a public developer portal with self-service signup and interactive API docs (OpenAPI *spec generation* is worth producing as machine-readable output in this phase, since it's low-cost given the endpoints are already built, but a polished public-facing docs site is a larger, separate initiative); GraphQL (explicitly rejected per Phase 4 §4); durable/guaranteed webhook delivery queue (best-effort + one retry only, this phase).

## 20. Future Enhancements

Public developer portal with self-service key management and interactive docs, durable webhook delivery queue with exponential backoff, GraphQL (only if a concrete need emerges), additional façade endpoints as CRM/Finance/Inventory mature, per-endpoint (not just per-client) rate-limit tuning.
