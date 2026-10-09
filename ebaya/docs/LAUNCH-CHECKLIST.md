# Testing & Launch Checklist

## Configuration

- [ ] `config/config.php`: production DB credentials, the correct `SITE_URL` (https), a unique `APP_KEY` (backed up somewhere safe), `APP_ENV = production`, and `FORCE_HTTPS = true`.
- [ ] SSL is active and the HTTPS redirect is uncommented in `.htaccess`.
- [ ] `https://your-domain/config/config.php`, `/includes/db.php` and `/database/ebaya.sql` all return **403**.
- [ ] Uploading a `.php` file is impossible (the upload validator rejects it), and `/uploads/` blocks script execution.
- [ ] The seeded admin password has been changed, the admin email updated, and staff accounts created with the correct roles.

## Content

- [ ] Logo, favicon, contact details, address, business hours and social links (Store settings).
- [ ] Real photography: products (front, back, side and detail, with alt text), hero slides (desktop and mobile), categories, collections, and the handcrafted and story sections.
- [ ] Product data is verified: fabric, lining, transparency, length, care, sizes, colours, stock, prices and lead times.
- [ ] Sample products, coupons (`WELCOME10`, `FREESHIP`) and testimonials are deleted or replaced. Only genuine testimonials are published.
- [ ] Every policy page is reviewed: Shipping, Returns & Exchanges, Privacy, Terms, Size Guide, Care Guide and FAQs.
- [ ] Announcement bar text and the free-delivery threshold match your shipping rates.

## Shipping & payments

- [ ] Delivery zones, cities, rates, free-delivery thresholds, minimum order, express delivery, and COD availability, fee and maximum per zone.
- [ ] COD: place a test order, mark it shipped, then delivered, then mark COD collected. The dashboard sales figure should increase.
- [ ] Each online gateway passes in sandbox: a success, a failure or cancellation, a duplicate callback (no change), and a refund.
- [ ] Each gateway passes in live mode with a real low-value order, then *Testing completed* is ticked.
- [ ] Callback and IPN/webhook URLs are registered with each provider.

## Email

- [ ] SMTP is configured (or PHP mail is enabled), the test email arrives, and *Send emails* is on.
- [ ] Order confirmation, status update and password reset emails arrive and the links work.
- [ ] The new-order notification email is set.

## Functional tests (desktop and mobile)

- [ ] Browse, filter, sort, paginate and search.
- [ ] Product page: you must select a size and colour before adding to the bag; sold-out combinations are disabled; the size guide, zoom and lightbox work.
- [ ] Customisation: length outside the allowed range is rejected; the fee and lead time are applied; you must confirm the custom-order terms.
- [ ] Cart: update and remove items, the stock limit is enforced, coupons apply and are rejected correctly, and the guest bag survives a browser restart.
- [ ] Guest checkout, checkout with "create account", and logged-in checkout with saved addresses.
- [ ] Double-clicking *Place order* creates **one** order.
- [ ] Two shoppers buying the last item: only one succeeds.
- [ ] Order confirmation page, guest order tracking, account order history.
- [ ] Cancelling an order restores stock, and the inventory history shows both movements.
- [ ] Newsletter subscribe (consent required), unsubscribe link, contact form.
- [ ] Reviews go into moderation; approving one updates the product rating.
- [ ] Homepage builder: save a draft, preview it (visitors still see the live version), then publish. Theme draft, preview and publish behave the same way.
- [ ] Each role's restrictions: for example, an Order Manager gets a 403 on Products and Settings.

## SEO

- [ ] `/sitemap.xml` lists the published products, categories, collections and pages; it has been submitted to Google Search Console (verification code added).
- [ ] `/robots.txt` is correct and *Discourage indexing* is **off**.
- [ ] Analytics IDs (GA4, GTM or Meta Pixel) are added and a test visit is recorded.
- [ ] Changing a product slug redirects the old URL (301).
- [ ] Rich-results test passes on a product page (Product and BreadcrumbList).

## Performance & backups

- [ ] Images are uploaded as JPG or WebP; the uploader resizes anything over 2400px.
- [ ] Lighthouse mobile check on the homepage and a product page.
- [ ] cPanel backups are scheduled (database plus `uploads/`), and `APP_KEY` is stored safely.
