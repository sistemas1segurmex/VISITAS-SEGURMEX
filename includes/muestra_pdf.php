<?php
/**
 * PDF de una solicitud de muestra (09-oct-2026). Lo abren el vendedor que la
 * pidió, la responsable de muestras y el admin desde muestra_pdf.php (con
 * sesión). Reemplaza al formato "Solicitud de muestras" del cotizador
 * anterior, con más datos: foto, cambios de la variante, a quién y dónde se
 * entrega, envío e historial.
 *
 * Mismo estilo y mismas ayudas que el PDF de la cotización
 * (includes/cotizacion_pdf.php: Dompdf, pdfH, pdfDato, pdfFotoModelo...).
 * Dompdf no soporta flex/grid: todo el acomodo va con tablas.
 */
require_once __DIR__ . '/cotizador_helpers.php'; // COTIZACION_EMPRESA_ERP, catálogo
require_once __DIR__ . '/cotizacion_pdf.php';
require_once __DIR__ . '/muestras.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Foto y atributo (PP+D...) del estilo, del mismo catálogo del que se pidió.
 * Si el ERP no responde o el estilo ya no está, el PDF sale sin foto.
 */
function datosCatalogoMuestraPdf(array $s): array {
    $item = !empty($s['id_estilo_erp']) ? 'e:' . (int)$s['id_estilo_erp']
          : (!empty($s['id_modelo_legacy']) ? 'm:' . (int)$s['id_modelo_legacy'] : null);
    if (!$item) return ['foto' => '', 'atributo' => ''];
    try {
        require_once __DIR__ . '/db_erp.php';
        $e = catalogoParaMuestraErp($item);
        return ['foto' => pdfFotoModelo($e['foto'] ?? null), 'atributo' => (string)($e['atributo'] ?? '')];
    } catch (Throwable $e) {
        error_log('[VISITAS] PDF muestra (catálogo): ' . $e->getMessage());
        return ['foto' => '', 'atributo' => ''];
    }
}

/**
 * HTML que se convierte a PDF. $conNombres: false para el vendedor (al
 * externo no se le muestra quién atiende -- ver vendedor/muestras.php).
 */
function htmlPdfMuestra(array $s, array $historial, bool $conNombres): string {
    $logo    = __DIR__ . '/../assets/img/logo-cotizacion.jpg';
    $logoSrc = is_file($logo) ? 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logo)) : '';
    $emp     = COTIZACION_EMPRESA_ERP;
    $fecha   = date('d/m/Y H:i', strtotime($s['created_at'] ?? 'now') ?: time());
    $estado  = (string)$s['estado'];
    $cat     = datosCatalogoMuestraPdf($s);

    // Aviso de estado arriba (mismos colores que la cotización).
    $clases = ['enviada' => 'ojo', 'en_preparacion' => 'ojo', 'embarcada' => 'ok', 'cancelada' => 'no'];
    $detalleEstado = [
        'enviada'        => 'Recibida, pendiente de atender.',
        'en_preparacion' => (textoPreparacionMuestra($s) ?? 'Se está preparando') . '.',
        'embarcada'      => 'Enviada: ' . textoEnvioMuestra($s) . '.',
        'cancelada'      => 'Motivo: ' . ($s['motivo_cancelacion'] ?? ''),
    ];
    $estadoHtml = '<table class="estado ' . ($clases[$estado] ?? 'ojo') . '"><tr><td><b>'
        . pdfH(mb_strtoupper(etiquetaEstadoMuestra($estado))) . '</b> · ' . pdfH($detalleEstado[$estado] ?? '') . '</td></tr></table>';

    // Renglón de la muestra.
    $foto = $cat['foto'] !== '' ? '<img src="' . $cat['foto'] . '" alt="">' : '<div class="sinfoto">SIN<br>FOTO</div>';
    $tipo = $s['tipo'] === 'variante' ? 'Variante (con cambios)' : 'Idéntico al estilo';
    $fila = '<tr>'
        . '<td class="fcel">' . $foto . '</td>'
        . '<td><div class="modelo">' . pdfH($s['estilo_nombre']) . ($cat['atributo'] !== '' ? ' <span class="attr">' . pdfH($cat['atributo']) . '</span>' : '') . '</div>'
        . '<div class="meta">' . pdfH($tipo) . '</div></td>'
        . '<td>' . pdfDato($s['color'] ?? '') . '</td>'
        . '<td>' . pdfDato($s['talla'] ?? '') . '</td>'
        . '<td class="num fuerte">' . max(1, (int)($s['cantidad'] ?? 1)) . '</td>'
        . '</tr>';

    $cambios = '';
    if (!empty($s['cambios'])) {
        $li = '';
        foreach ($s['cambios'] as $c) {
            $nom = ($c['categoria'] ?? '') === 'otro'
                ? ($c['categoria_otro'] ?? 'Otro')
                : (MUESTRA_CATEGORIAS_CAMBIO[$c['categoria'] ?? ''] ?? ($c['categoria'] ?? ''));
            $li .= '<tr><td class="k">' . pdfH($nom) . ':</td><td class="v">' . nl2br(pdfH($c['descripcion'] ?? '')) . '</td></tr>';
        }
        $cambios = '<div class="seccion">Cambios que pide el cliente</div><table class="datos">' . $li . '</table>';
    }

    $notas = trim((string)($s['notas_planta'] ?? '')) !== ''
        ? '<table class="notas"><tr><td><b>Notas para planta:</b> ' . nl2br(pdfH($s['notas_planta'])) . '</td></tr></table>' : '';

    $promesa = !empty($s['fecha_promesa']) ? date('d/m/Y', strtotime($s['fecha_promesa'])) : '';

    $envio = '';
    if ($estado === 'embarcada') {
        $rastreo = !empty($s['guia_url']) ? ' · <a href="' . pdfH($s['guia_url']) . '">Rastrear envío</a>' : '';
        $envio = '<tr><td class="k">Envío:</td><td class="v" colspan="3">' . pdfH(textoEnvioMuestra($s)) . $rastreo . '</td></tr>';
    }

    // Historial.
    $hist = '';
    $txt = [
        'enviada'        => 'Solicitud enviada',
        'en_preparacion' => 'En preparación',
        'embarcada'      => 'Embarcada',
        'cancelada'      => 'Cancelada',
    ];
    foreach ($historial as $h) {
        $quien = ($conNombres && !empty($h['usuario_nombre'])) ? pdfH($h['usuario_nombre']) : '';
        $hist .= '<tr><td class="hf">' . pdfH(date('d/m/Y H:i', strtotime($h['creado_en']))) . '</td>'
            . '<td><b>' . pdfH($txt[$h['estado']] ?? $h['estado']) . '</b>' . ($quien !== '' ? ' · ' . $quien : '')
            . (!empty($h['nota']) ? '<div class="meta">' . pdfH($h['nota']) . '</div>' : '') . '</td></tr>';
    }

    $vendTel = trim((string)($s['vendedor_telefono'] ?? ''));

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Solicitud de muestra ' . pdfH($s['folio']) . '</title>
<style>
  @page { size: A4; margin: 14mm 14mm 18mm 14mm; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #211D14; }
  table { border-collapse: collapse; width: 100%; }
  a { color: #8A6D14; text-decoration: underline; }
  .hdr td { vertical-align: middle; }
  .hdr .logo img { height: 46px; }
  .titulo { font-size: 16pt; font-weight: bold; color: #B8901E; letter-spacing: 1px; }
  .sub { font-size: 7.5pt; color: #6B6249; }
  .folio { border: 1px solid #E3D6B2; border-top: 3px solid #C9A227; padding: 7px 12px; text-align: right; width: 170px; }
  .folio .lbl { font-size: 6.5pt; color: #8A7A46; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .folio .num { font-size: 14pt; font-weight: bold; }
  .folio .fechas { font-size: 7pt; color: #6B6249; margin-top: 2px; }
  .regla { height: 2px; background: #C9A227; margin: 10px 0 12px; }
  .estado { margin-bottom: 10px; }
  .estado td { padding: 7px 12px; font-size: 8.5pt; border: 1px solid; }
  .estado b { letter-spacing: .5px; }
  .estado.ok td  { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
  .estado.no td  { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }
  .estado.ojo td { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
  .cajas td.c { width: 50%; vertical-align: top; border: 1px solid #E3D6B2; border-left: 3px solid #C9A227; padding: 8px 12px; }
  .cajas td.sep { width: 8px; }
  .lbl { font-size: 6.5pt; color: #8A7A46; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
  .nom { font-size: 10.5pt; font-weight: bold; color: #17140C; margin-top: 2px; }
  .seccion { font-size: 9.5pt; font-weight: bold; border-bottom: 1.5px solid #E3D6B2; padding-bottom: 3px; margin: 14px 0 6px; }
  .datos td { padding: 2px 0; vertical-align: top; font-size: 8.5pt; }
  .datos .k { color: #6B6249; width: 110px; padding-right: 8px; white-space: nowrap; }
  .datos .v { font-weight: bold; padding-right: 14px; }
  .vacio { color: #A79E86; font-weight: normal; font-style: italic; }
  .det th { font-size: 6.8pt; text-transform: uppercase; letter-spacing: .5px; color: #6B6249; text-align: left; padding: 5px 6px; border-bottom: 1.5px solid #C9A227; }
  .det td { padding: 6px; border-bottom: 1px solid #EAE0C6; vertical-align: middle; font-size: 8.5pt; }
  .det .num, .det th.num { text-align: right; white-space: nowrap; }
  .det td.fcel { width: 60px; padding-right: 2px; }
  .det .fcel img { width: 56px; height: 56px; border: 0.6pt solid #EEE7D3; }
  .sinfoto { width: 56px; height: 40px; padding-top: 16px; border: 0.6pt solid #EEE7D3; background: #FBF7EA; color: #C9B98A; font-size: 6pt; font-weight: bold; text-align: center; line-height: 1.1; }
  .det .modelo { font-weight: bold; font-size: 10pt; }
  .meta { font-size: 7.5pt; color: #6B7280; margin-top: 1px; }
  .fuerte { font-weight: bold; }
  .attr { font-size: 6.5pt; font-weight: bold; color: #8A6D14; border: 0.6pt solid #C9A227; padding: 0 3px; }
  .notas { margin-top: 10px; background: #FFFBEB; border: 1px solid #F1D98A; border-left: 3px solid #C9A227; }
  .notas td { padding: 8px 12px; font-size: 8.5pt; }
  .hist td { padding: 4px 6px; border-bottom: 1px solid #EAE0C6; font-size: 8pt; vertical-align: top; }
  .hist td.hf { width: 95px; color: #6B6249; white-space: nowrap; }
  .pie { position: fixed; bottom: -11mm; left: 0; right: 0; border-top: 1px solid #E3D6B2; padding-top: 4px; }
  .pie td { font-size: 6.8pt; color: #8A7A46; }
</style></head><body>

<div class="pie"><table><tr>
  <td>SEGURMEX · Documento interno · ' . pdfH($s['folio']) . '</td>
  <td style="text-align:right">Generado desde Control de Visitas el ' . date('d/m/Y H:i') . '</td>
</tr></table></div>

<table class="hdr"><tr>
  <td class="logo" style="width:150px">' . ($logoSrc ? '<img src="' . $logoSrc . '" alt="SEGURMEX">' : '<b>SEGURMEX</b>') . '</td>
  <td style="padding-left:12px">
    <div class="titulo">SOLICITUD DE MUESTRA</div>
    <div class="sub">' . pdfH($emp['giro']) . ' · RFC ' . pdfH($emp['rfc']) . '<br>' . pdfH($emp['direccion']) . ', ' . pdfH($emp['ciudad']) . '</div>
  </td>
  <td class="folio">
    <div class="lbl">Folio</div>
    <div class="num">' . pdfH($s['folio']) . '</div>
    <div class="fechas">Solicitada ' . $fecha . '</div>
  </td>
</tr></table>
<div class="regla"></div>
' . $estadoHtml . '

<table class="cajas"><tr>
  <td class="c">
    <div class="lbl">Cliente</div>
    <div class="nom">' . pdfH($s['cliente_nombre']) . '</div>
    <table class="datos" style="margin-top:4px">
      <tr><td class="k">Contacto:</td><td class="v">' . pdfDato($s['cliente_contacto'] ?? '') . '</td></tr>
      <tr><td class="k">Teléfono:</td><td class="v">' . pdfDato(pdfTelefono($s['cliente_telefono'] ?? '')) . '</td></tr>
    </table>
  </td>
  <td class="sep"></td>
  <td class="c">
    <div class="lbl">Solicitada por</div>
    <div class="nom">' . pdfH($s['vendedor_nombre']) . '</div>
    <table class="datos" style="margin-top:4px">
      <tr><td class="k">Teléfono:</td><td class="v">' . pdfDato($vendTel !== '' ? pdfTelefono($vendTel) : '') . '</td></tr>
      <tr><td class="k">Correo:</td><td class="v">' . pdfDato($s['vendedor_email'] ?? '') . '</td></tr>
    </table>
  </td>
</tr></table>

<div class="seccion">Muestra solicitada</div>
<table class="det">
  <thead><tr><th colspan="2">Modelo</th><th style="width:80px">Color</th><th style="width:55px">Talla</th><th class="num" style="width:45px">Pares</th></tr></thead>
  <tbody>' . $fila . '</tbody>
</table>
' . $cambios . '

<div class="seccion">Motivo y prueba</div>
<table class="datos">
  <tr><td class="k">Motivo:</td><td class="v" colspan="3">' . (trim((string)($s['motivo'] ?? '')) !== '' ? nl2br(pdfH($s['motivo'])) : '<span class="vacio">Sin registro</span>') . '</td></tr>
  <tr><td class="k">Fecha requerida:</td><td class="v">' . pdfDato($promesa) . '</td>
      <td class="k">Tiempo de prueba:</td><td class="v">' . pdfDato(textoTiempoPruebaMuestra($s['tiempo_prueba_dias'] ?? null)) . '</td></tr>
</table>
' . $notas . '

<div class="seccion">Entrega</div>
<table class="datos">
  <tr><td class="k">Entregar a:</td><td class="v" colspan="3">' . pdfH(textoEntregarAMuestra($s)) . '</td></tr>
  <tr><td class="k">Dirección:</td><td class="v" colspan="3">' . nl2br(pdfH($s['destino_direccion'])) . '</td></tr>
  ' . $envio . '
</table>

<div class="seccion">Historial</div>
<table class="hist">' . $hist . '</table>
</body></html>';
}

/** Genera el PDF y regresa sus bytes. */
function pdfMuestra(array $s, array $historial, bool $conNombres): string {
    $opt = new Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('isPhpEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', sys_get_temp_dir());
    $opt->set('chroot', [realpath(__DIR__ . '/..')]);

    $dompdf = new Dompdf($opt);
    $dompdf->loadHtml(htmlPdfMuestra($s, $historial, $conNombres), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $canvas = $dompdf->getCanvas();
    if ($canvas->get_page_count() > 1) {
        $fm = $dompdf->getFontMetrics();
        $canvas->page_text(268, 828, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fm->getFont('DejaVu Sans'), 6.5, [0.54, 0.48, 0.27]);
    }
    return $dompdf->output();
}
