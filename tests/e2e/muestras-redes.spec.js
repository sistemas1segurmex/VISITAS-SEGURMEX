// @ts-check
// Cotizador y Solicitud de muestras (varios estilos y tallas, tope de 6
// pares por cliente al mes) probados con distintas calidades de red, en
// celular (Pixel 7 emulado). Ver supabase/migrations/20261022120000_muestras_varios_estilos.sql.
//
// Qué hace, por cada red simulada:
//   1. Cotizador: abre Nueva cotización, elige cliente, agrega un estilo y
//      revisa que calcule el total. NO guarda la cotización.
//   2. Muestras: pide una muestra con 2 estilos y 2 tallas cada uno (4 pares),
//      revisa el aviso del tope (pasarse de los pares que le quedan al
//      cliente bloquea el envío), la envía, la revisa como responsable de
//      muestras (detalle y PDF) y la CANCELA con motivo «Prueba automática».
//   3. Conexión que se cae justo al enviar: debe avisar sin tronar y, al
//      volver la red, enviarse una sola vez.
//
// Redes simuladas (Chromium throttling): lan, wifi, datos_4g, datos_3g.
// Para probar redes REALES (wifi de la planta, datos del celular, fuera de
// la LAN) se corre el mismo archivo desde esa red y se anota con E2E_RED:
//   PowerShell:  $env:E2E_RED="datos celular"; npx playwright test tests/e2e/muestras-redes.spec.js
//   Producción:  $env:E2E_VISITAS_URL="https://sgmx.com.mx/visitas/"; ...
// Solo una red simulada:  $env:E2E_PERFIL="datos_3g"
//
// Necesita en .env (cuentas DE PRUEBA, nunca las de un vendedor real: entrar
// aquí cierra la sesión que esa cuenta tenga abierta en otro lado):
//   E2E_VISITAS_EMAIL / E2E_VISITAS_PASSWORD     vendedor con al menos un cliente
//   E2E_MUESTRAS_EMAIL / E2E_MUESTRAS_PASSWORD   usuario con rol «muestras»
// Opcional: E2E_MUESTRAS_CLIENTE = nombre exacto del cliente de prueba a usar.
const { test, expect } = require('@playwright/test');

const VEND_EMAIL = process.env.E2E_VISITAS_EMAIL;
const VEND_PASS  = process.env.E2E_VISITAS_PASSWORD;
const MU_EMAIL   = process.env.E2E_MUESTRAS_EMAIL;
const MU_PASS    = process.env.E2E_MUESTRAS_PASSWORD;
const RED_REAL   = process.env.E2E_RED || 'sin anotar';
const CLIENTE_PRUEBA = process.env.E2E_MUESTRAS_CLIENTE || '';
const MOTIVO_CANCELA = 'Prueba automática (Playwright) — se cancela sola';

// kbps -> bytes/s para CDP. latency en ms.
const PERFILES = {
  lan:      { nombre: 'LAN interna',         latency: 2,   down: 100000, up: 100000 },
  wifi:     { nombre: 'Wifi',                latency: 25,  down: 20000,  up: 8000 },
  datos_4g: { nombre: 'Datos 4G',            latency: 90,  down: 9000,   up: 1500 },
  datos_3g: { nombre: 'Datos 3G / poca señal', latency: 350, down: 1200, up: 500 },
};
const perfilesACorrer = process.env.E2E_PERFIL ? [process.env.E2E_PERFIL] : Object.keys(PERFILES);

test.skip(!VEND_EMAIL || !VEND_PASS, 'Faltan E2E_VISITAS_EMAIL / E2E_VISITAS_PASSWORD en .env');
test.describe.configure({ mode: 'serial', timeout: 240_000 });

async function aplicarRed(page, clave) {
  const p = PERFILES[clave];
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false, latency: p.latency,
    downloadThroughput: (p.down * 1024) / 8, uploadThroughput: (p.up * 1024) / 8,
  });
}

// Los recorridos guiados (v26-tour.js) se abren la primera vez y tapan la
// pantalla: se marcan como ya vistos.
async function sinTours(page) {
  await page.addInitScript(() => {
    const orig = Storage.prototype.getItem;
    Storage.prototype.getItem = function (k) { return String(k).startsWith('v26_tour') ? '1' : orig.call(this, k); };
  });
}

async function entrar(page, email, pass, destino) {
  await sinTours(page);
  await page.goto('login.php');
  await page.locator('#vlg-email').fill(email);
  await page.locator('#vlg-pass').fill(pass);
  await page.locator('#vlg-submit').click();
  // Si la cuenta quedó abierta en otra corrida, la página ofrece cerrar las
  // otras sesiones y hay que volver a poner la contraseña.
  if (await page.getByText('ya está abierta').isVisible().catch(() => false)) {
    await page.locator('#vlg-pass').fill(pass);
    await page.locator('#vlg-submit').click();
  }
  await expect(page).toHaveURL(destino, { timeout: 60_000 });
}

function anotar(info, tipo, texto) {
  info.annotations.push({ type: tipo, description: texto });
  console.log(`[${tipo}] ${texto}`);
}

async function medir(fn) {
  const t0 = Date.now();
  await fn();
  return ((Date.now() - t0) / 1000).toFixed(1) + ' s';
}

// Elige cliente y estilos de lo que el propio formulario cargó (no depende
// de claves fijas): el cliente con más pares disponibles y dos estilos con
// al menos dos tallas.
async function datosDelFormulario(page, paresNecesarios) {
  await expect(page.locator('.mu-lin .mu-est-item').first()).toBeVisible({ timeout: 90_000 });
  const d = await page.evaluate(() => ({ clientes: clientesCat, estilos: estilosCat, tope: TOPE_PARES }));
  let clientes = d.clientes.map(c => ({ ...c, disp: d.tope - (c.pares_muestra_mes || 0) }));
  if (CLIENTE_PRUEBA) clientes = clientes.filter(c => c.nombre === CLIENTE_PRUEBA);
  clientes.sort((a, b) => b.disp - a.disp);
  const cliente = clientes[0];
  const estilos = d.estilos.filter(e => (e.tallas || []).length >= 2).slice(0, 2);
  return { cliente, estilos, tope: d.tope, alcanza: !!cliente && cliente.disp >= paresNecesarios && estilos.length === 2 };
}

async function llenarEstilo(page, n, estilo, tallas) {
  const lin = page.locator('.mu-lin').nth(n);
  await lin.locator(`.mu-est-item[data-id="${estilo.id}"]`).click();
  if ((estilo.colores || []).length > 1) await lin.locator('[data-accion="color"]').selectOption(estilo.colores[0]);
  for (let i = 0; i < tallas.length; i++) {
    if (i > 0) await lin.locator('[data-accion="agregar-talla"]').click();
    await lin.locator('.mu-talla').nth(i).locator('.mu-talla-sel').selectOption(tallas[i]);
  }
}

async function cancelarComoResponsable(browser, baseURL, red, id, info) {
  if (!MU_EMAIL || !MU_PASS) {
    anotar(info, 'aviso', `Sin E2E_MUESTRAS_EMAIL: la solicitud ${id} quedó SIN cancelar (cuenta para el tope del cliente).`);
    return null;
  }
  const ctx = await browser.newContext({ baseURL, viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true });
  const page = await ctx.newPage();
  await aplicarRed(page, red);
  await entrar(page, MU_EMAIL, MU_PASS, /muestras\//);
  // Detalle: los dos estilos con sus tallas.
  const tDetalle = await medir(async () => {
    await page.goto(`muestras/ver.php?id=${id}`);
    await expect(page.locator('.md-estilo')).toHaveCount(2);
  });
  await expect(page.locator('.md-tallas tbody tr')).toHaveCount(4);
  await info.attach(`detalle responsable (${red})`, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
  // PDF.
  const pdf = await ctx.request.get(`muestra_pdf.php?id=${id}`);
  expect(pdf.status()).toBe(200);
  expect(pdf.headers()['content-type'] || '').toContain('pdf');
  // Cancelar (mismo endpoint que el botón «Cancelar solicitud»).
  const r = await ctx.request.post('api/muestra_estado.php', { form: { id: String(id), estado: 'cancelada', motivo: MOTIVO_CANCELA } });
  const j = await r.json();
  expect(j.ok, j.error || '').toBeTruthy();
  await ctx.close();
  return tDetalle;
}

for (const red of perfilesACorrer) {
  test.describe(`Red: ${PERFILES[red].nombre}`, () => {
    test.beforeEach(async ({ page }, info) => {
      anotar(info, 'red real', RED_REAL);
      anotar(info, 'servidor', String(info.project.use.baseURL));
      await aplicarRed(page, red);
      await entrar(page, VEND_EMAIL, VEND_PASS, /vendedor\//);
    });

    test('cotizador: elegir cliente, agregar estilo y calcular total (sin guardar)', async ({ page }, info) => {
      const tCarga = await medir(async () => {
        await page.goto('vendedor/nueva_cotizacion.php');
        await expect(page.locator('#btn-cliente')).toBeVisible();
      });
      await page.locator('#btn-cliente').click();
      await page.locator('#lista-clientes .nc-op').first().click({ timeout: 90_000 });
      await page.locator('#btn-abrir-catalogo').click();
      const tCatalogo = await medir(async () => {
        await page.locator('#nc-lista-estilos .nc-estilo-item').first().click({ timeout: 90_000 });
      });
      await page.locator('#btn-listo-catalogo').click();
      await expect(page.locator('#lista-renglones')).not.toBeEmpty();
      await expect(page.locator('#tot-total')).toHaveText(/[1-9]/);
      anotar(info, 'tiempos', `abrir cotizador ${tCarga} · catálogo ${tCatalogo}`);
      await info.attach(`cotizador (${red})`, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
    });

    test('muestras: 2 estilos × 2 tallas, tope por cliente, enviar, revisar y cancelar', async ({ page, browser }, info) => {
      const tCarga = await medir(async () => { await page.goto('vendedor/solicitar_muestra.php'); });
      const d = await datosDelFormulario(page, 4);
      test.skip(!d.alcanza, d.cliente
        ? `El cliente con más pares disponibles (${d.cliente.nombre}) solo tiene ${d.cliente.disp}; se necesitan 4. O faltan estilos con 2 tallas.`
        : 'El vendedor de prueba no tiene clientes');
      anotar(info, 'cliente', `${d.cliente.nombre} (${d.cliente.disp} de ${d.tope} pares disponibles)`);

      await page.locator('#id_cliente').selectOption(String(d.cliente.id));
      // Si a la ficha le falta contacto, se llena con datos de prueba.
      if (await page.locator('#contacto_nombre').isVisible()) await page.locator('#contacto_nombre').fill('Contacto de prueba');
      if (await page.locator('#contacto_telefono').isVisible()) await page.locator('#contacto_telefono').fill('4771234567');
      await llenarEstilo(page, 0, d.estilos[0], d.estilos[0].tallas.slice(0, 2));
      await page.locator('#btn-agregar-estilo').click();
      await llenarEstilo(page, 1, d.estilos[1], d.estilos[1].tallas.slice(0, 2));
      await expect(page.locator('#mu-total-pares')).toContainText('4 pares');
      await expect(page.locator('#btn-enviar-muestra')).toBeEnabled();

      // Tope: un par más de los que le quedan al cliente bloquea el envío.
      const ultima = page.locator('.mu-lin').nth(1).locator('.mu-talla').nth(1);
      const extra = d.cliente.disp + 1 - 4;
      for (let i = 0; i < extra; i++) await ultima.locator('[data-accion="mas"]').click();
      await expect(page.locator('#btn-enviar-muestra')).toBeDisabled();
      await expect(page.locator('#aviso-tope')).toContainText('Quita 1 par');
      await info.attach(`tope excedido (${red})`, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
      for (let i = 0; i < extra; i++) await ultima.locator('[data-accion="menos"]').click();
      await expect(page.locator('#btn-enviar-muestra')).toBeEnabled();

      await page.locator('#motivo').fill(`Prueba automática Playwright · red simulada: ${PERFILES[red].nombre} · red real: ${RED_REAL}`);
      if ((await page.locator('#destino_direccion').inputValue()).trim() === '') await page.locator('#destino_direccion').fill('Dirección de prueba');
      const tEnvio = await medir(async () => {
        await page.locator('#btn-enviar-muestra').click();
        await expect(page.locator('#pantalla-exito')).toBeVisible({ timeout: 120_000 });
      });
      const folio = (await page.locator('#folio-exito').textContent()) || '';
      expect(folio).toMatch(/^MV-\d+/);
      const id = Number(new URL(await page.locator('#btn-pdf-exito').getAttribute('href') || '', 'http://x/').searchParams.get('id'));
      anotar(info, 'folio', folio);
      await info.attach(`enviada (${red})`, { body: await page.screenshot(), contentType: 'image/png' });

      const tDetalle = await cancelarComoResponsable(browser, info.project.use.baseURL, red, id, info);

      // Mis muestras del vendedor: la ve (cancelada si se pudo cancelar).
      await page.goto('vendedor/muestras.php');
      await page.locator('#btn-todas').click();
      const tarjeta = page.locator(`.v26-muestra-card[data-key="${folio}"]`);
      await expect(tarjeta).toBeVisible({ timeout: 60_000 });
      if (tDetalle) await expect(tarjeta).toContainText('Cancelada');
      anotar(info, 'tiempos', `abrir formulario ${tCarga} · enviar ${tEnvio}` + (tDetalle ? ` · detalle responsable ${tDetalle}` : ''));
    });
  });
}

test('se cae la conexión al enviar: avisa, no truena y se envía una sola vez al volver', async ({ page, browser, context }, info) => {
  anotar(info, 'red real', RED_REAL);
  await entrar(page, VEND_EMAIL, VEND_PASS, /vendedor\//);
  await page.goto('vendedor/solicitar_muestra.php');
  const d = await datosDelFormulario(page, 1);
  test.skip(!d.cliente || d.cliente.disp < 1, 'El cliente de prueba no tiene pares disponibles este mes');
  await page.locator('#id_cliente').selectOption(String(d.cliente.id));
  if (await page.locator('#contacto_nombre').isVisible()) await page.locator('#contacto_nombre').fill('Contacto de prueba');
  if (await page.locator('#contacto_telefono').isVisible()) await page.locator('#contacto_telefono').fill('4771234567');
  await llenarEstilo(page, 0, d.estilos[0], d.estilos[0].tallas.slice(0, 1));
  const motivo = `Prueba automática Playwright · conexión que se cae · ${Date.now()}`;
  await page.locator('#motivo').fill(motivo);
  if ((await page.locator('#destino_direccion').inputValue()).trim() === '') await page.locator('#destino_direccion').fill('Dirección de prueba');

  await context.setOffline(true);
  await page.locator('#btn-enviar-muestra').click();
  await expect(page.locator('#msg-muestra')).toContainText('No se pudo enviar');
  await expect(page.locator('#btn-enviar-muestra')).toBeEnabled();
  await expect(page.locator('#pantalla-form')).toBeVisible(); // lo capturado sigue ahí
  await info.attach('sin conexión', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });

  await context.setOffline(false);
  await page.locator('#btn-enviar-muestra').click();
  await expect(page.locator('#pantalla-exito')).toBeVisible({ timeout: 120_000 });
  const folio = (await page.locator('#folio-exito').textContent()) || '';
  const id = Number(new URL(await page.locator('#btn-pdf-exito').getAttribute('href') || '', 'http://x/').searchParams.get('id'));
  anotar(info, 'folio', folio);

  // Una sola solicitud con ese motivo en Mis muestras (no se duplicó).
  await page.goto('vendedor/muestras.php');
  await expect(page.locator(`.v26-muestra-card[data-key="${folio}"]`)).toHaveCount(1, { timeout: 60_000 });
  expect(await page.evaluate((m) => muestrasData.filter(x => x.motivo === m).length, motivo)).toBe(1);

  if (MU_EMAIL && MU_PASS) {
    const ctx = await browser.newContext({ baseURL: info.project.use.baseURL });
    const p2 = await ctx.newPage();
    await entrar(p2, MU_EMAIL, MU_PASS, /muestras\//);
    const r = await ctx.request.post('api/muestra_estado.php', { form: { id: String(id), estado: 'cancelada', motivo: MOTIVO_CANCELA } });
    expect((await r.json()).ok).toBeTruthy();
    await ctx.close();
  } else {
    anotar(info, 'aviso', `Sin E2E_MUESTRAS_EMAIL: ${folio} quedó SIN cancelar.`);
  }
});
