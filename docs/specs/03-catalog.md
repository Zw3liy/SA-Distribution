# Technical Specification — Catalog / PIM Domain

See [00-index.md](00-index.md) for cross-domain conventions.

## 1. Responsibilities

What can be sold: products, categories, brands, media, attributes, and pricing rules. Owns "is this sellable and what does it look like" — not "how many do we have" (Inventory) or "what did it sell for" (Orders/Finance).

## 2. Business Rules

- A product must belong to exactly one category and one brand (existing `FOREIGN KEY ... ON DELETE RESTRICT` — a category/brand can't be deleted while products reference it; unchanged).
- `slug` is globally unique and immutable once a product has been ordered at least once (changing it would break historical order references and external links) — new rule, enforced in `ProductService`, not the database (the DB unique constraint alone doesn't know about order history).
- `sale_price`, when set, must be less than `price` — existing implicit assumption, made an explicit validated rule in this phase.
- `is_active = 0` products are excluded from all customer-facing catalog browsing/search but remain fully readable by staff and by Orders (a historical order must still be able to display a since-deactivated product).
- **New:** attributes are stored as a JSON column (`products.attributes_json`) rather than a full EAV schema — chosen deliberately for this phase (see §19) over a normalized attribute table, to avoid the complexity of EAV until real variant/attribute requirements are known from actual catalog data, which doesn't exist yet in this codebase beyond flat fields.

## 3. Database Tables

| Table | Status | Notes |
|---|---|---|
| `products` | existing, altered | add `attributes_json JSON NULL`; `stock` column marked deprecated (read-only compatibility shim, real value moves to Inventory's `inventory_items` in domain #5) |
| `categories` | existing, unchanged | |
| `brands` | existing, unchanged | |
| `product_images` | existing, unchanged | |
| `product_variants` | new (schema only, unpopulated until real variant needs arise) | `id, product_id, sku, attributes_json, price_override, is_active` |
| `tax_classes` | new | `id, name, default_rate` — referenced by Finance's `TaxCalculator`, owned here since it's a product-classification concern |

## 4. Entity Definitions

`Product` (existing `Models\Product`, + new `attributesJson` field, `taxClassId`). `ProductVariant` (new, minimal — schema exists but no UI/service logic populates it yet in this phase; see §19). `TaxClass` (new, simple lookup entity).

## 5. Service Interfaces

```
interface ProductServiceInterface {
    public function getProducts(array $filters, string $sort, int $limit, int $offset): array;
    public function getProductBySlug(string $slug): ?Product;
    public function create(array $data): Product;
    public function update(int $id, array $data): void;
    public function deactivate(int $id): void; // never hard-deletes a product with order history
}
interface TaxClassServiceInterface {
    public function forProduct(Product $product): TaxClass;
}
```
`ProductService`'s read methods are the existing Phase 3 implementation, unchanged; `create`/`update`/`deactivate` are new (Phase 3 had no product-management write path at all — the storefront was read-only against seed data).

## 6. Repository Interfaces

```
interface ProductRepositoryInterface {
    // existing methods unchanged: getProducts, getProductBySlug, getProductsCount, getCategories, getBrands, getProductImages, getRelatedProducts
    public function insert(array $data): int;   // new
    public function updateFields(int $id, array $data): void; // new
    public function slugHasOrderHistory(string $slug): bool; // new — used by the immutability rule in §2
}
```

## 7. Controller Responsibilities

`ProductController` (existing, migrated) — storefront browsing (`index`, `show`), unchanged. `AdminProductController` (new) — admin-portal product CRUD, permission-gated, calls `AuditLoggerInterface` on every write per Administration's convention.

## 8. Validation Rules

`sku`/`slug` required + unique; `price >= 0`; `sale_price < price` when present; `category_id`/`brand_id` must reference existing rows; `attributes_json` must be valid JSON (rejected at the service layer before it ever reaches the database, not relying on MySQL's JSON column validation alone).

## 9. Events Published

`Catalog\Events\ProductCreated`, `ProductUpdated`, `ProductDeactivated` — consumed by Inventory (to create a corresponding `InventoryItem` row when a product is created), Analytics, and AI (search index refresh).

## 10. Events Consumed

None required for core logic (Catalog is close to foundational — only Identity/Administration sit "below" it).

## 11. Permissions Required

`catalog.product.view` (staff), `catalog.product.create`, `catalog.product.edit`, `catalog.product.deactivate`, `catalog.category.manage`, `catalog.brand.manage`. Storefront browsing requires no permission (public).

## 12. API Endpoints

Public, via API Platform (domain #11) once that exists: `GET /api/v1/catalog/products`, `GET /api/v1/catalog/products/{slug}` — read-only, no write endpoints exposed publicly in this phase. Internal: existing `/products.php`, `/product-details.php` (unchanged).

## 13. UI Pages

Existing: products listing, product detail (unchanged). New: `/admin/catalog/products` (list + edit), `/admin/catalog/categories`, `/admin/catalog/brands`.

## 14. Error Handling

`ProductNotFoundException`, `DuplicateSkuException`, `SlugImmutableException` (thrown when attempting to change a slug with order history), `InvalidPricingException` (sale_price ≥ price).

## 15. Logging Requirements

Product create/update/deactivate logged via `AuditLoggerInterface` (Administration) with before/after diffs of changed fields — not the full row, to keep audit entries readable.

## 16. Security Requirements

Admin product write endpoints require staff `account_kind` + specific permission (defense in depth, per Identity/Administration convention). Product `description`/`short_description` fields are rendered with output escaping in views (already the case via the existing `esc()` helper) — explicitly re-verified as part of this domain's implementation, not assumed.

## 17. Performance Requirements

Catalog listing queries must remain index-backed as filters grow (existing `category_id`/`brand_id`/`search` filters) — add a composite index on `(is_active, category_id, brand_id)` as part of this phase's migration, since the current schema only has single-column indexes implicitly via foreign keys. Product detail page (`getProductBySlug` + images + related products) should remain a small, bounded number of queries (already true in Phase 3 — verify it stays true as fields are added).

## 18. Testing Strategy

Unit: `ProductService` validation rules (sale_price < price, slug immutability) with a mocked repository. Integration: `ProductRepository` filtering/sorting/pagination against a real seeded test database (reusing the existing `database/seed.sql` fixture data). Regression: re-run the exact storefront browsing verification from Phase 2/3 (page loads, filters, product detail) after migration, since this is the domain most likely to have subtle behavioral regressions given how much existing logic it carries over.

## 19. Migration Strategy (Phase 5 implementation scope)

**In scope:** move `Models/Product.php`, `Repositories/ProductRepository.php`, `Services/ProductService.php`, `Controllers/ProductController.php` → `Domains/Catalog/*` (namespace + Kernel binding updates only — zero logic changes to the existing read paths, preserving Phase 3's verified behavior exactly). Add `attributes_json`, `tax_classes` table, product create/update/deactivate write path, `AdminProductController` + admin screens, and the events in §9.

**Explicitly out of scope:** `product_variants` stays schema-only — no service/UI logic populates or reads it yet (built ahead of need only at the schema level, since Inventory's per-location stock design in domain #5 needs to know whether it's tracking stock per-product or per-variant; deferring the *behavior* avoids guessing at variant requirements with zero real product data to base them on). Full EAV attribute system (explicitly rejected for now, see §2).

## 20. Future Enhancements

Product variants (size/color/config) once real catalog needs justify the complexity, product bundles, B2B tiered pricing (customer-segment-specific pricing, depends on Customers domain existing first), full-text/AI-assisted search (AI domain), supplier cost visibility on the admin product screen (depends on Suppliers domain).
