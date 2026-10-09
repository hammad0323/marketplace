# Testing & pre-launch checklist

## Automated smoke test (staging only — it creates real orders)
```bash
php tools/smoke-test.php https://staging.example.com admin@example.com 'YourAdminPassword'
```
The test covers the following:
- Every public route, the 404 page, and blocking of `app/` and `install/`.
- Product JSON-LD, variant enforcement, the CSRF rejection, and the stock and quantity caps.
- Coupons, the delivery quote, a guest COD checkout with tampered price fields (which are ignored), and the duplicate-submit guard.
- Admin login and every admin screen.
- The order lifecycle: confirm, process, ship and deliver, then COD collection, invalid-transition refusal, the invoice and the CSV export.

Expected result: `All checks passed`. The admin account must not have a pending temporary password.

Syntax check of every PHP file:
```bash
find . -name '*.php' -not -path './assets/*' -exec php -l {} \; | grep -v 'No syntax errors'
```

## Manual test plan
| Area | Steps | Expected |
|---|---|---|
| Responsive | Browse home, listing, product, cart and checkout at 375px, 768px, 1024px and 1440px | No horizontal scroll, mobile menu and filters drawer work, sticky add-to-bag appears on mobile |
| Reduced motion | Turn on OS "reduce motion" | No parallax, reveal or Ken Burns animation; content fully visible |
| Variants | Pick each colour on a product | Price, stock, image and button update; sold-out colours are disabled |
| Stock | Set a variant to 1 and order 2 | Refused with a clear message |
| Checkout | Guest, new account during checkout, logged-in with a saved address | Order created, confirmation email sent, address saved |
| Delivery | Karachi, Lahore, another city, express, free threshold | Correct charges and estimates; COD fee applied only to COD |
| Coupons | Expired, below minimum, per-customer limit | Clear validation messages |
| Order tracking | `/track-order` with a wrong email, then the correct one | Generic error, then order details; rate-limited after 10 attempts |
| Admin roles | Sign in as an Order Manager | Product, settings and payments screens return "Access denied"; order actions work |
| Homepage builder | Edit a section, save as draft, preview, publish | Live site changes only after publishing |
| Slugs | Change a product slug | The old URL 301-redirects to the new one |
| SEO | View source on product and category pages; open `/sitemap.xml` and `/robots.txt` | Canonical, OG and JSON-LD tags present; sitemap lists published items |
| Payments | See [PAYMENT-GATEWAYS.md](PAYMENT-GATEWAYS.md) | Sandbox success, cancel and IPN paths verified |

## Pre-launch checklist
**Content and honesty**
- [ ] Replace every sample image (`assets/img/sample/…`) with real product photography, and add alt text to each image.
- [ ] Review product materials, finishes and descriptions. Publish only claims you can substantiate (leather type, handmade, origin, warranty).
- [ ] Delete the sample testimonials and turn **off** *Settings → Show sample content*. Add only genuine, permitted customer feedback.
- [ ] Rewrite the About, Shipping, Returns, Privacy and Terms pages (remove the "Store owner:" notes) and have the policies reviewed.
- [ ] Delete or replace the sample coupons `WELCOME10` and `FREESHIP`.

**Configuration**
- [ ] Use a unique `APP_KEY`, set `APP_ENV=production`, set `APP_URL` to `https://`, and keep the config file outside `public_html`.
- [ ] Change the default admin email and password, and create staff accounts with least-privilege roles.
- [ ] Fill in the contact phone, WhatsApp, emails, address, social links, logo and favicon (*Settings*).
- [ ] Set the shipping zones and rates, free-delivery threshold, COD fee and COD zones.
- [ ] Set up payment gateways (or leave them disabled), complete the sandbox tests, then switch to live.
- [ ] Configure SMTP email, test order confirmations, and set up SPF and DKIM.
- [ ] Add the cron job for `tools/cron.php`.

**SEO and analytics**
- [ ] Turn on *Allow search engines to index*, review robots.txt, and submit the sitemap in Search Console.
- [ ] Add the GA4 ID and verification codes. Add ads.txt lines only if you have real advertising partner IDs.

**Operations**
- [ ] Enable HTTPS and the force-HTTPS redirect.
- [ ] Set up automated backups (database and `uploads/`).
- [ ] Place and refund a real low-value order with every enabled payment method.
- [ ] Turn off maintenance mode.
