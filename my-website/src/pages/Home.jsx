import { Link } from 'react-router-dom';
import { site, stats } from '../data/siteData.js';
import { useContent } from '../hooks/useContent.js';
import Loader from '../components/Loader.jsx';
import ErrorMessage from '../components/ErrorMessage.jsx';
import './Home.css';

function Home() {
  // services come from the database (PHP API)
  const { content, loading, error } = useContent();

  return (
    <>
      {/* ===== Hero ===== */}
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

      {/* ===== Stats ===== */}
      <section className="container stats">
        {stats.map((item) => (
          <div key={item.label} className="stat">
            <strong>{item.value}</strong>
            <span>{item.label}</span>
          </div>
        ))}
      </section>

      {/* ===== Services ===== */}
      <section className="section container">
        <div className="section-heading">
          <span className="eyebrow">What I do</span>
          <h2>Services I Offer</h2>
        </div>

        {loading && <Loader text="Loading services..." />}
        {error && <ErrorMessage message={error} />}

        {content && (
          <div className="cards">
            {content.services.map((service) => (
              <article key={service.id} className="card">
                <div className="card-icon">{service.icon}</div>
                <h3>{service.title}</h3>
                <p>{service.text}</p>
              </article>
            ))}
          </div>
        )}
      </section>

      {/* ===== Call to action ===== */}
      <section className="container">
        <div className="cta">
          <h2>Have a project in mind?</h2>
          <p>Let's work together and build something great.</p>
          <Link to="/contact" className="btn btn-light">Contact Me</Link>
        </div>
      </section>
    </>
  );
}

export default Home;
