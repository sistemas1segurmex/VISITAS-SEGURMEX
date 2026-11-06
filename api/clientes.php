<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/coincidencias.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

// 'muestras' solo consulta: los clientes del vendedor que eligió en Nueva
// cotización (?vendedor_id=). Dar de alta sigue siendo solo del vendedor.
$u  = requireAnyRole(['vendedor', 'muestras']);
$db = getDB();
$idVendedor = (int)$u['id'];
if ($u['rol'] === 'muestras') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['ok' => false, 'error' => 'Sin permiso para esta acción'], 403);
    }
    $idVendedor = (int)($_GET['vendedor_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Se suman visitas completadas y la fecha de la última, para que la
    // tarjeta de cada cliente en "Mis clientes" pueda mostrar qué tan
    // reciente es la relación sin tener que golpear otro endpoint.
    // El interés ya no vive en el cliente -- se califica por cita, al
    // registrar la salida (ver api/checkin.php). Aquí se trae el de la
    // visita completada más reciente que sí tenga uno capturado.
    // ?incluir_reasignados=1 (solo "Mis clientes"): también los clientes que
    // la responsable de muestras pasó a otro vendedor y con los que este
    // vendedor tuvo citas -- de solo consulta, para seguir viendo su historial.
    $conReasignados = $u['rol'] === 'vendedor' && !empty($_GET['incluir_reasignados']);
    $whereDueno = $conReasignados
        ? '(c.vendedor_id = ? OR EXISTS (SELECT 1 FROM citas cr WHERE cr.cliente_id = c.id AND cr.vendedor_id = ?))'
        : 'c.vendedor_id = ?';
    $stmt = $db->prepare(
        "SELECT c.*, (c.vendedor_id <> " . (int)$idVendedor . ") AS reasignado,
                (SELECT COUNT(*) FROM citas ci WHERE ci.cliente_id = c.id AND ci.estado = 'completada') AS total_visitas,
                (SELECT MAX(fecha_hora) FROM citas ci WHERE ci.cliente_id = c.id AND ci.estado = 'completada') AS ultima_visita,
                (SELECT ci.interes FROM citas ci WHERE ci.cliente_id = c.id AND ci.interes IS NOT NULL ORDER BY ci.fecha_hora DESC LIMIT 1) AS ultimo_interes
         FROM clientes c
         WHERE $whereDueno
           -- Descartados por duplicado (coincidencias_clientes): solo evidencia.
           AND c.estado_revision IS DISTINCT FROM 'descartado'
         ORDER BY c.nombre"
    );
    $stmt->execute($conReasignados ? [$idVendedor, $idVendedor] : [$idVendedor]);
    jsonResponse(['ok' => true, 'clientes' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre        = trim($_POST['nombre'] ?? '');
    $calleNumero   = trim($_POST['calle_numero'] ?? '');
    $codigoPostal  = trim($_POST['codigo_postal'] ?? '');
    $colonia       = trim($_POST['colonia'] ?? '');
    $municipio     = trim($_POST['municipio'] ?? '');
    $estado        = trim($_POST['estado'] ?? '');
    $lat           = $_POST['lat'] ?? null;
    $lng           = $_POST['lng'] ?? null;
    $telefono      = trim($_POST['telefono'] ?? '');
    $nombreContacto = trim($_POST['nombre_contacto'] ?? '');
    // «Cliente ya ganado» (06-nov-2026): solo se acepta si el vendedor elige
    // cuál de SUS clientes del ERP es (clientesErpSinVisitas); el registro
    // nace «convertido» y ligado (id_cliente_erp). Si no está en el ERP se da
    // de alta como prospecto y se hace cliente desde el ERP con el enlace.
    $yaEsCliente   = ($_POST['ya_es_cliente'] ?? '') === '1';
    $idClienteErp  = null;
    $clienteErp    = null;
    $etapaInicial  = $yaEsCliente ? 'convertido' : 'prospecto_agregado';
    $tipoCliente   = ($_POST['tipo_cliente'] ?? '') === 'persona' ? 'persona' : 'organizacion';

    if ($nombre === '' || $calleNumero === '') {
        jsonResponse(['ok' => false, 'error' => 'Nombre y dirección son obligatorios'], 400);
    }
    // La ubicación GPS es obligatoria sin excepción: sin ella no se puede
    // verificar el check-in por distancia ni ordenar la cartera por cercanía.
    if ($lat === null || $lat === '' || $lng === null || $lng === '') {
        jsonResponse(['ok' => false, 'error' => 'Marca la ubicación del cliente en el mapa antes de guardar'], 400);
    }
    if ($yaEsCliente) {
        $idElegido = (int)($_POST['id_cliente_erp'] ?? 0);
        if (!$idElegido) {
            jsonResponse(['ok' => false, 'error' => 'Elige de la lista cuál de tus clientes es. Si no aparece, regístralo como «Prospecto nuevo» y después usa «Hacer cliente» en Prospectos.'], 400);
        }
        $vend = vendedorQueCotiza($db, $u, 0);
        $disponibles = $vend ? array_column(clientesErpSinVisitas($db, $vend), null, 'id') : [];
        if (!isset($disponibles[$idElegido])) {
            jsonResponse(['ok' => false, 'error' => 'Ese cliente no es tuyo o ya está en tu cartera. Revisa la lista o avisa a Sistemas.'], 400);
        }
        $idClienteErp = $idElegido;
        $clienteErp   = $disponibles[$idElegido];
    }
    // El teléfono es opcional, pero si viene se guarda siempre a 10 dígitos
    // (lo usan Llamar y WhatsApp en la cartera y en las cotizaciones).
    $telefono = normalizarTelefonoMx($telefono);
    if ($telefono === null) {
        jsonResponse(['ok' => false, 'error' => MSG_TELEFONO_INVALIDO], 400);
    }

    // Dirección completa y legible, compuesta a partir de las partes
    // capturadas (para no tener que tocar las pantallas que ya muestran
    // "direccion" tal cual, como el check-in o el panel del dueño).
    // Sin colonia/CP/ciudad repetidos aunque los hayan pegado en la calle.
    $calleNumero = limpiarCalleNumero($calleNumero, $colonia, $municipio, $estado);
    $partes = array_filter([$calleNumero, $colonia, $municipio, $estado, $codigoPostal]);
    $direccion = implode(', ', $partes);

    [$ubicacionConfirmada, $ubicacionFuente] = ubicacionDesdeFormulario('registro');
    $stmt = $db->prepare(
        'INSERT INTO clientes (vendedor_id, nombre, direccion, calle_numero, lat, lng, telefono, codigo_postal, estado, municipio, colonia, etapa, etapa_inicial, etapa_actualizada_en, tipo_cliente, nombre_contacto,
                               ubicacion_confirmada, ubicacion_fuente, ubicacion_confirmada_en, id_cliente_erp)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?,?,?,?,' . ($ubicacionConfirmada ? 'CURRENT_TIMESTAMP' : 'NULL') . ',?)'
    );
    $stmt->execute([
        $u['id'], $nombre, $direccion, $calleNumero,
        $lat !== '' ? $lat : null, $lng !== '' ? $lng : null,
        $telefono,
        $codigoPostal ?: null, $estado ?: null, $municipio ?: null, $colonia ?: null,
        $etapaInicial, $etapaInicial, $tipoCliente, $nombreContacto ?: null,
        $ubicacionConfirmada ? 'true' : 'false', $ubicacionFuente,
        $idClienteErp,
    ]);
    $nuevoId = (int)$db->lastInsertId();
    $diffAlta = ['Etapa inicial' => [null, etiquetaEtapa($etapaInicial)]];
    if ($clienteErp) {
        $diffAlta['Cliente ligado'] = [null, trim(($clienteErp['cinterno'] ? $clienteErp['cinterno'] . ' · ' : '') . $clienteErp['razon_social'])];
    }
    registrarCambio($db, $u['id'], 'cliente', $nuevoId, 'alta', "Dio de alta al cliente {$nombre}", $diffAlta);
    // ¿Coincide con el cliente de OTRO vendedor del mismo municipio? Se queda
    // guardado pero en revisión hasta que la responsable de muestras decida.
    $coincidencias = buscarCoincidenciasOtrosVendedores($db, (int)$u['id'], [
        'nombre' => $nombre, 'telefono' => $telefono, 'municipio' => $municipio,
        'colonia' => $colonia, 'codigo_postal' => $codigoPostal, 'lat' => $lat, 'lng' => $lng,
    ], $nuevoId);
    if ($coincidencias) {
        registrarCoincidencias($db, $u, $nuevoId, $nombre, $coincidencias, 'alta');
        jsonResponse(['ok' => true, 'id' => $nuevoId, 'en_revision' => true,
            'mensaje' => 'Se guardó, pero quedó en revisión: coincide con un cliente que ya atiende otro vendedor. La responsable de muestras lo va a validar y te avisaremos.']);
    }
    jsonResponse(['ok' => true, 'id' => $nuevoId]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
