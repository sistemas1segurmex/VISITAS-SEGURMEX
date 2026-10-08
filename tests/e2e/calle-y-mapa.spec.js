// @ts-check
// "Nuevo cliente", paso 2 y 3 (8-oct-2026): Calle y Número separados, la
// dirección completa pegada en Calle se limpia sola, el aviso rojo de
// ubicación se quita al marcar el pin y, si el mapa normal no carga, se
// cambia solo a Satélite. Nunca llega a guardar: cada prueba se detiene en
// una validación antes de mandar el formulario.
//
// Correr:  npx playwright test tests/e2e/calle-y-mapa.spec.js
const { test, expect } = require('@playwright/test');

const EMAIL = process.env.E2E_VISITAS_EMAIL;
const PASSWORD = process.env.E2E_VISITAS_PASSWORD;

test.skip(!EMAIL || !PASSWORD, 'Faltan E2E_VISITAS_EMAIL / E2E_VISITAS_PASSWORD en .env');

async function entrar(page) {
  await page.goto('login.php');
  await page.locator('#vlg-email').fill(EMAIL);
  await page.locator('#vlg-pass').fill(PASSWORD);
  await page.locator('#vlg-submit').click();
  if (await page.getByText('ya está abierta').isVisible().catch(() => false)) {
    await page.locator('#vlg-pass').fill(PASSWORD);
    await page.locator('#vlg-submit').click();
  }
  await expect(page).toHaveURL(/vendedor\//);
}

// Colonia del caso real: Parque Industrial de Logística Automotriz (CP 20340).
async function elegirColoniaPila(page) {
  await page.locator('#buscar-colonia').fill('20340');
  const resumen = page.locator('#resumen-colonia');
  const opcion = page.locator('#resultados-colonia button[data-i]').filter({ hasText: /Log[ií]stica Automotriz/ }).first();
  await expect(resumen.or(opcion)).toBeVisible();
  if (!(await resumen.isVisible())) await opcion.click();
  await expect(page.locator('#resumen-colonia-nombre')).toContainText(/Log[ií]stica Automotriz/);
}

test.describe('Nuevo cliente: calle, número y mapa', () => {
  test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto('vendedor/nuevo_cliente.php');
    await expect(page.locator('#calle')).toBeVisible();
  });

  test('dirección completa pegada en Calle: queda solo calle y número, con deshacer', async ({ page }) => {
    await elegirColoniaPila(page);
    const pegado = 'Parque Industrial de Logística Automotriz, circuito progreso no.102 Parque Industrial, 20340 Aguascalientes, Ags.';
    await page.locator('#calle').fill(pegado);
    await page.locator('#calle').press('Tab');

    await expect(page.locator('#calle')).toHaveValue('circuito progreso');
    await expect(page.locator('#numero')).toHaveValue('102');
    await expect(page.locator('#calle-numero')).toHaveValue('circuito progreso 102');
    await expect(page.locator('#nota-calle')).toContainText('Dejamos solo la calle');

    await page.locator('#btn-deshacer-calle').click();
    await expect(page.locator('#calle')).toHaveValue(pegado);
  });

  test('número escrito al final de la calle pasa a Número', async ({ page }) => {
    await page.locator('#calle').fill('Blvd. Diamantes 116');
    await page.locator('#calle').press('Tab');
    await expect(page.locator('#calle')).toHaveValue('Blvd. Diamantes');
    await expect(page.locator('#numero')).toHaveValue('116');
  });

  test('"Calle 3" no pierde su número de nombre; "Sin número" llena S/N', async ({ page }) => {
    await page.locator('#calle').fill('Calle 3');
    await page.locator('#calle').press('Tab');
    await expect(page.locator('#calle')).toHaveValue('Calle 3');
    await expect(page.locator('#numero')).toHaveValue('');
    await page.locator('#btn-sin-numero').click();
    await expect(page.locator('#numero')).toHaveValue('S/N');
    await expect(page.locator('#calle-numero')).toHaveValue('Calle 3 S/N');
  });

  test('sin número no deja guardar y lo dice', async ({ page }) => {
    await page.locator('input[name="nombre"]').fill('Prueba E2E (no se guarda)');
    await elegirColoniaPila(page);
    await page.locator('#calle').fill('Calle 3');
    await page.getByRole('button', { name: 'Guardar cliente' }).click();
    await expect(page.locator('#msg-cliente')).toContainText('Falta el número');
    await expect(page.locator('#numero')).toBeFocused();
  });

  test('el aviso "Falta marcar la ubicación" se quita al tocar el mapa', async ({ page }) => {
    await page.locator('input[name="nombre"]').fill('Prueba E2E (no se guarda)');
    await elegirColoniaPila(page);
    await page.locator('#calle').fill('Circuito Progreso');
    await page.locator('#numero').fill('102');
    await page.getByRole('button', { name: 'Guardar cliente' }).click();
    await expect(page.locator('#alerta-ubicacion')).toContainText('Estoy aquí');

    await page.locator('#mapa-cliente').click({ position: { x: 120, y: 120 } });
    await expect(page.locator('#ubicacion-estado')).toContainText('marcada');
    await expect(page.locator('#alerta-ubicacion')).toHaveCount(0);
  });
});

test.describe('Nuevo cliente: mapa bloqueado', () => {
  test('si openstreetmap.org no carga, se cambia solo a Satélite y avisa', async ({ page }) => {
    await page.route(/tile\.openstreetmap\.org/, route => route.abort());
    await entrar(page);
    await page.goto('vendedor/nuevo_cliente.php');
    await expect(page.locator('#mapa-cliente')).toContainText('te pusimos Satélite');
    await expect(page.locator('#mapa-cliente').getByLabel('Satélite')).toBeChecked();
  });
});
