# My Website (React + PHP + MySQL)

A modern multi-page website with **Home**, **About** and **Contact** pages.

- **Frontend:** React, React Router, Vite
- **Backend:** PHP API (`backend/api/`)
- **Database:** MySQL (services, skills, timeline + contact messages)

## Run it on your computer

1. Start **MySQL** (and Apache for phpMyAdmin) in XAMPP.
2. Import `backend/database.sql` in phpMyAdmin (http://localhost/phpmyadmin → Import).
3. Install packages (first time only):
   ```bash
   npm install
   ```
4. Terminal 1, the PHP backend:
   ```bash
   npm run api
   ```
5. Terminal 2, the React frontend:
   ```bash
   npm run dev
   ```
6. Open http://localhost:5173

Database settings are in `backend/api/config.php`.

## Guides

- 📘 **What to learn from this project:** [LEARNING.md](./LEARNING.md)
- 🇵🇰 **Har file ka guide (Roman Urdu):** [FILES_GUIDE_URDU.md](./FILES_GUIDE_URDU.md)
- 🗄️ **PHP backend + database guide (Roman Urdu):** [BACKEND_GUIDE_URDU.md](./BACKEND_GUIDE_URDU.md)
