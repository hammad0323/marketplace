import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

// When the page (URL) changes, scroll back to the top.
// Without this, the new page could open half-way down.
function ScrollToTop() {
  const { pathname } = useLocation(); // current URL path, e.g. "/about"

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [pathname]); // run every time the path changes

  return null; // this component shows nothing on screen
}

export default ScrollToTop;
