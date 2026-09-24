<?php
/**
 * ONE VISION COMMUNITY — DÉCONNEXION
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

logout_user();
set_flash('info', 'Vous avez été déconnecté avec succès. À très vite !');
header('Location: login.php');
exit;
