import { profile } from '../data.js';

function Footer() {
  const year = new Date().getFullYear();

  return (
    <footer className="footer">
      <div className="socials">
        <a href={profile.github} target="_blank" rel="noreferrer">GitHub</a>
        <a href={profile.linkedin} target="_blank" rel="noreferrer">LinkedIn</a>
      </div>
      <p>© {year} {profile.name}. Built with React.</p>
    </footer>
  );
}

export default Footer;
