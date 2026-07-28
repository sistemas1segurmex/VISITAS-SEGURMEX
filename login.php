<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (currentUser()) {
    $u = currentUser();
    header('Location: ' . ($u['rol'] === 'admin' ? 'admin/index.php' : 'vendedor/index.php'));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $stmt = getDB()->prepare('SELECT * FROM usuarios WHERE email = ? AND activo = 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        $_SESSION['usuario_id']     = $u['id'];
        $_SESSION['usuario_nombre'] = $u['nombre'];
        $_SESSION['usuario_rol']    = $u['rol'];
        header('Location: ' . ($u['rol'] === 'admin' ? 'admin/index.php' : 'vendedor/index.php'));
        exit;
    }
    $error = 'Correo o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Control de Visitas — Iniciar sesión</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-bg d-flex align-items-center justify-content-center min-vh-100">
  <div class="card shadow-sm" style="max-width:380px;width:100%">
    <div class="card-body p-4">
      <h4 class="mb-1 text-center">Control de Visitas</h4>
      <p class="text-muted text-center small mb-4">Segurmex</p>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Correo</label>
          <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-brand w-100">Entrar</button>
      </form>
    </div>
  </div>
</body>
</html>
