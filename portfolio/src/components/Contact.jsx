import { useState } from 'react';
import { profile } from '../data.js';

function Contact() {
  // One state object holds all form fields
  const [form, setForm] = useState({ name: '', email: '', message: '' });
  const [sent, setSent] = useState(false);

  // Runs every time the user types in any input
  function handleChange(e) {
    const { name, value } = e.target;
    setForm({ ...form, [name]: value });
  }

  // Runs when the form is submitted
  function handleSubmit(e) {
    e.preventDefault(); // stop the page from reloading
    console.log('Form data:', form); // a real site would send this to a server
    setSent(true);
    setForm({ name: '', email: '', message: '' });
  }

  return (
    <section id="contact" className="section">
      <h2 className="section-title">Contact Me</h2>

      {/* Conditional rendering: show thank-you OR the form */}
      {sent ? (
        <p className="success">
          Thanks for your message! I'll get back to you soon.{' '}
          <button className="link-btn" onClick={() => setSent(false)}>
            Send another
          </button>
        </p>
      ) : (
        <form className="contact-form" onSubmit={handleSubmit}>
          <input
            name="name"
            placeholder="Your name"
            value={form.name}
            onChange={handleChange}
            required
          />
          <input
            type="email"
            name="email"
            placeholder="Your email"
            value={form.email}
            onChange={handleChange}
            required
          />
          <textarea
            name="message"
            rows="5"
            placeholder="Your message"
            value={form.message}
            onChange={handleChange}
            required
          />
          <button type="submit" className="btn">Send Message</button>
        </form>
      )}

      <p className="contact-alt">
        Or email me directly at <a href={`mailto:${profile.email}`}>{profile.email}</a>
      </p>
    </section>
  );
}

export default Contact;
