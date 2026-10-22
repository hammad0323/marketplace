# Testing & Launch Checklists

## Functional testing checklist

### Storefront
- [ ] Homepage loads; hero autoplays, arrows/dots/swipe work; sections appear in the admin order.
- [ ] Mega menu (desktop) and drawer menu (mobile) open; all links resolve (no 404s).
- [ ] Announcement bar rotates; sticky header hides on scroll down and returns on scroll up.
- [ ] Search overlay shows instant suggestions; `/search?q=…` lists results.
- [ ] Category page: subcategory chips, filters (price, size, colour, fit, fabric, pattern, occasion,
      season, brand, availability), sorting and pagination all work, on desktop and mobile.
- [ ] `/category/shoes` redirects to `/category/footwear` (redirect manager).
- [ ] Product page: gallery thumbnails, hover zoom, full-screen lightbox, video (if set).
- [ ] Adding to bag without choosing size/colour shows a warning; unavailable combinations are
      struck through; sold-out variants cannot be added.
- [ ] Price/SKU/stock update when switching variants; colour-specific images swap.
- [ ] Customisation fields appear only on customisable products and add the fee.
- [ ] Size guide modal, delivery estimate by city, wishlist heart, share links.
- [ ] Quick view from product cards adds to bag.
- [ ] Bag drawer: quantities, remove, free-delivery progress; bag page: coupon apply/remove.
- [ ] Reviews: signed-in customer can review; review appears after approval.
- [ ] Newsletter: invalid email and missing consent are rejected; duplicate signup handled;
      unsubscribe link works.
- [ ] Contact form stores the message (Admin » Newsletter & Messages).
- [ ] 404 page, `/sitemap.xml`, `/robots.txt` render.

### Checkout & orders
- [ ] Guest checkout with COD: delivery fee changes with city/province; COD disabled for zones where
      it is off; express option shown where configured.
- [ ] Order confirmation page shows totals; stock decreased (Admin » Inventory » Stock movements).
- [ ] Double-clicking *Place Order* / refreshing creates only one order.
- [ ] Coupon limits: min order, expiry, per-customer limit, total uses.
- [ ] Optional account creation at checkout signs the customer in; saved address is reused next time.
- [ ] Track order with order number + email or phone.
- [ ] Customer account: orders, addresses (add/edit/default/delete), profile, password change,
      forgot/reset password (requires email setup).

### Admin
- [ ] Forced password change on first sign-in; rate limiting after repeated wrong passwords.
- [ ] Each role sees only its permitted areas (try a Store Manager, Order Manager, Content Manager).
- [ ] Create a product with variants (Generate rows), images, SEO; duplicate it; change slug →
      old URL redirects; delete it.
- [ ] Categories (nested), collections (ordered products), sizes & colours.
- [ ] Order workflow: confirmed → processing → courier & tracking → shipped → delivered + cash collected;
      dashboard sales update only when paid.
- [ ] Cancel an order → stock restored, coupon use released.
- [ ] Refund (partial and full) with/without restock.
- [ ] Invoice and packing slip print correctly; CSV export opens in Excel.
- [ ] Homepage builder: add a section as Draft, preview it, publish it, reorder, hide.
- [ ] Theme changes (colours, fonts, card ratio) appear on the store; *Restore defaults* works.
- [ ] Navigation editor: add a link, a dropdown child, change a mega-menu image.
- [ ] Payment method states behave as documented in PAYMENTS.md.
- [ ] Audit log records the changes above.

### Online payments (per gateway, in sandbox)
- [ ] Successful payment → order *Paid* + *Confirmed*; transaction log shows valid signature.
- [ ] Declined / cancelled payment → *Failed*; customer can retry with *Pay now*.
- [ ] Closing the browser after paying still marks the order paid (via IPN/webhook or cron verification).
- [ ] Amount tampering is rejected (transaction log shows *amount_mismatch*).

### Quality
- [ ] Mobile (iPhone/Android widths), tablet, desktop: no horizontal scrolling, readable text.
- [ ] Keyboard navigation and visible focus; "reduce motion" device setting disables animations.
- [ ] Google PageSpeed Insights / Lighthouse on home, category and product pages.
- [ ] Rich Results Test on a product page (Product, Breadcrumb) and the FAQ page.

## Launch checklist

- [ ] `config/config.php`: production DB, `SITE_URL` with https, unique `APP_KEY`, `APP_ENV = production`.
- [ ] SSL active and *Force HTTPS* enabled in `.htaccess`.
- [ ] Default admin email changed; strong unique passwords for every admin; unused admins removed.
- [ ] Sample products, photos, collections and slides replaced with real ones (or deactivated).
- [ ] Demo coupons (`WELCOME10`, `FREESHIP`, `URBAN500`) reviewed or deactivated.
- [ ] Store name, logo, favicon, phone, email, address, WhatsApp, social links correct.
- [ ] All policy pages reviewed (Shipping, Returns, Privacy, Terms, Payment, Delivery, FAQs, Size Guide);
      the *“review before launch”* notes removed.
- [ ] Delivery zones, fees, free-delivery threshold and delivery times match your courier contracts.
- [ ] COD settings and areas confirmed.
- [ ] Online gateways tested in sandbox, switched to live, small live payment verified, *Testing completed* ticked
      — or left disabled.
- [ ] Email sending tested (order confirmation, status update, password reset).
- [ ] Cron job added for `tools/cron.php`.
- [ ] *SEO*: site-wide noindex **off**, Search Console verified, sitemap submitted, analytics IDs set.
- [ ] `robots.txt` reviewed.
- [ ] Database backup schedule set up (cPanel » Backup / JetBackup); `uploads/` included.
- [ ] `database/` folder removed from the server (optional, it is already blocked from the web).
- [ ] Place one real COD order end-to-end and one real online order, then cancel/refund them.
