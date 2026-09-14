<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (currentUser()) {
    $u = currentUser();
    header('Location: ' . ($u['rol'] === 'admin' ? 'admin/index.php' : 'vendedor/index.php'));
    exit;
}

$error = null;
$puedeForzar = false;
$emailIngresado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $forzar = !empty($_POST['forzar_login']);
    $emailIngresado = $email;

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? AND activo = 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);

        if ($forzar) {
            // El usuario confirmó explícitamente cerrar las sesiones anteriores
            cerrarTodasLasSesionesDelUsuario($db, (int)$u['id']);
        }

        $activas = contarSesionesActivas($db, (int)$u['id']);
        if ($activas >= SESION_MAX_ACTIVAS) {
            $error = 'Ya tienes ' . SESION_MAX_ACTIVAS . ' sesiones activas en otros dispositivos o navegadores.';
            $puedeForzar = true;
            registrarAcceso($db, (int)$u['id'], $email, 'bloqueado_limite', $error);
        } else {
            $_SESSION['usuario_id']     = $u['id'];
            $_SESSION['usuario_nombre'] = $u['nombre'];
            $_SESSION['usuario_rol']    = $u['rol'];
            registrarSesion($db, (int)$u['id'], session_id());
            registrarAcceso($db, (int)$u['id'], $email, 'correcto');
            header('Location: ' . ($u['rol'] === 'admin' ? 'admin/index.php' : 'vendedor/index.php'));
            exit;
        }
    } else {
        $error = 'Correo o contraseña incorrectos.';
        registrarAcceso($db, $u ? (int)$u['id'] : null, $email, 'fallido', $u ? 'Contraseña incorrecta' : 'Correo no encontrado o inactivo');
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Control de Visitas — Iniciar sesión</title>
<link rel="icon" type="image/png" href="assets/img/Favv-Segu.png">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/css/style.css">
<style>
  :root {
    --vlg-brand-1: #FFD23F;
    --vlg-brand-2: #E8A400;
    --vlg-indigo: #4F46E5;
    --vlg-grad: linear-gradient(135deg, var(--vlg-brand-1), var(--vlg-brand-2));
    --vlg-ink: #14171F;
    --vlg-ink-soft: #6B7280;
    --vlg-border: rgba(20, 23, 31, .1);
    --vlg-bg: #FBF9F6;
  }
  * { box-sizing: border-box; }
  html, body { height: 100%; }
  .visually-hidden {
    position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
  }
  body.vlg {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, "Segoe UI", sans-serif;
    color: var(--vlg-ink);
    background:
      radial-gradient(60vw 45vh at 88% -8%, rgba(255, 210, 63, .28), transparent 60%),
      radial-gradient(55vw 45vh at -10% 108%, rgba(79, 70, 229, .20), transparent 60%),
      radial-gradient(40vw 30vh at 50% 40%, rgba(232, 164, 0, .10), transparent 65%),
      var(--vlg-bg);
    overflow: hidden;
    position: relative;
  }

  .vlg-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    pointer-events: none;
    z-index: 0;
  }
  .vlg-orb-1 { width: 420px; height: 420px; background: rgba(255, 210, 63,.35); top: -140px; left: -110px; }
  .vlg-orb-2 { width: 380px; height: 380px; background: rgba(79,70,229,.25); bottom: -150px; right: -110px; }
  .vlg-orb-3 { width: 260px; height: 260px; background: rgba(232, 164, 0,.22); bottom: 12%; left: 6%; }

  .vlg-card {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 410px;
    background: rgba(255, 255, 255, .94);
    border: 1px solid rgba(255,255,255,.6);
    border-radius: 28px;
    padding: 40px 34px 30px;
    box-shadow: 0 30px 70px -24px rgba(20, 23, 31, .28), 0 0 0 1px rgba(255,255,255,.4) inset;
    opacity: 0;
    animation: vlg-rise .7s cubic-bezier(.22,1,.36,1) .05s both;
  }
  /* Borde tipo "aurora" alrededor de la tarjeta -- ya no gira: el
     backdrop-filter y las animaciones continuas (este borde, el brillo del
     título, el destello del botón, el vaivén del logo) eran lo que hacía
     que el login tardara en reaccionar al escribir, así que se dejan
     estáticos en TODAS las pantallas, no solo en móvil. */
  .vlg-card::before {
    content: '';
    position: absolute;
    inset: -1.5px;
    border-radius: 30px;
    padding: 1.5px;
    background: conic-gradient(from 0deg, var(--vlg-brand-1), var(--vlg-indigo), var(--vlg-brand-2), var(--vlg-brand-1));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: .55;
    z-index: -1;
  }

  @keyframes vlg-rise {
    from { opacity: 0; transform: translateY(22px) scale(.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  .vlg-logo-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 18px;
  }
  .vlg-logo-wrap img {
    height: 46px;
    width: auto;
    display: block;
  }

  .vlg-card h1 {
    margin: 0 0 4px;
    text-align: center;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: -.01em;
    background: linear-gradient(100deg, var(--vlg-ink) 30%, var(--vlg-brand-2) 50%, var(--vlg-ink) 70%);
    background-size: 220% auto;
    background-position: 0% center;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }
  .vlg-sub {
    text-align: center;
    margin: 0 0 26px;
    font-size: .8rem;
    font-weight: 700;
    color: var(--vlg-ink-soft);
    text-transform: uppercase;
    letter-spacing: .07em;
  }

  .vlg-error {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(225, 29, 72, .08);
    border: 1px solid rgba(225, 29, 72, .25);
    color: #b91c3c;
    font-size: .85rem;
    font-weight: 600;
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 18px;
    animation: vlg-shake .45s cubic-bezier(.22,1,.36,1);
  }
  .vlg-error i { font-size: 1.1rem; flex: none; }
  @keyframes vlg-shake {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-6px); }
    40% { transform: translateX(5px); }
    60% { transform: translateX(-4px); }
    80% { transform: translateX(3px); }
  }

  .vlg-field {
    position: relative;
    margin-bottom: 20px;
    opacity: 0;
    animation: vlg-rise .55s cubic-bezier(.22,1,.36,1) both;
  }
  .vlg-field:nth-of-type(1) { animation-delay: .18s; }
  .vlg-field:nth-of-type(2) { animation-delay: .26s; }

  .vlg-field > i.vlg-icon-left {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--vlg-ink-soft);
    font-size: 1.05rem;
    pointer-events: none;
    transition: color .18s;
  }
  .vlg-field input {
    width: 100%;
    background: #fff;
    border: 1.5px solid var(--vlg-border);
    color: var(--vlg-ink);
    font-family: inherit;
    font-size: .95rem;
    border-radius: 14px;
    padding: 20px 16px 8px 46px;
    transition: border-color .18s, box-shadow .18s;
  }
  .vlg-field input[type="password"] { padding-right: 44px; }
  .vlg-field input::placeholder { color: transparent; }
  .vlg-field input:focus {
    outline: none;
    border-color: var(--vlg-brand-1);
    box-shadow: 0 0 0 4px rgba(255, 210, 63, .16);
  }
  .vlg-field input:focus + label,
  .vlg-field input:not(:placeholder-shown) + label {
    top: 7px;
    font-size: .66rem;
    font-weight: 700;
    letter-spacing: .02em;
    color: var(--vlg-brand-2);
  }
  .vlg-field.is-focused > i.vlg-icon-left { color: var(--vlg-brand-2); }
  .vlg-field label {
    position: absolute;
    left: 46px;
    top: 50%;
    transform: translateY(-50%);
    font-size: .92rem;
    color: var(--vlg-ink-soft);
    pointer-events: none;
    transition: top .16s cubic-bezier(.22,1,.36,1), font-size .16s, color .16s;
  }

  .vlg-eye {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--vlg-ink-soft);
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: color .15s, background .15s;
  }
  .vlg-eye:hover { color: var(--vlg-brand-2); background: rgba(255, 210, 63,.1); }

  .vlg-btn {
    position: relative;
    overflow: hidden;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--vlg-grad);
    color: #fff;
    font-family: inherit;
    font-weight: 800;
    font-size: .95rem;
    border: none;
    border-radius: 14px;
    padding: 15px 18px;
    margin-top: 6px;
    cursor: pointer;
    box-shadow: 0 14px 30px -10px rgba(232, 164, 0, .5);
    transition: transform .15s cubic-bezier(.22,1,.36,1), box-shadow .15s;
    opacity: 0;
    animation: vlg-rise .55s cubic-bezier(.22,1,.36,1) .34s both;
  }
  .vlg-btn:hover { transform: translateY(-2px); box-shadow: 0 18px 36px -10px rgba(232, 164, 0, .6); }
  .vlg-btn:active { transform: scale(.97); }
  .vlg-btn:disabled { opacity: .8 !important; cursor: default; transform: none; }
  .vlg-btn .spinner {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,.4); border-top-color: #fff;
    animation: vlg-spin .7s linear infinite;
    display: none;
  }
  .vlg-btn.is-loading .spinner { display: inline-block; }
  .vlg-btn.is-loading .vlg-btn-text { opacity: .85; }
  .vlg-btn.is-loading i.bi-arrow-right { display: none; }
  @keyframes vlg-spin { to { transform: rotate(360deg); } }

  .vlg-trust {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    font-size: .74rem;
    font-weight: 600;
    color: var(--vlg-ink-soft);
    opacity: 0;
    animation: vlg-rise .55s cubic-bezier(.22,1,.36,1) .42s both;
  }
  .vlg-trust i { color: var(--vlg-brand-2); }
  .vlg-foot {
    text-align: center;
    margin: 10px 0 0;
    font-size: .72rem;
    color: rgba(20,23,31,.35);
  }

  @media (prefers-reduced-motion: reduce) {
    .vlg-card, .vlg-field, .vlg-btn, .vlg-trust, .vlg-error { animation: none !important; opacity: 1 !important; }
  }
</style>
</head>
<body class="vlg">
  <div class="vlg-orb vlg-orb-1"></div>
  <div class="vlg-orb vlg-orb-2"></div>
  <div class="vlg-orb vlg-orb-3"></div>

  <div class="vlg-card" id="vlg-card">
    <div class="vlg-logo-wrap">
      <img src="logo.png" alt="Segurmex">
    </div>
    <h1>Control de Visitas</h1>
    <p class="vlg-sub">Acceso al sistema · 2026</p>

    <?php if ($error): ?>
      <div class="vlg-error">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div style="flex:1;">
          <div><?= htmlspecialchars($error) ?></div>
          <?php if ($puedeForzar): ?>
            <div style="font-size:.72rem;margin-top:6px;opacity:.85;">Por seguridad no guardamos tu contraseña -- vuelve a escribirla y presiona el botón.</div>
            <button type="submit" form="vlg-form" name="forzar_login" value="1" class="v26-btn v26-btn-ghost mt-2" style="font-size:.76rem;font-weight:700;padding:6px 12px;border-radius:10px;background:#fff;border:1px solid #b91c3c;color:#b91c3c;cursor:pointer;display:inline-flex;align-items:center;gap:6px;width:100%;justify-content:center;">
              <i class="bi bi-box-arrow-in-right"></i> Cerrar otras sesiones y entrar aquí
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" id="vlg-form">
      <div class="vlg-field">
        <i class="bi bi-envelope-fill vlg-icon-left"></i>
        <input type="email" id="vlg-email" name="email" value="<?= htmlspecialchars($emailIngresado) ?>" placeholder=" " required autofocus>
        <label for="vlg-email">Correo electrónico</label>
      </div>
      <div class="vlg-field">
        <i class="bi bi-lock-fill vlg-icon-left"></i>
        <input type="password" id="vlg-pass" name="password" placeholder=" " required>
        <label for="vlg-pass">Contraseña</label>
        <button type="button" class="vlg-eye" id="vlg-toggle" aria-label="Mostrar contraseña" tabindex="-1">
          <i class="bi bi-eye"></i>
        </button>
      </div>
      <button type="submit" class="vlg-btn" id="vlg-submit">
        <span class="vlg-btn-text">Entrar</span>
        <i class="bi bi-arrow-right"></i>
        <span class="spinner"></span>
      </button>
    </form>

    <div class="vlg-trust"><i class="bi bi-shield-lock-fill"></i> Conexión protegida</div>
    <p class="vlg-foot">© <?= date('Y') ?> Segurmex — Sistema de Control de Visitas</p>
  </div>

<script>
  // Las partículas de fondo (tsParticles) se quitaron por completo -- pesaban
  // ~130 KB y hacían más lento el login en todas las pantallas, no solo en
  // móvil. GSAP se sigue cargando (solo en pantallas de escritorio, donde
  // aplican el efecto magnético del botón y el rebote del ojito de la
  // contraseña) sin descargarlo en móvil, donde esos efectos no aportan.
  if (window.matchMedia('(min-width: 769px)').matches) {
    const scriptGsap = document.createElement('script');
    scriptGsap.src = 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js';
    document.body.appendChild(scriptGsap);
  }

  document.querySelectorAll('.vlg-field input').forEach((inp) => {
    const field = inp.closest('.vlg-field');
    inp.addEventListener('focus', () => field.classList.add('is-focused'));
    inp.addEventListener('blur', () => field.classList.remove('is-focused'));
  });

  const vlgToggle = document.getElementById('vlg-toggle');
  const vlgPass = document.getElementById('vlg-pass');
  <?php if ($puedeForzar): ?>
  // El campo de contraseña nunca se rellena de vuelta (por seguridad), así
  // que si el usuario va a usar "Cerrar otras sesiones y entrar aquí" hay
  // que ponerle el cursor listo para que la vuelva a escribir.
  vlgPass.focus();
  <?php endif; ?>
  vlgToggle.addEventListener('click', () => {
    const showing = vlgPass.type === 'text';
    vlgPass.type = showing ? 'password' : 'text';
    vlgToggle.querySelector('i').className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
    if (window.gsap) gsap.fromTo(vlgToggle, { scale: .7 }, { scale: 1, duration: .3, ease: 'back.out(3)' });
  });

  document.getElementById('vlg-form').addEventListener('submit', (e) => {
    const btn = document.getElementById('vlg-submit');
    if (btn.classList.contains('is-loading')) { e.preventDefault(); return; }
    btn.classList.add('is-loading');
    btn.disabled = true;
    btn.querySelector('.vlg-btn-text').textContent = 'Entrando…';
  });

  // Efecto "magnético" sutil del botón principal al mover el mouse encima
  const vlgBtn = document.getElementById('vlg-submit');
  vlgBtn.addEventListener('mousemove', (e) => {
    if (!window.gsap) return;
    const r = vlgBtn.getBoundingClientRect();
    const x = (e.clientX - r.left - r.width / 2) * 0.15;
    const y = (e.clientY - r.top - r.height / 2) * 0.4;
    gsap.to(vlgBtn, { x, y, duration: .3, ease: 'power2.out' });
  });
  vlgBtn.addEventListener('mouseleave', () => {
    if (!window.gsap) return;
    gsap.to(vlgBtn, { x: 0, y: 0, duration: .4, ease: 'elastic.out(1, .4)' });
  });
</script>
</body>
</html>
