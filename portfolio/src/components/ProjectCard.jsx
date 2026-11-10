// A reusable card. It receives one project's data through props.
function ProjectCard({ title, description, tech, link }) {
  return (
    <article className="card">
      <h3>{title}</h3>
      <p>{description}</p>
      <div className="tags">
        {tech.map((t) => (
          <span key={t} className="tag">{t}</span>
        ))}
      </div>
      <a href={link} target="_blank" rel="noreferrer" className="card-link">
        View Code →
      </a>
    </article>
  );
}

export default ProjectCard;
