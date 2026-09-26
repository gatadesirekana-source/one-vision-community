<?php
/**
 * ONE VISION COMMUNITY — CONFIGURATION GLOBALE
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'One Vision Community');
define('APP_TAGLINE', "Une communauté d'entrepreneurs qui avancent, pas qui attendent");
define('APP_PRICE_MONTHLY', 9);
define('BASE_DIR', dirname(__DIR__));
define('DB_FILE', BASE_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite');

/**
 * Chargeur autonome de fichier .env (sans dépendance externe)
 */
function load_env(string $path): void {
    if (!file_exists($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if (strlen($val) >= 2 && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
                $val = substr($val, 1, -1);
            }
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 1. Chargement prioritaire du fichier .env
load_env(BASE_DIR . DIRECTORY_SEPARATOR . '.env');

// 2. Chargement de config.local.php si présent (fallback de compatibilité)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// 3. Détection et configuration de l'URL publique de base (APP_URL)
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';

if (!empty($currentHost) && (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false || strpos($currentHost, ':') !== false)) {
    define('APP_URL', $protocol . '://' . $currentHost);
} else {
    $envAppUrl = getenv('APP_URL') ?: getenv('PUBLIC_APP_URL');
    if (!empty($envAppUrl)) {
        define('APP_URL', rtrim($envAppUrl, '/'));
    } else {
        $host = !empty($currentHost) ? $currentHost : 'localhost:8080';
        define('APP_URL', $protocol . '://' . $host);
    }
}

// Fuseau horaire
date_default_timezone_set('Europe/Paris');
