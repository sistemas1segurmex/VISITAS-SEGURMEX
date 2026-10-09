<?php
/**
 * PDF de una solicitud de muestra (ver includes/muestra_pdf.php).
 *
 * CON sesión (a diferencia del PDF de la cotización, que es público por
 * token): es un documento interno.
 *   - vendedor: solo las suyas (y sin el nombre de quien atiende)
 *   - muestras (responsable) y admin: cualquiera
 *
 *   muestra_pdf.php?id=ID              -> se ve en el navegador
 *   muestra_pdf.php?id=ID&descargar=1  -> se descarga
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/muestras.php';
require_once __DIR__ . '/includes/muestra_pdf.php';

$u = requireAnyRole(['vendedor', 'muestras', 'admin']);

function pdfMuestraNoDisponible(int $codigo, string $titulo, string $texto): void {
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . htmlspecialchars($titulo) . ' — SEGURMEX</title>'
       . '<style>body{font-family:system-ui,Arial,sans-serif;text-align:center;padding:80px 20px;color:#6B7280}h2{color:#211D14}</style></head>'
       . '<body><h2>' . htmlspecialchars($titulo) . '</h2><p>' . htmlspecialchars($texto) . '</p></body></html>';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$s  = $id ? cargarSolicitudMuestra($db, $id) : null;
// Al vendedor, una ajena se le contesta igual que una que no existe.
if (!$s || ($u['rol'] === 'vendedor' && (int)$s['vendedor_id'] !== (int)$u['id'])) {
    pdfMuestraNoDisponible(404, 'Solicitud no encontrada', 'La solicitud no existe o no es tuya.');
}

try {
    $pdf = pdfMuestra($s, historialSolicitudMuestra($db, $id), $u['rol'] !== 'vendedor');
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_pdf.php: ' . $e->getMessage());
    pdfMuestraNoDisponible(500, 'No se pudo generar el PDF', 'Intenta de nuevo en unos minutos.');
}

$archivo = 'Muestra-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$s['folio']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['descargar']) ? 'attachment' : 'inline') . '; filename="' . $archivo . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
echo $pdf;
