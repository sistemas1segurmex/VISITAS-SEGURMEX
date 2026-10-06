<?php
/**
 * PDF de una cotización, generado en el servidor con Dompdf (vendorizado en
 * includes/dompdf/, sin Composer -- mismo patrón que includes/phpmailer/, así
 * en producción basta con "git pull").
 *
 * Mismo contenido que erp/cotizacion/ficha_pdf.php (que es HTML + imprimir
 * del navegador y pide sesión del ERP), pero como archivo .pdf real que el
 * cliente abre desde el link de WhatsApp sin cuenta.
 *
 * Dompdf no soporta flex/grid: todo el acomodo va con tablas. El logo es JPG
 * a propósito: un PNG con transparencia necesitaría la extensión GD de PHP.
 */
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!function_exists('money')) {
    function money($n): string { return '$' . number_format((float)$n, 2); }
}

function pdfH($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Teléfono guardado (10 dígitos) como "477 123 4567"; otro formato, tal cual. */
function pdfTelefono(?string $tel): string {
    $d = preg_replace('/\D+/', '', (string)$tel);
    return strlen($d) === 10 ? substr($d, 0, 3) . ' ' . substr($d, 3, 3) . ' ' . substr($d, 6) : (string)$tel;
}

/**
 * HTML que se convierte a PDF. $urlResponder (opcional) es el link público
 * del ERP donde el cliente acepta o rechaza; solo se pone mientras la
 * cotización sigue abierta.
 */
function htmlPdfCotizacion(array $cot, array $detalle, string $atiende, ?string $urlResponder): string {
    $creada = strtotime($cot['created_at'] ?? 'now') ?: time();
    $fecha  = date('d/m/Y', $creada);
    $vence  = date('d/m/Y', $creada + ((int)($cot['vigencia_dias'] ?? 0) * 86400));
    $tasa   = number_format((float)($cot['tasa_iva'] ?? 0.16) * 100, 0);
    $logo   = __DIR__ . '/../assets/img/logo-cotizacion.jpg';
    $logoSrc = is_file($logo) ? 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logo)) : '';

    $filas = '';
    foreach ($detalle as $d) {
        $lista = (float)($d['precio_lista'] ?? 0);
        $final = (float)($d['precio_final'] ?? 0);
        $tachado = ($lista > 0 && $final < $lista) ? '<div class="tachado">' . money($lista) . '</div>' : '';
        $color = !empty($d['color']) ? '<div class="color">Color: ' . pdfH($d['color']) . '</div>' : '';
        $filas .= '<tr>'
            . '<td class="clave">' . pdfH($d['clave_estilo']) . '</td>'
            . '<td><div class="modelo">' . pdfH($d['nombre_estilo']) . '</div>' . $color . '</td>'
            . '<td class="num">' . (int)$d['cantidad'] . '</td>'
            . '<td class="num">' . $tachado . money($final) . '</td>'
            . '<td class="num fuerte">' . money($d['importe']) . '</td>'
            . '</tr>';
    }

    $descuentos = [];
    if (!empty($cot['aplica_mayoreo'])) $descuentos[] = 'mayoreo';
    if (!empty($cot['pronto_pago']))    $descuentos[] = 'pronto pago';
    $lista = ($cot['tipo_lista'] ?? '') === 'distribuidor' ? 'Distribuidor' : 'Industria';

    $responder = $urlResponder ? '
    <table class="cta"><tr>
      <td class="cta-txt"><b>¿Le interesa?</b> Puede aceptar la cotización o enviarnos sus comentarios en línea, sin necesidad de cuenta.</td>
      <td class="cta-btn"><a href="' . pdfH($urlResponder) . '">Responder cotización</a></td>
    </tr></table>' : '';

    $notas = trim((string)($cot['notas'] ?? '')) !== '' ? '
    <div class="seccion">Notas</div>
    <div class="notas">' . nl2br(pdfH($cot['notas'])) . '</div>' : '';

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Cotización ' . pdfH($cot['folio']) . '</title>
<style>
  @page { size: A4; margin: 14mm 14mm 18mm 14mm; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #211D14; }
  table { border-collapse: collapse; width: 100%; }
  .hdr td { vertical-align: middle; }
  .hdr .logo img { height: 46px; }
  .titulo { font-size: 17pt; font-weight: bold; color: #B8901E; letter-spacing: 1px; }
  .cliente { font-size: 10pt; margin-top: 2px; }
  .folio { border: 1px solid #E3D6B2; border-top: 3px solid #C9A227; padding: 7px 12px; text-align: right; width: 170px; }
  .folio .lbl { font-size: 6.5pt; color: #8A7A46; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .folio .num { font-size: 14pt; font-weight: bold; }
  .folio .fechas { font-size: 7pt; color: #6B6249; margin-top: 2px; }
  .regla { height: 2px; background: #C9A227; margin: 10px 0 12px; }
  .stats td { width: 25%; padding: 0 4px; }
  .stats td:first-child { padding-left: 0; } .stats td:last-child { padding-right: 0; }
  .stat { border: 1px solid #E3D6B2; text-align: center; padding: 8px 4px; }
  .stat .l { font-size: 6.5pt; color: #8A7A46; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .stat .v { font-size: 12pt; font-weight: bold; margin-top: 3px; }
  .stat.total { background: #FBF4DC; border-color: #C9A227; }
  .stat.total .v { color: #8A6D14; font-size: 13pt; }
  .seccion { font-size: 9.5pt; font-weight: bold; border-bottom: 1.5px solid #E3D6B2; padding-bottom: 3px; margin: 14px 0 6px; }
  .datos td { padding: 2px 0; vertical-align: top; font-size: 8.5pt; }
  .datos .k { color: #6B6249; width: 92px; padding-right: 8px; white-space: nowrap; }
  .datos .v { font-weight: bold; padding-right: 14px; }
  .det th { font-size: 6.8pt; text-transform: uppercase; letter-spacing: .5px; color: #6B6249; text-align: left; padding: 5px 6px; border-bottom: 1.5px solid #C9A227; }
  .det td { padding: 6px; border-bottom: 1px solid #EAE0C6; vertical-align: top; font-size: 8.5pt; }
  .det .num { text-align: right; white-space: nowrap; }
  .det th.num { text-align: right; }
  .det .clave { font-weight: bold; white-space: nowrap; }
  .det .modelo { font-weight: bold; }
  .det .color { font-size: 7.5pt; color: #6B7280; margin-top: 1px; }
  .det .fuerte { font-weight: bold; }
  .tachado { text-decoration: line-through; color: #A79E86; font-size: 7pt; }
  .totales { width: 230px; margin-left: auto; margin-top: 8px; }
  .totales td { padding: 3px 6px; font-size: 8.8pt; color: #4B4536; }
  .totales td.n { text-align: right; }
  .totales tr.t td { font-size: 11pt; font-weight: bold; color: #17140C; border-top: 1.5px solid #C9A227; padding-top: 6px; }
  .notas { border: 1px solid #E3D6B2; padding: 7px 10px; font-size: 8.5pt; }
  .cta { margin-top: 16px; background: #FFFBEB; border: 1px solid #F3D27A; }
  .cta td { padding: 10px 12px; vertical-align: middle; }
  .cta-txt { font-size: 8.5pt; color: #5B4A12; }
  .cta-btn { width: 150px; text-align: right; }
  .cta-btn a { display: inline-block; background: #C9A227; color: #17140C; font-weight: bold; text-decoration: none; padding: 7px 12px; font-size: 8.5pt; }
  .pie { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 6.8pt; color: #8A7A46; border-top: 1px solid #E3D6B2; padding-top: 4px; }
  .pie td { font-size: 6.8pt; color: #8A7A46; }
</style></head><body>

<div class="pie"><table><tr>
  <td>SEGURMEX' . ($atiende !== '' ? ' · Atiende: ' . pdfH($atiende) : '') . '</td>
  <td style="text-align:right">Cotización sujeta a cambios sin previo aviso · Generada el ' . date('d/m/Y') . '</td>
</tr></table></div>

<table class="hdr"><tr>
  <td class="logo" style="width:150px">' . ($logoSrc ? '<img src="' . $logoSrc . '" alt="SEGURMEX">' : '<b>SEGURMEX</b>') . '</td>
  <td style="padding-left:12px">
    <div class="titulo">COTIZACIÓN</div>
    <div class="cliente"><b>' . pdfH($cot['cliente_nombre']) . '</b></div>
  </td>
  <td class="folio">
    <div class="lbl">Folio</div>
    <div class="num">' . pdfH($cot['folio']) . '</div>
    <div class="fechas">' . $fecha . ' — vence ' . $vence . '</div>
  </td>
</tr></table>
<div class="regla"></div>

<table class="stats"><tr>
  <td><div class="stat"><div class="l">Pares</div><div class="v">' . (int)($cot['total_pares'] ?? 0) . '</div></div></td>
  <td><div class="stat"><div class="l">Subtotal</div><div class="v">' . money($cot['subtotal']) . '</div></div></td>
  <td><div class="stat"><div class="l">IVA (' . $tasa . '%)</div><div class="v">' . money($cot['iva']) . '</div></div></td>
  <td><div class="stat total"><div class="l">Total</div><div class="v">' . money($cot['total']) . '</div></div></td>
</tr></table>

<div class="seccion">Datos del cliente</div>
<table class="datos"><tr>
  <td class="k">Contacto:</td><td class="v">' . pdfH($cot['cliente_contacto'] ?: '—') . '</td>
  <td class="k">Teléfono:</td><td class="v">' . pdfH(pdfTelefono($cot['cliente_telefono'] ?? '') ?: '—') . '</td>
</tr><tr>
  <td class="k">Correo:</td><td class="v">' . pdfH($cot['cliente_email'] ?: '—') . '</td>
  <td class="k">Dirección:</td><td class="v">' . pdfH($cot['cliente_direccion'] ?: '—') . '</td>
</tr></table>

<div class="seccion">Modelos cotizados</div>
<table class="det">
  <thead><tr><th style="width:52px">Clave</th><th>Modelo</th><th class="num" style="width:44px">Pares</th><th class="num" style="width:82px">Precio unitario</th><th class="num" style="width:82px">Importe</th></tr></thead>
  <tbody>' . $filas . '</tbody>
</table>
<table class="totales">
  <tr><td>Subtotal</td><td class="n">' . money($cot['subtotal']) . '</td></tr>
  <tr><td>IVA (' . $tasa . '%)</td><td class="n">' . money($cot['iva']) . '</td></tr>
  <tr class="t"><td>Total</td><td class="n">' . money($cot['total']) . '</td></tr>
</table>

<div class="seccion">Condiciones</div>
<table class="datos"><tr>
  <td class="k">Vigencia:</td><td class="v">' . (int)($cot['vigencia_dias'] ?? 0) . ' días (hasta el ' . $vence . ')</td>
  <td class="k">Lista:</td><td class="v">' . $lista . ($descuentos ? ' · con ' . implode(' y ', $descuentos) : '') . '</td>
</tr><tr>
  <td class="k">Entrega:</td><td class="v">' . pdfH($cot['tiempo_entrega'] ?: 'A confirmar') . '</td>
  <td class="k">Forma de pago:</td><td class="v">' . pdfH($cot['forma_pago'] ?: 'A confirmar') . '</td>
</tr></table>
' . $notas . $responder . '
</body></html>';
}

/** Genera el PDF y regresa sus bytes. */
function pdfCotizacion(array $cot, array $detalle, string $atiende = '', ?string $urlResponder = null): string {
    $opt = new Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);      // nada se baja de internet
    $opt->set('isPhpEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', sys_get_temp_dir()); // no escribir dentro del repo
    $opt->set('chroot', [realpath(__DIR__ . '/..')]);

    $dompdf = new Dompdf($opt);
    $dompdf->loadHtml(htmlPdfCotizacion($cot, $detalle, $atiende, $urlResponder), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    // Número de página abajo a la derecha, solo si son varias.
    $canvas = $dompdf->getCanvas();
    if ($canvas->get_page_count() > 1) {
        $fm = $dompdf->getFontMetrics();
        $canvas->page_text(268, 828, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fm->getFont('DejaVu Sans'), 6.5, [0.54, 0.48, 0.27]);
    }
    return $dompdf->output();
}
