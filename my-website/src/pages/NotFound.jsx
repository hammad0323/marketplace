import { Link } from 'react-router-dom';

// Shown when the URL does not match any page (path="*" in App.jsx)
function NotFound() {
  return (
    <section className="section container not-found">
      <h1 className="gradient-text">404</h1>
      <h2>Page not found</h2>
      <p>Sorry, the page you are looking for does not exist.</p>
      <Link to="/" className="btn">Go Back Home</Link>
    </section>
  );
}

export default NotFound;
