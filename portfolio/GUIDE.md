# React Portfolio — Complete Beginner Guide

This guide explains **every part** of this portfolio website, step by step.
No prior React experience needed. You only need basic HTML, CSS and a little JavaScript.

---

## Table of Contents

1. [What is React? (Brief)](#1-what-is-react-brief)
2. [Tools Used in This Project](#2-tools-used-in-this-project)
3. [How to Run the Project](#3-how-to-run-the-project)
4. [Folder Structure Explained](#4-folder-structure-explained)
5. [How the App Starts](#5-how-the-app-starts)
6. [Core React Concepts (with examples from this project)](#6-core-react-concepts-with-examples-from-this-project)
   - 6.1 Components
   - 6.2 JSX
   - 6.3 Props
   - 6.4 State (`useState`)
   - 6.5 Events
   - 6.6 Lists and `.map()` with `key`
   - 6.7 Conditional Rendering
   - 6.8 Forms (Controlled Inputs)
   - 6.9 Side Effects (`useEffect`)
   - 6.10 Import / Export
7. [Every Component, Explained](#7-every-component-explained)
8. [Styling (CSS) Explained](#8-styling-css-explained)
9. [How to Make It Yours](#9-how-to-make-it-yours)
10. [Build and Deploy (Put It Online)](#10-build-and-deploy-put-it-online)
11. [Common Beginner Errors](#11-common-beginner-errors)
12. [Practice Exercises](#12-practice-exercises)
13. [Glossary](#13-glossary)

---

## 1. What is React? (Brief)

**React** is a JavaScript library for building user interfaces (the parts of a website you see and click).
Facebook made it, and many companies use it, including Netflix, Instagram and Airbnb.

**The big idea:** you split your page into small, reusable pieces called **components**.

```
Website
├── Navbar
├── Hero
├── About
├── Skills
├── Projects
│   ├── ProjectCard
│   ├── ProjectCard
│   └── ProjectCard
├── Contact
└── Footer
```

Each piece is a JavaScript function that returns what should appear on screen.
When your data changes, React updates the page for you. You don't have to change the HTML by hand.

**Plain HTML/JS vs React:**

| Plain JavaScript | React |
|---|---|
| `document.getElementById('x').innerText = 'Hi'` | `<p>{message}</p>`. React updates it for you |
| Copy-paste the same HTML many times | Write a component once and reuse it |
| Hard to manage in big projects | Organized into small files |

---

## 2. Tools Used in This Project

| Tool | What it is | Why we use it |
|---|---|---|
| **Node.js** | Runs JavaScript outside the browser | Needed to install packages and run Vite |
| **npm** | Node Package Manager (comes with Node.js) | Downloads libraries like React |
| **React** (`react`) | The UI library | Builds components |
| **ReactDOM** (`react-dom`) | Connects React to the browser | Puts React components on the actual web page |
| **Vite** (`vite`) | A fast development server and build tool | Runs the site locally with instant reload and makes the final files |
| **@vitejs/plugin-react** | A Vite plugin | Lets Vite understand JSX and React |
| **Plain CSS** | Styling | Simple, with no extra library to learn |

> **No extra libraries** (no Tailwind, no Router, no Redux), so you can focus on React itself.

---

## 3. How to Run the Project

### Step 1: Install Node.js
Download the **LTS** version from https://nodejs.org and install it.
Then check it works by opening a terminal (Command Prompt / PowerShell / Terminal):

```bash
node -v
```

```bash
npm -v
```

Both should print a version number.

### Step 2: Open the project folder

```bash
cd portfolio
```

### Step 3: Install dependencies (only the first time)

```bash
npm install
```

This reads `package.json` and downloads React and Vite into a `node_modules` folder.

### Step 4: Start the development server

```bash
npm run dev
```

You will see something like:

```
  ➜  Local:   http://localhost:5173/
```

Open that link in your browser. 🎉
When you edit any file and save it, the browser updates **automatically**.

### Available commands

| Command | What it does |
|---|---|
| `npm run dev` | Starts the local development server |
| `npm run build` | Creates the final optimized website in the `dist/` folder |
| `npm run preview` | Previews the built `dist/` version locally |

> 💡 **Starting a brand-new project yourself later?** Run `npm create vite@latest my-app -- --template react`.
> That creates the same kind of setup as this project.

---

## 4. Folder Structure Explained

```
portfolio/
├── index.html            ← The only HTML page. React fills <div id="root">
├── package.json          ← Project info + list of dependencies + scripts
├── vite.config.js        ← Vite settings (enables React plugin)
├── .gitignore            ← Files Git should ignore (node_modules, dist)
├── GUIDE.md              ← This guide
└── src/                  ← ALL your code lives here
    ├── main.jsx          ← Entry point: starts React
    ├── App.jsx           ← Main component: puts all sections together
    ├── index.css         ← All styles
    ├── data.js           ← YOUR content (name, skills, projects)
    └── components/       ← Reusable pieces of the page
        ├── Navbar.jsx
        ├── Hero.jsx
        ├── About.jsx
        ├── Skills.jsx
        ├── Projects.jsx
        ├── ProjectCard.jsx
        ├── Contact.jsx
        └── Footer.jsx
```

**Why `.jsx`?** Files ending in `.jsx` contain JSX (HTML-like code inside JavaScript). See section 6.2.

---

## 5. How the App Starts

Here's the order things happen in when you open the website:

```
index.html  →  src/main.jsx  →  src/App.jsx  →  components (Navbar, Hero, ...)
```

**1. `index.html`** has an empty div:

```html
<div id="root"></div>
<script type="module" src="/src/main.jsx"></script>
```

**2. `main.jsx`** finds that div and renders `<App />` into it:

```jsx
ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
```

- `createRoot(...)` tells React: "this div is yours, control everything inside it."
- `<React.StrictMode>` is a helper that warns you about mistakes during development. It does not appear on the page.

**3. `App.jsx`** returns all the sections in order:

```jsx
return (
  <>
    <Navbar ... />
    <main>
      <Hero />
      <About />
      <Skills />
      <Projects />
      <Contact />
    </main>
    <Footer />
  </>
);
```

`<> ... </>` is called a **Fragment**. It groups elements without adding an extra `<div>` to the page.

---

## 6. Core React Concepts (with examples from this project)

### 6.1 Components

A **component** is a JavaScript function that returns UI.
Its name **must start with a Capital Letter**.

```jsx
// src/components/About.jsx
function About() {
  return (
    <section id="about" className="section">
      <h2 className="section-title">About Me</h2>
      <p className="about-text">{profile.about}</p>
    </section>
  );
}

export default About;
```

Use it like an HTML tag: `<About />`.

✅ `function About()` &nbsp;&nbsp; ❌ `function about()` (lowercase = React thinks it's a normal HTML tag)

---

### 6.2 JSX

JSX lets you write HTML-like code inside JavaScript. It looks like HTML, with a few differences:

| HTML | JSX | Why |
|---|---|---|
| `class="btn"` | `className="btn"` | `class` is a reserved word in JS |
| `onclick="..."` | `onClick={...}` | camelCase event names |
| `<input>` | `<input />` | Every tag must be closed |
| `for="x"` | `htmlFor="x"` | `for` is reserved in JS |

**Curly braces `{ }` = "run JavaScript here":**

```jsx
<h1>{profile.name}</h1>                    // shows a variable
<p>© {year} {profile.name}</p>             // mixes text + variables
<a href={`mailto:${profile.email}`}>       // a JS template string as attribute
```

**Rule:** a component must return **one parent element**. Wrap multiple elements in a `<div>`, `<section>` or `<> </>`.

---

### 6.3 Props

**Props** (short for "properties") let a parent pass data **down** to a child, just like HTML attributes.

**Parent** (`Projects.jsx`) sends data:

```jsx
<ProjectCard
  title={project.title}
  description={project.description}
  tech={project.tech}
  link={project.link}
/>
```

**Child** (`ProjectCard.jsx`) receives it:

```jsx
function ProjectCard({ title, description, tech, link }) {
  return (
    <article className="card">
      <h3>{title}</h3>
      <p>{description}</p>
      ...
    </article>
  );
}
```

`{ title, description, tech, link }` is called **destructuring**. It's a shortcut for:

```jsx
function ProjectCard(props) {
  return <h3>{props.title}</h3>;
}
```

**You can pass functions as props too.** In `App.jsx`:

```jsx
<Navbar darkMode={darkMode} onToggleTheme={() => setDarkMode(!darkMode)} />
```

Then `Navbar` calls `onToggleTheme` when its button is clicked. This is how a **child talks back to a parent**.

> 📌 Props are **read-only**. A child should never change its props.

---

### 6.4 State (`useState`)

**State** is data that can **change** over time. When state changes, React **re-renders** (redraws) the component automatically.

```jsx
import { useState } from 'react';

const [menuOpen, setMenuOpen] = useState(false);
//     ↑ value    ↑ function to update it   ↑ starting value
```

**Example from `Navbar.jsx`** (the mobile menu open/close):

```jsx
const [menuOpen, setMenuOpen] = useState(false);

<button onClick={() => setMenuOpen(!menuOpen)}>
  {menuOpen ? '✕' : '☰'}
</button>
```

- Click → `setMenuOpen(true)` → React re-renders → icon becomes ✕ and menu shows.
- Click again → `setMenuOpen(false)` → icon becomes ☰ and menu hides.

**A tiny standalone example (a counter):**

```jsx
function Counter() {
  const [count, setCount] = useState(0);
  return (
    <button onClick={() => setCount(count + 1)}>
      Clicked {count} times
    </button>
  );
}
```

> ⚠️ Never change state directly, like `count = 5` ❌. Always use the setter: `setCount(5)` ✅

**Props vs State:**

| Props | State |
|---|---|
| Passed in from parent | Created inside the component |
| Read-only | Can change (with the setter) |
| Like function arguments | Like the component's memory |

---

### 6.5 Events

React handles clicks, typing, submitting and similar actions with `on...` attributes in camelCase.

```jsx
<button onClick={handleClick}>Click</button>     // pass the function
<button onClick={() => setSent(false)}>Reset</button>  // inline arrow function
<input onChange={handleChange} />                  // runs on every keystroke
<form onSubmit={handleSubmit}>                     // runs when form is submitted
```

⚠️ Common mistake:

```jsx
<button onClick={handleClick()}>   ❌ runs immediately when page loads
<button onClick={handleClick}>     ✅ runs only on click
```

---

### 6.6 Lists and `.map()` with `key`

To show many items from an array, use JavaScript's `.map()`.

**`data.js`:**
```js
export const skills = ['HTML', 'CSS', 'JavaScript', 'React', ...];
```

**`Skills.jsx`:**
```jsx
<ul className="skills-list">
  {skills.map((skill) => (
    <li key={skill} className="skill">{skill}</li>
  ))}
</ul>
```

This turns `['HTML', 'CSS']` into:

```html
<li>HTML</li>
<li>CSS</li>
```

**What is `key`?** A unique ID for each item so React can track which one changed.
Use something unique like an `id` (`key={project.id}`). Don't use random values.

---

### 6.7 Conditional Rendering

Show different things depending on a condition.

**Ternary `? :`** (this OR that), from `Contact.jsx`:

```jsx
{sent ? (
  <p className="success">Thanks for your message!</p>
) : (
  <form>...</form>
)}
```

**Inside text**, from `Navbar.jsx`:

```jsx
{darkMode ? '☀️ Light' : '🌙 Dark'}
```

**Conditional class name:**

```jsx
<nav className={menuOpen ? 'nav-links open' : 'nav-links'}>
```

**`&&`** (show only if true). Not used in this project, but common:

```jsx
{isLoggedIn && <p>Welcome back!</p>}
```

---

### 6.8 Forms (Controlled Inputs)

In React, input values are usually stored in **state**. This is called a **controlled input**.

From `Contact.jsx`:

```jsx
const [form, setForm] = useState({ name: '', email: '', message: '' });

function handleChange(e) {
  const { name, value } = e.target;     // which input + what was typed
  setForm({ ...form, [name]: value });  // copy old form, update one field
}

function handleSubmit(e) {
  e.preventDefault();                    // stop page reload
  console.log('Form data:', form);
  setSent(true);
}

<input name="name" value={form.name} onChange={handleChange} />
```

**How it flows:**
1. User types "A" → `onChange` fires.
2. `handleChange` updates the state → `form.name = "A"`.
3. React re-renders → input shows `value={form.name}` = "A".

**`...form`** is the **spread operator**. It copies all existing fields so we only change one.
**`[name]: value`** uses the input's `name` attribute ("name", "email" or "message") as the key.

> 📨 This form only logs to the console. To really receive emails, connect a free service like
> **Formspree** or **EmailJS** inside `handleSubmit`.

---

### 6.9 Side Effects (`useEffect`)

`useEffect` runs code **after** React renders. Use it for things outside React, like changing `<body>`,
saving to `localStorage`, timers, or fetching data from an API.

From `App.jsx` (dark mode):

```jsx
useEffect(() => {
  document.body.classList.toggle('dark', darkMode);
  localStorage.setItem('theme', darkMode ? 'dark' : 'light');
}, [darkMode]);   // ← dependency array: run again ONLY when darkMode changes
```

**The dependency array:**

| Code | When it runs |
|---|---|
| `useEffect(fn)` | After **every** render |
| `useEffect(fn, [])` | Only **once** when the component first appears |
| `useEffect(fn, [darkMode])` | First time + whenever `darkMode` changes |

**Fetching data example** (for learning, not used in this project):

```jsx
const [users, setUsers] = useState([]);

useEffect(() => {
  fetch('https://jsonplaceholder.typicode.com/users')
    .then((res) => res.json())
    .then((data) => setUsers(data));
}, []);
```

---

### 6.10 Import / Export

Each file shares code using `export` and uses other files' code with `import`.

**Default export** (one main thing per file):

```jsx
// Footer.jsx
export default Footer;

// App.jsx
import Footer from './components/Footer.jsx';
```

**Named exports** (many things per file):

```js
// data.js
export const profile = {...};
export const skills = [...];

// Hero.jsx
import { profile } from '../data.js';   // curly braces for named imports
```

`./` = same folder, `../` = one folder up.

---

## 7. Every Component, Explained

| File | What it shows | React concepts it teaches |
|---|---|---|
| `App.jsx` | Puts all sections together, controls dark mode | `useState`, `useEffect`, passing props, Fragment |
| `Navbar.jsx` | Top menu, mobile hamburger, theme button | `useState`, events, `.map()`, conditional class, function props |
| `Hero.jsx` | Big intro with name and buttons | JSX, using imported data |
| `About.jsx` | Short bio | Simplest component |
| `Skills.jsx` | Skill "pills" | `.map()` and `key` |
| `Projects.jsx` | Grid of project cards | `.map()` + reusing a component with props |
| `ProjectCard.jsx` | One project card | Receiving props, nested `.map()` |
| `Contact.jsx` | Contact form + thank-you message | Controlled form, `useState` object, conditional rendering |
| `Footer.jsx` | Social links + copyright year | JS expressions in JSX (`new Date().getFullYear()`) |
| `data.js` | All your text content | Separating data from UI (good practice) |

**Data flow diagram:**

```
data.js ──(import)──▶ Hero, About, Skills, Projects, Contact, Footer
                                     │
                                     └─(props)─▶ ProjectCard

App (darkMode state) ──(props)──▶ Navbar
Navbar ──(calls onToggleTheme)──▶ App updates state
```

---

## 8. Styling (CSS) Explained

All styles are in **`src/index.css`**. It is imported once in `main.jsx` with `import './index.css';`.

**Key techniques used:**

**1. CSS Variables (for easy theming + dark mode)**

```css
:root {
  --primary: #4f46e5;
  --bg: #f8fafc;
}
body.dark {
  --primary: #818cf8;
  --bg: #0f172a;
}
.btn { background: var(--primary); }
```

When React adds the `dark` class to `<body>`, all the variables switch at once, so every color changes.

**2. Flexbox** (lining items up in a row or column): used in the navbar, hero and skills.

```css
.navbar { display: flex; justify-content: space-between; align-items: center; }
```

**3. CSS Grid** (responsive card layout without media queries):

```css
.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}
```

This means "fit as many columns as possible, each at least 260px wide".

**4. Media Query** (mobile layout):

```css
@media (max-width: 700px) {
  .menu-btn { display: block; }         /* show hamburger */
  .nav-links { display: none; }          /* hide links */
  .nav-links.open { display: flex; }     /* show when React adds "open" */
}
```

**5. Smooth scrolling** for the `#about`, `#projects` and other links:

```css
html { scroll-behavior: smooth; }
```

---

## 9. How to Make It Yours

**Step 1: Edit `src/data.js`.** This is the only file you *need* to change:

```js
export const profile = {
  name: 'Ali Khan',
  role: 'React Developer',
  email: 'ali@example.com',
  github: 'https://github.com/alikhan',
  ...
};
```

**Step 2: Add a project.** Add one more object to the `projects` array:

```js
{
  id: 4,
  title: 'Portfolio Website',
  description: 'This website! Built with React and Vite.',
  tech: ['React', 'Vite', 'CSS'],
  link: 'https://github.com/alikhan/portfolio',
},
```

It appears automatically, because `Projects.jsx` uses `.map()`.

**Step 3: Change colors.** Edit `--primary` in `index.css`.

**Step 4 (optional): Add your photo.**
1. Put `me.jpg` inside `portfolio/public/`.
2. In `Hero.jsx`, add: `<img src="/me.jpg" alt="My photo" className="avatar" />`
3. In CSS: `.avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; }`

**Step 5 (optional): Add a new section**, for example "Experience":
1. Create `src/components/Experience.jsx`:
   ```jsx
   function Experience() {
     return (
       <section id="experience" className="section">
         <h2 className="section-title">Experience</h2>
         <p>Intern at XYZ Company (2025)</p>
       </section>
     );
   }
   export default Experience;
   ```
2. Import and place it in `App.jsx`: `import Experience from './components/Experience.jsx';` → `<Experience />`
3. Add `'experience'` to the `links` array in `Navbar.jsx`.

---

## 10. Build and Deploy (Put It Online)

### Build

```bash
npm run build
```

This creates a `dist/` folder with plain HTML, CSS and JS files that any web host can serve.

### Deploy for free (choose one)

**Option A: Netlify (easiest, drag and drop)**
1. Run `npm run build`.
2. Go to https://app.netlify.com/drop
3. Drag the `dist` folder onto the page. Done, you'll get a live link.

**Option B: Vercel**
1. Push your code to GitHub.
2. Go to https://vercel.com → "Add New Project" → pick your repo.
3. Set **Root Directory** to `portfolio`. Vercel detects Vite automatically → Deploy.

**Option C: GitHub Pages**
1. In `vite.config.js` add `base: '/your-repo-name/',`
2. Run `npm install gh-pages --save-dev`
3. Add a script in `package.json`: `"deploy": "vite build && gh-pages -d dist"`
4. Run `npm run deploy`

---

## 11. Common Beginner Errors

| Error / Problem | Cause | Fix |
|---|---|---|
| `'vite' is not recognized` | Dependencies not installed | Run `npm install` |
| `npm: command not found` | Node.js not installed | Install Node.js LTS |
| Blank white page | JS error | Open browser DevTools (F12) → Console tab to read the error |
| `Warning: Each child in a list should have a unique "key" prop` | Missing `key` in `.map()` | Add `key={item.id}` |
| `Adjacent JSX elements must be wrapped` | Returning two elements side by side | Wrap them in `<> </>` or a `<div>` |
| Component not showing | Name starts with lowercase, or forgot to import | Use `MyComponent`, check the `import` line |
| Function runs on page load instead of click | Wrote `onClick={fn()}` | Write `onClick={fn}` or `onClick={() => fn()}` |
| Styles not working | Used `class=` | Use `className=` |
| Input can't be typed in | `value` set but no `onChange` | Add an `onChange` handler |

---

## 12. Practice Exercises

Try these one at a time to build your skills:

1. **Easy:** Change your name, role and skills in `data.js`.
2. **Easy:** Add a 4th project card.
3. **Easy:** Change the main color to green (`--primary: #16a34a;`).
4. **Medium:** Add a "Download CV" button in `Hero.jsx` that links to `/cv.pdf` (put the file in `public/`).
5. **Medium:** Add a `level` field to each skill (like `{ name: 'React', level: 'Intermediate' }`) and show it.
6. **Medium:** Add a filter on the Projects section: buttons "All", "React", "API" that show only matching projects. *(Hint: `useState` for the selected filter + `.filter()` before `.map()`.)*
7. **Hard:** Use `useEffect` + `fetch` to load your real GitHub repositories from
   `https://api.github.com/users/YOUR_USERNAME/repos` and show them as cards.
8. **Hard:** Connect the contact form to Formspree so you actually receive messages.

---

## 13. Glossary

| Term | Simple meaning |
|---|---|
| **Component** | A reusable piece of UI (a function that returns JSX) |
| **JSX** | HTML-like syntax inside JavaScript |
| **Props** | Data passed from parent to child component |
| **State** | Data inside a component that can change |
| **Hook** | Special React functions starting with `use` (`useState`, `useEffect`) |
| **Render** | React drawing the component on screen |
| **Re-render** | Drawing it again after state or props change |
| **Fragment** | `<> </>`, groups elements without an extra div |
| **Destructuring** | `const { a, b } = obj`, pulls values out of objects |
| **Spread operator** | `...obj`, copies all values of an object or array |
| **Dependency array** | The `[ ]` in `useEffect` that decides when it runs again |
| **npm package** | A ready-made library you can install |
| **Dev server** | Local server (`npm run dev`) for building with live reload |
| **Build** | Turning your source code into optimized files for the web |

---

**Next steps after this project:** learn **React Router** (multiple pages), **fetching APIs**,
**Tailwind CSS** (faster styling) and then **Next.js** (a React framework). Happy coding! 🚀
