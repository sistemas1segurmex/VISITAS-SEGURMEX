<?php
/**
 * Segunda conexión a la MISMA base de Supabase que usa includes/db.php, pero
 * sin fijar el search_path a "visitas" -- esta lee/escribe en el esquema
 * "public", que es donde vive el ERP (cotizaciones, cotizador_prospectos,
 * estilos, usuarios, config_cotizador).
 *
 * No es una conexión a otro servidor: es el mismo proyecto de Postgres, dos
 * "cajones" (esquemas) distintos -- por eso basta con reusar las mismas
 * credenciales SUPABASE_DB_* del .env, ya cargadas por includes/db.php.
 *
 * Ver docs/arquitectura-cotizaciones-foraneos.md (en el repo del ERP) para
 * el diseño completo de "Cotizaciones para vendedores foráneos".
 */

require_once __DIR__ . '/db.php';      // trae envConfig()
require_once __DIR__ . '/helpers.php'; // trae jsonResponse()

function getDBErp(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $host = envConfig('SUPABASE_DB_HOST');
            $port = envConfig('SUPABASE_DB_PORT', '5432');
            $name = envConfig('SUPABASE_DB_NAME', 'postgres');
            $user = envConfig('SUPABASE_DB_USER');
            $pass = envConfig('SUPABASE_DB_PASSWORD');

            if (!$host || !$user || !$pass) {
                throw new RuntimeException('Faltan credenciales de Supabase en .env (SUPABASE_DB_*).');
            }

            $dsn = "pgsql:host=$host;port=$port;dbname=$name;sslmode=require";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // Sin SET search_path: se queda en "public" (default), igual que
            // la propia conexión del ERP en config.php -- ahí es donde viven
            // las tablas del Cotizador.
        } catch (Throwable $e) {
            error_log('[VISITAS] Error de conexión al ERP (público): ' . $e->getMessage());
            $esAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
                   || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
                   || (stripos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false);
            if ($esAjax) {
                jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el Cotizador. Intenta más tarde.'], 503);
            }
            http_response_code(503);
            die('<!doctype html><html lang="es"><meta charset="utf-8">'
              . '<title>Servicio no disponible</title>'
              . '<div style="font-family:system-ui,sans-serif;text-align:center;padding:80px 20px;color:#374151">'
              . '<h2 style="color:#DC2626">Servicio temporalmente no disponible</h2>'
              . '<p>No se pudo conectar con el Cotizador. Intenta de nuevo en unos minutos.</p>'
              . '</div></html>');
        }
    }
    return $pdo;
}
