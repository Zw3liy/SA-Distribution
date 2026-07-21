# Enterprise Foundation Report — SA Business Distribution

**Phase:** 2 — Enterprise Foundation Recovery
**Date:** 21 July 2026
**Scope:** Restore the storefront to a stable, runnable state. No new features, no business-logic redesign, no enterprise modules introduced.

---

## Issues fixed

1. **Broken entry points (critical, application-breaking).** All 13 page-level PHP files — `index.php`, `login.php`, `register.php`, `logout.php`, `cart.php`, `cart-api.php`, `products.php`, `product-details.php`, `account-dashboard.php`, `account-edit.php`, `wishlist.php`, `quote-request.php`, `quote-api.php` — were moved from `AI_Agentina/` back to the repository root, where their `require_once __DIR__ . '/includes/init.php'`-style paths correctly resolve against `includes/`, `config/`, `components/`, `services/`, `repositories/`, and `controllers/`. Git history confirmed these files were relocated into `AI_Agentina/` in a previous commit without updating their internal paths, which is why the application could not run at all.

2. **Missing require in `quote-api.php`.** It instantiated `ProductRepository` without ever requiring `repositories/ProductRepository.php`, causing a fatal "class not found" error on every request. Added the missing `require_once`.

3. **Type-mismatch bug in `CartService`.** `addProductToCart()` and `updateCartItemQuantity()` treated the array returned by `ProductRepository::getProductBySlug()` as an object (`$product->id`, `$product->stock`, etc.). The repository actually returns a plain associative array. This silently nulled out every field being inserted and made the database-backed cart API (`cart-api.php`) fail on every add/update with a `NOT NULL` constraint violation. Fixed both methods to use array access matching what the repository returns.

4. **~150MB of committed binaries.** Removed the portable PHP 7 runtime (`php.exe`, 30+ `.dll` files, `.lib`, `.phar`) and its bundled distribution docs (`install.txt`, `license.txt`, `news.txt`, `snapshot.txt`) from both the repository root (`ext/`, `sasl2/`, `dev/`, `lib/`, `extras/`) and `AI_Agentina/`.

5. **Debug/diagnostic artifacts.** Removed the path-troubleshooting scripts left over from the original breakage (`diag_test.php`, `phpdiag.php`, `check_paths.php`, `one_drive_path_test.php`, `path_test_short.php`, `tmp_test.php`, `read_dir.php`, `test_php.cmd`, `output.html`) and an unused duplicate database class (`AI_Agentina/includes_db.php`).

6. **Obsolete prototype.** Removed `AI_Agentina/index.html` and `main.css`, an earlier Swiper/AOS/GSAP-based draft superseded by the current `index.php` + `css/styles.css` + `js/main.js` build, plus two stray zero-byte placeholder files (`style`, `python`).

7. **Committed secrets.** `AI_Agentina/.env`, `API key.md`, and `Anthropic Key.txt` were tracked in git. Untracked them (`git rm --cached`) while leaving the files on disk so the local AI CLI tool keeps working, and strengthened `.gitignore` to catch secrets and vendored binaries going forward.

8. **Stray files.** Removed empty placeholder files (`New Microsoft Word Document.docx`, `New Text Document.txt` at root) and a committed `__pycache__/*.pyc` artifact.

9. **Documentation gap.** Added a root `README.md` (setup steps, project structure, known risks) and `.env.example`. Consolidated two duplicate copies of a PHP-install batch script (`AI_Agentina/New Text Document.txt` and `administration.md`, identical content) into one canonical `scripts/setup-windows.bat`.

## Files modified

Full detail is in the commit (`git log`, "Phase 2: Enterprise foundation recovery"). Summary: 13 files moved (root ← `AI_Agentina/`), 2 files edited for bugs (`quote-api.php`, `services/CartService.php`), ~110 files deleted (binaries, debug scripts, secrets, stray files), 4 files added (`README.md`, `.env.example`, `scripts/setup-windows.bat`, and this report). Net: 719 insertions, 10,887 deletions.

## Verification results

Verified against a real PHP 8.1 + MariaDB 10.6 instance (not a simulation), with the actual `database/*.sql` schema and seed data loaded.

| Check | Result |
|---|---|
| `php -l` on every PHP file | Clean — no syntax errors |
| Database connectivity | Connects, schema + seed load without error, queries return expected rows |
| `index.php`, `products.php`, `product-details.php`, `login.php`, `register.php`, `cart.php`, `wishlist.php` | All HTTP 200, render without PHP warnings/errors |
| `css/styles.css`, `js/main.js` | HTTP 200, correct byte size |
| Registration → login → authenticated dashboard | Full flow works, including CSRF token validation |
| Unauthenticated access to `account-dashboard.php` | Correctly redirects to `login.php` |
| Session-based cart (`cart.php`) | Add persists, appears on cart page |
| Database-backed cart (`cart-api.php`) | Add persists to `cart_items` table (previously fatal — see fix #3) |
| Quote submission (`quote-api.php`) | Creates a `quotes` row with correct VAT math (verified: R24,999 → R28,748.85 at 15% VAT) |
| Wishlist add/view | Works |
| Logout | Clears session; subsequent dashboard access redirects to login |
| Unknown product slug | Returns HTTP 404 with proper not-found page |

## Remaining risks

- **Secrets already in git history.** Untracking the secret files stops *future* commits from carrying them, but the old commit that introduced them still has the blobs. Anyone with a clone of this repository (or if it's ever pushed to a shared remote) can retrieve those credentials from history. They should be treated as compromised and rotated regardless of the untracking done here; a history rewrite (`git filter-repo` or BFG) is a separate, deliberate action this phase did not perform since it changes commit hashes and needs your sign-off first.
- **A loose SSH private key file** (`ssh-key-2026-07-19 (1).key`) sits untracked in `AI_Agentina/`. It's now gitignored so it can't be accidentally committed, but it should be moved out of the repository folder and the key rotated if there's any doubt about exposure.
- **Two parallel cart implementations remain.** `cart.php` uses a session-only cart; `cart-api.php` uses the database-backed one. They don't share state. This was flagged in the original audit and deliberately left alone here — fixing it would mean choosing one as canonical, which is a business-logic decision, not a structural repair.
- **No automated tests.** Verification in this phase was a manual, scripted smoke test, not a regression suite. A future change could reintroduce any of these bugs without anything catching it.
- **No autoloader or router.** Every page still wires up its own controller/service/repository chain by hand. This was the structural root cause of the original breakage and, while now fixed, remains fragile against the next file move.
- **Wishlist is still session-only**, not persisted to the database (no `wishlist` table exists).

## Recommended next phase

Per the original audit's roadmap, with the P0 items now resolved:

1. Composer + PSR-4 autoloading, so pages stop hand-chaining `require_once` and can't break the same way again.
2. A minimal front controller/router to remove the duplicated `<head>`/boilerplate across pages.
3. A PHPUnit test harness, starting with the service layer (`AuthService`, `CartService`, `QuoteService`) — these are already unit-testable and would have caught fix #3 automatically.
4. A deliberate decision on the cart architecture: unify `cart.php` and `cart-api.php` onto one persistence model.
5. Only after the above: begin the Digital Commerce Ecosystem build-out (checkout/orders, admin portal, and the wider module set), as originally scoped.
