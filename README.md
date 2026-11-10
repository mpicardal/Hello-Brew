# Hello Brew! — café e-commerce (PHP + MySQL)

Order coffee, pastries and rice meals online. Customers browse, search and filter the menu, keep a cart across pages, check out with a mock payment, track every order and request cancellations/returns. Staff run the café from a role-based portal with analytics.

**Stack:** PHP 8.1+ (no framework, no Composer), MySQL 8 / MariaDB 10.4+ (InnoDB, utf8mb4), plain CSS and JS.

## Run it (VS Code terminal)

Needs PHP 8.1+ with `pdo_mysql`, `gd`, `mbstring`, `fileinfo` (all included in XAMPP) and MySQL running.

```bash
cd hello-brew
php database/setup.php --demo        # creates .env, database, menu, accounts (+ sample sales)
php -S localhost:8000 -t public public/router.php
```

Open http://localhost:8000. The setup prints one login per role with a random password — save them.
- Different MySQL login: `php database/setup.php --db-user=root --db-pass=yourpass --demo`
- Start over (wipes data): add `--fresh`
- Lost a password: `php database/reset_password.php admin@hellobrew.local`
- XAMPP Apache instead of `php -S`: put the folder in `htdocs`, set `APP_BASE=/hello-brew/public` in `.env`, open http://localhost/hello-brew/public

Test cards (no real money): `4242 4242 4242 4242` succeeds, `4000 0000 0000 0002` is declined. Coupons: `HELLOBREW` (10%), `BREWBREAK` (₱50 off ₱400+).

## Roles and what each can see

| Role | Where | Can do |
|---|---|---|
| Customer | Store + My account | Browse, search, filter, cart, checkout, order history and tracking, cancel/return requests, reviews, saved addresses |
| Admin | /admin | Everything, including refunds, coupons, staff accounts, email log, audit log |
| Staff | /admin | Products, stock, categories, orders, returns (approve/reject), customers, reviews, analytics |
| Fulfillment | /admin | Order queue, status updates, courier assignment. Customer emails are masked |
| Courier | /courier | Only orders assigned to them: address, phone, notes and cash to collect. No emails |

Public sign-up only creates customers. Staff accounts are created by an admin. Every page checks the role on the server.

## Use cases covered

Browsing (catalog, search, category/price filters, sort, product details) · Account (register, log in, profile, addresses, order history) · Buying (add/update cart, coupon, checkout with stock and address validation, mock payment gateway) · Order processing (confirm, prepare, pack, inventory deducted on order) · Delivery (assign courier, out for delivery, confirm delivery, customer tracker) · Returns and refunds (request cancellation/return, approve/reject, restock, refund) · Admin (products with image upload, variants, stock, categories, coupons, customers, staff roles) · Email service (confirmation, status updates, cancellations, refunds, all logged in Email log) · Analytics (sales, revenue, refunds, AOV, top products, sales by category, conversion funnel, cart and checkout abandonment, top searches, low-stock alerts, CSV export).

## Security

- Passwords hashed with `password_hash` (bcrypt) and verified with `password_verify`; login throttled to 5 tries per 15 minutes; timing-safe for unknown emails
- PDO prepared statements everywhere (real, not emulated) — no SQL injection
- All output escaped with `htmlspecialchars`; strict Content-Security-Policy with no inline scripts
- CSRF token on every POST; logout is POST-only
- Session cookie HttpOnly, SameSite=Lax, Secure on HTTPS; ID regenerated on login and every 15 min; 30-minute idle timeout
- Customers can only open their own orders (queries are scoped by user id)
- Prices, stock and coupon rules are re-checked on the server inside a locked transaction at checkout
- Card numbers and CVC are never stored (last 4 digits only)
- Uploads: type-checked, max 2 MB, re-encoded with GD under a random name, and script execution disabled in `uploads/`
- `.env`, `config/`, `includes/` and `database/` sit outside the web root
- Analytics are first-party: no third-party trackers, no IP addresses stored, sessions stored as keyed hashes, staff activity not tracked
- Audit log of staff actions, including who viewed a customer's details

## Folder layout

```
config/      settings read from .env
database/    schema.sql, seed.sql, setup.php, reset_password.php
includes/    database, security, auth/roles, cart, orders, payment, mail, analytics, layouts
public/      web root: store pages, account/, admin/, courier/, assets/, uploads/
```
