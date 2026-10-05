<?php
/**
 * ONE VISION COMMUNITY — DÉCONNEXION
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

$token = $_POST['csrf_token'] ?? $_GET['token'] ?? $_GET['csrf_token'] ?? null;
$redirect = $_GET['redirect'] ?? 'login.php';
$allowedRedirects = ['index.php', 'login.php', 'register.php'];
if (!in_array($redirect, $allowedRedirects, true)) {
    $redirect = 'login.php';
}

$isMinorOrQuiet = !empty($_GET['quiet']) || !empty($_GET['minor']);

// Déconnexion avec token CSRF ou sortie directe depuis le questionnaire/mineur
if (verify_csrf_token($token) || $isMinorOrQuiet) {
    logout_user();
    if (!$isMinorOrQuiet) {
        set_flash('info', 'Vous avez été déconnecté avec succès. À très vite !');
    }
    header('Location: ' . $redirect);
    exit;
}

set_flash('error', 'Lien de déconnexion invalide ou expiré.');
header('Location: index.php');
exit;

