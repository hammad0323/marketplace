# Har File ka Complete Guide (Roman Urdu) 📘

Is guide mein **project ki har file** ko aasaan Roman Urdu mein samjhaya gaya hai:
- Yeh file **kya hai**
- Yeh file **kyun use hoti hai**
- Is ke andar ka **code kya kaam karta hai** (line by line)

> 💡 Tip: Har file ko VS Code mein khol kar is guide ke saath saath parhein. Is tarah zyada jaldi samajh aayega.

---

## Fehrist (Table of Contents)

**Hissa 1: Shuru ki baatein**
1. [Poora project ek nazar mein](#1-poora-project-ek-nazar-mein)
2. [Website chalti kaise hai? (Flow)](#2-website-chalti-kaise-hai-flow)

**Hissa 2: Bahar wali files (Project setup)**

3. [package.json](#3-packagejson)
4. [vite.config.js](#4-viteconfigjs)
5. [index.html](#5-indexhtml)
6. [.gitignore, public/_redirects, vercel.json](#6-gitignore-public_redirects-verceljson)

**Hissa 3: `src` folder ki files**

7. [main.jsx](#7-srcmainjsx)
8. [App.jsx](#8-srcappjsx)
9. [index.css](#9-srcindexcss)
10. [data/siteData.js](#10-srcdatasitedatajs)

**Hissa 4: `components` folder**

11. [Layout.jsx](#11-componentslayoutjsx)
12. [ScrollToTop.jsx](#12-componentsscrolltotopjsx)
13. [Navbar.jsx + Navbar.css](#13-componentsnavbarjsx--navbarcss)
14. [Footer.jsx + Footer.css](#14-componentsfooterjsx--footercss)
15. [PageHeader.jsx + PageHeader.css](#15-componentspageheaderjsx--pageheadercss)

**Hissa 5: `pages` folder**

16. [Home.jsx + Home.css](#16-pageshomejsx--homecss)
17. [About.jsx + About.css](#17-pagesaboutjsx--aboutcss)
18. [Contact.jsx + Contact.css](#18-pagescontactjsx--contactcss)
19. [NotFound.jsx](#19-pagesnotfoundjsx)

**Hissa 6: Aakhri baatein**

20. [Kaunsi file kab edit karni hai?](#20-kaunsi-file-kab-edit-karni-hai)
21. [Mushkil alfaaz ki dictionary](#21-mushkil-alfaaz-ki-dictionary)

---

# Hissa 1: Shuru ki baatein

## 1. Poora project ek nazar mein

```
my-website/
├── package.json          ← Project ka "shanakhti card" + libraries ki list
├── vite.config.js        ← Vite (tool) ki settings
├── index.html            ← Website ka sirf EK HTML page
├── .gitignore            ← Git ko batata hai kaunsi files ignore karni hain
├── vercel.json           ← Vercel par deploy ke liye fix
├── public/
│   └── _redirects        ← Netlify par deploy ke liye fix
└── src/                  ← ⭐ Aapka saara code yahan hai
    ├── main.jsx          ← App yahan se SHURU hoti hai
    ├── App.jsx           ← Kaunsa URL → kaunsa page (routes)
    ├── index.css         ← Poori website ki common styling
    ├── data/
    │   └── siteData.js   ← Aapka saara text (naam, skills, services)
    ├── components/       ← Choti choti cheezein jo baar baar use hoti hain
    │   ├── Layout.jsx
    │   ├── ScrollToTop.jsx
    │   ├── Navbar.jsx + Navbar.css
    │   ├── Footer.jsx + Footer.css
    │   └── PageHeader.jsx + PageHeader.css
    └── pages/            ← Har page ki apni file
        ├── Home.jsx + Home.css         → /
        ├── About.jsx + About.css       → /about
        ├── Contact.jsx + Contact.css   → /contact
        └── NotFound.jsx                → galat URL (404)
```

### Teen folders ka farq (bohat zaroori)

| Folder | Kya rakhte hain | Misaal |
|---|---|---|
| `pages/` | **Poora page** jiska apna URL ho | Home, About, Contact |
| `components/` | **Chota hissa** jo kai pages par use ho | Navbar, Footer |
| `data/` | Sirf **text aur lists**, koi design nahi | Naam, skills ki list |

**Asaan misaal:** Ghar ko sochiye 🏠
- `pages/` = **kamray** (bedroom, kitchen, drawing room)
- `components/` = **furniture** jo har kamray mein use hota hai (pankha, light, darwaza)
- `data/` = **saaman** jo kamray mein rakha hai

### `.jsx` aur `.css` ka joda
Har component ki **do files** hain:
- `Navbar.jsx` → **kya dikhana hai** (structure + logic)
- `Navbar.css` → **kaisa dikhna chahiye** (rang, size, design)

---

## 2. Website chalti kaise hai? (Flow)

Jab aap browser mein website kholte hain, to yeh cheezein **isi tarteeb (order)** se hoti hain:

```
1. Browser  →  index.html  khulta hai
                   │  (is mein <div id="root"> khaali hai)
                   ▼
2. index.html  →  src/main.jsx  chalata hai
                   │  (React shuru hota hai + Router on hota hai)
                   ▼
3. main.jsx  →  <App />  dikhata hai
                   │  (App URL dekhta hai: "/" ya "/about" ya "/contact")
                   ▼
4. App.jsx  →  <Layout />  dikhata hai
                   │  (Navbar + beech mein page + Footer)
                   ▼
5. Layout  →  <Outlet />  ki jagah sahi PAGE dikhata hai
                   │
                   ▼
6. Page (Home/About/Contact)  →  siteData.js se text leta hai aur dikhata hai
```

**Ek line mein:** `index.html` → `main.jsx` → `App.jsx` → `Layout.jsx` → `Page` → `siteData.js`

---

# Hissa 2: Bahar wali files (Project setup)

## 3. `package.json`

### Yeh kya hai?
Yeh project ka **"shanakhti card" (ID card)** hai. Is mein likha hota hai:
- Project ka naam
- Kaunsi **libraries** chahiye
- Kaunse **commands** chalte hain

### Kyun zaroori hai?
Jab aap `npm install` likhte hain, to npm **isi file ko parhta hai** aur saari libraries `node_modules` folder mein download kar deta hai.
Is file ke baghair npm ko pata hi nahi chalega ke kya install karna hai.

### Code samjhein

```json
{
  "name": "my-website",          // project ka naam
  "private": true,               // yeh npm par publish nahi hoga
  "version": "1.0.0",            // project ka version
  "type": "module",              // "import/export" wala modern JavaScript use karo

  "scripts": {                   // ⭐ yeh commands hain
    "dev": "vite",               // npm run dev     → website test ke liye chalao
    "build": "vite build",       // npm run build   → final website banao (dist folder)
    "preview": "vite preview"    // npm run preview → final website check karo
  },

  "dependencies": {              // ⭐ website CHALANE ke liye zaroori libraries
    "react": "^18.3.1",          // React khud
    "react-dom": "^18.3.1",      // React ko browser se jorta hai
    "react-router-dom": "^7.0.0" // Multiple pages (Home/About/Contact) ke liye
  },

  "devDependencies": {           // sirf BANATE waqt chahiye, live site mein nahi
    "@vitejs/plugin-react": "^4.3.4", // Vite ko React samjhata hai
    "vite": "^5.4.11"                 // Fast development server
  }
}
```

### `^` ka matlab kya hai?
`"^18.3.1"` ka matlab: "18.3.1 ya is se naya version, lekin 19 nahi."
Is tarah chhote updates khud aa jaate hain, magar bara tabdeeli wala update nahi aata.

### `dependencies` vs `devDependencies`

| | dependencies | devDependencies |
|---|---|---|
| Kab chahiye | Website **chalte waqt** | Sirf **banate waqt** |
| Misaal | react, react-router-dom | vite |

> ⚠️ Is file ko haath se zyada edit na karein. Nayi library ke liye `npm install library-name` likhein, npm khud add kar dega.

---

## 4. `vite.config.js`

### Yeh kya hai?
**Vite** ek tool hai jo:
1. Aapki website ko `localhost:5173` par chalata hai
2. File save karte hi browser **khud refresh** kar deta hai
3. Aakhir mein website ko **chhota aur fast** bana kar `dist` folder mein deta hai

Yeh file Vite ki **settings** hai.

### Code samjhein

```js
import { defineConfig } from 'vite';          // Vite ka settings function
import react from '@vitejs/plugin-react';     // React plugin

export default defineConfig({
  plugins: [react()],    // Vite ko batao: "yeh React project hai, JSX samjho"
});
```

### Kyun zaroori hai?
Browser **JSX** (`<h1>Hello</h1>` JavaScript ke andar) nahi samajhta.
`react()` plugin JSX ko normal JavaScript mein badal deta hai jo browser samajh sake.

> 💡 Beginner level par is file ko **chherne ki zaroorat nahi**.

---

## 5. `index.html`

### Yeh kya hai?
Poori website mein **sirf yehi EK HTML file** hai! 😮
Home, About, Contact, sab isi ek page ke andar React dikhata hai. Is liye ise **SPA (Single Page Application)** kehte hain.

### Code samjhein

```html
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <!-- Mobile par website sahi size mein dikhe -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!-- Browser tab par yeh naam dikhega -->
    <title>My Website</title>

    <!-- Google Font "Poppins" load ho raha hai (khoobsurat font ke liye) -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  </head>
  <body>
    <!-- ⭐ Yeh KHAALI div hai. React poori website isi ke andar banayega -->
    <div id="root"></div>

    <!-- ⭐ Yeh line React ka code (main.jsx) chalati hai -->
    <script type="module" src="/src/main.jsx"></script>
  </body>
</html>
```

### Do sab se zaroori lines
1. `<div id="root"></div>`: React ka **"ghar"**. Saari website yahan aati hai.
2. `<script src="/src/main.jsx">`: React ko **"start"** karne wala button.

### Kab edit karein?
- Browser tab ka naam badalna ho → `<title>` badlein
- Font badalna ho → Google Fonts ka link badlein

---

## 6. `.gitignore`, `public/_redirects`, `vercel.json`

### `.gitignore`
```
node_modules
dist
```
**Kyun?** Yeh Git ko batata hai ke yeh folders GitHub par **upload na karo**.
- `node_modules` bohat bhaari hota hai (hazaron files). Koi bhi `npm install` se dobara bana sakta hai.
- `dist` bhi `npm run build` se dobara ban jaata hai.

### `public/_redirects` (Netlify ke liye)
```
/*    /index.html   200
```
### `vercel.json` (Vercel ke liye)
```json
{ "rewrites": [{ "source": "/(.*)", "destination": "/index.html" }] }
```

**Yeh dono kyun?**
Masla yeh hai: jab website online ho aur koi `/about` par **refresh** kare, to server `about.html` dhoondta hai jo **mojood hi nahi** (hamare paas sirf `index.html` hai). Natija: **404 error** ❌

Yeh files server ko kehti hain: *"Koi bhi URL aaye, `index.html` bhej do. Baaki React Router khud sambhal lega."* ✅

> 📁 `public/` folder ki files **bilkul waise hi** final website mein copy ho jaati hain. Apni photo (`me.jpg`) bhi yahin rakhein.

---

# Hissa 3: `src` folder ki files

## 7. `src/main.jsx`

### Yeh kya hai?
Yeh React app ka **"start button"** hai. Sab se pehle yahi file chalti hai.

### Code samjhein

```jsx
import React from 'react';                       // React library
import ReactDOM from 'react-dom/client';         // React ko browser se jorne wali library
import { BrowserRouter } from 'react-router-dom'; // Pages (routing) ke liye
import App from './App.jsx';                      // Hamara main component
import './index.css';                             // Poori website ki common CSS

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>
);
```

### Line by line

| Code | Matlab |
|---|---|
| `document.getElementById('root')` | `index.html` wala khaali `<div id="root">` dhoondo |
| `ReactDOM.createRoot(...)` | React ko kaho: "yeh div ab tumhara hai" |
| `.render(...)` | Is div ke andar yeh cheez dikhao |
| `<React.StrictMode>` | Development mein galtiyon ki **warning** deta hai. Website par nazar nahi aata |
| `<BrowserRouter>` | ⭐ **Routing on karta hai.** Browser ka URL dekhta rehta hai |
| `<App />` | Hamari poori app |

### `BrowserRouter` yahan kyun?
`BrowserRouter` ko **sab se bahar** hona chahiye taake andar ki **har file** routing use kar sake (`Link`, `NavLink`, `useLocation`).
Agar yeh na ho to error aayega: *"useLocation() may be used only in the context of a `<Router>`"*

### `import './index.css'` yahan kyun?
Common CSS (buttons, rang, fonts) **poori website** par lagni chahiye, is liye sab se pehli file mein import ki.

> 💡 Is file ko aam taur par **kabhi edit nahi** karna parta.

---

## 8. `src/App.jsx`

### Yeh kya hai?
Yeh website ka **"naqsha" (map)** hai. Yeh batata hai: **kaunsa URL kholne par kaunsa page dikhe.**

### Code samjhein

```jsx
import { Routes, Route } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import Home from './pages/Home.jsx';
import About from './pages/About.jsx';
import Contact from './pages/Contact.jsx';
import NotFound from './pages/NotFound.jsx';

function App() {
  return (
    <Routes>
      <Route path="/" element={<Layout />}>
        <Route index element={<Home />} />
        <Route path="about" element={<About />} />
        <Route path="contact" element={<Contact />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  );
}

export default App;
```

### Har cheez ka matlab

| Code | Matlab |
|---|---|
| `<Routes>` | Saare routes ka **dabba**. Is mein se sirf **ek** match hone wala dikhta hai |
| `<Route path="/" element={<Layout />}>` | Bahar wala route: **har page** ke gird Layout (Navbar + Footer) lagao |
| `<Route index element={<Home />} />` | `index` = default. URL `/` ho to **Home** dikhao |
| `path="about"` | URL `/about` ho to **About** dikhao |
| `path="contact"` | URL `/contact` ho to **Contact** dikhao |
| `path="*"` | `*` = **"kuch bhi aur"**. Galat URL par **404 page** dikhao |

### Table ki shakal mein

| Browser mein URL | Kya dikhega |
|---|---|
| `localhost:5173/` | Navbar + **Home** + Footer |
| `localhost:5173/about` | Navbar + **About** + Footer |
| `localhost:5173/contact` | Navbar + **Contact** + Footer |
| `localhost:5173/xyz` | Navbar + **404 Page** + Footer |

### Routes ek doosre ke andar (nested) kyun hain?
Home, About, Contact **Layout ke andar** hain. Is liye har page par **khud ba khud** Navbar aur Footer aa jaate hain.
Har page mein alag se Navbar likhne ki zaroorat **nahi**.

### Naya page kaise banayein? (3 steps)
1. `pages/Services.jsx` file banao
2. Yahan `App.jsx` mein: `import Services from './pages/Services.jsx';` aur `<Route path="services" element={<Services />} />`
3. `Navbar.jsx` ki `links` list mein `{ to: '/services', label: 'Services' }` add karo

---

## 9. `src/index.css`

### Yeh kya hai?
**Global CSS**: woh styling jo **poori website** par lagti hai (har page par).

### Kyun alag file?
Buttons, cards, rang aur fonts **har page** par same hone chahiye. Har page mein dobara likhne ke bajaye **ek jagah** likh diye.

### Hisson mein samjhein

#### 🎨 Hissa 1: Design Tokens (CSS Variables), sab se zaroori!
```css
:root {
  --primary: #6d28d9;        /* main jamni (purple) rang */
  --primary-light: #ede9fe;  /* halka jamni (backgrounds ke liye) */
  --secondary: #db2777;      /* gulabi (pink) rang */
  --text: #1f2937;           /* text ka rang */
  --muted: #6b7280;          /* halka grey text */
  --bg: #f9fafb;             /* page ka background */
  --surface: #ffffff;        /* cards ka safaid rang */
  --border: #e5e7eb;         /* border ka rang */
  --dark: #111827;           /* footer ka kaala rang */
  --danger: #dc2626;         /* laal (errors ke liye) */
  --success: #16a34a;        /* hara (success ke liye) */

  --gradient: linear-gradient(135deg, var(--primary), var(--secondary)); /* jamni → gulabi */
  --shadow: 0 10px 30px rgba(17, 24, 39, 0.08);  /* halka saya */
  --radius: 16px;            /* kono ki golai */
}
```
**Kyun?** Rang **ek jagah** likhe hain aur har jagah `var(--primary)` se use hote hain.
👉 **Sirf `--primary` aur `--secondary` badlein, poori website ka rang badal jaayega!** 🎉

#### 🧹 Hissa 2: Reset
```css
*, *::before, *::after {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
```
**Kyun?** Har browser apni taraf se margin/padding laga deta hai. Yeh sab ko **zero** kar deta hai taake design har browser mein same dikhe.
`box-sizing: border-box` ka matlab: width mein padding aur border bhi shaamil hon (size ka hisaab aasaan ho jaata hai).

#### 📦 Hissa 3: Layout Helpers
```css
.container {
  max-width: 1140px;  /* content zyada chaura na ho */
  margin: 0 auto;     /* beech mein rahe */
  padding: 0 1.25rem; /* side se thori jagah */
}
```
**Kyun?** Har section ka content **beech mein** aur **same chaurai** ka rahe. Yeh class har page par use hoti hai.

```css
.page { animation: fadeIn 0.4s ease; }
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
```
**Kyun?** Page badalte waqt naya page **aahista se ubhar kar** aata hai, jo professional lagta hai.

| Class | Kaam |
|---|---|
| `.section` | Upar neeche bari jagah (padding) |
| `.section-alt` | Safaid background wala section (farq dikhane ke liye) |
| `.section-heading` | Beech mein heading |
| `.eyebrow` | Heading ke upar chhota rangeen text ("WHAT I DO") |
| `.gradient-text` | Text mein jamni→gulabi rang |

#### 🔘 Hissa 4: Buttons
```css
.btn { background: var(--gradient); border-radius: 999px; ... }
.btn:hover { transform: translateY(-2px); }  /* mouse le jao to button upar uthta hai */
.btn-outline { background: transparent; }    /* sirf border wala button */
.btn-light { background: #fff; }             /* safaid button */
.btn-small { ... }                           /* chhota button (navbar mein) */
.btn-full { width: 100%; }                   /* poori chaurai (form mein) */
```
**Kyun?** Ek basic `.btn` aur kuch **"variations"**. Use aise karte hain: `className="btn btn-outline"`

#### 🃏 Hissa 5: Cards
```css
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}
```
**Yeh magic line hai!** Matlab: "Jitne cards fit hon (har ek kam se kam 260px), utne ek line mein rakho."
Laptop par 3, tablet par 2, mobile par 1. **Media query ke baghair khud ho jaata hai!**

---

## 10. `src/data/siteData.js`

### Yeh kya hai?
Website ka **saara text aur maloomat** (naam, email, skills, services) **sirf is ek file** mein hai.

### Kyun alag file? (bohat achhi aadat ✅)
- Apna naam badalna hai? → **Sirf yeh file** kholo, 10 files dhoondne ki zaroorat nahi
- Design (JSX) aur maloomat (data) **alag alag** rahte hain
- Kal ko yeh data **database ya API** se aa sakta hai aur pages ka code waisa hi rahega

### Code samjhein

```js
// 1. Ek OBJECT: aap ki basic maloomat
export const site = {
  brand: 'Ali.dev',           // navbar mein logo
  name: 'Ali Khan',           // aap ka naam
  role: 'Frontend Developer', // aap ka kaam
  tagline: '...',             // ek line ka taaruf
  email: 'hello@example.com',
  phone: '+92 300 1234567',
  location: 'Karachi, Pakistan',
  socials: [                  // object ke andar ARRAY
    { label: 'GitHub', url: '...' },
    ...
  ],
};

// 2. ARRAYS: lists jo .map() se dikhti hain
export const stats = [ { value: '2+', label: 'Years learning' }, ... ];   // Home page ke number boxes
export const services = [ { icon: '🎨', title: '...', text: '...' }, ... ]; // Home page ke cards
export const skills = [ { name: 'React', level: 75 }, ... ];              // About page ke bars
export const timeline = [ { year: '2026', title: '...', text: '...' }, ... ]; // About page ka timeline
```

### Object vs Array

| | Object `{ }` | Array `[ ]` |
|---|---|---|
| Kya hai | **Ek cheez** ki maloomat | **Bohat si cheezon** ki list |
| Misaal | `site` (aap ka profile) | `skills` (skills ki list) |
| Kaise use | `site.name` | `skills.map(...)` |

### `export const` kyun?
`export` = "doosri files is ko use kar sakti hain."
Doosri file mein aise mangwate hain: `import { site, skills } from '../data/siteData.js';`

### Kaunsa data kahan dikhta hai?

| Data | Kahan dikhta hai |
|---|---|
| `site` | Har jagah (Navbar, Footer, Home, About, Contact) |
| `stats` | Home → 3 number boxes |
| `services` | Home → 3 service cards |
| `skills` | About → progress bars |
| `timeline` | About → Experience section |

> ✏️ **Yeh file sab se pehle edit karein.** Apna naam, email aur skills daalein!

---

# Hissa 4: `components` folder

## 11. `components/Layout.jsx`

### Yeh kya hai?
Website ka **"frame" (dhancha)**. Upar Navbar, neeche Footer, aur **beech mein** badalne wala page.

### Code samjhein

```jsx
import { Outlet } from 'react-router-dom';
import Navbar from './Navbar.jsx';
import Footer from './Footer.jsx';
import ScrollToTop from './ScrollToTop.jsx';

function Layout() {
  return (
    <>
      <ScrollToTop />      {/* page badle to upar scroll karo */}
      <Navbar />           {/* har page par upar */}
      <main className="page">
        <Outlet />         {/* ⭐ yahan Home/About/Contact aata hai */}
      </main>
      <Footer />           {/* har page par neeche */}
    </>
  );
}
```

### `<Outlet />` kya hai? ⭐
Yeh ek **khaali jagah (slot)** hai. React Router URL dekh kar **sahi page** yahan rakh deta hai.

```
┌─────────── Layout ───────────┐
│  Navbar        (hamesha same) │
│ ┌───────── Outlet ─────────┐ │
│ │ Home YA About YA Contact │ │ ← sirf yeh hissa badalta hai
│ └──────────────────────────┘ │
│  Footer        (hamesha same) │
└──────────────────────────────┘
```

**Misaal:** TV ka frame same rehta hai, sirf screen par channel badalta hai. 📺

### `<> </>` kya hai?
Isay **Fragment** kehte hain. React mein component ko **sirf ek** cheez return karni hoti hai.
Fragment kai cheezon ko **group** kar deta hai, lekin page par koi extra `<div>` nahi banata.

### `<main>` kyun?
`<main>` ek **semantic** HTML tag hai jo batata hai "page ka asal content yeh hai." Google aur screen readers (nabeena logon ke software) isay samajhte hain.

---

## 12. `components/ScrollToTop.jsx`

### Yeh kya hai?
Ek chhota sa **"helper"** jo page badalne par screen ko **sab se upar** le jaata hai.

### Masla kya tha?
Aap Home page par **neeche** scroll karein aur "About" dabayein, to About page bhi **neeche se** khulta tha. 😕
Yeh component is masle ko hal karta hai.

### Code samjhein

```jsx
import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

function ScrollToTop() {
  const { pathname } = useLocation();  // abhi ka URL, jaise "/about"

  useEffect(() => {
    window.scrollTo(0, 0);              // screen ko bilkul upar le jao (x=0, y=0)
  }, [pathname]);                       // ⭐ jab bhi URL badle, tab chalao

  return null;                          // screen par KUCH nahi dikhata
}
```

### Nayi cheezein

| Cheez | Matlab |
|---|---|
| `useLocation()` | React Router ka **hook**. Abhi ka URL batata hai |
| `useEffect(() => {...}, [pathname])` | Jab bhi `pathname` badle, andar wala code chalao |
| `return null` | Yeh component **kuch dikhata nahi**, sirf kaam karta hai |

**Seekh:** Har component ka kaam kuch dikhana nahi hota. Kuch components sirf **kaam** karte hain. 🛠️

---

## 13. `components/Navbar.jsx` + `Navbar.css`

### Yeh kya hai?
Upar wala **menu**: logo, page links, "Hire Me" button, aur mobile par ☰ (hamburger) button.

### Code samjhein: hisson mein

#### Hissa 1: Imports aur links ki list
```jsx
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { site } from '../data/siteData.js';
import './Navbar.css';                     // sirf Navbar ki CSS

const links = [
  { to: '/', label: 'Home' },
  { to: '/about', label: 'About' },
  { to: '/contact', label: 'Contact' },
];
```
**Kyun list?** Teen `<NavLink>` alag alag likhne ke bajaye ek **list** banai aur `.map()` se loop chalaya.
Naya page add karna ho to **sirf ek line** list mein daalni hai.

#### Hissa 2: State (menu khula hai ya band?)
```jsx
const [open, setOpen] = useState(false);
```
- `open` = menu **khula** hai (`true`) ya **band** (`false`)
- Shuru mein `false` = band
- `setOpen(...)` se badalte hain

#### Hissa 3: Logo
```jsx
<Link to="/" className="brand" onClick={() => setOpen(false)}>
  {site.brand}
</Link>
```
- `<Link to="/">`: logo dabao to Home par jao, **bina page reload** ke
- `onClick={() => setOpen(false)}`: mobile par menu band kar do

#### Hissa 4: Hamburger button (☰)
```jsx
<button
  className="menu-toggle"
  onClick={() => setOpen(!open)}   // ! = ulta karo (true→false, false→true)
  aria-label="Toggle menu"         // screen reader ke liye naam
  aria-expanded={open}             // screen reader ko batao: khula hai ya band
>
  <span className={open ? 'bar bar-open' : 'bar'} />
</button>
```
- `!open` = **ulta**. Khula hai to band karo, band hai to kholo
- `open ? 'bar bar-open' : 'bar'`: khula ho to ☰ ki jagah ✕ ban jaaye (CSS se)

#### Hissa 5: Links (`.map()` + `NavLink`)
```jsx
<nav className={open ? 'nav nav-open' : 'nav'}>
  {links.map((link) => (
    <NavLink
      key={link.to}
      to={link.to}
      end={link.to === '/'}
      className={({ isActive }) => (isActive ? 'nav-link active' : 'nav-link')}
      onClick={() => setOpen(false)}
    >
      {link.label}
    </NavLink>
  ))}
  <Link to="/contact" className="btn btn-small">Hire Me</Link>
</nav>
```

| Cheez | Matlab |
|---|---|
| `links.map(...)` | Har link ke liye ek `NavLink` banao |
| `key={link.to}` | React ko har item ki **pehchaan** chahiye (unique honi chahiye) |
| `NavLink` | `Link` jaisa, lekin **jaanta hai** ke yeh page khula hai ya nahi |
| `isActive` | Yeh page abhi khula hai to `true` |
| `'nav-link active'` | Khula page ho to `active` class lagao (jamni + neeche line) |
| `end={link.to === '/'}` | Sirf Home ke liye. Warna `/` **har page** par active lagega (kyunke har URL `/` se shuru hota hai) |

### `Link` vs `NavLink` vs `<a>`

| Cheez | Kab use karein |
|---|---|
| `<Link to="/about">` | Apni website ke page par jaana ho |
| `<NavLink to="/about">` | Menu mein, jahan **khula page highlight** karna ho |
| `<a href="https://...">` | **Doosri** website par jaana ho (GitHub, LinkedIn) |

⚠️ Apne pages ke liye `<a href="/about">` **mat** use karein. Is se poori website **reload** hoti hai (slow).

### `Navbar.css`: zaroori cheezein

```css
.navbar {
  position: sticky;   /* ⭐ scroll karo to bhi navbar upar chipka rahe */
  top: 0;
  z-index: 50;        /* doosri cheezon ke UPAR rahe */
  background: rgba(255, 255, 255, 0.85);  /* thora transparent safaid */
  backdrop-filter: blur(10px);            /* peeche ka content dhundla (glass effect) */
}
```

```css
/* Khule page ke neeche line */
.nav-link.active::after {
  content: '';
  position: absolute;
  bottom: -4px;
  height: 2px;
  background: var(--primary);
}
```
`::after` ek **nakli element** hai jo CSS khud banati hai. HTML mein kuch likhne ki zaroorat nahi.

```css
/* ☰ ki teen lakeerein: ek .bar + ::before + ::after */
.bar::before { top: -7px; }   /* upar wali lakeer */
.bar::after  { top: 7px; }    /* neeche wali lakeer */

/* ✕ banana: beech wali gayab, baaqi do ghoom jaayen */
.bar-open { background: transparent; }
.bar-open::before { transform: translateY(7px) rotate(45deg); }
.bar-open::after  { transform: translateY(-7px) rotate(-45deg); }
```

```css
/* Mobile (768px se chhoti screen) */
@media (max-width: 768px) {
  .menu-toggle { display: block; }   /* ☰ dikhao */
  .nav { opacity: 0; visibility: hidden; }  /* links chhupao */
  .nav-open { opacity: 1; visibility: visible; }  /* khula ho to dikhao */
}
```
**Media query** = "agar screen itni chhoti ho, to yeh CSS lagao."

---

## 14. `components/Footer.jsx` + `Footer.css`

### Yeh kya hai?
Har page ke **neeche** wala kaala hissa: brand, page links, social links aur copyright.

### Code samjhein

```jsx
function Footer() {
  const year = new Date().getFullYear();   // ⭐ abhi ka saal khud nikalta hai (2026)

  return (
    <footer className="footer">
      <div className="container footer-grid">
        {/* Column 1: Brand */}
        <div>
          <h3 className="footer-brand">{site.brand}</h3>
          <p>{site.tagline}</p>
        </div>

        {/* Column 2: Apne pages → <Link> */}
        <div>
          <h4>Pages</h4>
          <ul>
            <li><Link to="/">Home</Link></li>
            <li><Link to="/about">About</Link></li>
            <li><Link to="/contact">Contact</Link></li>
          </ul>
        </div>

        {/* Column 3: Bahar ki websites → <a> */}
        <div>
          <h4>Follow</h4>
          <ul>
            {site.socials.map((s) => (
              <li key={s.label}>
                <a href={s.url} target="_blank" rel="noreferrer">{s.label}</a>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <p className="footer-bottom">© {year} {site.name}. All rights reserved.</p>
    </footer>
  );
}
```

### Zaroori baatein

| Cheez | Matlab |
|---|---|
| `new Date().getFullYear()` | Saal **khud** update hota hai. Har saal badalne ki zaroorat nahi |
| Pages ke liye `<Link>` | Apni website ke andar, bina reload |
| Socials ke liye `<a>` | Doosri website (GitHub) par jaana hai |
| `target="_blank"` | Link **nayi tab** mein khule |
| `rel="noreferrer"` | **Security**: nayi website aap ki website ko control na kar sake |

**Yeh file `Link` aur `<a>` ka farq samajhne ki best misaal hai!**

### `Footer.css`
```css
.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;  /* 3 columns: pehla dugna chaura */
}
@media (max-width: 768px) {
  .footer-grid { grid-template-columns: 1fr; }  /* mobile par ek ke neeche ek */
}
```
`fr` = **fraction (hissa)**. `2fr 1fr 1fr` = jagah ke 4 hisse: pehle column ko 2, baaqi dono ko 1-1.

---

## 15. `components/PageHeader.jsx` + `PageHeader.css`

### Yeh kya hai?
About aur Contact page ke **upar wala banner** (bara heading + chhota text).

### Kyun banaya? (Reusable component ki misaal ⭐)
About aur Contact dono par **same design** ka banner chahiye tha, sirf **text alag**.
Do baar code likhne ke bajaye **ek component** banaya aur text **props** se bheja.

### Code samjhein

```jsx
// { title, subtitle } = props (bahar se aane wali maloomat)
function PageHeader({ title, subtitle }) {
  return (
    <section className="page-header">
      <div className="container">
        <h1>{title}</h1>
        <p>{subtitle}</p>
      </div>
    </section>
  );
}
```

**Use aise hota hai:**
```jsx
// About.jsx mein
<PageHeader title="About Me" subtitle="A little about who I am..." />

// Contact.jsx mein
<PageHeader title="Contact Me" subtitle="Have a question or a project idea?..." />
```

### Props kya hain?
Props = component ko **bahar se maloomat dena**, bilkul function ke **arguments** ki tarah.

```
About.jsx  ──(title="About Me")──▶  PageHeader  ──▶  <h1>About Me</h1>
```

**Misaal:** PageHeader ek **khaali form** hai, aur props us mein **bhari gayi maloomat** hain. 📝

### `PageHeader.css`
```css
.page-header h1 {
  font-size: clamp(2.2rem, 5vw, 3.2rem);
}
```
`clamp(kam se kam, behtar, zyada se zyada)`: font screen ke hisaab se **khud** chhota bara hota hai.
Mobile par 2.2rem, bari screen par 3.2rem tak.

---

# Hissa 5: `pages` folder

## 16. `pages/Home.jsx` + `Home.css`

### Yeh kya hai?
Website ka **pehla page** (URL: `/`). Is ke **4 hisse** hain:

```
┌────────────────────────────────────┐
│ 1. HERO     → Naam, kaam, 2 buttons │
├────────────────────────────────────┤
│ 2. STATS    → 3 number boxes        │
├────────────────────────────────────┤
│ 3. SERVICES → 3 cards               │
├────────────────────────────────────┤
│ 4. CTA      → "Contact Me" box      │
└────────────────────────────────────┘
```

### Hissa 1: Hero
```jsx
<section className="hero">
  <div className="container hero-grid">
    <div className="hero-text">
      <span className="badge">👋 Available for work</span>
      <h1>
        Hi, I'm <span className="gradient-text">{site.name}</span>
        <br />
        {site.role}
      </h1>
      <p>{site.tagline}</p>
      <div className="hero-actions">
        <Link to="/contact" className="btn">Let's Talk</Link>
        <Link to="/about" className="btn btn-outline">About Me</Link>
      </div>
    </div>

    <div className="hero-visual" aria-hidden="true">
      <div className="blob" />
      <div className="avatar">{site.name.charAt(0)}</div>
    </div>
  </div>
</section>
```

| Cheez | Matlab |
|---|---|
| `{site.name}` | `siteData.js` se naam aata hai |
| `className="gradient-text"` | Naam jamni→gulabi rang mein |
| `{site.name.charAt(0)}` | Naam ka **pehla harf** ("Ali" → "A"). Photo ki jagah |
| `aria-hidden="true"` | Screen reader ko kaho: "yeh sirf design hai, ignore karo" |
| `className="btn btn-outline"` | Do classes ek saath: basic button + border wala style |

### Hissa 2: Stats
```jsx
{stats.map((item) => (
  <div key={item.label} className="stat">
    <strong>{item.value}</strong>   {/* "2+" */}
    <span>{item.label}</span>       {/* "Years learning" */}
  </div>
))}
```
**`.map()` kya karta hai?** List ke **har item** ke liye ek box bana deta hai.
3 items = 3 boxes. 4 daalo to 4 ban jaayenge, code badle baghair!

### Hissa 3: Services
```jsx
<div className="cards">
  {services.map((service) => (
    <article key={service.title} className="card">
      <div className="card-icon">{service.icon}</div>
      <h3>{service.title}</h3>
      <p>{service.text}</p>
    </article>
  ))}
</div>
```
Bilkul Stats jaisa, `.map()` se cards banaye. `.cards` aur `.card` ki CSS `index.css` mein hai (common hai).

### Hissa 4: CTA (Call To Action)
```jsx
<div className="cta">
  <h2>Have a project in mind?</h2>
  <p>Let's work together and build something great.</p>
  <Link to="/contact" className="btn btn-light">Contact Me</Link>
</div>
```
**CTA kyun?** Page ke aakhir mein visitor ko **agla qadam** batana (contact karna). Har achhi website mein hota hai.

### `Home.css`: khaas cheezein

```css
.hero-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr;  /* 2 columns: text thora bara, picture thori chhoti */
}
```

```css
/* Hilta hua blob 🫧 */
.blob {
  border-radius: 42% 58% 63% 37% / 41% 44% 56% 59%;  /* ajeeb golai = blob shape */
  animation: morph 8s ease-in-out infinite;          /* hamesha chalta rahe */
}
@keyframes morph {
  50% { border-radius: 58% 42% 37% 63% / 56% 59% 41% 44%; }  /* shape badle */
}
```
`@keyframes` = animation ke **qadam**. Blob ki shape aahista aahista badalti rehti hai.

```css
.stats { margin-top: -2.5rem; }  /* minus margin = boxes upar khisak kar hero ke upar aa jaate hain */
```

```css
@media (max-width: 768px) {
  .hero-grid { grid-template-columns: 1fr; }  /* mobile: 2 ki jagah 1 column */
  .hero-visual { order: -1; }                  /* picture text se UPAR aa jaaye */
  .stats { grid-template-columns: 1fr; }       /* boxes ek ke neeche ek */
}
```

---

## 17. `pages/About.jsx` + `About.css`

### Yeh kya hai?
"Mere baare mein" page (URL: `/about`). Is ke **4 hisse** hain:

```
1. PageHeader  → "About Me" banner
2. Intro       → Picture + taaruf
3. Skills      → Progress bars
4. Timeline    → Experience
```

### Hissa 1: PageHeader (reusable component)
```jsx
<PageHeader
  title="About Me"
  subtitle="A little about who I am, what I know and where I'm going."
/>
```

### Hissa 2: Intro
```jsx
<p>
  Hello! I'm {site.name}, a {site.role.toLowerCase()} based in {site.location}.
</p>
```
`.toLowerCase()` = text ko **chhote huroof** mein badlo: "Frontend Developer" → "frontend developer"

### Hissa 3: Skills ke Progress Bars ⭐
```jsx
{skills.map((skill) => (
  <div key={skill.name} className="skill">
    <div className="skill-top">
      <span>{skill.name}</span>       {/* "React" */}
      <span>{skill.level}%</span>     {/* "75%" */}
    </div>
    <div className="skill-bar">       {/* grey patti (poori) */}
      <div className="skill-fill" style={{ width: `${skill.level}%` }} />  {/* rangeen patti */}
    </div>
  </div>
))}
```

**Nayi cheez: Inline Style**
```jsx
style={{ width: `${skill.level}%` }}
```
- Bahar wale `{ }` = "JavaScript shuru"
- Andar wale `{ }` = JavaScript **object**
- `` `${skill.level}%` `` = template string: 75 → "75%"

**Kyun inline style?** Har skill ki chaurai **alag** hai (90%, 80%, 75%) aur **data se** aati hai. CSS file mein yeh nahi likh sakte.

```
skill-bar  [████████████████████░░░░░]  ← grey (100%)
skill-fill [████████████████████]       ← rangeen (75%)
```

### Hissa 4: Timeline
```jsx
<ol className="timeline">
  {timeline.map((item) => (
    <li key={item.year} className="timeline-item">
      <span className="timeline-year">{item.year}</span>
      <h3>{item.title}</h3>
      <p>{item.text}</p>
    </li>
  ))}
</ol>
```
`<ol>` = **ordered list** (tarteeb wali list), kyunke timeline mein tarteeb maayne rakhti hai.

### `About.css`: khaas cheezein

```css
.about-photo {
  aspect-ratio: 1;   /* hamesha chaukor (square): chaurai = oonchai */
}
```

```css
.skill-bar {
  overflow: hidden;  /* andar wali patti bahar na nikle */
  border-radius: 999px;  /* golai wale kinare */
}
```

```css
/* Timeline ki lakeer */
.timeline { border-left: 2px solid var(--border); }

/* Har item par gol nuqta (dot) */
.timeline-item::before {
  content: '';
  position: absolute;
  width: 14px; height: 14px;
  border-radius: 50%;          /* 50% = poora gol */
  background: var(--primary);
}
```
`::before` se **dot** bana. HTML mein koi extra element nahi likhna para!

---

## 18. `pages/Contact.jsx` + `Contact.css`

### Yeh kya hai?
Raabte ka page (URL: `/contact`). **Sab se zyada seekhne wali file** hai kyunke is mein **form + validation** hai.

```
┌──────────────────────────────────────────┐
│          PageHeader "Contact Me"          │
├──────────────┬───────────────────────────┤
│ Let's talk   │  ┌─────────────────────┐  │
│ 📧 Email     │  │ Name     │ Email    │  │
│ 📞 Phone     │  │ Subject             │  │
│ 📍 Location  │  │ Message             │  │
│              │  │ [ Send Message ]    │  │
│              │  └─────────────────────┘  │
└──────────────┴───────────────────────────┘
```

### Hissa 1: Khaali form ka saancha
```jsx
const emptyForm = { name: '', email: '', subject: '', message: '' };
```
**Kyun bahar?** Do jagah use hota hai: (1) shuru mein, (2) bhejne ke baad form **khaali** karne ke liye.

### Hissa 2: Validate function (form check karna) ⭐
```jsx
function validate(form) {
  const errors = {};                                        // khaali object = koi galti nahi

  if (!form.name.trim()) errors.name = 'Please enter your name.';

  if (!form.email.trim()) {
    errors.email = 'Please enter your email.';
  } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
    errors.email = 'Please enter a valid email address.';
  }

  if (form.message.trim().length < 10) {
    errors.message = 'Message should be at least 10 characters.';
  }

  return errors;
}
```

| Code | Matlab |
|---|---|
| `.trim()` | Shuru aur aakhir ki **spaces hata do**. `"   "` ko khaali samjho |
| `!form.name.trim()` | `!` = "nahi". Agar naam **khaali** hai to... |
| `/^\S+@\S+\.\S+$/` | **Regex**: check karta hai email `kuch@kuch.kuch` jaisa hai |
| `.test(form.email)` | Kya email is pattern se milta hai? `true`/`false` |
| `.length < 10` | Kya message 10 huroof se kam hai? |
| `return errors` | Jaise `{ email: 'Please enter...' }`. Khaali `{}` = sab theek ✅ |

**Kyun alag function?** Component ke bahar rakha kyunke yeh **sirf check** karta hai, React ki koi cheez use nahi karta. Saaf aur samajhne mein aasaan.

### Hissa 3: Teen States
```jsx
const [form, setForm] = useState(emptyForm);  // form mein kya likha hai
const [errors, setErrors] = useState({});     // kaunsi galtiyan hain
const [sent, setSent] = useState(false);      // message bhej diya? (success dikhana hai?)
```

### Hissa 4: handleChange (jab user type kare)
```jsx
function handleChange(e) {
  const { name, value } = e.target;          // kaunsa input + kya likha
  setForm({ ...form, [name]: value });       // sirf us field ko update karo
  if (errors[name]) setErrors({ ...errors, [name]: '' });  // type karte hi error hatao
}
```

| Code | Matlab |
|---|---|
| `e.target` | Woh input jis mein user ne type kiya |
| `{ name, value }` | Input ka `name` ("email") aur likha hua text |
| `...form` | **Spread**: purane form ki saari values copy karo |
| `[name]: value` | Phir sirf **is field** ko naya text do |

**Misaal:** User ne email mein "a" likha:
```js
// Pehle:  { name: 'Ali', email: '',  subject: '', message: '' }
// Baad:   { name: 'Ali', email: 'a', subject: '', message: '' }
//                         ↑ sirf yeh badla
```
**Faida:** 4 inputs ke liye **ek hi** function kaafi hai!

### Hissa 5: handleSubmit (jab "Send" dabayein)
```jsx
function handleSubmit(e) {
  e.preventDefault();                              // 1. page reload mat karo
  const newErrors = validate(form);                // 2. form check karo
  setErrors(newErrors);                            // 3. galtiyan save karo (screen par dikhengi)
  if (Object.keys(newErrors).length > 0) return;   // 4. galti hai? → yahin ruk jao ✋

  console.log('Message sent:', form);              // 5. (yahan asal mein email bhejna hai)
  setSent(true);                                   // 6. success message dikhao ✅
  setForm(emptyForm);                              // 7. form khaali karo
}
```
- `e.preventDefault()`: HTML form ki **aadat** hai page reload karna. Yeh usay rokta hai.
- `Object.keys(newErrors).length`: errors object mein **kitni cheezein** hain? 0 = koi galti nahi.

### Hissa 6: Contact info ki list
```jsx
const contactInfo = [
  { icon: '📧', label: 'Email', value: site.email, href: `mailto:${site.email}` },
  { icon: '📞', label: 'Phone', value: site.phone, href: `tel:${site.phone}` },
  { icon: '📍', label: 'Location', value: site.location },  // ← href NAHI hai
];
```
- `mailto:` = click karo to **email app** khule
- `tel:` = mobile par click karo to **call** lag jaaye
- Location ka koi link nahi, is liye `href` nahi diya

```jsx
{item.href ? <a href={item.href}>{item.value}</a> : <p>{item.value}</p>}
```
**Conditional rendering:** `href` hai? → link banao. Nahi hai? → sirf text.

### Hissa 7: Form YA Success message
```jsx
{sent ? (
  <div className="success">... Message sent! ...</div>    // sent = true
) : (
  <form onSubmit={handleSubmit} noValidate>...</form>      // sent = false
)}
```
`? :` = **"agar... to... warna..."**. Ek waqt mein **sirf ek** cheez dikhegi.

`noValidate` = browser ki **apni** validation band karo, kyunke hum **apni** khoobsurat validation use kar rahe hain.

### Hissa 8: Field component (ek file mein doosra component)
```jsx
function Field({ label, name, type = 'text', textarea = false, value, onChange, error }) {
  const props = {
    id: name,
    name,
    value,
    onChange,
    className: error ? 'input input-error' : 'input',  // galti ho to laal border
  };

  return (
    <div className="field">
      <label htmlFor={name}>{label}</label>
      {textarea ? <textarea rows="6" {...props} /> : <input type={type} {...props} />}
      {error && <span className="error-text">{error}</span>}
    </div>
  );
}
```

| Cheez | Matlab |
|---|---|
| `type = 'text'` | **Default value**: agar `type` na bheja to `'text'` maan lo |
| `textarea = false` | Default: normal input. `textarea` bhejo to bara box |
| `{...props}` | Object ki **saari** values ek saath attributes bana do |
| `htmlFor={name}` | Label par click karo to input select ho jaaye (HTML ka `for`) |
| `error && <span>` | Error **hai** to hi laal text dikhao |

**Kyun banaya?** Label + input + error ka code **4 baar** likhna parta. Ab sirf ek line:
```jsx
<Field label="Your name" name="name" value={form.name} onChange={handleChange} error={errors.name} />
```

**Kyun isi file mein?** Yeh **sirf** Contact page use karta hai. Agar doosre pages par bhi chahiye ho to `components/` mein le jaayein.

### `Contact.css`: khaas cheezein

```css
.input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 4px var(--primary-light);  /* click karne par halka jamni ghera */
}
.input-error { border-color: var(--danger); }  /* galti par laal border */
.error-text  { color: var(--danger); }         /* laal text */
```

```css
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;   /* Name aur Email ek line mein */
}
@media (max-width: 860px) {
  .contact-grid { grid-template-columns: 1fr; }  /* mobile: info upar, form neeche */
  .form-row { grid-template-columns: 1fr; }      /* Name, Email ek ke neeche ek */
}
```

### ⚠️ Zaroori note
Yeh form abhi **asal email nahi bhejta**, sirf browser ke **Console** (F12) mein dikhata hai.
Asal email ke liye **Formspree** (free) use karein. `console.log(...)` ki jagah yeh likhein:

```jsx
fetch('https://formspree.io/f/AAPKA_FORM_ID', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify(form),
});
```
(Formspree.io par account bana kar `AAPKA_FORM_ID` milega.)

---

## 19. `pages/NotFound.jsx`

### Yeh kya hai?
**404 page**: jab koi **galat URL** likhe (jaise `/abcd`).

### Code samjhein
```jsx
function NotFound() {
  return (
    <section className="section container not-found">
      <h1 className="gradient-text">404</h1>
      <h2>Page not found</h2>
      <p>Sorry, the page you are looking for does not exist.</p>
      <Link to="/" className="btn">Go Back Home</Link>
    </section>
  );
}
```

### Kyun zaroori?
Is ke baghair galat URL par **khaali safaid** page dikhta, aur user pareshan ho jaata. 😕
Ab use **saaf message** aur **"Home" ka button** milta hai. ✅

### Yeh kab dikhta hai?
`App.jsx` mein: `<Route path="*" element={<NotFound />} />`
`*` = "koi bhi URL jo upar kisi route se match na hua."

### CSS kahan hai?
Yeh page chhota hai, is liye is ki CSS (`.not-found`) `index.css` mein hi rakh di. **Alag file ki zaroorat nahi thi.**

---

# Hissa 6: Aakhri baatein

## 20. Kaunsi file kab edit karni hai?

| Aap kya karna chahte hain | Kaunsi file kholein |
|---|---|
| ✏️ Apna naam, email, phone badalna | `src/data/siteData.js` |
| ✏️ Skills, services, experience badalna | `src/data/siteData.js` |
| 🎨 Website ka rang badalna | `src/index.css` → `--primary`, `--secondary` |
| 🔤 Font badalna | `index.html` (Google Font link) + `index.css` (`font-family`) |
| 📄 Browser tab ka naam badalna | `index.html` → `<title>` |
| ➕ Naya page banana | `pages/` mein nayi file + `App.jsx` + `Navbar.jsx` |
| 🔗 Menu mein link add karna | `components/Navbar.jsx` → `links` list |
| 🦶 Footer badalna | `components/Footer.jsx` |
| 🏠 Home page ka design | `pages/Home.jsx` + `pages/Home.css` |
| 🖼️ Apni photo lagana | Photo `public/` mein rakhein → `<img src="/me.jpg" />` |
| 📧 Form se asal email | `pages/Contact.jsx` → `handleSubmit` |
| 🚫 Kabhi touch na karein (abhi) | `main.jsx`, `vite.config.js`, `node_modules/` |

---

## 21. Mushkil alfaaz ki dictionary

| Lafz | Aasaan matlab |
|---|---|
| **Component** | Website ka ek tukra (function jo design return kare) |
| **JSX** | JavaScript ke andar HTML jaisa code |
| **Props** | Component ko bahar se di gayi maloomat |
| **State** (`useState`) | Component ki **yaaddasht**. Badle to screen update ho |
| **Hook** | React ke khaas functions jo `use` se shuru hon |
| **useEffect** | "Jab yeh badle, tab yeh kaam karo" |
| **Route** | URL aur page ka joda (`/about` → About) |
| **Routing** | URL ke hisaab se page dikhana |
| **SPA** | Single Page Application: ek HTML, bina reload ke pages badalte hain |
| **Link / NavLink** | Bina reload ke page badalne wala link |
| **Outlet** | Layout mein khaali jagah jahan page aata hai |
| **Layout** | Har page ka common frame (Navbar + Footer) |
| **Render** | React ka screen par cheez dikhana |
| **`.map()`** | List ke har item ke liye kuch banana |
| **`key`** | List ke har item ki unique pehchaan |
| **Spread `...`** | Object ya list ki saari values copy karna |
| **Destructuring** | `const { a, b } = obj`: object se values nikalna |
| **Conditional rendering** | Shart ke hisaab se cheez dikhana (`? :` ya `&&`) |
| **Validation** | Form ki maloomat sahi hai ya nahi, check karna |
| **Regex** | Text ka pattern check karne ka tareeqa |
| **CSS Variable** | `--primary` jaisi value jo baar baar use ho |
| **Media query** | Screen size ke hisaab se alag CSS |
| **Flexbox** | Cheezon ko ek line (row/column) mein lagana |
| **Grid** | Cheezon ko table jaisi rows aur columns mein lagana |
| **Responsive** | Website har screen (mobile, laptop) par sahi dikhe |
| **Deploy** | Website ko internet par live karna |
| **npm** | Libraries download karne wala tool |
| **Vite** | Website chalane aur banane wala fast tool |

---

## 🎯 Aakhri Mashwara

1. **Pehle** `siteData.js` mein apni maloomat daalein, aur foran farq dekhein.
2. **Phir** rang badlein (`index.css`).
3. **Phir** ek **naya page** banane ki koshish karein (Services ya Projects).
4. **Code copy mat karein, khud type karein**. Is tarah haath aur dimagh dono seekhte hain.
5. **Galti se mat darein!** Error aaye to browser mein **F12 → Console** khol kar parhein. Har error kuch sikhata hai.

**Allah aap ko kamyabi de! Happy Coding! 🚀💜**
