<?php
/**
 * ONE VISION COMMUNITY — DÉCONNEXION
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

$token = $_POST['csrf_token'] ?? $_GET['token'] ?? $_GET['csrf_token'] ?? null;

if (!verify_csrf_token($token)) {
    set_flash('error', 'Lien de déconnexion invalide ou expiré.');
    header('Location: index.php');
    exit;
}

logout_user();
set_flash('info', 'Vous avez été déconnecté avec succès. À très vite !');
header('Location: login.php');
exit;
