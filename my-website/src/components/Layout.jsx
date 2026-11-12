import { Outlet } from 'react-router-dom';
import Navbar from './Navbar.jsx';
import Footer from './Footer.jsx';
import ScrollToTop from './ScrollToTop.jsx';

// Layout = the "frame" shared by every page.
// <Outlet /> is the empty slot where the current page (Home/About/Contact) appears.
function Layout() {
  return (
    <>
      <ScrollToTop />
      <Navbar />
      <main className="page">
        <Outlet />
      </main>
      <Footer />
    </>
  );
}

export default Layout;
