<?php
/**
 * PDF de una cotización, generado en el servidor con Dompdf (vendorizado en
 * includes/dompdf/, sin Composer -- mismo patrón que includes/phpmailer/, así
 * en producción basta con "git pull").
 *
 * Mismo contenido y orden que la página pública del ERP
 * (erp/cotizacion/publica.php), pero como archivo .pdf real que el cliente
 * abre desde el link de WhatsApp sin cuenta. Si cambias uno, revisa el otro.
 *
 * Dompdf no soporta flex/grid: todo el acomodo va con tablas. El logo es JPG
 * a propósito: un PNG con transparencia necesitaría la extensión GD de PHP
 * (por eso las fotos PNG de los modelos solo se ponen si GD está cargada).
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

/** Valor o "Sin registro" en gris (datos que no se capturaron). */
function pdfDato($v): string {
    $v = trim((string)($v ?? ''));
    return $v !== '' ? pdfH($v) : '<span class="vacio">Sin registro</span>';
}

/**
 * Foto del modelo como data URI (Dompdf corre con chroot en VISITAS y sin
 * descargas, así que se lee del disco). Las fotos viven en el ERP
 * (erp/assets/img/cotizador/), hermano de visitas/ en el mismo servidor.
 */
function pdfFotoModelo(?string $foto): string {
    $foto = basename((string)$foto);
    if ($foto === '') return '';
    $ext = strtolower(pathinfo($foto, PATHINFO_EXTENSION));
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? null;
    if (!$mime || ($mime === 'image/png' && !extension_loaded('gd'))) return '';
    foreach ([__DIR__ . '/../../erp/assets/img/cotizador/', __DIR__ . '/../assets/img/cotizador/'] as $dir) {
        $ruta = $dir . $foto;
        if (is_file($ruta) && filesize($ruta) < 1500000) {
            return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($ruta));
        }
    }
    return '';
}

/**
 * HTML que se convierte a PDF. $urlResponder (opcional) es el link público
 * del ERP donde el cliente acepta o rechaza; solo se pone mientras la
 * cotización sigue abierta.
 *
 * $extra (opcional): 'vend_tel', 'vend_email', 'vend_zona' (contacto del vendedor) y
 * 'fecha_resuelta' (d/m/Y en que se aceptó/rechazó, del historial).
 */
function htmlPdfCotizacion(array $cot, array $detalle, string $atiende, ?string $urlResponder, array $extra = []): string {
    $creada = strtotime($cot['created_at'] ?? 'now') ?: time();
    $fecha  = date('d/m/Y', $creada);
    $limite = $creada + ((int)($cot['vigencia_dias'] ?? 0) * 86400);
    $vence  = date('d/m/Y', $limite);
    $tasa   = number_format((float)($cot['tasa_iva'] ?? 0.16) * 100, 0);
    $logo   = __DIR__ . '/../assets/img/logo-cotizacion.jpg';
    $logoSrc = is_file($logo) ? 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logo)) : '';
    $estado  = (string)($cot['estado'] ?? '');
    $cerrada = in_array($estado, ['aceptada', 'rechazada', 'cancelada'], true);
    $vencida = !$cerrada && $limite < time();
    $resuelta = (string)($extra['fecha_resuelta'] ?? '');

    $filas = '';
    foreach ($detalle as $d) {
        $lista = (float)($d['precio_lista'] ?? 0);
        $final = (float)($d['precio_final'] ?? 0);
        $tachado = ($lista > 0 && $final < $lista) ? '<div class="tachado">' . money($lista) . '</div>' : '';
        $meta = ['Clave ' . pdfH($d['clave_estilo'])];
        if (!empty($d['color']))        $meta[] = 'Color: ' . pdfH($d['color']);
        if (!empty($d['entrega_dias'])) $meta[] = 'Entrega: ' . (int)$d['entrega_dias'] . ' días hábiles';
        $src  = pdfFotoModelo($d['foto'] ?? null);
        $foto = $src !== '' ? '<img src="' . $src . '" alt="">' : '<div class="sinfoto">SIN<br>FOTO</div>';
        $filas .= '<tr>'
            . '<td class="fcel">' . $foto . '</td>'
            . '<td><div class="modelo">' . pdfH($d['nombre_estilo']) . (!empty($d['atributo']) ? ' <span class="attr">' . pdfH($d['atributo']) . '</span>' : '') . '</div>'
            . '<div class="meta">' . implode(' · ', $meta) . '</div></td>'
            . '<td class="num">' . (int)$d['cantidad'] . '</td>'
            . '<td class="num">' . $tachado . money($final) . '</td>'
            . '<td class="num fuerte">' . money($d['importe']) . '</td>'
            . '</tr>';
    }

    $descuentos = [];
    if (!empty($cot['aplica_mayoreo'])) $descuentos[] = 'mayoreo';
    if (!empty($cot['pronto_pago']))    $descuentos[] = 'pronto pago';
    $lista = ($cot['tipo_lista'] ?? '') === 'distribuidor' ? 'Distribuidor' : 'Industria';

    $leyendaAttr = leyendaAtributosDeLineasErp($detalle);
    $leyenda = $leyendaAttr ? '<div class="leyenda-attr">' . implode(' · ', array_map(fn($c, $s) => '<b>' . pdfH($c) . '</b> = ' . pdfH($s), array_keys($leyendaAttr), $leyendaAttr)) . '</div>' : '';

    // Aviso de estado (solo cuando ya hay algo que decir: aceptada, rechazada, vencida).
    $quien = $atiende !== '' ? $atiende : 'su vendedor';
    $estadoHtml = '';
    if ($estado === 'aceptada') {
        $estadoHtml = ['ok', 'COTIZACIÓN ACEPTADA' . ($resuelta ? ' · ' . $resuelta : ''), pdfH(ucfirst($quien)) . ' le contactará para continuar con su pedido.'];
    } elseif ($estado === 'rechazada') {
        $estadoHtml = ['no', 'COTIZACIÓN RECHAZADA' . ($resuelta ? ' · ' . $resuelta : ''), 'Si desea ajustarla (cantidades, modelos o condiciones), contacte a ' . pdfH($quien) . '.'];
    } elseif ($vencida) {
        $estadoHtml = ['ojo', 'COTIZACIÓN VENCIDA · ' . $vence, 'Pida a ' . pdfH($quien) . ' una nueva si sigue interesado.'];
    }
    if ($estadoHtml) {
        $estadoHtml = '<table class="estado ' . $estadoHtml[0] . '"><tr><td><b>' . $estadoHtml[1] . '</b><br>' . $estadoHtml[2] . '</td></tr></table>';
    }

    // Desglose de descuentos (bajo el subtotal), ventajas y datos de la fábrica:
    // textos fijos en includes/cotizador_helpers.php (copia de los del ERP).
    $descFilas = '';
    foreach (descuentosAplicadosCotizacionErp($cot) as $txt) {
        $descFilas .= '<tr class="desc"><td colspan="2">✓ ' . pdfH($txt) . '</td></tr>';
    }
    $vent = COTIZACION_VENTAJAS_ERP;
    $mitad = (int)ceil(count($vent) / 2);
    $col = fn(array $xs) => implode('', array_map(fn($v) => '<div class="vi">• ' . pdfH($v) . '</div>', $xs));
    $ventajas = '
    <table class="ventajas"><tr><td colspan="2" class="vl">Ventajas SEGURMEX</td></tr>
      <tr><td>' . $col(array_slice($vent, 0, $mitad)) . '</td><td>' . $col(array_slice($vent, $mitad)) . '</td></tr>
    </table>';
    $emp = COTIZACION_EMPRESA_ERP;
    $empresa = '<div class="lbl">' . pdfH($emp['nombre']) . ' · Fábrica</div>'
        . '<div class="sub">' . pdfH($emp['direccion']) . '<br>' . pdfH($emp['ciudad']) . '<br>' . pdfH($emp['web']) . ' · RFC ' . pdfH($emp['rfc']) . '</div>';

    // Contacto del vendedor.
    $tel  = preg_replace('/\D+/', '', (string)($extra['vend_tel'] ?? ''));
    $mail = (string)($extra['vend_email'] ?? '');
    $wa   = strlen($tel) === 10 ? '52' . $tel : (strlen($tel) === 12 && substr($tel, 0, 2) === '52' ? $tel : '');
    $datosVend = [];
    if ($tel !== '')  $datosVend[] = 'Tel. ' . pdfH(pdfTelefono($tel));
    if ($mail !== '') $datosVend[] = '<a href="mailto:' . pdfH($mail) . '">' . pdfH($mail) . '</a>';
    $zona = trim((string)($extra['vend_zona'] ?? ''));
    $waTxt = 'Hola' . ($atiende !== '' ? ' ' . $atiende : '') . ', le escribo sobre la cotización ' . ($cot['folio'] ?? '') . '.';
    $contacto = '
    <table class="contacto"><tr>
      <td class="emp">' . $empresa . '</td>
      <td><div class="lbl">¿Dudas? Le atiende</div>
        <div class="nom">' . pdfH($atiende !== '' ? $atiende : 'Ventas SEGURMEX') . '</div>'
        . ($datosVend ? '<div class="sub">' . implode(' · ', $datosVend) . '</div>' : '')
        . ($zona !== '' ? '<div class="sub">Zona: ' . pdfH($zona) . '</div>' : '') . '</td>'
      . ($wa !== '' ? '<td class="wa"><a href="https://wa.me/' . $wa . '?text=' . rawurlencode($waTxt) . '">WhatsApp</a></td>' : '') . '
    </tr></table>';

    $responder = $urlResponder ? '
    <table class="cta"><tr>
      <td class="cta-txt"><b>¿Le interesa?</b> Puede aceptar la cotización o rechazarla en línea, sin necesidad de cuenta. Vigente hasta el ' . $vence . '.</td>
      <td class="cta-btn"><a href="' . pdfH($urlResponder) . '">Responder cotización</a></td>
    </tr></table>' : '';

    $notas = trim((string)($cot['notas'] ?? '')) !== '' ? '
    <div class="seccion">Notas</div>
    <div class="notas">' . nl2br(pdfH($cot['notas'])) . '</div>' : '';

    $nModelos = count($detalle);

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Cotización ' . pdfH($cot['folio']) . '</title>
<style>
  @page { size: A4; margin: 14mm 14mm 18mm 14mm; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #211D14; }
  table { border-collapse: collapse; width: 100%; }
  a { color: #8A6D14; text-decoration: none; }
  .hdr td { vertical-align: middle; }
  .hdr .logo img { height: 46px; }
  .titulo { font-size: 17pt; font-weight: bold; color: #B8901E; letter-spacing: 1px; }
  .cliente { font-size: 10pt; margin-top: 2px; }
  .rfc { font-size: 7.5pt; color: #6B6249; }
  .folio { border: 1px solid #E3D6B2; border-top: 3px solid #C9A227; padding: 7px 12px; text-align: right; width: 180px; }
  .folio .lbl { font-size: 6.5pt; color: #8A7A46; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .folio .num { font-size: 14pt; font-weight: bold; }
  .folio .fechas { font-size: 7pt; color: #6B6249; margin-top: 2px; }
  .regla { height: 2px; background: #C9A227; margin: 10px 0 12px; }
  .estado { margin-bottom: 10px; }
  .estado td { padding: 8px 12px; font-size: 8.5pt; border: 1px solid; }
  .estado b { font-size: 10pt; letter-spacing: .5px; }
  .estado.ok td  { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
  .estado.no td  { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }
  .estado.ojo td { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
  .stats td { padding: 0; vertical-align: top; }
  .stat { border: 1px solid #E3D6B2; padding: 8px 12px; }
  .stat .l { font-size: 6.5pt; color: #8A7A46; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .stat .v { font-size: 14pt; font-weight: bold; margin-top: 2px; }
  .stat .n { font-size: 7pt; color: #8A7A46; }
  .stat.total { background: #FBF4DC; border-color: #C9A227; text-align: right; }
  .stat.total .v { color: #8A6D14; font-size: 15pt; }
  .seccion { font-size: 9.5pt; font-weight: bold; border-bottom: 1.5px solid #E3D6B2; padding-bottom: 3px; margin: 14px 0 6px; }
  .datos td { padding: 2px 0; vertical-align: top; font-size: 8.5pt; }
  .datos .k { color: #6B6249; width: 110px; padding-right: 8px; white-space: nowrap; }
  .datos .v { font-weight: bold; padding-right: 14px; }
  .vacio { color: #A79E86; font-weight: normal; font-style: italic; }
  .det th { font-size: 6.8pt; text-transform: uppercase; letter-spacing: .5px; color: #6B6249; text-align: left; padding: 5px 6px; border-bottom: 1.5px solid #C9A227; }
  .det td { padding: 6px; border-bottom: 1px solid #EAE0C6; vertical-align: middle; font-size: 8.5pt; }
  .det .num { text-align: right; white-space: nowrap; }
  .det th.num { text-align: right; }
  .det td.fcel { width: 44px; padding-right: 2px; }
  .det .fcel img { width: 40px; height: 40px; border: 0.6pt solid #EEE7D3; }
  .sinfoto { width: 40px; height: 30px; padding-top: 10px; border: 0.6pt solid #EEE7D3; background: #FBF7EA; color: #C9B98A; font-size: 5.5pt; font-weight: bold; text-align: center; line-height: 1.1; }
  .det .modelo { font-weight: bold; }
  .det .meta { font-size: 7.5pt; color: #6B7280; margin-top: 1px; }
  .det .fuerte { font-weight: bold; }
  .tachado { text-decoration: line-through; color: #A79E86; font-size: 7pt; }
  .attr { font-size: 6.5pt; font-weight: bold; color: #8A6D14; border: 0.6pt solid #C9A227; padding: 0 3px; }
  .leyenda-attr { font-size: 6.8pt; color: #6B6249; margin: 3px 0 4px; }
  .totales { width: 280px; margin-left: auto; margin-top: 8px; }
  .totales td { padding: 3px 6px; font-size: 8.8pt; color: #4B4536; }
  .totales td.n { text-align: right; }
  .totales tr.t td { font-size: 11pt; font-weight: bold; color: #17140C; border-top: 1.5px solid #C9A227; padding-top: 6px; }
  .notas { border: 1px solid #E3D6B2; padding: 7px 10px; font-size: 8.5pt; }
  .contacto { margin-top: 16px; border: 1px solid #E3D6B2; }
  .contacto td { padding: 8px 12px; vertical-align: middle; }
  .contacto .lbl { font-size: 6.5pt; color: #8A7A46; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .contacto .nom { font-size: 10.5pt; font-weight: bold; color: #17140C; }
  .contacto .sub { font-size: 8pt; color: #6B6249; }
  .contacto .wa { width: 110px; text-align: right; }
  .contacto .wa a { display: inline-block; background: #16A34A; color: #fff; font-weight: bold; padding: 6px 12px; font-size: 8.5pt; }
  .cta { margin-top: 10px; background: #FFFBEB; border: 1px solid #F3D27A; }
  .cta td { padding: 10px 12px; vertical-align: middle; }
  .cta-txt { font-size: 8.5pt; color: #5B4A12; }
  .cta-btn { width: 170px; text-align: right; white-space: nowrap; }
  .cta-btn a { display: inline-block; background: #C9A227; color: #17140C; font-weight: bold; text-decoration: none; padding: 7px 12px; font-size: 8.5pt; }
  .totales tr.desc td { font-size: 7.5pt; color: #047857; padding-top: 1px; padding-bottom: 1px; }
  .ventajas { margin-top: 14px; background: #FFFBEB; border: 1px solid #F1D98A; border-left: 3px solid #C9A227; }
  .ventajas td { padding: 2px 12px; vertical-align: top; width: 50%; }
  .ventajas td.vl { padding-top: 7px; font-size: 6.8pt; color: #8A6D14; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .ventajas .vi { font-size: 8pt; color: #3A3526; padding: 1px 0; }
  .ventajas tr:last-child td { padding-bottom: 7px; }
  .contacto td.emp { width: 46%; border-right: 1px solid #E3D6B2; }
  .pie { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 6.8pt; color: #8A7A46; border-top: 1px solid #E3D6B2; padding-top: 4px; }
  .pie td { font-size: 6.8pt; color: #8A7A46; }
</style></head><body>

<div class="pie"><table><tr>
  <td>SEGURMEX · ' . pdfH(COTIZACION_EMPRESA_ERP['giro']) . ' · ' . pdfH($cot['folio']) . '</td>
  <td style="text-align:right">Cotización sujeta a cambios sin previo aviso · Generada el ' . date('d/m/Y') . '</td>
</tr></table></div>

<table class="hdr"><tr>
  <td class="logo" style="width:150px">' . ($logoSrc ? '<img src="' . $logoSrc . '" alt="SEGURMEX">' : '<b>SEGURMEX</b>') . '</td>
  <td style="padding-left:12px">
    <div class="titulo">COTIZACIÓN</div>
    <div class="cliente"><b>' . pdfH($cot['cliente_nombre']) . '</b></div>'
    . (!empty($cot['cliente_rfc']) ? '<div class="rfc">RFC: ' . pdfH($cot['cliente_rfc']) . '</div>' : '') . '
  </td>
  <td class="folio">
    <div class="lbl">Folio</div>
    <div class="num">' . pdfH($cot['folio']) . '</div>
    <div class="fechas">Emitida ' . $fecha . ($cerrada ? '' : '<br>Vence ' . $vence) . '</div>
  </td>
</tr></table>
<div class="regla"></div>
' . $estadoHtml . '
<table class="stats"><tr>
  <td style="width:38%; padding-right:6px"><div class="stat"><div class="l">Pares</div><div class="v">' . (int)($cot['total_pares'] ?? 0) . '</div><div class="n">' . $nModelos . ' modelo' . ($nModelos === 1 ? '' : 's') . '</div></div></td>
  <td><div class="stat total"><div class="l">Total</div><div class="v">' . money($cot['total']) . '</div><div class="n">IVA incluido</div></div></td>
</tr></table>

<div class="seccion">Datos del cliente</div>
<table class="datos"><tr>
  <td class="k">Contacto:</td><td class="v">' . pdfDato($cot['cliente_contacto'] ?? '') . '</td>
  <td class="k">Teléfono:</td><td class="v">' . pdfDato(pdfTelefono($cot['cliente_telefono'] ?? '')) . '</td>
</tr><tr>
  <td class="k">Correo:</td><td class="v">' . pdfDato($cot['cliente_email'] ?? '') . '</td>
  <td class="k">Dirección:</td><td class="v">' . pdfDato($cot['cliente_direccion'] ?? '') . '</td>
</tr></table>

<div class="seccion">Modelos cotizados</div>
<table class="det">
  <thead><tr><th colspan="2">Modelo</th><th class="num" style="width:44px">Pares</th><th class="num" style="width:82px">Precio unitario</th><th class="num" style="width:82px">Importe</th></tr></thead>
  <tbody>' . $filas . '</tbody>
</table>' . $leyenda . '
<table class="totales">
  <tr><td>Subtotal</td><td class="n">' . money($cot['subtotal']) . '</td></tr>' . $descFilas . '
  <tr><td>IVA (' . $tasa . '%)</td><td class="n">' . money($cot['iva']) . '</td></tr>
  <tr class="t"><td>Total</td><td class="n">' . money($cot['total']) . '</td></tr>
</table>

<div class="seccion">Condiciones</div>
<table class="datos"><tr>
  <td class="k">Vigencia:</td><td class="v">' . (int)($cot['vigencia_dias'] ?? 0) . ' días (hasta el ' . $vence . ')</td>
  <td class="k">Lista:</td><td class="v">' . $lista . ($descuentos ? ' · con ' . implode(' y ', $descuentos) : '') . '</td>
</tr><tr>
  <td class="k">Tiempo de entrega:</td><td class="v">' . pdfH($cot['tiempo_entrega'] ?: 'A confirmar') . '</td>
  <td class="k">Forma de pago:</td><td class="v">' . pdfH($cot['forma_pago'] ?: 'A confirmar') . '</td>
</tr></table>
' . $notas . $ventajas . $contacto . $responder . '
</body></html>';
}

/** Genera el PDF y regresa sus bytes. */
function pdfCotizacion(array $cot, array $detalle, string $atiende = '', ?string $urlResponder = null, array $extra = []): string {
    $opt = new Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);      // nada se baja de internet
    $opt->set('isPhpEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', sys_get_temp_dir()); // no escribir dentro del repo
    $opt->set('chroot', [realpath(__DIR__ . '/..')]);

    $dompdf = new Dompdf($opt);
    $dompdf->loadHtml(htmlPdfCotizacion($cot, $detalle, $atiende, $urlResponder, $extra), 'UTF-8');
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
