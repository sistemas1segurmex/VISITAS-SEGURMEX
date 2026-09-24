<?php
require_once __DIR__ . '/helpers.php';

/**
 * Sesion propia de VISITAS (24-sep-2026): cookie VISITASSID y archivos de
 * sesion en una subcarpeta "visitas" de la ruta de sesiones de PHP, en vez
 * de compartir PHPSESSID y carpeta con el ERP. Compartiendo, el recolector
 * de basura de PHP que corre en los requests del ERP (gc_maxlifetime de 35
 * min, ver erp/includes/auth.php) borraba las sesiones de los vendedores
 * aunque aqui duraran mas, y el ERP pisaba la cookie con sus parametros.
 * La cookie dura SESION_DURACION_SEG y se renueva con el uso (abajo), para
 * que Android no saque al vendedor cada que cierra la app en segundo plano.
 * El puente con el ERP ahora lee la sesion del ERP aparte, ver
 * leerUsuarioERP().
 */
function iniciarSesionVisitas(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // session.save_path puede venir como "N;/ruta" o "N;MODO;/ruta".
    $base = (string)ini_get('session.save_path');
    $pos  = strrpos($base, ';');
    $base = ($pos === false ? $base : substr($base, $pos + 1)) ?: sys_get_temp_dir();
    define('SESION_RUTA_ERP', $base);
    $propia = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'visitas';
    if (is_dir($propia) || @mkdir($propia, 0770, true)) {
        session_save_path($propia);
    } else {
        error_log('[VISITAS] No se pudo crear la carpeta de sesiones ' . $propia . '; se usa la compartida con el ERP.');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.gc_maxlifetime', (string)SESION_DURACION_SEG);
    ini_set('session.use_strict_mode', '1');
    session_name('VISITASSID');
    session_set_cookie_params([
        'lifetime' => SESION_DURACION_SEG,
        'path'     => '/visitas/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // PHP solo manda la cookie al crear la sesion, asi que su vencimiento
    // se renueva a mano (a lo mas 1 vez por hora) para que cuente desde el
    // ultimo uso y no desde el login.
    if (!empty($_SESSION['usuario_id']) && !headers_sent()
        && time() - ($_SESSION['_cookie_renovada_en'] ?? 0) > 3600) {
        $p = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + SESION_DURACION_SEG,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
        $_SESSION['_cookie_renovada_en'] = time();
    }
}
iniciarSesionVisitas();

/**
 * Lee $_SESSION['erp_user'] de la sesion del ERP (cookie PHPSESSID, carpeta
 * de sesiones por default) sin soltar la sesion propia de VISITAS: cierra
 * la propia, abre la del ERP solo para leer, y vuelve a abrir la propia.
 * Con session.use_cookies apagado mientras tanto para no mandarle al
 * navegador cookies de mas.
 */
function leerUsuarioERP(): ?array {
    $sidErp = $_COOKIE['PHPSESSID'] ?? '';
    if (!preg_match('/^[A-Za-z0-9,-]{22,256}$/', $sidErp) || !defined('SESION_RUTA_ERP')) return null;

    $propia = ['id' => session_id(), 'name' => session_name(), 'path' => session_save_path()];
    session_write_close();
    ini_set('session.use_cookies', '0');

    session_name('PHPSESSID');
    session_save_path(SESION_RUTA_ERP);
    session_id($sidErp);
    session_start(['read_and_close' => true]);
    $erp = $_SESSION['erp_user'] ?? null;

    session_name($propia['name']);
    session_save_path($propia['path']);
    session_id($propia['id']);
    session_start();
    ini_set('session.use_cookies', '1');

    return is_array($erp) ? $erp : null;
}

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
 * arriba): el ERP y VISITAS corren en el mismo servidor/dominio, asi que el
 * navegador tambien manda aqui la cookie PHPSESSID del ERP (path "/") y
 * leerUsuarioERP() saca de esa sesion $_SESSION['erp_user'] (armado por el
 * login del ERP). Si esa cuenta es de Direccion/CEO o Sistemas Y la
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
    if (!peticionVieneDelERP()) return; // pestaña nueva por su cuenta: que pida login
    $erp = leerUsuarioERP();
    if (!$erp || empty($erp['email'])) return;
    $rolErp = strtolower($erp['rol'] ?? '');
    $esDireccionOSistemas = in_array($rolErp, ['ceo', 'direccion', 'dirección', 'sistemas'], true);
    if (!$esDireccionOSistemas) return; // solo Direccion/CEO y Sistemas usan este puente

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

    session_regenerate_id(true);
    $_SESSION['usuario_id']     = $u['id'];
    $_SESSION['usuario_nombre'] = $u['nombre'];
    $_SESSION['usuario_rol']    = $u['rol'];
    // Sin su fila en usuarios_sesiones, tocarSesionActual() lo sacaria.
    registrarSesion($db, (int)$u['id'], session_id());
}

/** Olvida al usuario de esta sesion (sin tocar la BD). */
function olvidarUsuarioEnSesion(): void {
    unset($_SESSION['usuario_id'], $_SESSION['usuario_nombre'], $_SESSION['usuario_rol'],
          $_SESSION['_sesion_tocada_en'], $_SESSION['_cookie_renovada_en']);
}

function currentUser(): ?array {
    bridgeDesdeERP();
    if (!isset($_SESSION['usuario_id'])) return null;
    if (!tocarSesionActual(getDB())) {
        // Sesion cerrada desde otro lado o usuario desactivado.
        olvidarUsuarioEnSesion();
        return null;
    }
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
