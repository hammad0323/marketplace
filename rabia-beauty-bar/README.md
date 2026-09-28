# Rabia Khan's Beauty Bar & Academy — Website

Plain PHP + MySQL salon website with an online booking system, beauty academy
(courses + enrollment requests) and an admin panel. No framework and no build step.

## Install (cPanel / any PHP 8.1+ host)

1. Upload the contents of this folder to `public_html/` (or a sub-folder).
2. Create a MySQL database and user in cPanel.
3. Import `database.sql` in phpMyAdmin.
4. Edit the four `DB_*` lines at the top of `config.php`.
5. Make sure `uploads/` is writable (755).
6. Log in at `/admin/`: user **admin**, password **admin123**. **Change the password
   right away** (Admin → Settings).

## Public pages

| Page | What it does |
|------|--------------|
| `index.php` | Home page with 12 animated sections: parallax hero, services marquee, about, service categories, parallax quote banner, tabbed price menu, academy courses, why-us and counters, gallery with lightbox, testimonials slider, quick booking (parallax), and contact with a map |
| `services.php` | Full menu grouped by category, each with a "Book" link |
| `booking.php` | Booking form. It loads free time slots live (`slots.php`), stops a slot being double-booked, and gives the client a reference number and a WhatsApp confirm button |
| `courses.php` / `course.php` | Academy listing, course detail page and enrollment form |
| `contact.php` | Contact form that saves to Admin → Messages |

## Admin panel (`/admin/`)

- **Dashboard:** today's and pending bookings, new enrollments, unread messages
- **Bookings:** filter and search, set status (pending / confirmed / completed / cancelled), add internal notes, one-click WhatsApp to the client, delete
- **Enrollments:** requests per course, set status (new / contacted / enrolled / cancelled)
- **Services and Categories:** add, edit, delete, hide or show, set prices (leave empty to show "On consultation"), mark as popular, sort order, choose an icon
- **Courses:** add, edit or delete courses with a flyer or cover upload, fee, duration, schedule, timing, start date, seats and a "what's included" list
- **Gallery:** upload photos, set captions and order
- **Reviews:** manage the testimonials slider
- **Settings:** phone, WhatsApp, address, hours, social links, home page text, booking hours, slot length, clients per slot, weekly closed day, and the admin password

## Security

The site uses PDO prepared statements everywhere and CSRF tokens on every form. Forms have a honeypot spam trap. Uploaded images are checked with `getimagesize`, and scripts can't run from `uploads/` (see its `.htaccess`). Admin login is rate-limited.
