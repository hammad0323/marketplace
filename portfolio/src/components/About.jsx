import { profile } from '../data.js';

function About() {
  return (
    <section id="about" className="section">
      <h2 className="section-title">About Me</h2>
      <p className="about-text">{profile.about}</p>
    </section>
  );
}

export default About;
