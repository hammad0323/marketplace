# Payment Configuration

All payment methods are managed in **Admin » Payment Methods**. Credentials are stored encrypted
(AES-256-GCM with `APP_KEY` from `config/config.php`), are never shown back in full, and are never
sent to the browser.

## How payments are protected

- Customers pay on the provider's **hosted page** — card numbers, CVVs, wallet PINs never reach
  this server, and nothing card-related is stored.
- Totals are recalculated server-side at order placement; the amount sent to the gateway is the
  saved order total, and the amount reported back must match it exactly.
- An order is marked **paid only after server-side verification**: a signed callback/webhook plus
  (for JazzCash and Easypaisa) a server-to-server status inquiry. A browser redirect alone never
  marks an order paid.
- Payment updates are idempotent (row lock + status check), so duplicate callbacks are harmless.
- Every request/callback/inquiry is logged in `payment_transactions` and shown on the order page
  (*Payment transactions*), giving you a reconciliation trail.
- Order status and payment status are tracked separately: `pending / confirmed / processing /
  shipped / delivered / cancelled / refunded / on hold` and `unpaid / awaiting payment / paid /
  failed / cancelled / refunded / partially refunded`.
- Unpaid online orders are cancelled automatically after *Store Settings » Orders » Cancel unpaid
  online orders after (hours)* (default 48) and their reserved stock is released.

## Availability rules (the "go-live gate")

| State | Meaning | Shown to customers? |
|---|---|---|
| Disabled | switched off | No |
| Credentials missing | enabled but required fields empty | Shown as *Currently unavailable* |
| Sandbox — admins only | sandbox mode with credentials | Only to signed-in admins (for test orders) |
| Live — awaiting test confirmation | live credentials but *Testing completed* not ticked | Shown as *Currently unavailable* |
| Available | live + tested | Yes |

Changing credentials or mode automatically clears *Testing completed*.

## Cash on Delivery

Enabled by default. Options: COD fee, minimum / maximum order value, checkout description and the
product-page note. **COD availability by area** is set per delivery zone (*Delivery & Shipping »
zone » Cash on Delivery available*); the checkout disables COD for zones where it is off.

When the courier collects the cash, open the order and click **Mark cash collected** (or tick
*cash collected* when setting the status to Delivered). That marks the order paid and it then counts
towards sales on the dashboard.

## JazzCash (Hosted Checkout / Page Redirection v1.1)

1. Obtain a JazzCash merchant account. From the JazzCash merchant portal collect:
   **Merchant ID**, **Password** and **Integrity Salt**.
2. Admin » Payment Methods » JazzCash: enter the three values, keep **Mode = Sandbox**, tick
   **Enabled**, save.
3. In the JazzCash portal set:
   - Return URL: `https://YOUR-DOMAIN/api/payments/jazzcash-return.php`
   - IPN URL: `https://YOUR-DOMAIN/api/payments/jazzcash-ipn.php`
4. Signed in as admin, place test orders with JazzCash using the sandbox test wallets/cards supplied
   by JazzCash. Check each order shows **Paid** and the transaction log shows a valid signature and a
   successful inquiry. Also test a declined/cancelled payment.
5. Switch **Mode = Live** (enter live credentials if different), place a small real order, then tick
   **Testing completed**.

Technical notes: requests are signed with HMAC-SHA256 of the sorted `pp_` fields using the integrity
salt (`pp_SecureHash`); responses are verified the same way, then confirmed via the Payment Inquiry
API. The checkout and inquiry endpoints default to JazzCash's published URLs and can be overridden
in the form if JazzCash gives you different ones. Confirm the response codes your account returns
during sandbox testing (`000`/`121` = success; `124`/`157` = pending) — the full exchange is visible
in the order's transaction log.

## Easypaisa (Easypay Hosted Checkout)

1. Obtain an Easypay merchant account. Collect: **Store ID**, **Hash Key**, the **API
   username/password** for the transaction-inquiry API, and the **merchant account number**.
2. Enter them in Admin » Payment Methods » Easypaisa (Sandbox), choose the payment method
   (`MA_PAYMENT_METHOD` for Easypaisa wallet, `CC_PAYMENT_METHOD` for cards, `OTC_PAYMENT_METHOD`
   for over-the-counter), enable and save.
3. In the Easypay merchant portal set the IPN URL to
   `https://YOUR-DOMAIN/api/payments/easypaisa-ipn.php`. The post-back URL
   (`/api/payments/easypaisa-return.php`) is sent with each request automatically.
4. Test in sandbox as for JazzCash, then switch to Live and tick **Testing completed**.

Technical notes: `merchantHashedReq` is AES-128-ECB of the sorted request string with the hash key.
The flow posts the returned `auth_token` to `Confirm.jsf`; on return and on IPN the payment is
verified with the `inquire-transaction` API (status `PAID`, response code `0000`). Endpoints can be
overridden if Easypaisa issues different URLs for your account.

## Debit / Credit Cards (Stripe Checkout)

Stripe is supported where Stripe is available to your business. (If your business cannot open a
Stripe account, leave this disabled — JazzCash and Easypaisa hosted pages also accept cards.)

1. In the Stripe dashboard create a **Secret key** (`sk_test_…` for testing).
2. Add a webhook endpoint `https://YOUR-DOMAIN/api/payments/stripe-webhook.php` with events
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `checkout.session.async_payment_failed`, `checkout.session.expired`; copy its **Signing secret**
   (`whsec_…`).
3. Enter both in Admin » Payment Methods » Debit / Credit Card. Currency defaults to the store
   currency (PKR); set another ISO code if your Stripe account settles differently.
4. Test with Stripe test cards (e.g. 4242 4242 4242 4242), then switch to live keys, set Mode = Live,
   place a real order and tick **Testing completed**.

Technical notes: orders are confirmed by the signed webhook (HMAC-SHA256 `Stripe-Signature`, 5-minute
replay window) or by retrieving the Checkout Session server-side on the success page.

## Refunds

Use the **Refund** panel on the order (permission *Cancel & refund orders*). It records full or
partial refunds, updates payment/order status, optionally returns items to stock and logs the
event. Send the money itself through the provider's merchant portal or bank transfer.

## Manual payment recording

The *Payment* panel lets you record a payment status manually (e.g. after verifying a payment in
the merchant portal when a callback never arrived). For online methods you must tick *I verified this
payment in the merchant portal*. Every manual change is written to the audit log and transaction log.
The **Verify with gateway** button runs a fresh server-to-server status check.
