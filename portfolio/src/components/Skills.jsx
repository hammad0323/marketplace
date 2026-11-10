import { skills } from '../data.js';

function Skills() {
  return (
    <section id="skills" className="section">
      <h2 className="section-title">Skills</h2>
      <ul className="skills-list">
        {/* .map() turns each skill string into an <li> element */}
        {skills.map((skill) => (
          <li key={skill} className="skill">{skill}</li>
        ))}
      </ul>
    </section>
  );
}

export default Skills;
