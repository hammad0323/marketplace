import { useEffect, useState } from 'react';
import { getContent } from '../api.js';

// A CUSTOM HOOK: our own hook built from useState + useEffect.
// Any page can call useContent() to get the database content
// together with "loading" and "error" information.
export function useContent() {
  const [content, setContent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let ignore = false; // becomes true if the user leaves the page before loading ends

    getContent()
      .then((data) => {
        if (!ignore) setContent(data);
      })
      .catch((err) => {
        if (!ignore) setError(err.message);
      })
      .finally(() => {
        if (!ignore) setLoading(false);
      });

    return () => {
      ignore = true; // "cleanup" runs when the component disappears
    };
  }, []); // [] = load only once, when the page opens

  return { content, loading, error };
}
