// @ts-check
// Formulario de dirección de "Nuevo cliente" (vendedor), en celular:
// buscar la colonia por nombre y pegar un link de Google Maps que solo trae
// la dirección. Solo lee: nunca se toca "Guardar cliente".
//
// Correr:  npx playwright test
const { test, expect } = require('@playwright/test');

const EMAIL = process.env.E2E_VISITAS_EMAIL;
const PASSWORD = process.env.E2E_VISITAS_PASSWORD;

test.skip(!EMAIL || !PASSWORD, 'Faltan E2E_VISITAS_EMAIL / E2E_VISITAS_PASSWORD en .env');

async function entrar(page) {
  await page.goto('login.php');
  await page.locator('#vlg-email').fill(EMAIL);
  await page.locator('#vlg-pass').fill(PASSWORD);
  await page.locator('#vlg-submit').click();
  // Si la cuenta quedó abierta en otra corrida, la página ofrece cerrar las
  // otras sesiones (forzar_login) y hay que volver a poner la contraseña.
  if (await page.getByText('ya está abierta').isVisible().catch(() => false)) {
    await page.locator('#vlg-pass').fill(PASSWORD);
    await page.locator('#vlg-submit').click();
  }
  await expect(page).toHaveURL(/vendedor\//);
}

test.describe('Nuevo cliente: dirección', () => {
  test.beforeEach(async ({ page }) => {
    await entrar(page);
    await page.goto('vendedor/nuevo_cliente.php');
    await expect(page.locator('#buscar-colonia')).toBeVisible();
  });

  // [lo que escribe el vendedor, colonia que debe ofrecer, municipio]
  const casos = [
    ['col centro', 'Centro', 'León'],
    ['San Juan', 'San Juan', 'León'],
    ['Las Americas Leon', 'Las Américas', 'León'],
    ['tlalchichilpan san mateo', 'San Mateo Tlalchichilpan', 'Almoloya de Juárez'],
    ['Jardines de Jerez 2a seccion', 'Jardines de Jerez', 'León'],
    ['fracc las hilamas', 'Las Hilamas', 'León'],
  ];

  for (const [texto, colonia, municipio] of casos) {
    test(`buscar colonia "${texto}" ofrece ${colonia} (${municipio})`, async ({ page }) => {
      await page.locator('#buscar-colonia').fill(texto);
      const opcion = page.locator('#resultados-colonia button[data-i]')
        .filter({ has: page.locator('b', { hasText: new RegExp(`^${colonia}$`) }) })
        .filter({ hasText: municipio })
        .first();
      await expect(opcion).toBeVisible();

      // Al elegirla queda como colonia del cliente, con su CP.
      await opcion.click();
      await expect(page.locator('#resumen-colonia')).toBeVisible();
      await expect(page.locator('#resumen-colonia-nombre')).toHaveText(colonia);
      await expect(page.locator('#resumen-colonia-detalle')).toContainText(municipio);
      await expect(page.locator('#input-cp')).toHaveValue(/^\d{5}$/);
    });
  }

  test('una colonia que no existe avisa sin tronar', async ({ page }) => {
    await page.locator('#buscar-colonia').fill('zzqxw colonia inexistente');
    await expect(page.locator('#resultados-colonia')).toContainText('No hay colonias con ese nombre');
  });

  test('link de Google Maps que solo trae la dirección: la busca y lleva a la lista', async ({ page }) => {
    // Colonia de otro estado elegida antes: la dirección del link (SLP) no
    // debe buscarse con León/Guanajuato pegado.
    await page.locator('#buscar-colonia').fill('col centro');
    await page.locator('#resultados-colonia button[data-i]').filter({ hasText: 'León' }).first().click();
    await expect(page.locator('#resumen-colonia')).toBeVisible();

    await page.locator('#btn-link-ubicacion').click();
    await page.locator('#pegar-ubicacion').fill('https://maps.app.goo.gl/hSMQHtJmz5zbqQjh8');
    await page.locator('#pegar-ubicacion').press('Enter');

    const nota = page.locator('#nota-link');
    await expect(nota).toContainText('Elige el resultado en la lista de arriba', { timeout: 45_000 });
    const resultado = page.locator('#resultados-busqueda button[data-i]').filter({ hasText: /Circuito Exportaci[oó]n/ }).first();
    await expect(resultado).toBeVisible();
    await expect(resultado).toBeInViewport();

    // Al elegirlo queda el pin (por confirmar) en San Luis Potosí.
    await resultado.click();
    await expect(page.locator('#ubicacion-estado')).toContainText('marcada');
    const lat = Number(await page.locator('#lat').inputValue());
    const lng = Number(await page.locator('#lng').inputValue());
    expect(lat).toBeGreaterThan(21.9);
    expect(lat).toBeLessThan(22.4);
    expect(lng).toBeGreaterThan(-101.2);
    expect(lng).toBeLessThan(-100.7);
  });
});
