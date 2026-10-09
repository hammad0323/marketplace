# Payment Configuration

Configure gateways under **Admin → Settings → Payment methods**. Reconcile and audit them under **Admin → Sales → Payment log**.

## Rules every gateway follows

- **Server-side verification only.** An order is marked *paid* only after a server-to-server check:
  - JazzCash: a valid HMAC secure hash **and** a Payment Inquiry API response of *Completed*.
  - Easypaisa: an Inquire Transaction API response of *PAID* with the exact amount.
  - Card: the Checkout Session retrieved with your secret key, or a webhook with a valid signature.

  Browser redirects alone never mark an order paid. An unconfirmed payment stays *pending*, and the customer sees a "waiting for confirmation" message with a retry button.
- **Idempotent.** `order_mark_paid()` locks the payment row, so duplicate callbacks, IPNs and webhooks change nothing. Amount mismatches are refused and logged.
- **Separate statuses.** Order status (pending → confirmed → processing/in production → shipped → delivered) is tracked separately from payment status (unpaid, pending, paid, failed, refunded, partially refunded, cancelled).
- **Secrets are encrypted** with AES-256-GCM using `APP_KEY`. They are never sent to the browser, never re-displayed in the admin, and redacted from the transaction log.
- **No card data touches the server.** Cards are entered on the provider's hosted page.
- **Every event is logged** to `payment_transactions`: initiate, return, callback, verify, refund, manual and COD collection.
- **Gateways start unavailable.** A gateway is offered to customers only when it is *Enabled*, all required credentials are saved, mode is *Live*, and *Testing completed* is ticked. In *Sandbox* mode it is visible **only to signed-in admins**, so you can test on the live site safely. Changing credentials clears *Testing completed*.

## Cash on Delivery

- Turn it on or off under Payment methods. Availability, the **COD fee** and the **maximum COD order value** are set **per delivery zone** under *Shipping & delivery*.
- Checkout shows the exact amount due, and the order and invoice show "Amount to collect".
- When the courier remits the cash, open the order and click **Mark COD collected**. That records the collection and marks the order paid, so it then counts in sales on the dashboard.

## JazzCash (Hosted Checkout / Page Redirection v1.1)

1. Get your **Merchant ID**, **Password** and **Integrity Salt** from the JazzCash merchant portal. Use the sandbox credentials first.
2. Enter them in Payment methods → JazzCash, set the mode to **Sandbox**, and tick **Enabled**.
3. Register these in the merchant portal (they are also shown on the settings card):
   - Return URL: `https://your-domain/payment/jazzcash/return`
   - IPN URL: `https://your-domain/payment/jazzcash/ipn`
4. While signed in as an admin, place a test order with JazzCash. Use the sandbox test wallet or cards from JazzCash.
5. Confirm the order became *paid*. Then check the Payment log: the *return* event should show `signature valid`, followed by a *verify* event.
6. Switch to **Live**, enter the live credentials, test one real low-value order, then tick **Testing completed**.

Endpoints used (`includes/gateways/jazzcash.php`):

| | Sandbox | Live |
|---|---|---|
| Hosted form | `https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/` | `https://payments.jazzcash.com.pk/...` |
| Status inquiry | `https://sandbox.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire` | `https://payments.jazzcash.com.pk/...` |

JazzCash sometimes issues merchant-specific versions or endpoints. If your integration guide differs, update `jazzcash_endpoints()` or `jazzcash_hash()`.

## Easypaisa (Easypay Hosted Checkout)

1. From the Easypay merchant portal, collect your **Store ID**, **Hash Key**, **merchant account number**, and the **inquiry API username and password**.
2. Enter them in Payment methods → Easypaisa (Sandbox), then enable it.
3. Register these URLs with Easypaisa:
   - Postback URL: `https://your-domain/payment/easypaisa/return` (the confirm step and final status page are handled automatically)
   - IPN URL: `https://your-domain/payment/easypaisa/ipn`
4. Test as above. Each return or IPN triggers an **Inquire Transaction** call, and only `PAID` with a matching amount marks the order paid.

Endpoints are in `easypaisa_endpoints()`: the staging host is `easypaystg.easypaisa.com.pk`, live is `easypay.easypaisa.com.pk`. The request hash is AES-128-ECB over the sorted parameter string, base64-encoded. Confirm both against the guide issued with your account.

## Debit and credit cards (Stripe Checkout)

The card driver uses **Stripe Checkout**, a hosted and PCI-compliant payment page:

1. Create the account in the Stripe Dashboard, then copy the **Secret key** (`sk_test_…` for testing).
2. Under Developers → Webhooks, add the endpoint `https://your-domain/payment/card/webhook` with the events `checkout.session.completed`, `checkout.session.async_payment_succeeded` and `checkout.session.async_payment_failed`. Copy the **signing secret** (`whsec_…`).
3. Enter both under Payment methods → Debit / Credit Card, set it to Sandbox, enable it, and test with Stripe test cards.
4. Refunds recorded on a card order are sent to Stripe automatically.

> **Market note:** Stripe accepts businesses only in supported countries, and **Pakistan-registered businesses cannot open a Stripe account directly**. If that applies to Ebaya, use one of these options:
> - Accept cards through the **JazzCash or Easypaisa hosted pages**, which support Visa and Mastercard. Set *Transaction type* to `MPAY` or the payment method to `CC_PAYMENT_METHOD`.
> - Add a driver for a local acquirer, such as a bank payment gateway or PayFast. Implement `card_start()`, `card_handle_return()`, `card_handle_webhook()` and `card_refund()` in `includes/gateways/card.php` with the same rules: hosted page, signature check, server verification, then `order_mark_paid()`.

## Refunds

- On the order page, use **Record refund** (requires the `orders.refund` permission). Partial refunds are supported.
- **Card** refunds are submitted to the gateway API.
- **JazzCash, Easypaisa and COD** refunds are recorded in the store. Send the money through the merchant portal or by bank transfer.
- The order moves to *partially refunded* or *refunded*. Dashboard revenue is always reported net of refunds.

## Manual reconciliation

*Record payment status manually* on the order page is meant for confirmed bank transfers or for fixing a stuck payment after you have checked the provider's portal. Marking an order paid manually **requires a reference number**, and every manual change is logged with the admin's name.
