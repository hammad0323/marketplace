import { useState } from 'react';
import PageHeader from '../components/PageHeader.jsx';
import { site } from '../data/siteData.js';
import './Contact.css';

const emptyForm = { name: '', email: '', subject: '', message: '' };

// Checks the form and returns an object of error messages.
// An empty object {} means "no errors".
function validate(form) {
  const errors = {};
  if (!form.name.trim()) errors.name = 'Please enter your name.';
  if (!form.email.trim()) {
    errors.email = 'Please enter your email.';
  } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
    errors.email = 'Please enter a valid email address.';
  }
  if (form.message.trim().length < 10) {
    errors.message = 'Message should be at least 10 characters.';
  }
  return errors;
}

function Contact() {
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [sent, setSent] = useState(false);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm({ ...form, [name]: value });
    // Remove the error for this field as soon as the user types
    if (errors[name]) setErrors({ ...errors, [name]: '' });
  }

  function handleSubmit(e) {
    e.preventDefault(); // stop the browser from reloading the page

    const newErrors = validate(form);
    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) return; // stop if there are errors

    // A real website would send `form` to a server or a service like Formspree here.
    console.log('Message sent:', form);
    setSent(true);
    setForm(emptyForm);
  }

  const contactInfo = [
    { icon: '📧', label: 'Email', value: site.email, href: `mailto:${site.email}` },
    { icon: '📞', label: 'Phone', value: site.phone, href: `tel:${site.phone}` },
    { icon: '📍', label: 'Location', value: site.location },
  ];

  return (
    <>
      <PageHeader
        title="Contact Me"
        subtitle="Have a question or a project idea? Send me a message and I'll reply soon."
      />

      <section className="section container contact-grid">
        {/* ===== Left: contact info ===== */}
        <aside className="contact-info">
          <h2>Let's talk</h2>
          <p>I usually reply within 24 hours. You can also reach me directly:</p>

          {contactInfo.map((item) => (
            <div key={item.label} className="info-item">
              <span className="info-icon">{item.icon}</span>
              <div>
                <small>{item.label}</small>
                {/* Show a link only if this item has an href */}
                {item.href ? <a href={item.href}>{item.value}</a> : <p>{item.value}</p>}
              </div>
            </div>
          ))}
        </aside>

        {/* ===== Right: form OR success message ===== */}
        <div className="contact-card">
          {sent ? (
            <div className="success">
              <div className="success-icon">✓</div>
              <h3>Message sent!</h3>
              <p>Thank you for reaching out. I'll get back to you soon.</p>
              <button className="btn" onClick={() => setSent(false)}>
                Send another message
              </button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} noValidate>
              <div className="form-row">
                <Field label="Your name" name="name" value={form.name}
                  onChange={handleChange} error={errors.name} />
                <Field label="Your email" name="email" type="email" value={form.email}
                  onChange={handleChange} error={errors.email} />
              </div>
              <Field label="Subject (optional)" name="subject" value={form.subject}
                onChange={handleChange} />
              <Field label="Message" name="message" textarea value={form.message}
                onChange={handleChange} error={errors.message} />
              <button type="submit" className="btn btn-full">Send Message</button>
            </form>
          )}
        </div>
      </section>
    </>
  );
}

// A small reusable input component, used 4 times above.
function Field({ label, name, type = 'text', textarea = false, value, onChange, error }) {
  const props = {
    id: name,
    name,
    value,
    onChange,
    className: error ? 'input input-error' : 'input',
  };

  return (
    <div className="field">
      <label htmlFor={name}>{label}</label>
      {textarea ? <textarea rows="6" {...props} /> : <input type={type} {...props} />}
      {error && <span className="error-text">{error}</span>}
    </div>
  );
}

export default Contact;
