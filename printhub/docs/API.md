# PrintHub REST API (v1)

Base URL: `https://yourdomain.com/api/v1`

Every response is JSON with the same envelope:

```json
{ "status": true, "message": "Order submitted successfully", "data": { } }
```

On errors `status` is `false`, `message` is a human-readable reason and the HTTP code is set (400/401/402/403/404/409/422/429/500). Validation errors include `data.errors` (array of strings).

| Code | Meaning |
|------|---------|
| 401 | Missing, expired or revoked token |
| 402 | Shop subscription expired / suspended: read-only access, writes blocked |
| 403 | Forbidden (for example a cookie request without the CSRF header) |
| 404 | Not found, or not owned by the caller (other shops' data always looks "not found") |
| 409 | Shop is not accepting orders right now |
| 422 | Validation failed |
| 429 | Rate limit reached: wait and retry |

## Authentication

| Caller | How |
|--------|-----|
| Shop owner (app) | `POST /auth/login` returns a token. Send `Authorization: Bearer <token>`. Tokens are random 256-bit values; only their SHA-256 hash is stored. They expire after `TOKEN_TTL_DAYS` (30 by default) and are revoked by logout or a password change. |
| Shop owner (web dashboard) | The same token in an httpOnly `ph_owner` cookie, set by `/owner/login.php`. |
| Customer (app) | `POST /customer/session` returns an anonymous session token. Send `X-Customer-Token: <64 hex>`. No sign-up is needed. |
| Customer (web) | httpOnly `ph_cs` cookie. |

Send `X-Client: app` from mobile apps; orders are then tagged as app orders.

**CSRF:** browser requests that authenticate with a cookie and change data (POST, PUT, PATCH, DELETE) must send `X-Requested-With: XMLHttpRequest` from the same origin. Bearer and `X-Customer-Token` requests are not affected.

**Isolation:** the shop is always taken from the authenticated token on the server. `shop_id` and `user_id` values sent by the client are ignored.

**CORS:** same-origin always works. You can allow more browser origins (for example a Flutter web build) in `CORS_ORIGINS`. Cookies are never sent cross-origin.

**Rate limits** (per IP or per session): login 20 per 15 min, forgot password 5 per hour, uploads 60 per hour per session, orders 15 per hour per session, price checks 240 per 5 min, previews 40 per 10 min, tracking 60 per 5 min, global 600 per 5 min.

---

## Auth (shop owners)

| Method | Path | Body | Returns |
|--------|------|------|---------|
| POST | `/auth/login` | `username` (username, email or mobile), `password`, `device_name?` | `token`, `expires_in_days`, `user`, `shop`, `subscription` |
| POST | `/auth/logout` | – | revokes the current token |
| GET | `/auth/me` | – | `user`, `shop`, `subscription` (`state`: active/grace/expired/suspended/none, `can_work`, `days_left`, `end_date`, `grace_until`, `price`) |
| POST | `/auth/forgot` | `login` | always the same message; emails a 60-minute reset link if the account has an email |
| POST | `/auth/reset` | `token`, `password` | sets a new password |
| POST | `/auth/change-password` | `current_password`, `new_password` | required on first login (`user.must_change_password`). Other tokens are revoked. |
| POST | `/auth/profile` | `name`, `email`, `phone` | updated user |

There is no registration endpoint. Shop accounts are created by the platform admin.

## Owner (Bearer token required)

Write endpoints return **402** when the subscription is expired or suspended. The grace period still allows work.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/owner/dashboard` | `counts` (today, pending, accepted, printing, ready, completed, cancelled, revenue_today, revenue_month), `availability`, `subscription`, `new[]`, `recent[]`, `unread_notifications` |
| GET / POST | `/owner/shop` | GET returns `shop` and `settings`. POST accepts `name, phone, whatsapp, email, address, area, city, latitude, longitude, accepting_orders`. |
| POST | `/owner/shop/logo` | multipart `logo` (JPG/PNG/WebP) |
| POST | `/owner/settings` | `pricing_mode` (auto/quote), `payment_mode` (pay_at_shop/advance_optional/advance_required), `payment_instructions`, `min_order_charge`, `estimated_minutes`, `opening_hours`, `landing_heading`, `landing_message`, `theme_color`, `retention_days` (≤ platform max), `max_upload_mb` (≤ platform max) |
| GET | `/owner/subscription` | state, price, periods, payments, support contacts |
| GET / POST | `/owner/wifi` | `ssid`, `password`, `security` (WPA/WEP/nopass), `is_hidden_network`, `is_public`. The password is stored encrypted, and customers see it only when `is_public` is on. |
| GET | `/owner/wifi/qr.png` | Wi-Fi join QR (`WIFI:T:…;S:…;P:…;;`) |
| GET / POST | `/owner/qr` | design: `fg_color`, `bg_color`, `accent_color`, `show_logo`, `heading`, `subheading` |
| POST | `/owner/qr/regenerate` | new QR image version with default colours. The shop ID and URL never change. |
| GET | `/owner/qr.png?scale=6..30&download=1` | shop QR PNG |
| GET | `/owner/qr.pdf?download=1&no_wifi=1` | A4 poster PDF |
| GET / POST | `/owner/services` | GET returns categories, subcategories and option groups with `enabled`. POST accepts `categories{id:bool}`, `subcategories{id:bool}`, `options{id:bool}`, `notes{id:text}`. |
| GET | `/owner/pricing` | `rules[]`, `units`, `options`, `categories`, `min_order_charge` |
| POST | `/owner/pricing` | add a rule: `rule_type` (print/item/paper/finishing/discount), `category_id?`, `paper_size_id?`, `color?`, `sides?`, `paper_type_id?`, `gsm_id?`, `finishing_id` (for finishing), `unit` (per_page/per_sheet/per_copy/per_item/per_order; discount uses percent), `price`, `min_qty`, `is_active` |
| POST / DELETE | `/owner/pricing/{id}` | update or delete a rule |
| POST | `/owner/pricing/test` | item (`category_id, paper_size_id, color, sides, paper_type_id, pages, copies, quantity, finishing[]`) returns a price breakdown |
| GET | `/owner/orders?status=&q=&page=&per_page=` | status groups: `new, active, printing, ready, today, completed, closed`, or any single status |
| GET | `/owner/orders/{id}` | full order: items, files (temporary signed `url` and `download_url`), history, payments, `owner_actions` |
| POST | `/owner/orders/{id}/action` | `action` from `owner_actions`: `accept` (amount?), `quote` (amount, note?), `reject` (reason), `confirm_payment` (amount?, method), `printing`, `ready`, `complete` (amount?, method?) |
| GET | `/owner/reports?period=day\|week\|month\|quarter` | orders, completed, cancelled, revenue, average_order_value, daily[], top_categories[] |
| GET | `/owner/notifications?since=&limit=` | `items[]`, `unread` |
| POST | `/owner/notifications/read` | `id?` (omit to mark all as read) |

### Pricing engine

Each shop defines its own rates. There are no platform-wide default prices. For every item:

1. The **base price** comes from the matching `print` rules (per page or sheet) or `item` rules (per piece), whichever fits the category's pricing basis.
2. Rules match when every field they set equals the item's value. Empty fields mean "any".
3. **Quantity tiers:** among matching rules, the highest `min_qty` reached wins. On a tie, the more specific rule wins.
4. Then `paper` surcharges, `finishing` charges (per copy, piece or order) and `discount` percentages are applied.
5. The shop's minimum order charge tops up small orders.
6. If any part has no price, the order becomes a **quote request** and the shop sends the price.

## Public

| Method | Path | Notes |
|--------|------|-------|
| GET | `/platform` | name, support contacts, store links, max upload, accepted files, whether DOCX conversion is available |
| GET | `/shops/{SHOP-ID}` | public shop profile, `is_open`, Wi-Fi (only when the owner made it public), and the catalog (`categories[]` with subcategories and only the options this shop offers) |
| GET | `/shops/{SHOP-ID}/wifi.png` | Wi-Fi QR. Returns 404 unless shared publicly. |
| GET | `/catalog/categories` | global categories and options |

## Customer (X-Customer-Token or cookie)

| Method | Path | Notes |
|--------|------|-------|
| POST | `/customer/session` | `{token, is_new, expires_at}`. Keep the token in the app. |
| POST | `/shops/{SHOP-ID}/uploads` | multipart `file`: PDF, JPG, PNG, WebP or DOCX. The real file type is checked from the content, size is limited by the shop or platform, and the file is stored privately outside the web root. Returns `token, name, kind, pages, width, height, converted, needs_page_count, url, pdf_url, expires_at`. Temporary uploads expire after `temp_upload_hours`. |
| DELETE | `/uploads/{token}` | remove a temporary upload |
| POST | `/shops/{SHOP-ID}/price` | `items[]` (see the item format below). Returns `items[].lines`, `subtotal`, `minimum_adjustment`, `total`, `priced`, `pricing_mode`, `message`. |
| POST | `/shops/{SHOP-ID}/preview` | `files[]`, `paper_size_id`, `custom_width_mm`, `custom_height_mm`, `orientation`, `layout`, `page_selection`, `quantity`. Returns `{url, pages}`: a signed 30-minute link to the print-ready preview PDF. |
| POST | `/shops/{SHOP-ID}/orders` | `customer_name`, `customer_phone`, `instructions?`, `items[]`. Returns the order (including `order_number` and `tracking_url`). |
| GET | `/customer/orders` | orders placed from this session |
| GET | `/orders/{number}?t=` or `?phone=` | tracking: the tracking token or the order's mobile number is required |
| POST | `/orders/{number}/action` | `action`: `cancel`, `accept_quote`, `decline_quote`; `t` |
| POST | `/orders/{number}/payment` | `method` (bank_transfer/jazzcash/easypaisa/other), `reference?`, multipart `proof?` (image or PDF); `t` |
| GET | `/customer/notifications?since=` / POST `/customer/notifications/read` | order updates |

### Item format

```json
{
  "category_id": 1, "subcategory_id": 2,
  "paper_size_id": 1, "custom_width_mm": null, "custom_height_mm": null,
  "color": "bw", "sides": "single", "paper_type_id": 16, "gsm_id": 24,
  "orientation": "portrait", "finishing": [31],
  "copies": 2, "quantity": 1, "notes": "staple top-left",
  "files": ["<upload token>"],
  "page_selection": [{"page": 3, "rotate": 90}, {"page": 1, "rotate": 0}],
  "pages": 5,
  "layout": {"fit": "fit|fill|actual|grid|id_pair", "margin_mm": 5, "scale": 100,
             "rotate": 0, "flip": false, "crop": {"x": 0.1, "y": 0, "w": 0.8, "h": 1},
             "sheet": "4x6|a4", "gap_mm": 3},
  "cv_token": null
}
```

- `pages` is only used for DOCX files the server could not convert (the customer's estimate). Otherwise the server counts pages itself.
- Image edits are applied in this order: flip, rotate, crop. Crop values are fractions of the rotated image.
- The original files are always kept. The server builds a separate print-ready PDF for the shop.

## CV maker (customer session)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/cv/templates` | `templates[]` (id, key, name, name_ur, category, default_color, supports_photo, ats_friendly, preview_url), `sections`, `default_data` |
| GET / POST | `/cv` | list CVs, or create one with `template_id`, `title?`, `accent_color?`, `data?`, `section_order?` |
| POST | `/cv/preview` | the same fields plus `token?`. Returns `{html}` for a live preview. |
| GET / POST / DELETE | `/cv/{token}` | read (`?pages=1` adds the page count), autosave, delete |
| POST / DELETE | `/cv/{token}/photo` | multipart `photo`. Stored as a square JPEG with metadata removed. |
| GET | `/cv/{token}/pdf` | A4 PDF with real text (works with applicant tracking systems) |
| GET | `/cv/{token}/html` | print view |

To print a CV at a shop, send an order item with `cv_token`. The server attaches the generated PDF.

`data` structure: `personal{full_name,title,phone,email,city,address,website,linkedin,summary}`, `experience[{role,company,location,start,end,current,details}]`, `education[{degree,institute,location,start,end,grade,details}]`, `skills[]`, `projects[{name,link,details}]`, `certifications[{name,issuer,year}]`, `languages[{name,level}]`, `achievements[]`, `references[{name,position,company,phone,email}]`, `references_on_request`.

## Signed file links

`/files/{kind}/{id}?e=<expiry>&d=inline|attachment&s=<HMAC>`: temporary links to private files, signed with `APP_KEY`. Changing any parameter or using the link after expiry returns 403. Files are never served from a public folder.

## App links

- `/.well-known/assetlinks.json` (Android) and `/.well-known/apple-app-site-association` (iOS) are generated from **Admin → Settings → App links**.
- `/shop/{SHOP-ID}`, `/track/{number}?t=…` and `/cv/{token}` open the app when it is installed. Otherwise they open the responsive web app (a PWA), so customers never need to install anything.
