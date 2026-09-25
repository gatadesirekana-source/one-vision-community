<?php
/**
 * ONE VISION COMMUNITY — CONFIGURATION GLOBALE
 * Support complet des environnements Production & Sandbox SasaPay / SasPay
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

// 3. Définition de l'environnement (production ou sandbox)
$envMode = strtolower(getenv('SASAPAY_ENV') ?: getenv('SASPAY_ENV') ?: 'production');
define('SASPAY_ENV', $envMode);

// 4. URL de base de l'API selon l'environnement (Production vs Sandbox)
$customApiUrl = getenv('SASAPAY_API_URL') ?: getenv('SASPAY_API_URL');
if (!empty($customApiUrl)) {
    define('SASPAY_API_URL', rtrim($customApiUrl, '/'));
} else {
    if ($envMode === 'sandbox' || $envMode === 'test') {
        define('SASPAY_API_URL', 'https://api-sandbox.saspay.me/api/v1');
    } else {
        define('SASPAY_API_URL', 'https://api.saspay.me/api/v1'); // Production officielle
    }
}

// 5. Identifiants SasaPay / SasPay (récupérés des variables d'environnement)
if (!defined('SASPAY_API_KEY')) {
    define('SASPAY_API_KEY', getenv('SASAPAY_API_KEY') ?: getenv('SASPAY_API_KEY') ?: '');
}
if (!defined('SASPAY_CLIENT_ID')) {
    define('SASPAY_CLIENT_ID', getenv('SASAPAY_CLIENT_ID') ?: getenv('SASPAY_CLIENT_ID') ?: '');
}
if (!defined('SASPAY_CLIENT_SECRET')) {
    define('SASPAY_CLIENT_SECRET', getenv('SASAPAY_CLIENT_SECRET') ?: getenv('SASPAY_CLIENT_SECRET') ?: '');
}
if (!defined('SASPAY_MERCHANT_CODE')) {
    define('SASPAY_MERCHANT_CODE', getenv('SASAPAY_MERCHANT_CODE') ?: getenv('SASPAY_MERCHANT_CODE') ?: '');
}
if (!defined('SASPAY_WEBHOOK_SECRET')) {
    define('SASPAY_WEBHOOK_SECRET', getenv('SASAPAY_WEBHOOK_SECRET') ?: getenv('SASPAY_WEBHOOK_SECRET') ?: '');
}

define('SASPAY_CURRENCY', getenv('SASPAY_CURRENCY') ?: 'EUR');

// 6. Détection et configuration des URLs publiques (APP_URL et CALLBACK/WEBHOOK URL)
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';

// Si la requête provient d'un environnement local (localhost, 127.0.0.1, ou port spécifique),
// on utilise TOUJOURS l'adresse locale actuelle pour que les redirections du navigateur fonctionnent.
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

// URL Callback / IPN publique
$envCallbackUrl = getenv('SASAPAY_CALLBACK_URL') ?: getenv('SASPAY_WEBHOOK_URL');
if (!empty($envCallbackUrl)) {
    define('SASPAY_WEBHOOK_URL', $envCallbackUrl);
} else {
    define('SASPAY_WEBHOOK_URL', APP_URL . '/webhook-saspay.php');
}

// Fuseau horaire
date_default_timezone_set('Europe/Paris');
