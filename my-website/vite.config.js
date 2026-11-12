import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    // While developing, any request to /api/... is sent to the PHP server
    // running on port 8000 (start it with: npm run api).
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
});
