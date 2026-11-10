import { useState } from 'react';

// Props: darkMode (true/false) and onToggleTheme (a function) come from App
function Navbar({ darkMode, onToggleTheme }) {
  const [menuOpen, setMenuOpen] = useState(false);

  const links = ['about', 'skills', 'projects', 'contact'];

  return (
    <header className="navbar">
      <a href="#home" className="logo">Portfolio</a>

      {/* Hamburger button: only visible on small screens (see CSS) */}
      <button
        className="menu-btn"
        onClick={() => setMenuOpen(!menuOpen)}
        aria-label="Toggle menu"
      >
        {menuOpen ? '✕' : '☰'}
      </button>

      {/* Conditional class: add "open" when menuOpen is true */}
      <nav className={menuOpen ? 'nav-links open' : 'nav-links'}>
        {links.map((link) => (
          <a key={link} href={`#${link}`} onClick={() => setMenuOpen(false)}>
            {link.charAt(0).toUpperCase() + link.slice(1)}
          </a>
        ))}
        <button className="theme-btn" onClick={onToggleTheme}>
          {darkMode ? '☀️ Light' : '🌙 Dark'}
        </button>
      </nav>
    </header>
  );
}

export default Navbar;
