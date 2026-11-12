import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { site } from '../data/siteData.js';
import './Navbar.css';

const links = [
  { to: '/', label: 'Home' },
  { to: '/about', label: 'About' },
  { to: '/contact', label: 'Contact' },
];

function Navbar() {
  const [open, setOpen] = useState(false);

  return (
    <header className="navbar">
      <div className="container navbar-inner">
        {/* <Link> changes the page WITHOUT reloading the browser */}
        <Link to="/" className="brand" onClick={() => setOpen(false)}>
          {site.brand}
        </Link>

        <button
          className="menu-toggle"
          onClick={() => setOpen(!open)}
          aria-label="Toggle menu"
          aria-expanded={open}
        >
          <span className={open ? 'bar bar-open' : 'bar'} />
        </button>

        <nav className={open ? 'nav nav-open' : 'nav'}>
          {links.map((link) => (
            // <NavLink> is like <Link>, but it knows if it is the current page.
            // We use that to add the "active" class and highlight it.
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
          <Link to="/contact" className="btn btn-small" onClick={() => setOpen(false)}>
            Hire Me
          </Link>
        </nav>
      </div>
    </header>
  );
}

export default Navbar;
