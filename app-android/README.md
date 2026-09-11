# Visitas — app Android (Capacitor)

Envoltorio nativo de Android para el sistema de Visitas. No es una reescritura:
la app carga en vivo `https://sgmx.com.mx/visitas/` (ver `capacitor.config.json`
→ `server.url`), exactamente como el navegador. Esto significa que **la
inmensa mayoría de las actualizaciones no requieren tocar esta carpeta ni
generar un APK nuevo** — con hacer `git pull` en el servidor como siempre,
el vendedor ve el cambio la próxima vez que abre la app.

## Cuándo SÍ hace falta un APK nuevo

Solo cuando cambia algo a nivel nativo:
- Agregar/quitar un plugin de Capacitor (ej. background geolocation).
- Cambiar el ícono, el splash screen o el nombre de la app.
- Agregar un permiso nuevo de Android.
- Subir `compileSdk`/`targetSdk` (Google lo exige cada cierto tiempo si algún
  día se publica en Play Store).

## La keystore -- LO MÁS IMPORTANTE de esta carpeta

`keystore/visitas-release.jks` firma el APK. **Nunca se sube a git** (ver
`.gitignore`) porque si se perdiera o cambiara, todos los vendedores tendrían
que desinstalar la app vieja para poder instalar cualquier actualización
futura -- Android no deja "actualizar encima" si la firma no coincide.

- Guarda `keystore/visitas-release.jks` y `keystore/keystore.properties` en un
  lugar seguro fuera de este repo (ej. un gestor de contraseñas de la empresa,
  o un respaldo cifrado). Sistemas los tiene generados desde el día 1.
- Contraseña del keystore y de la llave: `SegurmexVisitas2026!` (alias `visitas`).
  Cámbiala si quieres, pero documenta el cambio en el mismo lugar seguro.
- Sin estos dos archivos, `assembleRelease` genera un APK **sin firmar** (no
  instalable) -- revisa que `keystore.properties` exista antes de compilar.

## Requisitos para compilar

- Node.js (para el CLI de Capacitor).
- JDK 21 (Capacitor Android exige source/target 21; JDK 17 no compila).
- Android SDK: `platform-tools`, `platforms;android-36`, `build-tools;36.0.0`
  (o los que pida `android/variables.gradle` si se actualiza Capacitor).
  Puede ser el SDK de Android Studio, o solo las cmdline-tools
  (`sdkmanager`) sin instalar el IDE completo.
- `android/local.properties` con `sdk.dir=<ruta al SDK>` (no se sube a git,
  cada quien que compile debe crear el suyo).

## Compilar un release firmado

```bash
cd app-android/android
./gradlew.bat assembleRelease
```

El APK queda en `android/app/build/outputs/apk/release/app-release.apk`.

## Actualizar la versión (solo cuando SÍ haga falta un APK nuevo)

En `android/app/build.gradle`, subir `versionCode` (entero, +1 cada vez) y
opcionalmente `versionName` (el que ve el usuario, ej. "1.1"). Recompilar con
el mismo comando de arriba -- **con la misma keystore**, Android lo instala
como actualización normal, sin pedir desinstalar.

## Distribución

Uso interno (no Play Store): se comparte el `.apk` directo (link interno,
WhatsApp, correo, o un MDM si la empresa usa uno). El celular pedirá permitir
"instalar apps de orígenes desconocidos" la primera vez -- normal para apps
fuera de Play Store, no significa que algo esté mal.

## Permisos declarados (`AndroidManifest.xml`)

- `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION`: tracking en vivo y
  verificación de check-in por distancia. Hoy solo funcionan mientras la app
  está abierta y la pantalla encendida (igual que en el navegador) -- GPS en
  segundo plano con pantalla bloqueada necesitaría un plugin aparte (ej.
  `@capacitor-community/background-geolocation`) con su propia notificación
  persistente y permiso "Permitir todo el tiempo".
- `CAMERA`: foto de evidencia en el check-in. La página ya intenta
  `getUserMedia` primero y cae de vuelta a `<input capture>` si no está
  disponible -- ambos caminos deberían funcionar dentro del WebView de
  Capacitor sin plugins adicionales, pero conviene probarlo en un celular
  real antes de repartir la app.

## Estructura

```
app-android/
├── android/            # Proyecto nativo generado por Capacitor (Gradle)
├── assets/             # Ícono y splash fuente (generados desde ../logo.png)
├── keystore/           # NUNCA en git -- ver arriba
├── www/                # Placeholder vacío (Capacitor exige un webDir; no se usa)
├── build-icon.js        # Regenera assets/icon.png y assets/splash*.png
├── capacitor.config.json
└── package.json
```

Para regenerar ícono/splash si cambia el logo:
```bash
node build-icon.js
npx capacitor-assets generate --android
```
