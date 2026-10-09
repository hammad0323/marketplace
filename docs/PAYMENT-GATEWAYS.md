# Payment gateway configuration

Configure gateways under **Admin → Settings → Payments** (Super Admin only by default).

> **Status:** Cash on Delivery works out of the box. JazzCash, Easypaisa and hosted card payments are **integration-ready but not live-tested**, because they need merchant accounts and credentials issued by each provider. They stay hidden at checkout until they are enabled *and* every required credential has been entered. Field names, endpoints and response codes follow the providers' published hosted-checkout guides. **Before going live, confirm them against the integration document issued with your merchant account and test end-to-end in the provider's sandbox.**

## How payments are secured
- Credentials are encrypted at rest with `APP_KEY` (libsodium). They are never echoed back to the admin form or sent to the browser.
- The customer pays on the **provider's hosted page**. Card numbers, CVVs and wallet PINs never touch this server.
- **An order is never marked paid because of a browser redirect.** It is marked paid only after a server-to-server status inquiry, or a signed server notification followed by an inquiry, confirms the payment and the amount matches the order exactly.
- Every interaction (initiate, return, callback, inquiry, refund, manual action) is logged in `payment_transactions` with signature validity and a redacted payload. You can review them under **Transactions** and on each order.
- Unique transaction references (`payments.reference`), row locks and idempotent "mark paid" logic prevent a payment from being processed twice.
- Stock is reserved when the order is placed. A failed or cancelled payment, or one left unpaid past the timeout (handled by cron), cancels the order and restores the stock and the coupon usage.
- If a payment arrives after the order was cancelled, the order moves to **On hold** for manual review.

## Cash on Delivery
- Enable or disable it, set an optional **COD fee** (added to the total and shown at checkout), and set an optional **maximum order value**.
- Turn COD on or off per delivery zone under **Shipping → zone → "Cash on delivery available"**.
- When the courier remits the cash, open the order and click **Mark COD collected**. This is recorded with the administrator and the time.

## JazzCash (Hosted Checkout / Page Redirection v1.1)
1. Obtain your **Merchant ID**, **Password** and **Integrity Salt** from the JazzCash merchant portal (sandbox first).
2. Enter them in the admin, leave the mode on **Sandbox**, and enable the gateway.
3. Register these URLs with JazzCash (they are also shown on the settings card):
   - Return URL: `https://your-domain/payment/jazzcash/return`
   - IPN URL: `https://your-domain/payment/jazzcash/ipn`
4. Flow: a signed form (HMAC-SHA256 over the sorted `pp_*` fields) is posted to the hosted page. The response is signature-checked and then confirmed with the **Status Inquiry API**. The order is marked paid only if the inquiry reports success.
5. Check the inquiry response codes in `app/gateways/jazzcash.php` (`jazzcash_inquire`) against your integration guide.

## Easypaisa (Easypay Hosted Checkout)
1. Obtain your **Store ID**, **Hash key**, **merchant account number** and **inquiry API username/password**.
2. Register these URLs with Easypaisa:
   - Post-back URL: `https://your-domain/payment/easypaisa/return`
   - Confirm post-back URL: `https://your-domain/payment/easypaisa/complete`
   - IPN listener: `https://your-domain/payment/easypaisa/ipn`
3. Flow: the request is signed with AES (`merchantHashedReq`), then an auth token is passed to `Confirm.jsf`, then the customer returns. The order is marked paid only when the **Inquire Transaction API** returns `PAID`. The IPN just triggers that inquiry, and only hosts listed under *Trusted IPN hosts* are contacted.

## Debit / credit cards
Cards are processed through the **hosted card page of JazzCash (MPAY) or Easypaisa (CC)**, whichever you select. Card acquiring must be enabled on that merchant account, and the selected provider must be fully configured first. If you later sign up with a different card processor, add an adapter in `app/gateways/` alongside the existing ones (`*_build_request`, `*_inquire`) and register it in `app/includes/payments.php`.

## Going live checklist
- [ ] A complete sandbox order succeeds, appears as **Paid** with a provider reference, and has a valid signature in **Transactions**.
- [ ] A cancelled sandbox payment cancels the order and restores stock.
- [ ] The IPN / server notification reaches your server (check **Transactions** for `callback` entries).
- [ ] Switch the mode to **Live**, enter the live credentials and place a low-value real order, then refund it.
- [ ] The cron job is running (`tools/cron.php`).

## Refunds
Process the refund in the provider's merchant portal, or by bank transfer for COD orders. Then use **Record a refund** on the order, with an optional restock, to keep the books reconciled. The platform does not call refund APIs automatically because those require provider-specific approval.
