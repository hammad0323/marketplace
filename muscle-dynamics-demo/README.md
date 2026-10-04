# Muscle Dynamics — HTML demo website

Static, no-build demo for **Muscle Dynamics Gym & Fitness Club, DHA Phase 5 Islamabad**.
Open `index.html` in a browser, or drag the folder onto Vercel / Netlify / any host.

## Pages
| File | Purpose / target search |
|---|---|
| `index.html` | Home: “gym in DHA Phase 5 Islamabad” |
| `facilities.html` | Gym facilities & equipment (300+ words) |
| `trainers.html` | Personal trainers in DHA Islamabad |
| `membership.html` | Gym fees in PKR |
| `ladies-gym-dha-islamabad.html` | Ladies gym / female timings |
| `personal-training.html` | 1-on-1 coaching |
| `gallery.html` | Inside Muscle Dynamics |
| `contact.html` | Contact & directions (was a 404 on the old site) |
| `404.html` | Not-found page (noindex) |

## What's included from the review
- Self-referencing canonical, a unique keyword title and a unique 140–160 character description on every page. No double “| Muscle Dynamics”.
- `ExerciseGym` LocalBusiness JSON-LD on the home page, FAQPage schema and breadcrumbs.
- `og:locale = en_PK`, `og:image` and Twitter tags on every page; no `maximum-scale`; no Bing TODO.
- One H1 per page, with the keyword in it. “Build Your Legacy” is kept as the visual slogan.
- Stats are in the HTML (not `0+`). They use real numbers: the 4.8★ Google rating and 19 reviews.
- Prices in **Rs / PKR**. The Premium plan has a “Most Popular” badge. Solid buttons open WhatsApp with a pre-filled message.
- Timings in a simple Gents / Ladies / Sunday format, with a **live “Open now / Closed” status** in Pakistan time.
- Single-column FAQ accordion with local questions (ladies timing, fees, trial, parking).
- Contact: WhatsApp first, a short form (Name, Phone, Message, optional Email) that sends via WhatsApp, a Get Directions button and a large map.
- A floating WhatsApp button on desktop, and a sticky **WhatsApp | Call** bar on mobile.
- Real Google reviews, a large Instagram block and strip, and a larger footer map.
- Parallax hero using the real storefront photo, floating gym-equipment clipart and scroll-reveal animations. Animations respect “reduce motion”.
- `sitemap.xml` lists only pages that exist. `robots.txt` has no `Host:` line. `vercel.json` 301-redirects the old `/trainers`, `/facilities`, `/gallery` and `/contact` URLs.

## ⚠️ Still needed from the gym (placeholders are clearly marked)
1. **Trainer names, photos, qualifications.** The cards currently show “Photo coming soon”.
2. **Real transformation photos**, shared with written consent. That section is labelled “Demo layout”.
3. **Confirm prices and features.** Premium Rs 15,000 is a guess; only the Rs 8,000–35,000 range was known. Also confirm the plan features, “No joining fee” and the free trial.
4. **Confirm FAQ facts:** parking, lockers, the ladies coach, and the Nutrifactor stock.
5. **More gym photos** (bright, well lit). Gallery tiles with icons are waiting for photos. Put them in `assets/img/` and swap the tiles in the HTML.
6. **Domain.** When a real domain (e.g. muscledynamics.pk) is bought, replace `https://muscle-dynamics.vercel.app` in every file, `sitemap.xml` and `robots.txt`.
