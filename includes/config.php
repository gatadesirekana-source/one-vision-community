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

// Charger la configuration locale privée si présente (ignorée par Git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Configuration SasPay (Carte Bancaire & Mobile Money)
if (!defined('SASPAY_API_KEY')) {
    define('SASPAY_API_KEY', getenv('SASPAY_API_KEY') ?: 'votre_cle_api_saspay_ici');
}
define('SASPAY_API_URL', 'https://api.saspay.me/api/v1');
define('SASPAY_CURRENCY', 'EUR'); // Montant de l'adhésion 9€/mois
if (!defined('SASPAY_WEBHOOK_SECRET')) {
    define('SASPAY_WEBHOOK_SECRET', getenv('SASPAY_WEBHOOK_SECRET') ?: 'votre_webhook_secret_ici');
}

// Détection URL de base de l'application
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443 ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
define('APP_URL', $protocol . '://' . $host);
define('SASPAY_WEBHOOK_URL', APP_URL . '/webhook-saspay.php');

// Fuseau horaire
date_default_timezone_set('Europe/Paris');
