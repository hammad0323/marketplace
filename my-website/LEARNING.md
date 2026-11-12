# Multi-Page React Website: What to Learn

This project is a website with **3 real pages**: **Home**, **About** and **Contact** (plus a 404 page).
This document explains the **main things you should learn** from it, in order of importance, with examples from the code.

> Read your first project's `GUIDE.md` before this one. It explains the basics (components, JSX, props, state).
> This document focuses on the **new** things.

---

## 🎯 The 7 Main Things to Learn From This Project

| # | Topic | Where in the code | Importance |
|---|---|---|---|
| 1 | **React Router** (multiple pages) | `main.jsx`, `App.jsx` | ⭐⭐⭐⭐⭐ Most important |
| 2 | **`Link` and `NavLink`** (moving between pages) | `Navbar.jsx`, `Footer.jsx` | ⭐⭐⭐⭐⭐ |
| 3 | **Layout + `Outlet`** (shared navbar/footer) | `Layout.jsx` | ⭐⭐⭐⭐ |
| 4 | **Project structure** (pages vs components vs data) | `src/` folder | ⭐⭐⭐⭐ |
| 5 | **Forms with validation** | `Contact.jsx` | ⭐⭐⭐⭐ |
| 6 | **Reusable components** | `PageHeader.jsx`, `Field` | ⭐⭐⭐ |
| 7 | **Modern CSS design** (variables, Grid, Flexbox, responsive) | `*.css` files | ⭐⭐⭐ |

Learn them in this order. **Number 1 is the big new skill.**

---

## How to Run

```bash
cd my-website
```
```bash
npm install
```
```bash
npm run dev
```

Open http://localhost:5173 and click **Home**, **About** and **Contact**. Watch the URL change in the address bar. Try http://localhost:5173/abc to see the 404 page.

---

## Folder Structure

```
my-website/
├── index.html                 ← Loads the Google Font + the app
├── package.json               ← Now includes "react-router-dom"
├── vercel.json                ← Deploy fix for Vercel (see Deploying)
├── public/_redirects          ← Deploy fix for Netlify
└── src/
    ├── main.jsx               ← Wraps the app in <BrowserRouter>
    ├── App.jsx                ← ALL the routes (which URL → which page)
    ├── index.css              ← Global styles (colors, buttons, cards)
    │
    ├── data/
    │   └── siteData.js        ← ✏️ All your text: name, services, skills...
    │
    ├── components/            ← Pieces used on MANY pages
    │   ├── Layout.jsx         ← Navbar + <Outlet/> + Footer
    │   ├── Navbar.jsx / .css
    │   ├── Footer.jsx / .css
    │   ├── PageHeader.jsx / .css
    │   └── ScrollToTop.jsx
    │
    └── pages/                 ← One file per page (one per URL)
        ├── Home.jsx / .css    ← URL: /
        ├── About.jsx / .css   ← URL: /about
        ├── Contact.jsx / .css ← URL: /contact
        └── NotFound.jsx       ← any wrong URL
```

**Simple rule to remember:**
- **`pages/`**: a full screen with its own URL.
- **`components/`**: a smaller piece that is reused.
- **`data/`**: plain content (text and lists), no UI.

---

## 1. React Router: Multiple Pages ⭐⭐⭐⭐⭐

### What problem does it solve?

A React app has only **one** HTML file (`index.html`). So how do you get `/about` and `/contact`?
**React Router** looks at the URL and shows a different component for each one, **without reloading the page**.
This kind of app is called a **SPA (Single Page Application)**. It feels very fast.

### Install it (already done in this project)

```bash
npm install react-router-dom
```

### Step A: Turn on routing in `main.jsx`

```jsx
import { BrowserRouter } from 'react-router-dom';

<BrowserRouter>
  <App />
</BrowserRouter>
```

`BrowserRouter` watches the address bar. Everything inside it can use routing.

### Step B: Define your pages in `App.jsx`

```jsx
import { Routes, Route } from 'react-router-dom';

<Routes>
  <Route path="/" element={<Layout />}>
    <Route index element={<Home />} />             {/* /        */}
    <Route path="about" element={<About />} />     {/* /about   */}
    <Route path="contact" element={<Contact />} /> {/* /contact */}
    <Route path="*" element={<NotFound />} />      {/* anything else */}
  </Route>
</Routes>
```

| Piece | Meaning |
|---|---|
| `<Routes>` | Container. It picks **one** matching route. |
| `<Route path="about" element={<About />} />` | "When the URL is `/about`, show `<About />`." |
| `index` | The default page for the parent path (`/`). |
| `path="*"` | Matches **any** URL that didn't match. Used for the 404 page. |

### ✍️ Practice: add a "Services" page

1. Create `src/pages/Services.jsx`:
   ```jsx
   function Services() {
     return <section className="section container"><h1>My Services</h1></section>;
   }
   export default Services;
   ```
2. In `App.jsx`, import it and add the route:
   ```jsx
   import Services from './pages/Services.jsx';
   <Route path="services" element={<Services />} />
   ```
3. In `Navbar.jsx`, add `{ to: '/services', label: 'Services' }` to the `links` array.

That's how **every** new page is added: **create the page, add the route, add the link.**

---

## 2. `Link` and `NavLink`: Moving Between Pages ⭐⭐⭐⭐⭐

### ❌ Don't use `<a href>` for your own pages

```jsx
<a href="/about">About</a>   // ❌ reloads the WHOLE website (slow, loses state)
```

### ✅ Use `<Link to>`

```jsx
import { Link } from 'react-router-dom';

<Link to="/about">About</Link>   // ✅ instant, no reload
```

### When to use which

| Going to... | Use |
|---|---|
| A page **inside your site** (`/about`) | `<Link to="/about">` |
| An **outside website** (GitHub, LinkedIn) | `<a href="https://..." target="_blank" rel="noreferrer">` |
| An email or phone number | `<a href="mailto:...">` / `<a href="tel:...">` |

You can see both in `Footer.jsx`: page links use `<Link>` and social links use `<a>`.

### `NavLink` highlights the current page

`NavLink` is a `Link` that **knows if its page is open**. Used in `Navbar.jsx`:

```jsx
<NavLink
  to="/about"
  className={({ isActive }) => (isActive ? 'nav-link active' : 'nav-link')}
>
  About
</NavLink>
```

When you are on `/about`, `isActive` is `true`, so it gets the `active` class (purple text with an underline).

**Why `end` on the Home link?** Without it, `/` would count as "active" on **every** page, because every URL starts with `/`.
`end` means "active only when the URL is exactly `/`".

### Bonus: change page with code (`useNavigate`)

Sometimes you want to change page **after something happens**, for example after a form is submitted:

```jsx
import { useNavigate } from 'react-router-dom';

function MyForm() {
  const navigate = useNavigate();

  function handleSubmit(e) {
    e.preventDefault();
    navigate('/thank-you');   // go to another page
  }
  ...
}
```

---

## 3. Layout + `Outlet`: Shared Navbar and Footer ⭐⭐⭐⭐

Every page needs the same Navbar and Footer. Instead of copying them into each page, we make **one Layout**:

```jsx
// components/Layout.jsx
import { Outlet } from 'react-router-dom';

function Layout() {
  return (
    <>
      <Navbar />
      <main className="page">
        <Outlet />     {/* ← the current page appears HERE */}
      </main>
      <Footer />
    </>
  );
}
```

In `App.jsx`, the pages are **nested** inside the Layout route:

```jsx
<Route path="/" element={<Layout />}>
  <Route index element={<Home />} />
  <Route path="about" element={<About />} />
</Route>
```

**Picture it like this:**

```
┌──────────── Layout ────────────┐
│  Navbar                        │
│  ┌──────── <Outlet /> ───────┐ │
│  │  Home OR About OR Contact │ │  ← only this part changes
│  └───────────────────────────┘ │
│  Footer                        │
└────────────────────────────────┘
```

### `ScrollToTop`: a small but important fix

By default, if you scroll down on Home and click "About", the About page opens **halfway down**.
`ScrollToTop.jsx` fixes this with `useLocation` + `useEffect`:

```jsx
const { pathname } = useLocation();   // current URL, e.g. "/about"

useEffect(() => {
  window.scrollTo(0, 0);              // jump to top
}, [pathname]);                       // every time the URL changes
```

This is also a good example of a component that **renders nothing** (`return null`) and only does a job.

---

## 4. Project Structure and Separating Data ⭐⭐⭐⭐

All the text is in **`src/data/siteData.js`**, not inside the components:

```js
export const services = [
  { icon: '🎨', title: 'Web Design', text: 'Clean and modern layouts...' },
  { icon: '⚛️', title: 'React Development', text: 'Fast single-page apps...' },
];
```

And the page just **loops over it**:

```jsx
{services.map((service) => (
  <article key={service.title} className="card">
    <div className="card-icon">{service.icon}</div>
    <h3>{service.title}</h3>
    <p>{service.text}</p>
  </article>
))}
```

**Why this is good:**
- To add a service, you add **one object**, with no JSX to copy.
- Later, this data could come from a **database or API** instead of a file, and the page code stays the same.

**Each page has its own CSS file** (`Home.css`, `About.css`...), imported at the top of the page:

```jsx
import './Home.css';
```

Shared styles (buttons, cards, colors) stay in `index.css`. This keeps files small and easy to find.

> ⚠️ Note: in Vite/React, all imported CSS is **global**, so `.hero` in `Home.css` would also affect any `.hero` on another page.
> Use clear, unique class names. Later you can learn **CSS Modules** (`Home.module.css`) to keep styles completely separate.

---

## 5. Forms With Validation ⭐⭐⭐⭐

The Contact page **checks the form before sending** and shows red error messages.
This is one of the most common real-world tasks.

### Step 1: Three pieces of state

```jsx
const [form, setForm] = useState({ name: '', email: '', subject: '', message: '' });
const [errors, setErrors] = useState({});   // e.g. { email: 'Please enter...' }
const [sent, setSent] = useState(false);    // show the success box?
```

### Step 2: A validate function

```jsx
function validate(form) {
  const errors = {};
  if (!form.name.trim()) errors.name = 'Please enter your name.';
  if (!/^\S+@\S+\.\S+$/.test(form.email)) errors.email = 'Please enter a valid email address.';
  if (form.message.trim().length < 10) errors.message = 'Message should be at least 10 characters.';
  return errors;   // {} = no errors
}
```

- `.trim()` removes spaces, so `"   "` counts as empty.
- `/^\S+@\S+\.\S+$/` is a simple **regular expression** that checks the text looks like `something@something.something`.

### Step 3: Check on submit

```jsx
function handleSubmit(e) {
  e.preventDefault();                       // don't reload the page
  const newErrors = validate(form);
  setErrors(newErrors);
  if (Object.keys(newErrors).length > 0) return;   // has errors → stop

  console.log('Message sent:', form);       // send it here
  setSent(true);
  setForm(emptyForm);                       // clear the form
}
```

### Step 4: Show errors under each field

```jsx
{error && <span className="error-text">{error}</span>}
```

`&&` means "only show this if `error` has a value".

### Making the form really send emails

The form only logs to the console right now. The easiest real option is **Formspree** (free):

1. Sign up at https://formspree.io and create a form. You'll get a URL like `https://formspree.io/f/abcdwxyz`.
2. In `handleSubmit`, replace `console.log(...)` with:
   ```jsx
   fetch('https://formspree.io/f/abcdwxyz', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
     body: JSON.stringify(form),
   });
   ```

---

## 6. Reusable Components ⭐⭐⭐

**Rule:** if you write the same JSX **2–3 times**, make it a component.

### Example 1: `PageHeader` (used on About and Contact)

```jsx
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

```jsx
<PageHeader title="About Me" subtitle="A little about who I am..." />
<PageHeader title="Contact Me" subtitle="Have a question or a project idea?..." />
```

### Example 2: `Field` (used 4 times in the contact form)

```jsx
function Field({ label, name, type = 'text', textarea = false, value, onChange, error }) { ... }

<Field label="Your name" name="name" value={form.name} onChange={handleChange} error={errors.name} />
```

New things in this example:
- **Default prop values:** `type = 'text'` is used when no `type` is passed.
- **Spreading props:** `<input {...props} />` passes every key in the object as an attribute.

---

## 7. Modern CSS Design ⭐⭐⭐

These CSS techniques are what make the site look professional.

### Design tokens (CSS variables)

```css
:root {
  --primary: #6d28d9;
  --gradient: linear-gradient(135deg, var(--primary), var(--secondary));
  --shadow: 0 10px 30px rgba(17, 24, 39, 0.08);
  --radius: 16px;
}
.btn { background: var(--gradient); }
```

👉 **Change `--primary` and `--secondary` to re-color the whole website.**

### The layout tools

| Technique | Used for | Example |
|---|---|---|
| `.container` | Same max-width and side padding on every section | `max-width: 1140px; margin: 0 auto;` |
| **Flexbox** | One row or column (navbar, buttons) | `display: flex; gap: 1rem;` |
| **Grid** | Two-column layouts and card grids | `grid-template-columns: 1.2fr 1fr;` |
| **Auto-fit grid** | Cards that wrap by themselves | `repeat(auto-fit, minmax(260px, 1fr))` |
| **`clamp()`** | Font size that grows with the screen | `font-size: clamp(2.2rem, 5vw, 3.6rem);` |
| **Media queries** | Change the layout on phones | `@media (max-width: 768px) { ... }` |
| **`position: sticky`** | Navbar stays at the top while scrolling | `.navbar { position: sticky; top: 0; }` |

### Small details that make a big difference

- **Hover effects:** `.card:hover { transform: translateY(-6px); }` makes cards lift up.
- **Transitions:** `transition: transform 0.2s;` makes changes smooth.
- **Gradient text:** see `.gradient-text` in `index.css`.
- **Page fade-in:** the `@keyframes fadeIn` animation on `.page`.
- **Hamburger → X:** pure CSS with `::before` and `::after` (see `Navbar.css`).
- **Focus ring on inputs:** `box-shadow: 0 0 0 4px var(--primary-light);`. It looks nice and helps keyboard users.

---

## 🚀 Deploying a Site With React Router (Important!)

Build it:

```bash
npm run build
```

⚠️ **Common problem:** after deploying, visiting `/about` directly or pressing **refresh** gives a **404 error**.
The reason is that the server looks for an `about.html` file that doesn't exist. Only `index.html` exists.

✅ **This project already includes both fixes**, so it works out of the box. Here is what they are:

**Fix for Netlify:** the file `public/_redirects` has this one line:

```
/*    /index.html   200
```

**Fix for Vercel:** the file `vercel.json` in the project folder:

```json
{ "rewrites": [{ "source": "/(.*)", "destination": "/index.html" }] }
```

This tells the server: "for any URL, send `index.html` and let React Router decide which page to show."

---

## 🐞 Common Errors in This Project

| Error | Cause | Fix |
|---|---|---|
| `useNavigate() may be used only in the context of a <Router>` | App isn't inside `<BrowserRouter>` | Check `main.jsx` |
| Page reloads fully when clicking a link | Used `<a href>` for an internal page | Use `<Link to>` |
| Home link is always highlighted | Missing `end` on the `/` NavLink | Add `end` |
| Navbar/Footer missing on a page | Route isn't nested inside the Layout route | Put the `<Route>` inside `<Route element={<Layout />}>` |
| Page is blank, no error | Forgot `<Outlet />` in Layout | Add `<Outlet />` |
| 404 after refreshing on a live site | Server doesn't know about SPA routes | Add `_redirects` / `vercel.json` (see above) |
| `Failed to resolve import "react-router-dom"` | Package not installed | Run `npm install` |

---

## ✍️ Practice Tasks (Do These!)

1. **Easy:** Put your own name, services and skills in `siteData.js`.
2. **Easy:** Change the colors with `--primary` and `--secondary` in `index.css`.
3. **Easy:** Replace the letter avatar with your photo: put `me.jpg` in a `public/` folder and use `<img src="/me.jpg" alt="Me" />`.
4. **Medium:** Add a **Services** page (follow the steps in section 1).
5. **Medium:** Add a **Projects** page that shows project cards from a `projects` array in `siteData.js`.
6. **Medium:** After the form is sent, go to a `/thank-you` page using `useNavigate`.
7. **Hard:** Make a **project details** page with a URL parameter: `/projects/:id`.
   Read the id with `const { id } = useParams();`, then find the matching project in your data.
8. **Hard:** Add a **dark mode** toggle (hint: `useState` + a class on `<body>` + a second set of CSS variables).
9. **Hard:** Connect the form to **Formspree** and receive a real email.

---

## 🗺️ Your Learning Roadmap

```
✅ 1. HTML, CSS, JavaScript basics
✅ 2. React basics: components, JSX, props, state, events, lists   ← first project
✅ 3. React Router, layouts, forms, project structure               ← THIS project
⬜ 4. Fetching data from APIs (fetch, async/await, loading & error states)
⬜ 5. More hooks: useRef, useContext (sharing data without props), custom hooks
⬜ 6. A CSS framework: Tailwind CSS
⬜ 7. Git & GitHub, and deploying to Netlify/Vercel
⬜ 8. Next.js (the React framework most jobs use)
⬜ 9. TypeScript (JavaScript with types)
```

**Tip:** don't just read. **Type the code yourself, break it, and fix it.** That is how you really learn. 💪
