<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

// Minutos que deben pasar desde la hora programada de la cita antes de que
// se pueda reportar "el cliente no llegó" (evita reportes prematuros).
define('ESPERA_NO_SHOW_MINUTOS', 10);

$citaId   = (int)($_POST['cita_id'] ?? 0);
$tipo     = $_POST['tipo'] ?? 'entrada';
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;
$accuracy = isset($_POST['accuracy']) && $_POST['accuracy'] !== '' ? (float)$_POST['accuracy'] : null;
$noShow   = ($_POST['no_show'] ?? '') === '1';
$motivo  = trim($_POST['motivo'] ?? '');
// Nivel de interés del cliente en ESTA visita -- obligatorio al registrar
// la salida (ver comentario junto al UPDATE de abajo).
$interes = trim($_POST['interes'] ?? '');
$INTERES_VALIDOS = ['bajo', 'medio', 'interesado', 'muy_interesado'];
if ($tipo === 'salida' && !$noShow && !in_array($interes, $INTERES_VALIDOS, true)) {
    jsonResponse(['ok' => false, 'error' => 'Elige qué tan interesado se mostró el cliente.'], 400);
}

if (!$citaId || !$lat || !$lng || !in_array($tipo, ['entrada', 'salida'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Datos incompletos (cita, GPS o tipo)'], 400);
}

if ($noShow && $motivo === '') {
    jsonResponse(['ok' => false, 'error' => 'Cuéntanos brevemente qué pasó (motivo obligatorio).'], 400);
}

$stmt = $db->prepare(
    'SELECT c.*, cl.lat AS cliente_lat, cl.lng AS cliente_lng, cl.nombre AS cliente_nombre,
            cl.ubicacion_confirmada
     FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.id = ? AND c.vendedor_id = ?'
);
$stmt->execute([$citaId, $u['id']]);
$cita = $stmt->fetch();
if (!$cita) {
    jsonResponse(['ok' => false, 'error' => 'Cita no encontrada'], 404);
}

// Reintento: con señal débil el servidor puede haber guardado el check-in
// sin que la respuesta llegara al celular. Si esta cita ya tiene uno de
// este tipo, se contesta como éxito en vez de duplicarlo (va antes del
// candado de estado: tras la salida la cita ya quedó "completada").
$stmt = $db->prepare('SELECT 1 FROM checkins WHERE cita_id = ? AND tipo = ? LIMIT 1');
$stmt->execute([$citaId, $tipo]);
if ($stmt->fetchColumn()) {
    jsonResponse(['ok' => true, 'ya_registrado' => true, 'estado' => $cita['estado']]);
}

if (in_array($cita['estado'], ['cancelada', 'no_realizada', 'completada'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Esta cita ya no admite check-in (estado: ' . $cita['estado'] . ').'], 400);
}

if ($noShow) {
    $minutosPasados = (time() - strtotime($cita['fecha_hora'])) / 60;
    if ($minutosPasados < ESPERA_NO_SHOW_MINUTOS) {
        $faltan = ceil(ESPERA_NO_SHOW_MINUTOS - $minutosPasados);
        jsonResponse(['ok' => false, 'error' => "Espera $faltan minuto(s) más desde la hora programada antes de reportar que el cliente no llegó."], 400);
    }
}

$distancia  = null;
$verificado = 0;
if ($cita['cliente_lat'] !== null && $cita['cliente_lng'] !== null) {
    // El propio checkin.php del vendedor ya reintenta el GPS hasta lograr
    // buena precisión antes de dejar enviar -- esto es el candado del
    // servidor por si aun así llega una lectura mala (app vieja, reintento
    // agotado). Se rechaza en vez de calcular "fuera de zona" con un GPS
    // que puede estar a kilómetros de error.
    if ($accuracy !== null && $accuracy > PRECISION_MINIMA_CHECKIN_METROS) {
        jsonResponse(['ok' => false, 'error' => 'Tu ubicación no es lo bastante precisa (±' . round($accuracy) . ' m). Sal a espacio abierto o espera unos segundos e intenta de nuevo.'], 400);
    }
    $distancia  = haversineDistance($lat, $lng, (float)$cita['cliente_lat'], (float)$cita['cliente_lng']);
    $verificado = $distancia <= RADIO_VERIFICACION_METROS ? 1 : 0;
}

// Pin del cliente sin confirmar (casi siempre se registró desde la oficina
// buscando la dirección) y la entrada cae fuera del radio con un GPS bueno:
// lo más probable es que el pin esté mal, no el vendedor. Antes de guardar
// nada se le pregunta si está en el lugar del cliente (corregir_ubicacion
// sin mandar => se contesta pregunta_ubicacion); '1' = sí, el pin pasa a
// donde está y la entrada queda verificada; '0' = no, "Fuera de zona" como
// siempre. Ver CORRECCION_* en includes/helpers.php.
$correccion = null;
if ($tipo === 'entrada' && !$noShow && $verificado === 0 && $distancia !== null
    && !$cita['ubicacion_confirmada']
    && $accuracy !== null && $accuracy <= CORRECCION_PRECISION_MAX_M
    && $distancia <= CORRECCION_REVISAR_MAX_M) {
    $respuesta = $_POST['corregir_ubicacion'] ?? null;
    if ($respuesta === null) {
        jsonResponse([
            'ok'                 => false,
            'pregunta_ubicacion' => true,
            'distancia_metros'   => round($distancia),
            'cliente_nombre'     => $cita['cliente_nombre'],
            'error'              => 'Tu ubicación no coincide con la guardada para el cliente. Vuelve a intentar.',
        ]);
    }
    if ($respuesta === '1') {
        $lejos = $distancia > CORRECCION_DIRECTA_MAX_M;
        $correccion = [
            'estado' => $lejos ? 'por_revisar' : 'aplicada',
            'nota'   => $lejos ? 'El pin anterior estaba a más de ' . (CORRECCION_DIRECTA_MAX_M / 1000) . ' km' : null,
        ];
        $verificado = 1;
    }
}

$fotoPath = null;
$destinoDir = __DIR__ . '/../uploads/checkins';
if (!is_dir($destinoDir)) {
    @mkdir($destinoDir, 0777, true);
}

if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['foto']['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        jsonResponse(['ok' => false, 'error' => 'El archivo no es una imagen válida'], 400);
    }
    $ext    = image_type_to_extension($info[2], false) ?: 'jpg';
    $nombre = 'checkin_' . $citaId . '_' . $tipo . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = $destinoDir . '/' . $nombre;

    $guardado = @move_uploaded_file($tmp, $destino);
    if (!$guardado) {
        $guardado = @copy($tmp, $destino);
    }
    if (!$guardado && is_readable($tmp)) {
        $contenido = @file_get_contents($tmp);
        if ($contenido !== false) {
            $guardado = (@file_put_contents($destino, $contenido) !== false);
        }
    }
    if (!$guardado) {
        $lastErr = error_get_last();
        $msgErr = !empty($lastErr['message']) ? ' (' . $lastErr['message'] . ')' : '';
        jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor' . $msgErr], 500);
    }
    $fotoPath = 'uploads/checkins/' . $nombre;
} elseif (!empty($_POST['foto_base64'])) {
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
    $nombre = 'checkin_' . $citaId . '_' . $tipo . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = $destinoDir . '/' . $nombre;
    if (@file_put_contents($destino, $decoded) === false) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor'], 500);
    }
    $fotoPath = 'uploads/checkins/' . $nombre;
} elseif (!$noShow) {
    if (isset($_FILES['foto']['error']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $err = $_FILES['foto']['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            jsonResponse(['ok' => false, 'error' => 'La foto es demasiado pesada para el servidor.'], 400);
        } else {
            jsonResponse(['ok' => false, 'error' => 'Error al recibir la foto (código ' . $err . ')'], 400);
        }
    }
    jsonResponse(['ok' => false, 'error' => 'Toma la foto de evidencia'], 400);
}

// El check-in y la corrección del pin van juntos o no va ninguno.
$db->beginTransaction();
$stmt = $db->prepare(
    'INSERT INTO checkins (cita_id, tipo, lat, lng, accuracy, distancia_metros, foto_path, verificado, ubicacion_corregida)
     VALUES (?,?,?,?,?,?,?,?,?) RETURNING id'
);
// distancia_metros se queda con la distancia al pin ANTERIOR (dato útil
// para el admin); ubicacion_corregida es lo que cambia la etiqueta.
$stmt->execute([$citaId, $tipo, $lat, $lng, $accuracy, $distancia, $fotoPath, $verificado, $correccion ? 'true' : 'false']);
$checkinId = (int)$stmt->fetchColumn();

if ($correccion) {
    $db->prepare(
        "UPDATE clientes SET lat = ?, lng = ?, ubicacion_confirmada = TRUE, ubicacion_fuente = 'checkin',
                ubicacion_confirmada_en = CURRENT_TIMESTAMP
         WHERE id = ?"
    )->execute([$lat, $lng, $cita['cliente_id']]);
    $db->prepare(
        'INSERT INTO correcciones_ubicacion (cliente_id, checkin_id, vendedor_id, lat_anterior, lng_anterior, lat_nueva, lng_nueva, distancia_metros, accuracy, estado, nota)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $cita['cliente_id'], $checkinId, $u['id'], $cita['cliente_lat'], $cita['cliente_lng'],
        $lat, $lng, $distancia, $accuracy, $correccion['estado'], $correccion['nota'],
    ]);
} elseif ($verificado === 1 && !$cita['ubicacion_confirmada']) {
    // Cayó dentro del radio: el pin que ya tenía queda comprobado.
    $db->prepare(
        "UPDATE clientes SET ubicacion_confirmada = TRUE, ubicacion_fuente = 'checkin_verificado',
                ubicacion_confirmada_en = CURRENT_TIMESTAMP
         WHERE id = ?"
    )->execute([$cita['cliente_id']]);
}
$db->commit();

if ($correccion) {
    registrarCambio($db, $u['id'], 'cliente', (int)$cita['cliente_id'], 'edicion',
        "Corrigió la ubicación de {$cita['cliente_nombre']} en la visita (el pin estaba a " . round($distancia) . ' m)', [
        'Ubicación' => [$cita['cliente_lat'] . ', ' . $cita['cliente_lng'], $lat . ', ' . $lng],
    ]);
}

// Si la entrada corrigió el pin, la salida debe registrarse en el mismo
// lugar: si no, la corrección pasa a revisión del admin.
if ($tipo === 'salida') {
    $stmt = $db->prepare(
        "SELECT cu.id, ch.lat, ch.lng FROM correcciones_ubicacion cu
         JOIN checkins ch ON ch.id = cu.checkin_id
         WHERE ch.cita_id = ? AND cu.estado = 'aplicada' LIMIT 1"
    );
    $stmt->execute([$citaId]);
    if ($corr = $stmt->fetch()) {
        $separacion = haversineDistance($lat, $lng, (float)$corr['lat'], (float)$corr['lng']);
        if ($separacion > CORRECCION_SALIDA_MAX_M) {
            $db->prepare("UPDATE correcciones_ubicacion SET estado = 'por_revisar', nota = ? WHERE id = ?")
               ->execute(['La salida se registró a ' . round($separacion) . ' m de la entrada', $corr['id']]);
        }
    }
}

if ($noShow) {
    $nuevoEstado = 'no_realizada';
    $db->prepare('UPDATE citas SET estado = ?, motivo = ? WHERE id = ?')->execute([$nuevoEstado, $motivo, $citaId]);
    registrarCambio($db, $u['id'], 'cita', $citaId, 'baja', "Reportó que {$cita['cliente_nombre']} no llegó a la cita", [
        'Estado' => [$cita['estado'], 'No realizada'],
        'Motivo' => [null, $motivo],
    ]);
} elseif ($tipo === 'salida') {
    // Solo al cerrar la visita (salida) tiene sentido preguntar el interés:
    // ya se tuvo la conversación completa con el cliente.
    $nuevoEstado = 'completada';
    $db->prepare('UPDATE citas SET estado = ?, interes = ? WHERE id = ?')
       ->execute([$nuevoEstado, $interes ?: null, $citaId]);
    registrarCambio($db, $u['id'], 'cita', $citaId, 'edicion', "Cerró la visita a {$cita['cliente_nombre']}", [
        'Estado'   => [$cita['estado'], 'Completada'],
        'Interés'  => [null, $interes ?: null],
    ]);
} else {
    $nuevoEstado = 'en_curso';
    $db->prepare('UPDATE citas SET estado = ? WHERE id = ?')->execute([$nuevoEstado, $citaId]);
}

if ($tipo === 'entrada' && $verificado === 0 && $distancia !== null) {
    $db->prepare('INSERT INTO alertas (vendedor_id, cita_id, tipo, mensaje) VALUES (?,?,?,?)')
       ->execute([
           $u['id'],
           $citaId,
           'fuera_de_zona',
           'Check-in a ' . round($distancia) . ' m del cliente (fuera del margen permitido de ' . RADIO_VERIFICACION_METROS . ' m).',
       ]);
}

jsonResponse([
    'ok'               => true,
    'verificado'       => (bool)$verificado,
    'ubicacion_corregida' => (bool)$correccion,
    'distancia_metros' => $distancia !== null ? round($distancia) : null,
    'foto'             => $fotoPath,
    'estado'           => $nuevoEstado,
]);
