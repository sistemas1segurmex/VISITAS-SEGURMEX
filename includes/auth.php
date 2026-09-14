<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/helpers.php';

/**
 * ¿Esta peticion viene realmente del ERP (el iframe de erp/visitas/index.php)
 * y no de una pestaña nueva abierta a mano/por marcador? Se detecta con el
 * header Referer: el iframe hace que el navegador mande como referer la
 * propia pagina del ERP (mismo origen); al escribir la URL, usar un
 * marcador o "duplicar pestaña" no se manda ningun referer. No es a prueba
 * de falsificacion (un referer se puede fabricar fuera del navegador), pero
 * aqui solo se usa para decidir una comodidad de UX (saltarse el login), no
 * como control de seguridad -- la cuenta admin que crea el puente sigue
 * necesitando que el rol en el ERP sea el correcto.
 */
function peticionVieneDelERP(): bool {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '') return false;
    $ref = parse_url($referer);
    if (empty($ref['host'])) return false;
    $hostActual = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    if (strcasecmp($ref['host'], $hostActual) !== 0) return false;
    return strpos($ref['path'] ?? '', '/erp/') === 0;
}

/**
 * Single sign-on con el ERP (21-ago-2026, ajustado despues para que solo
 * Direccion/CEO y Sistemas entren por aqui, y el 14-sep-2026 para que solo
 * aplique si la peticion viene del iframe del ERP -- ver peticionVieneDelERP
 * arriba): el ERP y VISITAS corren en el mismo servidor/dominio y comparten
 * la MISMA cookie de sesion de PHP (el ERP fija cookie path "/" en
 * includes/auth.php; VISITAS usa el default, que tambien es "/"), asi que
 * $_SESSION['erp_user'] (armado por el login del ERP) ya esta disponible
 * aqui sin nada especial. Si esa cuenta es de Direccion/CEO o Sistemas Y la
 * peticion viene del iframe del ERP, se "traduce" a una cuenta admin de
 * este sistema por email, sin volver a pedir contrasena -- para que
 * Direccion vea el panel de administrador de VISITAS directo desde el
 * link del ERP. Si en cambio abren VISITAS en una pestaña nueva por su
 * cuenta (URL escrita, marcador), se les pide su login propio como a
 * cualquiera, aunque tengan sesion abierta en el ERP en otra pestaña.
 * Los vendedores en campo NO pasan por aqui: ellos entran directo a la URL
 * de VISITAS con su login propio de siempre (login.php).
 * No pisa una sesion ya iniciada directamente aqui (login.php propio).
 */
function bridgeDesdeERP(): void {
    if (!empty($_SESSION['usuario_id'])) return; // ya hay sesion propia de VISITAS
    $erp = $_SESSION['erp_user'] ?? null;
    if (!$erp || empty($erp['email'])) return;
    $rolErp = strtolower($erp['rol'] ?? '');
    $esDireccionOSistemas = in_array($rolErp, ['ceo', 'direccion', 'dirección', 'sistemas'], true);
    if (!$esDireccionOSistemas) return; // solo Direccion/CEO y Sistemas usan este puente
    if (!peticionVieneDelERP()) return; // pestaña nueva por su cuenta: que pida login

    $db = getDB();
    $stmt = $db->prepare('SELECT id, nombre, rol, activo FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$erp['email']]);
    $u = $stmt->fetch();

    if (!$u) {
        // Primera vez que Direccion/Sistemas entra a VISITAS: se crea su
        // cuenta aqui como admin. password_hash aleatorio e inservible --
        // esta cuenta solo puede entrar por este puente, nunca con el login
        // propio de VISITAS usando ese correo (nadie conoce esa contrasena).
        $nombre = trim(($erp['nombre'] ?? '') . ' ' . ($erp['apellidos'] ?? '')) ?: ($erp['usuario'] ?? $erp['email']);
        $hash   = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $ins = $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES (?,?,?,'admin',1) RETURNING id, nombre, rol, activo");
        $ins->execute([$nombre, $erp['email'], $hash]);
        $u = $ins->fetch();
    }
    if (!$u['activo']) return; // desactivado en VISITAS: no lo dejamos pasar aunque el ERP lo permita

    $_SESSION['usuario_id']     = $u['id'];
    $_SESSION['usuario_nombre'] = $u['nombre'];
    $_SESSION['usuario_rol']    = $u['rol'];
}

function currentUser(): ?array {
    bridgeDesdeERP();
    if (!isset($_SESSION['usuario_id'])) return null;
    tocarSesionActual(getDB());
    return [
        'id'     => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'rol'    => $_SESSION['usuario_rol'] ?? '',
    ];
}

function esPeticionApi(): bool {
    return strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
}

function requireLogin(): array {
    $u = currentUser();
    if (!$u) {
        if (esPeticionApi()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            die(json_encode(['ok' => false, 'error' => 'No autenticado']));
        }
        header('Location: /visitas/login.php');
        exit;
    }
    return $u;
}

function requireRole(string $rol): array {
    $u = requireLogin();
    if ($u['rol'] !== $rol) {
        if (esPeticionApi()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            die(json_encode(['ok' => false, 'error' => 'Sin permiso para esta acción']));
        }
        http_response_code(403);
        die('No tienes permiso para ver esta página.');
    }
    return $u;
}
