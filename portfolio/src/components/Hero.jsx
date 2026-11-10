import { profile } from '../data.js';

function Hero() {
  return (
    <section id="home" className="hero">
      <p className="hello">Hello, I'm</p>
      <h1>{profile.name}</h1>
      <h2>{profile.role}</h2>
      <p className="intro">{profile.intro}</p>
      <div className="hero-buttons">
        <a href="#projects" className="btn">See My Work</a>
        <a href="#contact" className="btn btn-outline">Contact Me</a>
      </div>
    </section>
  );
}

export default Hero;
