// @ts-check
// Pruebas E2E de Visitas contra el XAMPP local. URL y usuario salen de .env
// (claves E2E_VISITAS_*, ver .env.example). La app es principalmente para
// celular, así que se prueba con un Android emulado.
const fs = require('fs');
const path = require('path');
const { defineConfig, devices } = require('@playwright/test');

// Mismo formato de .env que lee includes/db.php (CLAVE=valor por línea).
const rutaEnv = path.join(__dirname, '.env');
if (fs.existsSync(rutaEnv)) {
  for (const linea of fs.readFileSync(rutaEnv, 'utf8').split(/\r?\n/)) {
    const m = linea.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (m && process.env[m[1]] === undefined) process.env[m[1]] = m[2];
  }
}

module.exports = defineConfig({
  testDir: './tests/e2e',
  // La BD es remota y la búsqueda de calles va a internet (Google/Nominatim).
  timeout: 90_000,
  expect: { timeout: 20_000 },
  retries: 1,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.E2E_VISITAS_URL || 'http://localhost/visitas/',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    locale: 'es-MX',
  },
  projects: [
    { name: 'android', use: { ...devices['Pixel 7'] } },
  ],
});
