<?php
// Paradas de prospección libre: cada vez que el vendedor no tiene cita
// agendada pero visita a alguien por su cuenta, registra una parada con
// persona/empresa, dirección, foto+GPS de entrada -- y la cierra después
// con foto+GPS de salida + nivel de interés. Mismo rigor que api/checkin.php
// para citas, pero sin cita previa. Un vendedor puede tener varias paradas
// en un mismo día, aunque solo UNA abierta (sin hora_fin) a la vez.
//
// GET  -> paradas de hoy del vendedor logueado (para Inicio y esta pantalla).
// POST -> action=iniciar (nueva parada, con foto+GPS de entrada) o
//         action=finalizar (cierra la parada abierta, con foto+GPS de
//         salida + interés).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

$INTERES_VALIDOS = ['bajo', 'medio', 'interesado', 'muy_interesado'];
$TIPOS_VALIDOS   = ['persona', 'organizacion'];

// Guarda una foto (archivo o base64, mismo criterio que api/checkin.php) en
// uploads/prospecciones/ y regresa la ruta relativa, o null si no vino nada.
function guardarFotoProspeccion(string $prefijo): ?string {
    $destinoDir = __DIR__ . '/../uploads/prospecciones';
    if (!is_dir($destinoDir)) {
        @mkdir($destinoDir, 0777, true);
    }

    if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['foto']['tmp_name'];
        $info = @getimagesize($tmp);
        if ($info === false) {
            jsonResponse(['ok' => false, 'error' => 'El archivo no es una imagen válida'], 400);
        }
        $ext     = image_type_to_extension($info[2], false) ?: 'jpg';
        $nombre  = $prefijo . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $destinoDir . '/' . $nombre;

        $guardado = @move_uploaded_file($tmp, $destino) || @copy($tmp, $destino);
        if (!$guardado && is_readable($tmp)) {
            $contenido = @file_get_contents($tmp);
            if ($contenido !== false) $guardado = (@file_put_contents($destino, $contenido) !== false);
        }
        if (!$guardado) {
            jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor'], 500);
        }
        return 'uploads/prospecciones/' . $nombre;
    }

    if (!empty($_POST['foto_base64'])) {
        $base64Data = $_POST['foto_base64'];
        $ext = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $tipoMime = strtolower($type[1]);
            if (in_array($tipoMime, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $ext = $tipoMime === 'jpeg' ? 'jpg' : $tipoMime;
            }
        }
        $decoded = base64_decode($base64Data);
        if ($decoded === false) {
            jsonResponse(['ok' => false, 'error' => 'Error al decodificar la imagen'], 400);
        }
        $nombre  = $prefijo . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $destinoDir . '/' . $nombre;
        if (@file_put_contents($destino, $decoded) === false) {
            jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor'], 500);
        }
        return 'uploads/prospecciones/' . $nombre;
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare(
        'SELECT * FROM prospecciones WHERE vendedor_id = ? AND fecha = CURRENT_DATE ORDER BY hora_inicio ASC'
    );
    $stmt->execute([$u['id']]);
    jsonResponse(['ok' => true, 'paradas' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    if ($accion === 'iniciar') {
        $tipo      = ($_POST['tipo'] ?? '') === 'persona' ? 'persona' : 'organizacion';
        $nombre    = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $lat       = $_POST['lat'] ?? null;
        $lng       = $_POST['lng'] ?? null;

        if ($nombre === '') {
            jsonResponse(['ok' => false, 'error' => 'Escribe el nombre de la persona o empresa'], 400);
        }
        if ($lat === null || $lat === '' || $lng === null || $lng === '') {
            jsonResponse(['ok' => false, 'error' => 'Espera a que se obtenga tu ubicación GPS'], 400);
        }

        // No se deja abrir una parada nueva si ya hay una sin cerrar --
        // primero hay que registrar la salida de la actual.
        $stmt = $db->prepare(
            'SELECT id FROM prospecciones WHERE vendedor_id = ? AND fecha = CURRENT_DATE AND hora_fin IS NULL'
        );
        $stmt->execute([$u['id']]);
        if ($stmt->fetch()) {
            jsonResponse(['ok' => false, 'error' => 'Ya tienes una parada abierta. Registra su salida antes de iniciar otra.'], 400);
        }

        $fotoPath = guardarFotoProspeccion('entrada');
        if (!$fotoPath) {
            jsonResponse(['ok' => false, 'error' => 'Toma la foto de entrada'], 400);
        }

        $stmt = $db->prepare(
            'INSERT INTO prospecciones (vendedor_id, fecha, hora_inicio, tipo, nombre, direccion, lat_entrada, lng_entrada, foto_entrada_path)
             VALUES (?, CURRENT_DATE, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$u['id'], $tipo, $nombre, $direccion ?: null, $lat, $lng, $fotoPath]);
        $nuevaId = (int)$db->lastInsertId();

        registrarCambio($db, $u['id'], 'prospeccion', $nuevaId, 'alta', "Inició una parada de prospección con {$nombre}");

        $stmt = $db->prepare('SELECT * FROM prospecciones WHERE id = ?');
        $stmt->execute([$nuevaId]);
        jsonResponse(['ok' => true, 'parada' => $stmt->fetch()]);
    }

    if ($accion === 'finalizar') {
        $paradaId = (int)($_POST['parada_id'] ?? 0);
        $lat      = $_POST['lat'] ?? null;
        $lng      = $_POST['lng'] ?? null;
        $interes  = trim($_POST['interes'] ?? '');

        if (!$paradaId) {
            jsonResponse(['ok' => false, 'error' => 'Parada no válida'], 400);
        }
        if (!in_array($interes, $INTERES_VALIDOS, true)) {
            jsonResponse(['ok' => false, 'error' => 'Elige qué tan interesado se mostró'], 400);
        }
        if ($lat === null || $lat === '' || $lng === null || $lng === '') {
            jsonResponse(['ok' => false, 'error' => 'Espera a que se obtenga tu ubicación GPS'], 400);
        }

        $stmt = $db->prepare(
            'SELECT id, nombre FROM prospecciones WHERE id = ? AND vendedor_id = ? AND hora_fin IS NULL'
        );
        $stmt->execute([$paradaId, $u['id']]);
        $parada = $stmt->fetch();
        if (!$parada) {
            jsonResponse(['ok' => false, 'error' => 'Esa parada ya no está abierta'], 404);
        }

        $fotoPath = guardarFotoProspeccion('salida');
        if (!$fotoPath) {
            jsonResponse(['ok' => false, 'error' => 'Toma la foto de salida'], 400);
        }

        $db->prepare(
            'UPDATE prospecciones SET hora_fin = CURRENT_TIMESTAMP, lat_salida = ?, lng_salida = ?, foto_salida_path = ?, interes = ?
             WHERE id = ?'
        )->execute([$lat, $lng, $fotoPath, $interes, $paradaId]);

        registrarCambio($db, $u['id'], 'prospeccion', $paradaId, 'edicion', "Cerró la parada de prospección con {$parada['nombre']}", [
            'Interés' => [null, $interes],
        ]);

        jsonResponse(['ok' => true]);
    }

    jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
