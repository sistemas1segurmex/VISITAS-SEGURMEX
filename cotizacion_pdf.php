<?php
/**
 * PDF público de una cotización: lo abre el cliente desde el link que le
 * manda el vendedor por WhatsApp (ver vendedor/ver_cotizacion.php).
 *
 * SIN login a propósito, igual que erp/cotizacion/publica.php: el acceso es
 * por el token_publico de la cotización (48 caracteres aleatorios, mismo
 * campo que ya usa el link del ERP), no por el id.
 *
 *   cotizacion_pdf.php?t=TOKEN              -> se ve en el navegador
 *   cotizacion_pdf.php?t=TOKEN&descargar=1  -> se descarga
 */
require_once __DIR__ . '/includes/db_erp.php';
require_once __DIR__ . '/includes/cotizador_helpers.php';
require_once __DIR__ . '/includes/cotizacion_pdf.php';

function pdfNoDisponible(int $codigo, string $titulo, string $texto): void {
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . htmlspecialchars($titulo) . ' — SEGURMEX</title>'
       . '<style>body{font-family:system-ui,Arial,sans-serif;text-align:center;padding:80px 20px;color:#6B7280}h2{color:#211D14}</style></head>'
       . '<body><h2>' . htmlspecialchars($titulo) . '</h2><p>' . htmlspecialchars($texto) . '</p></body></html>';
    exit;
}

$token = trim((string)($_GET['t'] ?? ''));
if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
    pdfNoDisponible(404, 'Este link no es válido', 'Pide al vendedor que te comparta la cotización de nuevo.');
}

try {
    $db = getDBErp();
    $stmt = $db->prepare('SELECT * FROM cotizaciones WHERE token_publico = ?');
    $stmt->execute([$token]);
    $cot = $stmt->fetch();
    if (!$cot) {
        pdfNoDisponible(404, 'Este link no es válido', 'La cotización no existe o el link ya no está disponible. Pide al vendedor que te comparta uno nuevo.');
    }
    if ($cot['estado'] === 'cancelada') {
        pdfNoDisponible(410, 'Cotización cancelada', 'Esta cotización ya no está vigente. Pide al vendedor una nueva.');
    }

    $det = $db->prepare('SELECT * FROM cotizacion_detalle WHERE id_cotizacion = ? ORDER BY orden');
    $det->execute([$cot['id']]);
    $detalle = $det->fetchAll();

    $v = $db->prepare('SELECT nombre, apellidos FROM usuarios WHERE id = ?');
    $v->execute([$cot['id_vendedor']]);
    $vend = $v->fetch() ?: [];
    $atiende = trim(($vend['nombre'] ?? '') . ' ' . ($vend['apellidos'] ?? ''));

    // El botón "Responder" solo mientras el cliente todavía puede contestar.
    $abierta = in_array($cot['estado'], ['pendiente', 'enviada', 'en_negociacion'], true) && !cotizacionVencidaErp($cot);
    $pdf = pdfCotizacion($cot, $detalle, $atiende, $abierta ? urlPublicaCotizacionErp($token) : null);
} catch (Throwable $e) {
    error_log('[VISITAS] cotizacion_pdf.php: ' . $e->getMessage());
    pdfNoDisponible(500, 'No se pudo generar el PDF', 'Intenta de nuevo en unos minutos.');
}

$archivo = 'Cotizacion-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$cot['folio']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['descargar']) ? 'attachment' : 'inline') . '; filename="' . $archivo . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
echo $pdf;
