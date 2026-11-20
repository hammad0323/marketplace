# PrintHub: all-in-one digital printing platform for Pakistan

A multi-tenant platform where print shops pay a monthly subscription (PKR 1,500 by default, changeable by the admin) and customers scan the shop's QR code to upload files, choose print options, see the price, send the order and track it. No app install or account is needed.

| Component | Tech | Location |
|-----------|------|----------|
| Super Admin panel | PHP 8, MySQL, Bootstrap 5, jQuery/AJAX, SweetAlert2, Chart.js | `admin/` |
| Shop owner web dashboard | same | `owner/` |
| Customer web app (a PWA) | responsive PHP + vanilla JS, PDF.js, Cropper.js | `pages/`, `assets/` |
| REST API | core PHP, MySQLi prepared statements, JSON envelope, token auth | `api/` |
| Mobile app (owner + customer modes) | Flutter 3.50, Material 3 | `mobile/` |

**Main features:** shop and subscription management (activate, suspend, renew, grace period, reminders, manual payments with receipt uploads), global categories and printing options, per-shop services and **shop-defined pricing** (tiers, surcharges, finishing, discounts, minimum charge, automatic price or quote), QR generator (colours, logo, PNG and poster PDF, regenerate without changing the shop ID), Wi-Fi sharing with its own QR code, uploads (PDF, images, DOCX) with page selection, rotation and reordering, image crop, rotate and flip, layouts (fit, fill, actual size, photo sheet, CNIC front + back), server-built print-ready PDFs, order workflow and tracking, CV maker (10 templates, live preview, PDF, send to a shop), notifications, reports, automatic file retention, signed private file links, and app links (shop, track and CV links open the app when installed and fall back to the web app).

**Docs:** [Install](docs/INSTALL.md) · [Hosting & deployment](docs/DEPLOYMENT.md) · [API reference](docs/API.md)

## Honest limits

- Notifications use in-app polling plus local notifications. Background push (FCM/APNs) needs your own credentials (see DEPLOYMENT.md).
- DOCX files get previews and page counts only when LibreOffice is installed on the server. Word documents are not edited like in a desktop word processor; the shop always receives the original file.
- No automatic payment gateway is integrated. Payments are recorded manually (cash, bank transfer, JazzCash, Easypaisa).
- The CV maker produces English content. Urdu labels and right-to-left layout are built into the template system but not switched on in the editor yet.
