# PHP Backend + MySQL Database: Complete Guide (Roman Urdu) 🗄️

Ab aap ki website mein **PHP backend** aur **MySQL database** jur gaya hai.
Is guide mein sab kuch step by step samjhaya gaya hai: setup, har file ka kaam, aur yeh sab mil kar kaise chalta hai.

---

## Fehrist

1. [Kya badla hai? (Ek nazar mein)](#1-kya-badla-hai-ek-nazar-mein)
2. [Frontend, Backend, Database: farq kya hai?](#2-frontend-backend-database-farq-kya-hai)
3. [Setup: apne computer par chalayein (XAMPP)](#3-setup-apne-computer-par-chalayein-xampp)
4. [Data ka safar: React se Database tak](#4-data-ka-safar-react-se-database-tak)
5. [Backend files ka guide](#5-backend-files-ka-guide)
6. [React (frontend) mein kya badla](#6-react-frontend-mein-kya-badla)
7. [Security: website ko mehfooz rakhna](#7-security-website-ko-mehfooz-rakhna)
8. [Database mein data kaise badlein](#8-database-mein-data-kaise-badlein)
9. [Website online karna (cPanel hosting)](#9-website-online-karna-cpanel-hosting)
10. [Aam errors aur un ka hal](#10-aam-errors-aur-un-ka-hal)
11. [Practice tasks](#11-practice-tasks)
12. [Naye alfaaz ki dictionary](#12-naye-alfaaz-ki-dictionary)

---

## 1. Kya badla hai? (Ek nazar mein)

### Pehle ❌
- Services, skills aur timeline `siteData.js` file mein **likhe hue** the
- Contact form sirf **console** mein message dikhata tha. Message **kahin save nahi** hota tha

### Ab ✅
- Services, skills aur timeline **MySQL database** se aate hain
- Contact form ka har message **database mein save** hota hai
- Data load hote waqt **spinner** (⏳) dikhta hai
- Kuch kharab ho to **saaf error message** dikhta hai

### Nayi files

```
my-website/
├── backend/                     ← 🆕 PHP backend (server wala hissa)
│   ├── database.sql             ← Database aur tables banane ka code
│   └── api/
│       ├── config.php           ← Database ka naam, user, password
│       ├── db.php               ← Database se connection + helper functions
│       ├── content.php          ← GET: services, skills, timeline bhejta hai
│       └── contact.php          ← POST: contact form ka message save karta hai
│
├── public/.htaccess             ← 🆕 cPanel/Apache hosting ke liye
├── vite.config.js               ← ✏️ badla: /api ko PHP ki taraf bhejta hai
├── package.json                 ← ✏️ badla: naya command "npm run api"
│
└── src/
    ├── api.js                   ← 🆕 React se PHP ko call karne wale functions
    ├── hooks/
    │   └── useContent.js        ← 🆕 Custom hook: database ka data laata hai
    ├── components/
    │   ├── Loader.jsx           ← 🆕 Ghoomta hua spinner
    │   └── ErrorMessage.jsx     ← 🆕 Laal error box
    ├── data/siteData.js         ← ✏️ badla: services/skills/timeline hata diye
    └── pages/
        ├── Home.jsx             ← ✏️ badla: services database se
        ├── About.jsx            ← ✏️ badla: skills + timeline database se
        └── Contact.jsx          ← ✏️ badla: form database mein save
```

---

## 2. Frontend, Backend, Database: farq kya hai?

Restaurant ki misaal se samjhein 🍽️

| Hissa | Restaurant mein | Hamari website mein | Kahan chalta hai |
|---|---|---|---|
| **Frontend** | Waiter + menu card | **React** (jo dikhta hai) | User ke **browser** mein |
| **Backend** | Kitchen (bawarchi) | **PHP** (`backend/api/`) | **Server** par |
| **Database** | Godaam (store room) | **MySQL** (tables) | **Server** par |

```
   Customer                 Waiter               Kitchen              Godaam
   (User)  ──order──▶  (React) ──request──▶  (PHP) ──query──▶  (MySQL)
           ◀──khana──          ◀──JSON──────        ◀──data──
```

### React seedha database se baat kyun nahi karta?
Kyunke database ka **password** browser mein rakhna pare ga, aur browser ka code **koi bhi dekh sakta hai** (F12 dabayein). 😱
Is liye beech mein **PHP** hai. Password sirf server par rehta hai, aur PHP check karta hai ke kya allow hai aur kya nahi.

### Yeh aapas mein baat kaise karte hain?
**HTTP request** aur **JSON** ke zariye.

**JSON** ek text format hai jo JavaScript aur PHP dono samajhte hain:
```json
{ "name": "Bilal", "email": "bilal@test.com" }
```

---

## 3. Setup: apne computer par chalayein (XAMPP)

### Step 1: XAMPP install karein
**XAMPP** ek free package hai jis mein **PHP + MySQL + phpMyAdmin** sab ek saath aate hain.

1. https://www.apachefriends.org se download karein
2. Install karein (default settings theek hain)
3. **XAMPP Control Panel** kholein
4. **MySQL** ke saamne **Start** dabayein (hara ho jaaye ✅)
5. **Apache** ke saamne bhi **Start** dabayein (phpMyAdmin ke liye zaroori hai)

> Mac/Linux par bhi XAMPP milta hai. Ya MySQL + PHP alag se install kar sakte hain.

### Step 2: Database banayein
1. Browser mein kholein: **http://localhost/phpmyadmin**
2. Upar **Import** tab par click karein
3. **Choose File** → `my-website/backend/database.sql` select karein
4. Neeche **Import** (ya **Go**) dabayein

✅ Ab baen (left) taraf **`my_website`** database dikhega, 4 tables ke saath:
`services`, `skills`, `timeline`, `messages`

### Step 3: PHP ko terminal se chalne layak banayein (sirf Windows)
`npm run api` command ko `php` chahiye. Check karein:
```bash
php -v
```
Agar error aaye (*"php is not recognized"*), to **2 raaste** hain:

**Raasta A (aasaan):** XAMPP ka PHP seedha use karein. `my-website` folder mein yeh chalayein:
```bash
C:\xampp\php\php.exe -S localhost:8000 -t backend
```

**Raasta B (pakka hal):** PHP ko PATH mein daalein:
1. Windows search mein **"Environment Variables"** likhein → **Edit the system environment variables**
2. **Environment Variables** → **Path** → **Edit** → **New** → `C:\xampp\php` likhein → OK
3. Terminal **band kar ke dobara** kholein → `php -v` ab chalega ✅

### Step 4: Dono servers chalayein (2 terminals)

Ab **do server** ek saath chalne chahiye. VS Code mein **do terminal** kholein (Terminal → **Split Terminal**).

**Terminal 1: PHP backend** (port 8000)
```bash
npm run api
```

**Terminal 2: React frontend** (port 5173)
```bash
npm run dev
```

Phir browser mein kholein: **http://localhost:5173** 🎉

| Server | Port | Kaam |
|---|---|---|
| PHP (`npm run api`) | 8000 | Database se data deta hai |
| Vite/React (`npm run dev`) | 5173 | Website dikhata hai |
| MySQL (XAMPP) | 3306 | Data store karta hai |

### Step 5: Test karein ✅
1. **Home** page par 3 service cards database se aane chahiyein
2. **About** page par skills mein **"PHP & MySQL"** dikhega. Yeh skill sirf database mein hai, is se pata chalta hai ke data database se aa raha hai! 😉
3. **Contact** page se ek message bhejein
4. phpMyAdmin mein `my_website` → `messages` table kholein. Aap ka message wahan hoga! 🎉

---

## 4. Data ka safar: React se Database tak

### Misaal 1: Home page services kaise laata hai (GET)

```
① Home.jsx         useContent() call karta hai
        │
② useContent.js    getContent() call karta hai
        │
③ api.js           fetch('/api/content.php')
        │
④ Vite (5173)      "/api" dekh kar request PHP (8000) ko bhej deta hai (proxy)
        │
⑤ content.php      MySQL se SELECT karta hai
        │
⑥ MySQL            services table ka data deta hai
        │
⑦ content.php      Data ko JSON bana kar wapas bhejta hai
        │
⑧ Home.jsx         .map() se cards bana deta hai 🎉
```

### Misaal 2: Contact form kaise save hota hai (POST)

```
① User "Send Message" dabata hai
        │
② Contact.jsx      Pehle React mein check (validate) → galti ho to yahin ruk jao
        │
③ api.js           fetch('/api/contact.php', { method: 'POST', body: JSON })
        │
④ contact.php      DOBARA check karta hai (server par)
        │            ├── galti hai? → 422 + errors wapas bhejo
        │            └── sab theek? ↓
⑤ contact.php      INSERT INTO messages ... (database mein save)
        │
⑥ contact.php      { "success": true } bhejta hai (201)
        │
⑦ Contact.jsx      ✓ "Message sent!" dikhata hai
```

### GET vs POST

| | GET | POST |
|---|---|---|
| Kaam | Data **lena** (parhna) | Data **bhejna** (save karna) |
| Misaal | Services dikhana | Contact form bhejna |
| Hamari file | `content.php` | `contact.php` |
| Restaurant | Menu dekhna | Order dena |

### HTTP Status Codes (server ka jawab)

| Code | Matlab | Hamari website mein kab |
|---|---|---|
| **200** | OK, sab theek | Services mil gayi |
| **201** | Created, naya data ban gaya | Message save ho gaya |
| **400** | Bad Request, request hi ghalat hai | JSON theek nahi tha |
| **405** | Method Not Allowed | `contact.php` ko GET se khola |
| **422** | Data sahi nahi | Email ghalat likha |
| **500** | Server Error | Database band hai |

---

## 5. Backend files ka guide

### 📄 `backend/database.sql`

**Kya hai?** Database aur tables **banane** ka SQL code. Ek baar phpMyAdmin mein import karte hain.

```sql
CREATE DATABASE IF NOT EXISTS my_website   -- database banao (agar pehle se nahi hai)
  CHARACTER SET utf8mb4;                   -- utf8mb4 = har zabaan + emojis 🎨 support

USE my_website;                            -- ab is database mein kaam karo
SET NAMES utf8mb4;                         -- file ko UTF-8 mein parho (emojis ke liye)
```

**Table banana:**
```sql
CREATE TABLE IF NOT EXISTS messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,  -- har row ka number: 1, 2, 3... (khud barhta hai)
  name       VARCHAR(100)  NOT NULL,          -- text, zyada se zyada 100 huroof, khaali nahi
  email      VARCHAR(150)  NOT NULL,
  subject    VARCHAR(150)  NOT NULL DEFAULT '',  -- khaali ho to '' rakho
  message    TEXT          NOT NULL,          -- lamba text
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP  -- waqt khud likha jaaye
);
```

| Lafz | Matlab |
|---|---|
| `INT` | Number (1, 2, 75) |
| `VARCHAR(100)` | Chhota text (max 100 huroof) |
| `TEXT` | Lamba text (message) |
| `TINYINT UNSIGNED` | Chhota number 0–255 (skill level ke liye kaafi) |
| `TIMESTAMP` | Tareekh + waqt |
| `PRIMARY KEY` | Har row ki **unique pehchaan** |
| `AUTO_INCREMENT` | Number khud barhta jaaye |
| `NOT NULL` | Khaali nahi ho sakta |
| `DEFAULT` | Value na di jaaye to yeh rakho |

**4 Tables:**

| Table | Kya rakhta hai | Kahan dikhta hai |
|---|---|---|
| `services` | icon, title, text | Home page cards |
| `skills` | name, level (0–100) | About page bars |
| `timeline` | year, title, text | About page experience |
| `messages` | contact form ke messages | phpMyAdmin mein |

**`sort_order` column kyun?** Is se aap **tarteeb** badal sakte hain. Kam number pehle dikhega.

**Shuru ka data:**
```sql
INSERT INTO services (icon, title, text, sort_order)
SELECT * FROM (...) AS seed
WHERE NOT EXISTS (SELECT 1 FROM services);   -- ⭐ sirf tab daalo jab table khaali ho
```
`WHERE NOT EXISTS` ka faida: file **dobara import** karein to data **double nahi** hoga.

---

### 📄 `backend/api/config.php`

**Kya hai?** Database ki **settings**: kahan hai, naam kya hai, password kya hai.

```php
define('DB_HOST', 'localhost');    // database kahan hai (apna computer)
define('DB_NAME', 'my_website');   // database ka naam
define('DB_USER', 'root');         // XAMPP ka default user
define('DB_PASS', '');             // XAMPP ka default password: KHAALI
define('DEBUG', true);             // true = poori error dikhao (seekhte waqt)
```

**`define()` kya hai?** Ek **constant** banata hai, yani aisi value jo **badalti nahi**. Poori app mein `DB_NAME` likh kar use kar sakte hain.

**Alag file kyun?** Hosting par sirf **yeh ek file** badalni paregi. Baaqi code waisa hi rahega.

> ⚠️ **Live website par:** `DEBUG` ko **`false`** karein! Warna hackers ko database ki andar ki maloomat dikh sakti hai.

---

### 📄 `backend/api/db.php`

**Kya hai?** **Common helper functions** jo har API file use karti hai. Is tarah code **baar baar nahi** likhna parta.

#### `require_once`
```php
require_once __DIR__ . '/config.php';
```
- `require_once` = doosri PHP file ko **yahan shaamil karo** (sirf ek baar)
- `__DIR__` = **is file ka folder**. Is se raasta hamesha sahi rehta hai
- `.` = PHP mein text **jorne** ka nishaan (JavaScript ke `+` jaisa)

#### JSON header
```php
header('Content-Type: application/json; charset=utf-8');
```
Browser ko batata hai: *"Jo main bhej raha hoon woh JSON hai, HTML nahi."*

#### `sendJson()`: jawab bhejne wala function
```php
function sendJson($data, $status = 200)
{
    http_response_code($status);                         // status code set karo (200, 422...)
    echo json_encode($data, JSON_UNESCAPED_UNICODE);     // PHP array → JSON text
    exit;                                                // ⭐ yahin ruk jao, aage kuch mat chalao
}
```
- `json_encode()` = PHP array ko JSON mein badalta hai
- `JSON_UNESCAPED_UNICODE` = emojis aur Urdu text saaf rahein (`\ud83c` jaisa na banein)
- `exit` = jawab bhej diya, ab **aage ka code na chale**

**Misaal:**
```php
sendJson(['success' => true], 201);
// Browser ko milega: {"success":true}  (status 201)
```

#### `requireMethod()`: sirf sahi method allow karo
```php
function requireMethod($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendJson(['error' => "Only $method requests are allowed."], 405);
    }
}
```
`$_SERVER['REQUEST_METHOD']` = request **GET** thi ya **POST**. Ghalat ho to 405 error.

#### `getDB()`: database se connection ⭐
```php
function getDB()
{
    static $pdo = null;           // static = function khatam ho kar bhi value yaad rahe
    if ($pdo !== null) {
        return $pdo;              // pehle se connected? wahi wapas do
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // galti ho to "exception" do
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // rows ['name' => 'Ali'] jaisi
            PDO::ATTR_EMULATE_PREPARES   => false,                   // asli prepared statements
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());  // server ki log file mein likho
        $message = DEBUG ? '...' . $e->getMessage() : 'Database connection failed.';
        sendJson(['error' => $message], 500);
    }
}
```

| Cheez | Matlab |
|---|---|
| **PDO** | PHP ka **database se baat karne** ka tool (MySQL, PostgreSQL sab ke saath chalta hai) |
| **DSN** | Database ka "address": kaunsa type, kahan, kaunsa database |
| `static $pdo` | Connection **ek hi baar** banao, baar baar nahi (tez) |
| `try { } catch { }` | **Koshish karo**. Galti ho to `catch` mein sambhalo, website crash na ho |
| `FETCH_ASSOC` | Data `$row['name']` ki shakal mein mile |
| `error_log()` | Galti **server ki log file** mein likho (developer baad mein dekh sake) |
| `DEBUG ? A : B` | DEBUG on hai to poori galti dikhao, warna sirf aam message |

---

### 📄 `backend/api/content.php` (GET)

**Kya hai?** Database se **services, skills aur timeline** nikal kar React ko bhejta hai.
**URL:** `/api/content.php`

```php
require_once __DIR__ . '/db.php';   // helpers + connection lao
requireMethod('GET');               // sirf GET allow

$db = getDB();                      // database se connect

try {
    $services = $db->query('SELECT id, icon, title, text FROM services ORDER BY sort_order, id')->fetchAll();
    $skills   = $db->query('SELECT id, name, level FROM skills ORDER BY sort_order, id')->fetchAll();
    $timeline = $db->query('SELECT id, year, title, text FROM timeline ORDER BY sort_order, id')->fetchAll();
} catch (PDOException $e) { ... }
```

**SQL samjhein:**
```sql
SELECT id, icon, title, text    -- yeh columns chahiyein
FROM services                   -- is table se
ORDER BY sort_order, id         -- is tarteeb mein
```

| Function | Kaam |
|---|---|
| `$db->query('SQL')` | SQL chalao |
| `->fetchAll()` | **Saari rows** array mein do |

**Number theek karna:**
```php
foreach ($skills as &$skill) {
    $skill['level'] = (int) $skill['level'];   // "75" (text) → 75 (number)
}
unset($skill);
```
- `&$skill` = asal item ko **badlo** (copy nahi)
- `(int)` = text ko number banao
- `unset($skill)` = `&` wala link khatam karo. PHP ki ek zaroori aadat, warna baad mein ajeeb bugs aate hain

**Aakhir mein jawab:**
```php
sendJson([
    'services' => $services,
    'skills'   => $skills,
    'timeline' => $timeline,
]);
```

**React ko yeh milta hai:**
```json
{
  "services": [ { "id": 1, "icon": "🎨", "title": "Web Design", "text": "..." }, ... ],
  "skills":   [ { "id": 1, "name": "HTML & CSS", "level": 90 }, ... ],
  "timeline": [ { "id": 1, "year": "2026", "title": "...", "text": "..." }, ... ]
}
```

> 💡 **Khud dekhein:** PHP chalte waqt browser mein kholein: http://localhost:8000/api/content.php

---

### 📄 `backend/api/contact.php` (POST) ⭐ Sab se zaroori

**Kya hai?** Contact form ka data **leta hai, check karta hai, aur database mein save** karta hai.
**URL:** `/api/contact.php`

#### Step 1: React ka bheja hua JSON parhna
```php
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJson(['error' => 'Invalid request.'], 400);
}
```
- `file_get_contents('php://input')` = request ki **body** (jo React ne bheja) parho
- `json_decode(..., true)` = JSON text → PHP **array**
- `is_array()` = theek se array bana? Nahi bana to request hi ghalat thi

> ❓ **`$_POST` kyun nahi?** `$_POST` sirf HTML form ke data ke liye hai. React **JSON** bhejta hai, is liye `php://input` se parhte hain.

#### Step 2: Har field nikalna
```php
$name = trim((string) ($input['name'] ?? ''));
```
Andar se bahar parhein:
1. `$input['name'] ?? ''` → `name` hai to woh, **nahi hai to** `''` (khaali)
2. `(string)` → pakka **text** banao (koi number ya array bheje to bhi)
3. `trim()` → shuru aur aakhir ki **spaces** hatao

`??` ko **"null coalescing operator"** kehte hain. Matlab: "agar nahi hai to yeh use karo."

#### Step 3: Server par DOBARA check (validation)
```php
$errors = [];

if ($name === '') {
    $errors['name'] = 'Please enter your name.';
} elseif (mb_strlen($name) > 100) {
    $errors['name'] = 'Name is too long (max 100 characters).';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
...
if (!empty($errors)) {
    sendJson(['errors' => $errors], 422);
}
```

| Function | Kaam |
|---|---|
| `mb_strlen()` | Text ki lambai (Urdu/emoji ke saath bhi sahi). `strlen` emojis ko ghalat ginta hai |
| `filter_var($email, FILTER_VALIDATE_EMAIL)` | PHP ka **built-in** email checker |
| `empty($errors)` | Array khaali hai? |

### ❓ React mein check ho chuka, to dobara kyun? 🤔
**Bohat zaroori sabaq:** Browser par **kabhi bharosa mat karo!**
- Koi bhi F12 khol kar React ka check **band** kar sakta hai
- Koi bhi Postman ya curl se **seedha** PHP ko request bhej sakta hai

| Check | Kis liye |
|---|---|
| **React (frontend)** | User ki **sahulat**: foran error dikhe, server tak jaana na pare |
| **PHP (backend)** | **Hifazat**: asal pehredaar yahi hai 🛡️ |

#### Step 4: Database mein save karna (Prepared Statement) 🛡️
```php
$stmt = getDB()->prepare(
    'INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$name, $email, $subject, $message]);
```
- `prepare()` = SQL **pehle tayyar** karo, values ki jagah `?` rakho
- `execute([...])` = ab `?` ki jagah **values** mehfooz tareeqe se daalo

**Kyun `?` ? Seedha value kyun nahi?** → **SQL Injection** se bachne ke liye (Section 7 dekhein).

#### Step 5: Kamyabi ka jawab
```php
sendJson([
    'success' => true,
    'id'      => (int) getDB()->lastInsertId(),   // naye message ka id number
], 201);
```

---

## 6. React (frontend) mein kya badla

### 📄 `vite.config.js`: Proxy

```js
server: {
  proxy: {
    '/api': 'http://localhost:8000',
  },
},
```

**Masla:** React `5173` par hai, PHP `8000` par. Browser **alag ports** ke darmiyan request rokta hai. Isay **CORS** ka masla kehte hain.

**Hal: Proxy** 🔀
Vite kehta hai: *"Jo request `/api` se shuru ho, main khud PHP (8000) ko de dunga."*
Browser ko lagta hai sab **ek hi jagah (5173)** se aa raha hai, is liye koi masla nahi.

```
Browser ──/api/content.php──▶ Vite (5173) ──▶ PHP (8000)
```

> Yeh proxy **sirf development** mein chalta hai. Live website par `api` folder `index.html` ke saath hi hota hai, is liye wahan zaroorat nahi.

---

### 📄 `package.json`: naya command

```json
"api": "php -S localhost:8000 -t backend"
```
- `php -S localhost:8000` = PHP ka **built-in server** port 8000 par chalao
- `-t backend` = `backend` folder ko **root** banao. Is liye `/api/content.php` → `backend/api/content.php`

---

### 📄 `src/api.js`: PHP se baat karne wale functions

**Kyun alag file?** Saara `fetch` code **ek jagah**. Pages ko sirf `getContent()` call karna hai. Kal API ka URL badle to **sirf yeh file** badlegi.

```js
export const API_URL = '/api';
```

#### `getContent()`: GET request
```js
export async function getContent() {
  const response = await fetch(`${API_URL}/content.php`);   // request bhejo, jawab ka intezaar
  const data = await readJson(response);                     // JSON parho

  if (!response.ok) {                                        // 200-299 nahi? to galti
    throw new Error(data.error || 'Could not load content.');
  }
  return data;
}
```

| Cheez | Matlab |
|---|---|
| `async function` | Aisa function jis mein **intezaar** (`await`) ho sakta hai |
| `await` | "Jab tak jawab na aaye, **ruko**" |
| `fetch(url)` | Server ko request bhejo (JavaScript ka built-in) |
| `response.ok` | Status 200–299 hai? (kamyabi) |
| `throw new Error(...)` | Galti **phenko**. Bulane wala `catch` mein pakrega |
| `data.error \|\| '...'` | Server ne error message diya? Woh dikhao, warna yeh |

#### `sendContactMessage()`: POST request
```js
const response = await fetch(`${API_URL}/contact.php`, {
  method: 'POST',                                     // data bhej rahe hain
  headers: { 'Content-Type': 'application/json' },    // batao ke JSON hai
  body: JSON.stringify(form),                         // JS object → JSON text
});

if (response.status === 422 && data.errors) {
  return { ok: false, errors: data.errors };          // form mein galtiyan
}
```
`JSON.stringify()` = JavaScript object ko JSON **text** mein badalta hai (PHP ke `json_encode` jaisa).

#### `readJson()`: ek chhota helper
```js
async function readJson(response) {
  try {
    return await response.json();
  } catch {
    throw new Error('The server is not responding. Is the PHP server running?');
  }
}
```
**Kyun?** Agar PHP **band** ho to jawab JSON nahi hota aur ajeeb error aata hai.
Yeh function **saaf message** deta hai: *"PHP server chal raha hai?"*

---

### 📄 `src/hooks/useContent.js`: Custom Hook ⭐

**Custom hook kya hai?** Apna **khud ka hook**, jo `useState` aur `useEffect` ko mila kar banta hai.
Naam **`use`** se shuru hona **zaroori** hai.

**Kyun banaya?** Home aur About **dono** ko database ka data chahiye. Dono mein loading, error, fetch ka **same code** likhne ke bajaye **ek hook** bana diya.

```js
export function useContent() {
  const [content, setContent] = useState(null);   // data (shuru mein kuch nahi)
  const [loading, setLoading] = useState(true);   // load ho raha hai? (shuru mein haan)
  const [error, setError] = useState('');         // koi galti?

  useEffect(() => {
    let ignore = false;

    getContent()
      .then((data) => { if (!ignore) setContent(data); })   // ✅ mil gaya
      .catch((err) => { if (!ignore) setError(err.message); }) // ❌ galti
      .finally(() => { if (!ignore) setLoading(false); });   // dono soorat mein: loading khatam

    return () => { ignore = true; };   // cleanup
  }, []);   // [] = page khulne par sirf EK baar

  return { content, loading, error };
}
```

**3 states, 3 haalatein:**

| Haalat | loading | content | error | Screen par |
|---|---|---|---|---|
| ⏳ Load ho raha | `true` | `null` | `''` | Spinner |
| ✅ Mil gaya | `false` | `{...}` | `''` | Cards |
| ❌ Galti | `false` | `null` | `'...'` | Laal box |

| Cheez | Matlab |
|---|---|
| `.then()` | Kamyabi par yeh karo |
| `.catch()` | Galti par yeh karo |
| `.finally()` | **Har haal mein** yeh karo |
| `return () => {...}` | **Cleanup**: component gayab ho to yeh chalao |

**`ignore` kyun?** Agar user data aane se **pehle** doosre page par chala jaaye, to purane page ki state update na ho (React warning deta hai). Yeh **achhi aadat** hai.

**Use kaise karte hain:**
```jsx
const { content, loading, error } = useContent();
```

---

### 📄 `components/Loader.jsx` + `ErrorMessage.jsx`

```jsx
function Loader({ text = 'Loading...' }) {
  return (
    <div className="loader" role="status">
      <span className="spinner" />     {/* CSS se ghoomta gol */}
      <span>{text}</span>
    </div>
  );
}
```

```jsx
function ErrorMessage({ message }) {
  return (
    <div className="error-box" role="alert">
      <strong>⚠️ Something went wrong</strong>
      <p>{message}</p>
    </div>
  );
}
```
- `role="status"` / `role="alert"` = screen readers (nabeena logon ke software) ko batata hai ke yeh kya hai
- Spinner CSS (`index.css`): ek gol jis ka **ek kinara rangeen** hai, aur `@keyframes spin` use **ghumaata** hai

---

### 📄 `pages/Home.jsx`: badlaav

```jsx
const { content, loading, error } = useContent();

{loading && <Loader text="Loading services..." />}   {/* load ho raha? spinner */}
{error && <ErrorMessage message={error} />}          {/* galti? laal box */}

{content && (                                         {/* data aa gaya? cards */}
  <div className="cards">
    {content.services.map((service) => (
      <article key={service.id} className="card">...</article>
    ))}
  </div>
)}
```

**`content &&` kyun zaroori hai?** Shuru mein `content` **`null`** hai. `null.services` likhne se **crash** ho jaata! 💥
`content &&` = "pehle check karo ke data aa gaya hai."

**`key={service.id}` kyun?** Database ka `id` **hamesha unique** hota hai, is liye `key` ke liye sab se behtar hai.

### 📄 `pages/About.jsx`: badlaav
Bilkul Home jaisa: `useContent()` se `content.skills` aur `content.timeline`.

---

### 📄 `pages/Contact.jsx`: badlaav ⭐

#### 2 nayi states
```jsx
const [sending, setSending] = useState(false);       // PHP ke jawab ka intezaar?
const [serverError, setServerError] = useState('');  // server ki galti
```

#### Naya `handleSubmit`
```jsx
async function handleSubmit(e) {
  e.preventDefault();

  const newErrors = validate(form);          // 1. React mein check
  setErrors(newErrors);
  if (Object.keys(newErrors).length > 0) return;

  setSending(true);                          // 2. button: "Sending..."
  setServerError('');

  try {
    const result = await sendContactMessage(form);   // 3. PHP ko bhejo, intezaar

    if (!result.ok) {
      setErrors(result.errors);              // 4a. server ne galti batayi
      return;
    }

    setSent(true);                           // 4b. ✅ kamyabi
    setForm(emptyForm);
  } catch (err) {
    setServerError(err.message);             // 4c. ❌ server/database band
  } finally {
    setSending(false);                       // 5. har haal mein: button wapas normal
  }
}
```

#### Button
```jsx
<button type="submit" className="btn btn-full" disabled={sending}>
  {sending ? 'Sending...' : 'Send Message'}
</button>
```
`disabled={sending}` = bhejte waqt button **band**. User **do baar** click kar ke 2 messages na bhej de!

---

## 7. Security: website ko mehfooz rakhna 🛡️

### 1. SQL Injection, sab se khatarnak hamla

**❌ GHALAT tareeqa (kabhi mat karna!):**
```php
$sql = "INSERT INTO messages (name) VALUES ('$name')";
$db->query($sql);
```
Agar hacker naam mein yeh likhe:
```
x'); DROP TABLE messages; --
```
To SQL ban jaata hai:
```sql
INSERT INTO messages (name) VALUES ('x'); DROP TABLE messages; --')
```
💥 **Poora table delete!**

**✅ SAHI tareeqa (jo hum ne kiya): Prepared Statements**
```php
$stmt = $db->prepare('INSERT INTO messages (name) VALUES (?)');
$stmt->execute([$name]);
```
PDO value ko **sirf text** samajhta hai, kabhi SQL command nahi.
✅ Hum ne yeh test kiya: hacker wala naam bheja, aur woh **sirf text** ki tarah save hua. Table bilkul mehfooz raha! 🛡️

> 📌 **Sunehri usool:** User ka koi bhi data SQL mein **hamesha `?` ke zariye** daalein.

### 2. Server par validation
React ka check user ki **sahulat** ke liye hai, PHP ka check **hifazat** ke liye. Dono zaroori hain.

### 3. Lambai ki hadd (limits)
`mb_strlen($message) > 5000` → koi **10 MB** ka message bhej kar database na bhar de.

### 4. `DEBUG = false` (live website par)
Development mein poori error dekhna faidemand hai. Lekin live par hacker ko **database ki maloomat** nahi dikhni chahiye.

### 5. Password kabhi frontend mein nahi
Database ka password **sirf** `config.php` (server) mein hai. React (browser) mein **kabhi nahi**.

### 6. XSS se hifazat
Agar koi message mein `<script>` likhe, to **React khud** use text bana deta hai, chalne nahi deta. ✅
⚠️ Lekin agar aap kabhi **PHP se HTML page** banayein (jaise admin page), to `htmlspecialchars()` zaroor use karein.

---

## 8. Database mein data kaise badlein

**Ab website ka content badalne ke liye code nahi chhoona!** Seedha database badlein:

### phpMyAdmin se (aasaan tareeqa)
1. http://localhost/phpmyadmin kholein
2. `my_website` → `services` table par click karein
3. **Insert** tab → nayi service likhein (icon, title, text, sort_order) → **Go**
4. Website refresh karein. Nayi service aa gayi! 🎉

### SQL se
phpMyAdmin mein **SQL** tab kholein aur chalayein:

```sql
-- Nayi service add karo
INSERT INTO services (icon, title, text, sort_order)
VALUES ('🛒', 'E-commerce', 'Online stores with cart and checkout.', 4);

-- Skill ka level badlo
UPDATE skills SET level = 85 WHERE name = 'React';

-- Timeline ka item hatao
DELETE FROM timeline WHERE id = 3;

-- Saare messages dekho (naye pehle)
SELECT * FROM messages ORDER BY created_at DESC;
```

### SQL ke 4 bunyadi commands (CRUD)

| Kaam | SQL | Misaal |
|---|---|---|
| **C**reate (banana) | `INSERT` | Naya message save |
| **R**ead (parhna) | `SELECT` | Services dikhana |
| **U**pdate (badalna) | `UPDATE` | Skill level badlo |
| **D**elete (mitaana) | `DELETE` | Purana message hatao |

> ⚠️ `UPDATE` aur `DELETE` mein **`WHERE` kabhi mat bhoolein!** Warna **saari** rows badal ya mit jaayengi! 😱

---

## 9. Website online karna (cPanel hosting)

Pakistan mein zyada tar hosting (Hostinger, Namecheap, etc.) **cPanel** deti hai jis mein PHP aur MySQL pehle se hote hain.

### Step 1: Hosting par database banayein
1. cPanel → **MySQL Databases**
2. Naya database banayein (jaise `username_mywebsite`)
3. Naya user + **mazboot password** banayein
4. User ko database se jorein → **ALL PRIVILEGES**
5. cPanel → **phpMyAdmin** → apna database select karein → **Import** → `database.sql`

> ⚠️ Hosting par aksar `CREATE DATABASE` ki ijazat nahi hoti. Import se pehle `database.sql` ki shuru ki yeh 2 lines **hata dein**: `CREATE DATABASE ...` aur `USE my_website;`

### Step 2: `config.php` badlein
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'username_mywebsite');   // cPanel wala naam
define('DB_USER', 'username_dbuser');      // cPanel wala user
define('DB_PASS', 'MazbootPassword123!');  // cPanel wala password
define('DEBUG', false);                    // ⚠️ ZAROOR false
```

### Step 3: Website build karein
```bash
npm run build
```

### Step 4: Files upload karein
cPanel → **File Manager** → **`public_html`** folder mein:

```
public_html/
├── index.html        ← dist/ se
├── assets/           ← dist/ se
├── .htaccess         ← dist/ se (React Router ke liye)
└── api/              ← backend/api/ ka POORA folder
    ├── config.php
    ├── db.php
    ├── content.php
    └── contact.php
```

1. `dist` folder ke **andar** ki saari files `public_html` mein upload karein
2. `backend/api` folder ko `public_html/api` ki shakal mein upload karein

> 💡 `.htaccess` chhupi (hidden) file hai. File Manager mein **Settings → Show Hidden Files** on karein.

### Step 5: Test karein
- `https://aapki-website.com`: website khule
- `https://aapki-website.com/api/content.php`: JSON dikhe
- `https://aapki-website.com/about` par **refresh** karein. 404 nahi aana chahiye (`.htaccess` ki wajah se) ✅

### `.htaccess` kya karta hai?
```apache
RewriteRule ^api/ - [L]            # /api/ wali requests ko mat chhero (PHP chalne do)
RewriteCond %{REQUEST_FILENAME} -f # asli file hai? (jaise logo.png)
RewriteRule ^ - [L]                #   to wahi do
RewriteRule ^ index.html [L]       # baaqi sab (/about, /contact) → index.html (React Router)
```

---

## 10. Aam errors aur un ka hal

| Error | Wajah | Hal |
|---|---|---|
| *"The server is not responding. Is the PHP server running?"* | `npm run api` nahi chalaya | Doosre terminal mein `npm run api` chalayein |
| *"php is not recognized"* | PHP PATH mein nahi | Section 3, Step 3 dekhein |
| *"SQLSTATE[HY000] [2002] No connection could be made"* / *"No such file or directory"* | MySQL band hai | XAMPP mein **MySQL Start** karein |
| *"SQLSTATE[HY000] [1049] Unknown database 'my_website'"* | Database nahi bana | `database.sql` import karein |
| *"SQLSTATE[HY000] [1045] Access denied for user"* | Ghalat user/password | `config.php` check karein |
| *"Table 'my_website.services' doesn't exist"* | Tables nahi bane | `database.sql` dobara import karein |
| Emojis ajeeb dikhte hain (`ðŸŽ¨`) | Ghalat character set | Database **drop** kar ke `database.sql` dobara import karein |
| *"Address already in use"* (port 8000) | PHP pehle se chal raha hai | Purana terminal band karein, ya port badlein |
| Live site par `/about` refresh par 404 | `.htaccess` upload nahi hui | Hidden files on kar ke upload karein |
| Live site par API 500 error | `config.php` mein ghalat details | cPanel wali details daalein |

### 🔍 Debugging tip
1. Browser mein **F12** → **Network** tab kholein
2. Page refresh karein
3. `content.php` ya `contact.php` par click karein
4. **Response** tab mein PHP ka **asal jawab** dikhega

PHP ki errors us terminal mein bhi dikhti hain jahan `npm run api` chal raha hai.

---

## 11. Practice tasks

1. **Aasaan:** phpMyAdmin se ek nayi **service** add karein aur website par dekhein.
2. **Aasaan:** Ek skill ka `level` badlein aur About page refresh karein.
3. **Aasaan:** `sort_order` badal kar services ki **tarteeb** badlein.
4. **Darmiyana:** `stats` (Home ke number boxes) ko bhi database mein le jayein: naya table, `content.php` mein query, Home mein `content.stats`.
5. **Darmiyana:** `messages` table mein `phone` column add karein, form mein phone field lagayein, aur PHP mein save karein.
6. **Mushkil:** Naya API `backend/api/messages.php` banayein jo saare messages **GET** se de. Phir React mein `/admin` page banayein jo unhein table mein dikhaye.
   ⚠️ Is page par **password** lagana zaroori hai! Warna har koi messages parh lega.
7. **Mushkil:** `projects` table banayein aur ek **Projects page** banayein jo database se projects dikhaye.
8. **Mushkil:** Message save hone par PHP ki `mail()` function (ya **PHPMailer** library) se aap ko **email** bhi aaye.

---

## 12. Naye alfaaz ki dictionary

| Lafz | Aasaan matlab |
|---|---|
| **Frontend** | Jo browser mein dikhta hai (React) |
| **Backend** | Server par chalne wala code (PHP) |
| **Database** | Data ko mehfooz rakhne ki jagah (MySQL) |
| **API** | Backend ka "darwaza" jahan se frontend data maangta ya bhejta hai |
| **Endpoint** | API ka ek URL (jaise `/api/contact.php`) |
| **Request / Response** | Sawaal / jawab (browser ↔ server) |
| **HTTP Method** | Request ki qisam: GET (lena), POST (bhejna) |
| **Status Code** | Server ke jawab ka number (200, 404, 500) |
| **JSON** | Data likhne ka format jo JS aur PHP dono samajhte hain |
| **fetch** | JavaScript ka function jo server ko request bhejta hai |
| **async / await** | Intezaar wala code likhne ka aasaan tareeqa |
| **Promise** | "Wada" ke jawab baad mein aayega (`.then` / `.catch`) |
| **Custom Hook** | Apna hook, `use` se shuru (jaise `useContent`) |
| **Proxy** | Beech ka aadmi jo request aage bhejta hai |
| **CORS** | Browser ka qanoon jo alag domain/port ki request rokta hai |
| **SQL** | Database se baat karne ki zaban |
| **Table / Row / Column** | Excel sheet / ek line / ek khana |
| **Primary Key** | Har row ki unique pehchaan (`id`) |
| **CRUD** | Create, Read, Update, Delete |
| **PDO** | PHP ka database se jurne ka tool |
| **Prepared Statement** | `?` wala mehfooz SQL |
| **SQL Injection** | SQL ke zariye database par hamla |
| **Validation** | Data sahi hai ya nahi, check karna |
| **XAMPP** | PHP + MySQL + Apache ka free package |
| **phpMyAdmin** | Browser mein database dekhne aur badalne ka tool |
| **cPanel** | Hosting ka control panel |
| **Deploy** | Website ko internet par live karna |

---

## 🎯 Aakhri Mashwara

1. Sab se pehle **XAMPP** chala kar **database import** karein aur website chalayein.
2. phpMyAdmin mein **data badal kar** dekhein ke website par kya farq aata hai.
3. **F12 → Network tab** mein dekhein ke request kaise jaati hai aur jawab kaisa aata hai. Is se bohat kuch samajh aata hai!
4. **Security ke usool** (prepared statements, server validation) kabhi mat bhoolna. Har asli project mein kaam aayenge.

**Ab aap Full Stack Developer banne ke raaste par hain! 💪 Happy Coding! 🚀**
