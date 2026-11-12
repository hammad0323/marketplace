// =========================================================
// All calls from React to the PHP backend live in this file.
// Pages import these functions instead of writing fetch() themselves.
// =========================================================

// "/api" works both on your computer (Vite sends it to PHP)
// and on a live server where the "api" folder sits next to index.html.
export const API_URL = '/api';

// GET the services, skills and timeline from the database
export async function getContent() {
  const response = await fetch(`${API_URL}/content.php`);
  const data = await readJson(response);

  if (!response.ok) {
    throw new Error(data.error || 'Could not load content.');
  }
  return data; // { services: [...], skills: [...], timeline: [...] }
}

// POST the contact form to be saved in the database
export async function sendContactMessage(form) {
  const response = await fetch(`${API_URL}/contact.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(form),
  });
  const data = await readJson(response);

  // 422 = the server found mistakes in the form, e.g. { errors: { email: '...' } }
  if (response.status === 422 && data.errors) {
    return { ok: false, errors: data.errors };
  }
  if (!response.ok) {
    throw new Error(data.error || 'Could not send your message.');
  }
  return { ok: true };
}

// Turn the response into JSON. If PHP is not running, the answer is
// not JSON at all, so we give a clear message instead of a confusing crash.
async function readJson(response) {
  try {
    return await response.json();
  } catch {
    throw new Error('The server is not responding. Is the PHP server running?');
  }
}
