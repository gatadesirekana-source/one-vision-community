<?php
/**
 * ONE VISION COMMUNITY — HEADER GLOBAL PHP
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';

$currentUser = current_user();
$isLoggedIn = is_logged_in();

if (!isset($pageTitle)) {
    $pageTitle = "One Vision Community — Une communauté d'entrepreneurs qui avancent, pas qui attendent";
}
if (!isset($pageDescription)) {
    $pageDescription = "One Vision Community : Vous savez où vous allez, ici vous n'y allez plus seul. Un espace vivant pour 9€/mois sans engagement.";
}
if (!isset($bodyClass)) {
    $bodyClass = "";
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
  <meta name="keywords" content="One Vision Community, communauté entrepreneurs, réseau entrepreneurs, entraide business, masterminds, lives hebdomadaires">

  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">

  <!-- Google Fonts : Great Vibes & Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Stylesheet -->
  <link rel="stylesheet" href="./css/style.css?v=5">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">

  <!-- EN-TÊTE & NAVIGATION -->
  <header class="header">
    <div class="container nav-wrapper">
      <!-- Logo de la marque -->
      <a href="index.php" class="logo" aria-label="Accueil One Vision Community">
        <div class="logo-icon">OV</div>
        <div class="logo-text">
          <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
          <span class="logo-sub">Community</span>
        </div>
      </a>

      <!-- Actions de Navigation dynamiques selon l'état de connexion -->
      <div class="nav-actions">
        <?php if ($isLoggedIn): ?>
          <a href="creer-live.php" class="btn btn-secondary" style="font-size:0.88rem; padding:0.5rem 1rem;">
            <span>🎙️ Créer un Live</span>
          </a>
          <a href="dashboard.php" class="btn btn-secondary open-dashboard-link" style="font-size:0.88rem; padding:0.5rem 1rem; display:inline-flex; align-items:center; gap:0.5rem;">
            <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="Avatar" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
            <span>Mon Espace (<?= htmlspecialchars(explode(' ', $currentUser['full_name'])[0]) ?>)</span>
          </a>
          <a href="logout.php" class="btn btn-outline" style="font-size:0.85rem; padding:0.48rem 0.85rem; border:1px solid var(--color-border); border-radius:8px; color:var(--color-text-muted);" title="Se déconnecter">
            Déconnexion
          </a>
        <?php else: ?>
          <a href="login.php" class="btn btn-secondary" style="font-size:0.88rem; padding:0.55rem 1rem;">
            Se connecter
          </a>
          <a href="checkout.php" class="btn btn-primary">
            <span>Rejoindre pour 9€/mois</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="5" y1="12" x2="19" y2="12"></line>
              <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Messages Flash -->
  <?= render_flash() ?>
