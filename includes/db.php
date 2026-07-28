<?php
// Conexión a la base de datos: Supabase (PostgreSQL), mismo proyecto que usa
// el ERP pero en el esquema "visitas" (tablas propias, aisladas del ERP).
// Credenciales desde .env (no se versiona en git). Ver .env.example.
// Mismo patrón de carga de .env que usa el ERP en su config.php.
//
// Nota: si en el futuro montan su propio servidor Postgres/MySQL en la
// empresa (según el plan de dejar Supabase), solo hay que cambiar el DSN de
// abajo y las variables del .env — el resto del código no depende de esto.

function envConfig(string $clave, ?string $default = null): ?string {
    static $env = null;
    if ($env === null) {
        $env = [];
        $ruta = __DIR__ . '/../.env';
        if (is_readable($ruta)) {
            foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
                $linea = trim($linea);
                if ($linea === '' || $linea[0] === '#') continue;
                $pos = strpos($linea, '=');
                if ($pos === false) continue;
                $env[trim(substr($linea, 0, $pos))] = trim(substr($linea, $pos + 1));
            }
        }
    }
    $sys = getenv($clave);
    if ($sys !== false && $sys !== '') return $sys;
    return $env[$clave] ?? $default;
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $host = envConfig('SUPABASE_DB_HOST');
            $port = envConfig('SUPABASE_DB_PORT', '5432');
            $name = envConfig('SUPABASE_DB_NAME', 'postgres');
            $user = envConfig('SUPABASE_DB_USER');
            $pass = envConfig('SUPABASE_DB_PASSWORD');
            $schema = envConfig('DB_SCHEMA', 'visitas');

            if (!$host || !$user || !$pass) {
                throw new RuntimeException('Faltan credenciales de Supabase en .env (SUPABASE_DB_*).');
            }

            $dsn = "pgsql:host=$host;port=$port;dbname=$name;sslmode=require";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // Forzamos UTC en la sesión para que NOW()/CURRENT_TIMESTAMP sean
            // siempre consistentes sin importar la config del proyecto Supabase.
            // La conversión a hora local para mostrarla al usuario se hace en
            // el frontend (ver assets/js/fecha_utils.js).
            $pdo->exec("SET timezone = 'UTC'");
            // Todas las tablas de este sistema viven en el esquema "visitas",
            // no en "public" (ahí está el ERP). Esto evita tener que prefijar
            // cada consulta con "visitas.".
            $pdo->exec('SET search_path TO ' . $schema);
        } catch (Throwable $e) {
            error_log('[VISITAS] Error de conexión a la BD: ' . $e->getMessage());
            $esAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
                   || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
                   || (stripos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false);
            http_response_code(503);
            if ($esAjax) {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['ok' => false, 'error' => 'No se pudo conectar a la base de datos. Intenta más tarde.']));
            }
            die('<!doctype html><html lang="es"><meta charset="utf-8">'
              . '<title>Servicio no disponible</title>'
              . '<div style="font-family:system-ui,sans-serif;text-align:center;padding:80px 20px;color:#374151">'
              . '<h2 style="color:#DC2626">Servicio temporalmente no disponible</h2>'
              . '<p>No se pudo conectar a la base de datos. Intenta de nuevo en unos minutos.</p>'
              . '</div></html>');
        }
    }
    return $pdo;
}
