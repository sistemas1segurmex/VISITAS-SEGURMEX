<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$token = trim($_GET['token'] ?? '');
$db = getDB();

$invitacion = null;
$error = null;
if ($token === '') {
    $error = 'Falta el link de invitación.';
} else {
    $stmt = $db->prepare('SELECT id, email, expira_en, usado_en FROM invitaciones_vendedor WHERE token = ?');
    $stmt->execute([$token]);
    $invitacion = $stmt->fetch();
    if (!$invitacion) {
        $error = 'Este link de invitación no existe.';
    } elseif ($invitacion['usado_en']) {
        $error = 'Este link ya se usó para registrar una cuenta.';
    } elseif (strtotime($invitacion['expira_en']) < time()) {
        $error = 'Este link ya venció. Pide uno nuevo.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registro de vendedor — Segurmex</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
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
  .d-none { display: none !important; }
  body.vlg {
    margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 24px; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, "Segoe UI", sans-serif;
    color: var(--vlg-ink);
    background:
      radial-gradient(60vw 45vh at 88% -8%, rgba(255, 210, 63, .28), transparent 60%),
      radial-gradient(55vw 45vh at -10% 108%, rgba(79, 70, 229, .20), transparent 60%),
      var(--vlg-bg);
  }
  .vlg-card {
    position: relative; z-index: 2; width: 100%; max-width: 430px;
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(20px) saturate(160%); -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255,255,255,.6); border-radius: 28px;
    padding: 36px 32px 28px;
    box-shadow: 0 30px 70px -24px rgba(20, 23, 31, .28), 0 0 0 1px rgba(255,255,255,.4) inset;
  }
  .vlg-card::before {
    content: ''; position: absolute; inset: -1.5px; border-radius: 30px; padding: 1.5px;
    background: conic-gradient(from var(--vlg-angle, 0deg), var(--vlg-brand-1), var(--vlg-indigo), var(--vlg-brand-2), var(--vlg-brand-1));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude; opacity: .55; z-index: -1;
    animation: vlg-spin-border 6s linear infinite;
  }
  @property --vlg-angle { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
  @keyframes vlg-spin-border { to { --vlg-angle: 360deg; } }
  .vlg-logo-wrap { display: flex; justify-content: center; margin-bottom: 14px; }
  .vlg-logo-wrap img { height: 42px; width: auto; }
  .vlg-card h1 {
    margin: 0 0 4px; text-align: center; font-size: 1.25rem; font-weight: 800;
    background: linear-gradient(100deg, var(--vlg-ink) 30%, var(--vlg-brand-2) 50%, var(--vlg-ink) 70%);
    background-size: 220% auto; -webkit-background-clip: text; background-clip: text; color: transparent;
    animation: vlg-shine 5s linear infinite;
  }
  @keyframes vlg-shine { to { background-position: -220% center; } }
  .vlg-sub { text-align: center; margin: 0 0 22px; font-size: .78rem; font-weight: 600; color: var(--vlg-ink-soft); }
  .vlg-field { margin-bottom: 16px; }
  .vlg-field label { display: block; font-size: .74rem; font-weight: 700; color: var(--vlg-ink-soft); margin-bottom: 5px; }
  .vlg-field input {
    width: 100%; background: #fff; border: 1.5px solid var(--vlg-border); color: var(--vlg-ink);
    font-family: inherit; font-size: .92rem; border-radius: 12px; padding: 11px 13px;
  }
  .vlg-field input:disabled { background: #F3F1EC; color: var(--vlg-ink-soft); }
  .vlg-foto-wrap { display: flex; flex-direction: column; align-items: center; margin-bottom: 18px; }
  .vlg-foto-circle {
    width: 78px; height: 78px; border-radius: 50%; background: #fff;
    border: 2px dashed var(--vlg-border); display: flex; align-items: center; justify-content: center;
    color: var(--vlg-ink-soft); font-size: 1.6rem; margin-bottom: 8px; overflow: hidden; cursor: pointer;
  }
  .vlg-foto-circle img { width: 100%; height: 100%; object-fit: cover; }
  .vlg-foto-wrap label.vlg-foto-btn { font-size: .74rem; font-weight: 700; color: var(--vlg-brand-2); cursor: pointer; }
  .vlg-btn {
    width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
    background: var(--vlg-grad); color: #fff; font-family: inherit; font-weight: 800; font-size: .92rem;
    border: none; border-radius: 14px; padding: 14px 18px; margin-top: 4px; cursor: pointer;
    box-shadow: 0 14px 30px -10px rgba(232, 164, 0, .5);
  }
  .vlg-btn:disabled { opacity: .7; cursor: default; }
  .vlg-error, .vlg-msg-ok {
    display: flex; align-items: center; gap: 10px; font-size: .85rem; font-weight: 600;
    border-radius: 14px; padding: 12px 14px; margin-bottom: 16px;
  }
  .vlg-error { background: rgba(225, 29, 72, .08); border: 1px solid rgba(225, 29, 72, .25); color: #b91c3c; }
  .vlg-msg-ok { background: rgba(22, 163, 74, .08); border: 1px solid rgba(22, 163, 74, .25); color: #15803d; }
  .vlg-estado-pantalla { text-align: center; padding: 20px 6px; }
  .vlg-estado-pantalla i { font-size: 2.4rem; color: var(--vlg-brand-2); }
</style>
</head>
<body class="vlg">
  <div class="vlg-card">
    <div class="vlg-logo-wrap"><img src="logo.png" alt="Segurmex"></div>
    <?php if ($error): ?>
      <h1>Registro de vendedor</h1>
      <div class="vlg-estado-pantalla">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <p style="margin-top:10px;font-weight:700"><?= htmlspecialchars($error) ?></p>
        <p style="font-size:.82rem;color:var(--vlg-ink-soft)">Pide a Dirección que te mande un link nuevo.</p>
      </div>
    <?php else: ?>
      <h1>Regístrate como vendedor</h1>
      <p class="vlg-sub">Invitación de Segurmex · válida hasta <?= date('d/m/Y H:i', strtotime($invitacion['expira_en'])) ?></p>

      <div id="pantalla-exito" class="d-none vlg-estado-pantalla">
        <i class="bi bi-check-circle-fill" style="color:#16A34A"></i>
        <p style="margin-top:10px;font-weight:700">Tu registro fue enviado</p>
        <p style="font-size:.85rem;color:var(--vlg-ink-soft)">Un administrador va a revisarlo. Te avisarán cuando puedas iniciar sesión.</p>
      </div>

      <form id="form-registro" enctype="multipart/form-data">
        <div id="msg-registro"></div>
        <div class="vlg-foto-wrap">
          <label for="foto" class="vlg-foto-circle" id="foto-circle"><i class="bi bi-person"></i></label>
          <input type="file" id="foto" name="foto" accept="image/*" class="d-none" required>
          <label for="foto" class="vlg-foto-btn">Subir foto de perfil</label>
        </div>
        <div class="vlg-field">
          <label>Nombre completo</label>
          <input type="text" name="nombre" required>
        </div>
        <div class="vlg-field">
          <label>Correo electrónico</label>
          <input type="email" value="<?= htmlspecialchars($invitacion['email']) ?>" disabled>
        </div>
        <div class="vlg-field">
          <label>Teléfono</label>
          <input type="tel" name="telefono" id="telefono" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" placeholder="10 dígitos" required>
        </div>
        <div class="vlg-field">
          <label>Elige tu contraseña</label>
          <input type="password" name="password" minlength="6" required>
        </div>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <button type="submit" class="vlg-btn" id="btn-enviar">Enviar registro</button>
      </form>
    <?php endif; ?>
  </div>

<?php if (!$error): ?>
<script>
document.getElementById('telefono').addEventListener('input', (e) => {
  e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
});

document.getElementById('foto').addEventListener('change', (e) => {
  const archivo = e.target.files[0];
  if (!archivo) return;
  const lector = new FileReader();
  lector.onload = (ev) => {
    document.getElementById('foto-circle').innerHTML = `<img src="${ev.target.result}" alt="">`;
  };
  lector.readAsDataURL(archivo);
});

document.getElementById('form-registro').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-registro');
  const btn = document.getElementById('btn-enviar');
  msg.innerHTML = '';
  btn.disabled = true;
  btn.textContent = 'Enviando...';
  try {
    const fd = new FormData(e.target);
    const res = await fetch('api/registro_vendedor.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      e.target.classList.add('d-none');
      document.getElementById('pantalla-exito').classList.remove('d-none');
    } else {
      msg.innerHTML = `<div class="vlg-error"><i class="bi bi-exclamation-triangle-fill"></i><span>${data.error}</span></div>`;
      btn.disabled = false;
      btn.textContent = 'Enviar registro';
    }
  } catch (err) {
    msg.innerHTML = '<div class="vlg-error"><i class="bi bi-exclamation-triangle-fill"></i><span>No se pudo enviar. Intenta de nuevo.</span></div>';
    btn.disabled = false;
    btn.textContent = 'Enviar registro';
  }
});
</script>
<?php endif; ?>
</body>
</html>
