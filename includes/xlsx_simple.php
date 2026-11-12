<?php
// Generador mínimo de Excel (.xlsx) sin librerías (12-nov-2026), para
// exportar la cartera de vendedores (api/exportar_cartera.php).
//
// Visitas no tiene Composer ni PhpSpreadsheet, y ZipArchive no siempre está
// habilitado en XAMPP, así que el .zip se arma a mano (deflate con zlib, o
// sin comprimir si no hay zlib). Alcanza para lo que se usa aquí: varias
// hojas, encabezado en negritas con fondo, encabezado fijo, filtros, anchos
// de columna, fechas reales de Excel y enlaces con HYPERLINK().
//
// Uso:
//   $x = new XlsxSimple();
//   $x->agregarHoja('Vendedor A', [
//       'titulo'   => 'Cartera de Vendedor A',      // fila 1 (opcional)
//       'subtitulo'=> 'Exportado el ...',           // fila 2 (opcional)
//       'columnas' => [['Nombre', 30, 'texto'], ['Registrado el', 14, 'fecha'], ...],
//       'filas'    => [['Cliente X', '2026-11-12'], ...],
//       'vacio'    => 'Sin clientes ni prospectos', // si no hay filas
//   ]);
//   $x->enviar('Cartera.xlsx');
//
// Tipos de columna: 'texto', 'numero', 'fecha' ('AAAA-MM-DD'), 'enlace'
// (valor = [url, texto visible]).

class XlsxSimple {
    private array $hojas = [];

    public function agregarHoja(string $nombre, array $def): void {
        $this->hojas[] = ['nombre' => $this->nombreHoja($nombre), 'def' => $def];
    }

    /** Excel: máx. 31 caracteres, sin : \ / ? * [ ] y sin repetir. */
    private function nombreHoja(string $nombre): string {
        $n = trim(preg_replace('/[:\\\\\/\?\*\[\]]/u', ' ', $nombre));
        $n = preg_replace('/\s+/u', ' ', $n);
        if ($n === '') $n = 'Hoja';
        $n = mb_substr($n, 0, 31);
        $usados = array_map(fn($h) => mb_strtolower($h['nombre']), $this->hojas);
        $base = $n; $i = 2;
        while (in_array(mb_strtolower($n), $usados, true)) {
            $suf = ' (' . $i++ . ')';
            $n = mb_substr($base, 0, 31 - mb_strlen($suf)) . $suf;
        }
        return $n;
    }

    public function contenido(): string {
        if (!$this->hojas) $this->agregarHoja('Hoja', ['columnas' => [], 'filas' => []]);
        $f = [];
        $f['[Content_Types].xml'] = $this->contentTypes();
        $f['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $f['xl/workbook.xml'] = $this->workbook();
        $f['xl/_rels/workbook.xml.rels'] = $this->workbookRels();
        $f['xl/styles.xml'] = $this->estilos();
        foreach ($this->hojas as $i => $h) {
            $f['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->hoja($h['def']);
        }
        return $this->zip($f);
    }

    public function enviar(string $archivo): void {
        $bin = $this->contenido();
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $archivo);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($archivo));
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: no-store, max-age=0');
        echo $bin;
    }

    // ── Partes del archivo ─────────────────────────────────────────────

    private function contentTypes(): string {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->hojas as $i => $_) {
            $x .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $x . '</Types>';
    }

    private function workbook(): string {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($this->hojas as $i => $h) {
            $x .= '<sheet name="' . $this->esc($h['nombre']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $x .= '</sheets><definedNames>';
        foreach ($this->hojas as $i => $h) {
            $rango = $this->rangoFiltro($h['def']);
            if ($rango) {
                $x .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\''
                    . $this->esc(str_replace("'", "''", $h['nombre'])) . '\'!' . $rango . '</definedName>';
            }
        }
        return $x . '</definedNames></workbook>';
    }

    private function workbookRels(): string {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $n = count($this->hojas);
        foreach ($this->hojas as $i => $_) {
            $x .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $x .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $x . '</Relationships>';
    }

    // Estilos (índice = s="n"): 0 normal · 1 encabezado · 2 fecha · 3 título
    // · 4 subtítulo · 5 enlace · 6 número · 7 aviso de hoja vacía.
    private function estilos(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>'
            . '<fonts count="6">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><i/><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><u/><sz val="11"/><color rgb="FF1D4ED8"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><i/><sz val="11"/><color rgb="FF92400E"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1D6F42"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD9D9D9"/></left><right style="thin"><color rgb="FFD9D9D9"/></right>'
            . '<top style="thin"><color rgb="FFD9D9D9"/></top><bottom style="thin"><color rgb="FFD9D9D9"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** Fila del encabezado de columnas (1-based): 1, o 4 si hay título/subtítulo. */
    private function filaEncabezado(array $def): int {
        return (!empty($def['titulo']) || !empty($def['subtitulo'])) ? 4 : 1;
    }

    private function rangoFiltro(array $def): ?string {
        $cols = $def['columnas'] ?? [];
        $filas = $def['filas'] ?? [];
        if (!$cols || !$filas) return null;
        $fe = $this->filaEncabezado($def);
        return '$A$' . $fe . ':$' . $this->col(count($cols) - 1) . '$' . ($fe + count($filas));
    }

    private function hoja(array $def): string {
        $cols  = $def['columnas'] ?? [];
        $filas = $def['filas'] ?? [];
        $fe    = $this->filaEncabezado($def);
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0">';
        if ($cols && $filas) {
            // Encabezado fijo: lo de arriba no se mueve al bajar.
            $x .= '<pane ySplit="' . $fe . '" topLeftCell="A' . ($fe + 1) . '" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="bottomLeft" activeCell="A' . ($fe + 1) . '" sqref="A' . ($fe + 1) . '"/>';
        }
        $x .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
        if ($cols) {
            $x .= '<cols>';
            foreach ($cols as $i => $c) {
                $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)($c[1] ?? 14) . '" customWidth="1"/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        if ($fe === 4) {
            $x .= '<row r="1">' . $this->celdaTexto('A1', (string)($def['titulo'] ?? ''), 3) . '</row>';
            $x .= '<row r="2">' . $this->celdaTexto('A2', (string)($def['subtitulo'] ?? ''), 4) . '</row>';
        }
        if ($cols) {
            $x .= '<row r="' . $fe . '" ht="30" customHeight="1">';
            foreach ($cols as $i => $c) $x .= $this->celdaTexto($this->col($i) . $fe, (string)$c[0], 1);
            $x .= '</row>';
        }
        if (!$filas && !empty($def['vacio'])) {
            $r = $fe + 1;
            $x .= '<row r="' . $r . '">' . $this->celdaTexto('A' . $r, (string)$def['vacio'], 7) . '</row>';
        }
        foreach (array_values($filas) as $n => $fila) {
            $r = $fe + 1 + $n;
            $x .= '<row r="' . $r . '">';
            foreach ($cols as $i => $c) {
                $ref = $this->col($i) . $r;
                $v = $fila[$i] ?? null;
                $tipo = $c[2] ?? 'texto';
                if ($v === null || $v === '' || (is_array($v) && empty($v[0]))) { $x .= '<c r="' . $ref . '" s="0"/>'; continue; }
                if ($tipo === 'fecha') {
                    $serie = $this->serieFecha((string)$v);
                    $x .= $serie === null ? $this->celdaTexto($ref, (string)$v, 0) : '<c r="' . $ref . '" s="2"><v>' . $serie . '</v></c>';
                } elseif ($tipo === 'numero' && is_numeric($v)) {
                    $x .= '<c r="' . $ref . '" s="6"><v>' . (0 + $v) . '</v></c>';
                } elseif ($tipo === 'enlace' && is_array($v)) {
                    $url = str_replace('"', '""', (string)$v[0]);
                    $txt = str_replace('"', '""', (string)($v[1] ?? $v[0]));
                    $x .= '<c r="' . $ref . '" s="5" t="str"><f>' . $this->esc('HYPERLINK("' . $url . '","' . $txt . '")') . '</f><v>' . $this->esc((string)($v[1] ?? $v[0])) . '</v></c>';
                } else {
                    $x .= $this->celdaTexto($ref, (string)$v, 0);
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if ($rango = $this->rangoFiltro($def)) $x .= '<autoFilter ref="' . str_replace('$', '', $rango) . '"/>';
        $x .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape"/>';
        return $x . '</worksheet>';
    }

    private function celdaTexto(string $ref, string $v, int $estilo): string {
        // Texto en línea (sin tabla de cadenas compartidas). Un texto que empiece
        // con = + - @ se guarda como texto, nunca se evalúa como fórmula.
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
        return '<c r="' . $ref . '" s="' . $estilo . '" t="inlineStr"><is><t xml:space="preserve">' . $this->esc($v) . '</t></is></c>';
    }

    /** 'AAAA-MM-DD' -> número de serie de Excel (días desde 1899-12-30). */
    private function serieFecha(string $v): ?int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) return null;
        $d = gmmktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        return (int)round(($d - gmmktime(0, 0, 0, 12, 30, 1899)) / 86400);
    }

    private function col(int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    // ── .zip a mano (formato PKZIP básico) ─────────────────────────────

    private function zip(array $archivos): string {
        $datos = ''; $central = ''; $n = 0;
        $comprimir = function_exists('gzdeflate');
        [$hora, $fecha] = $this->fechaDos();
        foreach ($archivos as $nombre => $contenido) {
            $crc = crc32($contenido);
            $cuerpo = $comprimir ? gzdeflate($contenido, 6) : $contenido;
            $metodo = $comprimir ? 8 : 0;
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $metodo, $hora, $fecha,
                          $crc, strlen($cuerpo), strlen($contenido), strlen($nombre), 0);
            $offset = strlen($datos);
            $datos .= $local . $nombre . $cuerpo;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $metodo, $hora, $fecha,
                             $crc, strlen($cuerpo), strlen($contenido), strlen($nombre), 0, 0, 0, 0, 0, $offset) . $nombre;
            $n++;
        }
        $fin = pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), strlen($datos), 0);
        return $datos . $central . $fin;
    }

    private function fechaDos(): array {
        $t = getdate();
        $hora  = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $fecha = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        return [$hora, $fecha];
    }
}
