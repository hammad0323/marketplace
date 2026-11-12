import { Link } from 'react-router-dom';
import PageHeader from '../components/PageHeader.jsx';
import { site, skills, timeline } from '../data/siteData.js';
import './About.css';

function About() {
  return (
    <>
      <PageHeader
        title="About Me"
        subtitle="A little about who I am, what I know and where I'm going."
      />

      {/* ===== Intro ===== */}
      <section className="section container about-intro">
        <div className="about-photo" aria-hidden="true">
          {site.name.charAt(0)}
        </div>
        <div>
          <span className="eyebrow">Who I am</span>
          <h2>I turn ideas into beautiful websites</h2>
          <p>
            Hello! I'm {site.name}, a {site.role.toLowerCase()} based in {site.location}.
            I love building websites that are simple to use and nice to look at.
          </p>
          <p>
            I started with HTML and CSS, then fell in love with JavaScript and React.
            Today I help people and small businesses get online with modern websites.
          </p>
          <Link to="/contact" className="btn">Work With Me</Link>
        </div>
      </section>

      {/* ===== Skills with progress bars ===== */}
      <section className="section-alt">
        <div className="container">
          <div className="section-heading">
            <span className="eyebrow">My skills</span>
            <h2>What I'm Good At</h2>
          </div>

          <div className="skills">
            {skills.map((skill) => (
              <div key={skill.name} className="skill">
                <div className="skill-top">
                  <span>{skill.name}</span>
                  <span>{skill.level}%</span>
                </div>
                <div className="skill-bar">
                  {/* Inline style: the width comes from the data */}
                  <div className="skill-fill" style={{ width: `${skill.level}%` }} />
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ===== Timeline ===== */}
      <section className="section container">
        <div className="section-heading">
          <span className="eyebrow">My journey</span>
          <h2>Experience</h2>
        </div>

        <ol className="timeline">
          {timeline.map((item) => (
            <li key={item.year} className="timeline-item">
              <span className="timeline-year">{item.year}</span>
              <h3>{item.title}</h3>
              <p>{item.text}</p>
            </li>
          ))}
        </ol>
      </section>
    </>
  );
}

export default About;
