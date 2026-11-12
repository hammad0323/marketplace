import { Routes, Route } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import Home from './pages/Home.jsx';
import About from './pages/About.jsx';
import Contact from './pages/Contact.jsx';
import NotFound from './pages/NotFound.jsx';

// <Routes> looks at the URL and shows the ONE <Route> that matches.
function App() {
  return (
    <Routes>
      {/* Layout wraps every page: Navbar on top, Footer at the bottom */}
      <Route path="/" element={<Layout />}>
        <Route index element={<Home />} />            {/* URL: /        */}
        <Route path="about" element={<About />} />     {/* URL: /about   */}
        <Route path="contact" element={<Contact />} /> {/* URL: /contact */}
        <Route path="*" element={<NotFound />} />      {/* any other URL */}
      </Route>
    </Routes>
  );
}

export default App;
