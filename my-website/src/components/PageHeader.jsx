import './PageHeader.css';

// Reusable banner at the top of the About and Contact pages.
// Props let each page show its own title and subtitle.
function PageHeader({ title, subtitle }) {
  return (
    <section className="page-header">
      <div className="container">
        <h1>{title}</h1>
        <p>{subtitle}</p>
      </div>
    </section>
  );
}

export default PageHeader;
