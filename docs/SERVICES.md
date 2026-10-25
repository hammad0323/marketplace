# Custom Size Studio, Alterations & Urgent Delivery

Three services that sit alongside the ready-made catalogue. All three are managed from the admin
**Services** menu (and *Delivery & Shipping* for urgent rates) and are promoted on the homepage by
the *Custom Size & Alterations* section (Homepage Builder → section type “Services”).

## 1. Custom Size Studio — ready-made design, your measurements

Customers open **Custom Size** in the menu (`/custom-size`), pick a product and go through four steps:

1. **Usual size** — tap S / M / L / XL / XXL, or use the *height & weight size finder*. The standard
   measurements for that size fill in automatically. Signed-in customers can load saved measurements.
2. **Colour & style** — colour plus the garment's style choices (collar, cuffs, daman, pockets,
   shalwar / pajama / trouser for men; neckline, sleeves, daman, shalwar / trouser / palazzo /
   churidar for women; collar, cuffs, fit for shirts). The mannequin updates live.
3. **Measurements** — each field has a +/− stepper, a “how to measure” tip, and highlights the spot on
   the mannequin. Values are body measurements in inches; tailors add ease.
4. **Review & add to bag** — summary, quantity, notes, optional “save my measurements”.

The mannequin is an illustrated SVG figure that turns front / back / 360° (drag to rotate). It's not
a 3D body scan, but it shows the chosen colour and style and where each measurement is taken.

The **Measurement Guide** (`/measurement-guide`) explains how to measure each point (chest, shoulder,
sleeve, neck, qameez length, waist, hip, shalwar length, paincha…) and shows the S–XXL charts for men
and women. It's linked from every studio page and from the footer.

### Admin: Services → Custom Size Studio
- **Garments** — *Men's Shalwar Qameez & Kurta*, *Men's Office Shirt*, *Women's Shalwar Kameez*:
  turn on/off, optional custom-size fee (default Rs. 0 = ready-made price), and production time
  (added to the delivery estimate).
- **Size charts** — the S–XXL measurements that pre-fill the form, and the height / weight table for
  the size finder. The sample values are typical Pakistani ready-made sizing; adjust them to match
  your tailor's patterns.
- **Style options** — rename, hide, reorder, set an extra price (e.g. sherwani collar +Rs. 500) and
  choose the default for each option.
- To enable the studio for a product: *Products → Edit → Custom Size Studio garment*. The product page
  then shows a “Customise your size” button, and its card shows “Custom size available”.

### How custom orders behave
- The price is always recalculated on the server from the product price + garment fee + style
  extras. Measurements are checked against min/max limits, so tampered requests are rejected.
- Custom items don't use stock. The full specification (colour, styles, every measurement, notes)
  appears in the bag, checkout, order confirmation, customer account and **admin order view** for
  the tailor.
- The delivery estimate includes production days. Urgent delivery isn't offered when the bag
  contains a custom or made-to-order item.

## 2. Alteration Service — estimate first, then send it in

Customers open **Alterations** (`/alterations`), choose services from the price list (coat-pant / suit
fitting, sherwani, prince coat, waistcoat, shalwar qameez, shirts & jeans, ladies suits, party wear,
bridal lehenga / gharara / choli, repairs) with quantities, and instantly see an **estimated price
range and time** (e.g. “Rs. 3,300 – Rs. 6,000 · 3–5 working days, ready by Thu 29 Oct”). They choose
store drop-off or courier pickup (pickup fee is set in settings), add photos and notes, and submit.

The customer receives a reference (e.g. `ALT2610258771`) and a private link to follow the status
(*Received → Quote sent → Confirmed → In progress → Ready → Completed*). They can also look it up at
`/alterations/track` with the reference + phone. Signed-in customers see their requests under
*My Account → Alterations*.

### Admin
- **Services → Alteration Price List** — add/edit services by category and garment: price from–to
  and days from–to. This list drives the customer estimator.
- **Services → Alteration Requests** — filter by status, open a request to see services, photos,
  customer details and timeline; enter the **final price** after inspection, a ready date, status,
  payment, a message to the customer (optionally emailed) and internal tailor notes. There's a
  one-click WhatsApp button for the customer's number.
- The same *Alteration Price List* page holds the pickup fee, the drop-off note shown to customers
  and the notification email for new requests.

## 3. Urgent delivery — city-wise rates

*Delivery & Shipping → Urgent delivery (city-wise)*: add a row per city with the fee, label
(e.g. “Same-day Urgent Delivery”), delivery days (0 = same day, 1 = next day) and an order cut-off
time. After the cut-off, the estimate moves to the next day and tells the customer the cut-off.
Sample rates: Karachi Rs. 600, Lahore / Islamabad / Rawalpindi Rs. 700 same-day; Faisalabad,
Multan, Hyderabad, Peshawar next-day.

- The product page shows the urgent delivery box with a city selector and fee. The chosen city is
  carried to checkout.
- At checkout the urgent option appears only when the city has an active rate **and** every item
  may ship urgently. Turn it off per product with *Products → Edit → Allow urgent delivery*, or
  for the whole store with *Offer urgent delivery* on the same Delivery & Shipping page.

## Upgrading an existing store

New installs get all of this from `database/urban_collections.sql`. For a store installed before
this update, back up the database, then import in phpMyAdmin:

1. `database/migrations/2026_10_25_custom_studio_alterations_urgent.sql` (new tables and columns)
2. `database/migrations/2026_10_25_seed_features.sql` (permissions, garments, size charts, style
   options, alteration price list, urgent rates, homepage section and menu links — safe to re-run)

Then link your shalwar qameez / shirt products to a garment under *Products → Edit*.
