-- Cart session_id uniqueness fix for the Orders conversion design
-- (found by tests/Integration/Orders/CartRepositoryTest.php).
--
-- Background: the Phase 3 cart schema declared session_id UNIQUE
-- (uq_cart_session), which was correct while there was at most one cart
-- row per session. The Orders domain (docs/specs/06-orders.md §2/§13)
-- then introduced the converted-cart design: after checkout the cart
-- row is NOT deleted but marked with converted_to_order_id, and the
-- next add-to-cart for the same session must create a brand-new cart
-- row (CartRepository::getCartIdBySession() excludes converted carts).
--
-- A fresh row per session is impossible while uq_cart_session still
-- forbids a second row with the same session_id -- the first
-- post-checkout add-to-cart would fail with error 1062 (Duplicate
-- entry). No code path relies on the uniqueness (every access filters
-- on converted_to_order_id IS NULL with LIMIT 1, or joins cart_items by
-- cart_id), so the fix is to demote the unique key to a plain lookup
-- index, matching the documented intent.
--
-- Apply once per environment, after 2026_07_21_orders_domain.sql.
-- (Deliberately a separate file rather than an amendment to the Orders
-- migration, so environments that already applied that migration get
-- an unambiguous single statement to run.)

ALTER TABLE `cart`
    DROP KEY `uq_cart_session`,
    ADD KEY `idx_cart_session` (`session_id`);
