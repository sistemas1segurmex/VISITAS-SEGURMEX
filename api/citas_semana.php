<?php
// Total de citas por día en los últimos 7 días (todos los vendedores) —
// alimenta la mini-gráfica de tendencia del panel del admin. Sin filtro por
// vendedor: es un vistazo general de "qué tan lleno" ha estado el equipo.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$stmt = $db->query(
    "SELECT DATE(fecha_hora) AS dia, COUNT(*) AS total
     FROM citas
     WHERE fecha_hora >= CURRENT_DATE - INTERVAL '6 days'
     GROUP BY DATE(fecha_hora)"
);
$porDia = [];
foreach ($stmt->fetchAll() as $r) { $porDia[$r['dia']] = (int)$r['total']; }

// Rellena los días sin ninguna cita con 0, para que la gráfica no tenga huecos.
$dias = [];
$cur = new DateTime('-6 days');
for ($i = 0; $i < 7; $i++) {
    $f = $cur->format('Y-m-d');
    $dias[] = ['fecha' => $f, 'total' => $porDia[$f] ?? 0];
    $cur->modify('+1 day');
}

jsonResponse(['ok' => true, 'dias' => $dias]);
