# Fahim Store — Architecture & Business Rules

## 1. Architecture

```
Browser ──► Apache (.htaccess) ──► index.php (router) ──► pages/*.php ──► partials/*
                                  └─► api/*.php (JSON)
          ──► admin/*.php (own .htaccess, session + permission guard) ──► admin/ajax/*.php
All entry points ──► includes/bootstrap.php ──► config · database · security · auth · catalog · search
                                                 inventory · cart · orders · notify · seo · upload
```

- **Procedural, modular PHP.** Each file in `includes/` owns one domain and exposes functions. Pages contain only request handling and view markup.
- **One source of truth for money and stock.** Cart, checkout, the rashan builder and the POS all go through the same functions:
  - `sellable_item()` and `price_lines()` for prices;
  - `order_create()` for orders;
  - `stock_change()` for stock.
- **Database access** goes only through `db_query()`, `db_all()`, `db_row()`, `db_val()`, `db_exec()` and `db_insert()`. These always use prepared statements. `db_tx()` gives nested-safe transactions.

## 2. Database (52 tables, InnoDB, utf8mb4)

| Area | Tables |
|---|---|
| Admin & security | `admins`, `admin_roles`, `admin_permissions`, `login_attempts` |
| Customers | `customers` (registered, guest and POS customers share one table, unique by phone), `customer_addresses` |
| Catalog | `categories` (self-referencing `parent_id`, unlimited depth, globally unique slug), `brands`, `products`, `product_categories` (many-to-many), `product_images`, `product_variations`, `product_tags`, `related_products` |
| Inventory | `inventory` (one row per product + variation; `variation_id = 0` is the base product), `inventory_movements` (every change), `suppliers`, `purchases`, `purchase_items` |
| Shopping | `carts`, `cart_items`, `wishlists`, `wishlist_items`, `coupons`, `coupon_usage` |
| Orders | `orders` (item prices and names are copied into the order when it is placed), `order_items`, `order_status_history`, `payments`, `pos_sessions`, `pos_sales` |
| Rashan | `grocery_lists`, `grocery_list_items` |
| Content | `banners`, `homepage_sections` (JSON settings), `homepage_section_items`, `promotions`, `promotion_products`, `testimonials`, `pages`, `page_faqs`, `newsletter_subscribers`, `contact_messages` |
| SEO & search | `seo_metadata` (per entity: product, category, brand, page, promotion, static), `redirects`, `search_synonyms`, `search_logs` |
| System | `settings` (key/value), `notifications`, `delivery_zones`, `payment_methods` |

**Conventions**
- Money columns are `DECIMAL(12,2)`. Every table has `created_at` and `updated_at`.
- Soft deletes (`deleted_at`) on products, categories, brands, customers, admins and suppliers. Old orders keep their item snapshot even if a product is later deleted.
- Unique constraints: SKU, slugs, coupon codes, customer phone and email, and cart/list line combinations.

## 3. Customer shopping flow

1. **Browse** by category (main → sub → child → products), use search, or open the homepage sections.
2. **Add to cart** (AJAX). Validated against stock and min/max quantity. Guest carts use a cookie and are merged into the account when the customer logs in.
3. **Cart.** Changing quantities or applying a coupon is recalculated on the server.
4. **Checkout.** The customer:
   - chooses delivery (area, so the fee is known) or pickup (time slot);
   - enters contact details;
   - picks a payment method;
   - can optionally create an account.
5. **`order_create()`.** Inside one transaction it:
   - re-prices every line;
   - locks the inventory rows and validates stock;
   - applies the coupon, delivery fee and minimum-order rules;
   - creates the order, history, payment and coupon-usage rows;
   - deducts stock according to the stock policy.

   Notifications are sent after the transaction commits, so a mail failure can never lose an order.
6. **After ordering.** The customer sees the success page with the order number and can track it later (order number + phone).

## 4. Monthly Rashan flow

1. Products flagged `is_rashan_item` appear grouped by their primary category.
2. The customer sets a budget and adds items, choosing size and quantity. Totals update instantly in the browser and are then verified by the server (`api/rashan` → `calc`).
3. Guests keep their draft in browser storage. Logged-in customers can save lists, keep several, edit them, duplicate one for the new month, add notes and delete lists.
4. **Order this Rashan** replaces the cart with the list and opens checkout marked as a rashan order. The order is created with `order_type = 'rashan'` and linked to the list.
5. In admin, the order triggers a notification and appears in *Monthly Rashan Orders* and in the dashboard alert. Staff print a picking list grouped by category and move the order through its statuses. The customer is notified by email and WhatsApp when it is *Ready for Pickup* or *Out for Delivery*.

## 5. POS flow

1. The cashier opens the register (opening cash) and scans a barcode or searches.
2. Optionally the cashier selects or creates a customer and adds a discount (amount or %).
3. The cashier chooses Walk-in, Pickup (date/time) or Delivery (area, address, fee), then the payment method.
4. **Complete sale:**
   - Walk-in sales go straight to `completed`, paid. Pickup/delivery sales become `confirmed`, paid.
   - A `pos_sales` row records cash received and change.
   - Stock is deducted and the receipt is printed.
5. **Save pending:** creates a `pending` order for a shop customer's monthly rashan, with a picking list. It is updated later to Processing → Ready → Completed.
6. **Save draft:** an incomplete order (`status = 'draft'`) that never touches stock. It can be reloaded from the drafts list and then completed.
7. **Close register:** expected cash = opening cash + cash sales. The cashier enters the counted cash and the difference is stored.

The POS and the website use the same `inventory` rows, so stock is always shared.

## 6. Admin permissions

`dashboard`, `categories`, `products`, `import`, `inventory`, `orders`, `customers`, `content`, `coupons`, `delivery`, `reviews`, `seo`, `pos`, `reports`, `admins`, `settings`.

Roles are built from these permissions. A role marked Super Admin always has every permission, and the system refuses to remove or disable the last active Super Admin.

Starter roles:
- **Super Admin** — everything.
- **Store Manager** — everything except admin users and settings.
- **Cashier** — dashboard, POS, orders, customers.
- **Inventory Staff** — dashboard, products, categories, inventory, import.

Every admin page calls `admin_require()`, and every AJAX endpoint checks its permission. Admin sessions are tied to the browser's user agent and expire after 2 hours of inactivity.

## 7. Order statuses & inventory logic

Statuses: `draft` (POS only) → `pending` → `confirmed` → `processing` → `ready_for_pickup` | `out_for_delivery` → `completed`; any status can move to `cancelled`.

Stock policy (*Settings → When to deduct stock*):

| Policy | Stock is deducted when the order reaches… |
|---|---|
| `placed` (default) | any status except draft/cancelled (i.e. at placement) |
| `confirmed` | confirmed, processing, ready, out for delivery or completed |
| `completed` | completed only |

How this stays correct:
- **No double deductions.** `orders.stock_deducted` is the guard. `order_deduct_stock()` locks the order row (`SELECT … FOR UPDATE`) and only runs while the flag is 0.
- **Cancelling** a deducted order runs `order_restock()` exactly once and logs a `cancel_restock` movement. Re-opening a cancelled order deducts again if the policy requires it.
- **Stock is always checked** at order placement, even when the policy deducts it later.
- **Every stock change** goes through `stock_change()`. It runs in a transaction, locks the row and writes `inventory_movements` with the type (opening, purchase, sale, pos_sale, adjustment, cancel_restock, import, return, damage), quantity change, resulting balance, reason, reference and admin.
- **Purchases** add stock only once, when received. Received purchases are locked.
- **Low-stock and out-of-stock alerts** are created when stock first drops past the threshold.
