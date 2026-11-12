import { Link } from 'react-router-dom';
import { site } from '../data/siteData.js';
import './Footer.css';

function Footer() {
  const year = new Date().getFullYear();

  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div>
          <h3 className="footer-brand">{site.brand}</h3>
          <p>{site.tagline}</p>
        </div>

        <div>
          <h4>Pages</h4>
          <ul>
            <li><Link to="/">Home</Link></li>
            <li><Link to="/about">About</Link></li>
            <li><Link to="/contact">Contact</Link></li>
          </ul>
        </div>

        <div>
          <h4>Follow</h4>
          <ul>
            {site.socials.map((s) => (
              <li key={s.label}>
                {/* External sites use a normal <a> tag, not <Link> */}
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

export default Footer;
