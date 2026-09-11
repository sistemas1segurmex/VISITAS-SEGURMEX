// Genera el ícono fuente (1024x1024) y el splash fuente (2732x2732) de la
// app a partir del logo real de Visitas, sobre el degradado de marca --
// mismo criterio de color que el resto del sistema (v26-brand-grad).
const sharp = require('sharp');
const path = require('path');

const BRAND_1 = '#E8A400';
const BRAND_2 = '#C98800';
const LOGO = path.join(__dirname, '..', 'logo.png');
const OUT_DIR = path.join(__dirname, 'assets');

function gradientSquareSvg(size) {
  return Buffer.from(`
    <svg width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="${BRAND_1}"/>
          <stop offset="100%" stop-color="${BRAND_2}"/>
        </linearGradient>
      </defs>
      <rect width="${size}" height="${size}" fill="url(#g)"/>
    </svg>`);
}

async function construirIcono() {
  const size = 1024;
  const logoAncho = Math.round(size * 0.62);
  const logoBuf = await sharp(LOGO).resize({ width: logoAncho }).toBuffer();
  const logoMeta = await sharp(logoBuf).metadata();

  await sharp(gradientSquareSvg(size))
    .composite([{
      input: logoBuf,
      left: Math.round((size - logoMeta.width) / 2),
      top: Math.round((size - logoMeta.height) / 2),
    }])
    .png()
    .toFile(path.join(OUT_DIR, 'icon.png'));
}

async function construirSplash(nombre, size) {
  const logoAncho = Math.round(size * 0.42);
  const logoBuf = await sharp(LOGO).resize({ width: logoAncho }).toBuffer();
  const logoMeta = await sharp(logoBuf).metadata();

  await sharp(gradientSquareSvg(size))
    .composite([{
      input: logoBuf,
      left: Math.round((size - logoMeta.width) / 2),
      top: Math.round((size - logoMeta.height) / 2),
    }])
    .png()
    .toFile(path.join(OUT_DIR, nombre));
}

(async () => {
  const fs = require('fs');
  fs.mkdirSync(OUT_DIR, { recursive: true });
  await construirIcono();
  await construirSplash('splash.png', 2732);
  await construirSplash('splash-dark.png', 2732);
  console.log('Listo: assets/icon.png, assets/splash.png, assets/splash-dark.png');
})();
