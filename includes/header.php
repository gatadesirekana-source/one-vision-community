<?php
/**
 * ONE VISION COMMUNITY — HEADER GLOBAL PHP
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/permissions.php';

$currentUser = current_user();
$isLoggedIn = is_logged_in();

if (!isset($pageTitle)) {
    $pageTitle = "One Vision Community — Une communauté d'entrepreneurs qui avancent, pas qui attendent";
}
if (!isset($pageDescription)) {
    $pageDescription = "One Vision Community : Vous savez où vous allez, ici vous n'y allez plus seul. Un espace vivant pour 9€/mois sans engagement.";
}
// En-têtes de sécurité HTTP
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
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
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">

  <!-- EN-TÊTE & NAVIGATION -->
  <header class="header">
    <div class="container nav-wrapper">
      <!-- Logo de la marque -->
      <a href="index.php" class="logo" aria-label="Accueil One Vision Community">

        <div class="logo-text">
          <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
          <span class="logo-sub">Community</span>
        </div>
      </a>

      <!-- Actions de Navigation dynamiques selon l'état de connexion -->
      <div class="nav-actions">
        <?php if (!empty($hideHeaderNav)): ?>
          <!-- Navigation masquée -->
        <?php elseif (!empty($isCreateLivePage)): ?>
          <!-- Mode Création de Live : Bouton Retour + Bouton Profil à droite -->
          <button type="button" class="btn btn-secondary btn-return-trigger" id="btnHeaderReturn" style="font-size:0.88rem; padding:0.5rem 1rem; display:inline-flex; align-items:center; gap:0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Retour</span>
          </button>
          <a href="dashboard.php" class="btn btn-outline" id="btnHeaderProfile" style="font-size:0.88rem; padding:0.48rem 0.95rem; border:1px solid var(--border-light, #cbd5e1); border-radius:10px; background:#ffffff; color:var(--color-text, #0f172a); display:inline-flex; align-items:center; gap:0.5rem; font-weight:700;" title="Voir mon profil et mon espace">
            <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="Avatar" style="width:24px; height:24px; border-radius:50%; object-fit:cover; border:1.5px solid #2563eb;">
            <span>Profil</span>
          </a>
        <?php elseif ($isLoggedIn): ?>
          <?php if (empty($isAbonnementsPage) && empty($hideHeaderNav)): ?>
            <?php if (is_admin_user($currentUser)): ?>
              <a href="admin/index.php" class="btn btn-secondary" style="font-size:0.85rem; padding:0.48rem 0.85rem; background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-weight:700; display:inline-flex; align-items:center; gap:0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path>
                </svg>
                <span>Administration</span>
              </a>
            <?php endif; ?>

            <?php if (is_animateur_user($currentUser)): ?>
              <a href="espace-animateur.php" class="btn btn-secondary" style="font-size:0.85rem; padding:0.48rem 0.85rem; background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; font-weight:700; display:inline-flex; align-items:center; gap:0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"></path>
                  <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                  <line x1="12" y1="19" x2="12" y2="23"></line>
                  <line x1="8" y1="23" x2="16" y2="23"></line>
                </svg>
                <span>Espace Animateur</span>
              </a>
            <?php endif; ?>

            <a href="abonnements.php" class="btn btn-secondary" style="font-size:0.85rem; padding:0.48rem 0.85rem; display:inline-flex; align-items:center; gap:0.4rem;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 3h12l4 6-10 13L2 9z"></path>
                <path d="M11 3L8 9l4 13 4-13-3-6"></path>
                <path d="M2 9h20"></path>
              </svg>
              <span>Abonnements</span>
            </a>

            <a href="dashboard.php" class="btn btn-secondary open-dashboard-link" style="font-size:0.88rem; padding:0.5rem 1rem; display:inline-flex; align-items:center; gap:0.5rem;">
              <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="Avatar" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
              <span>Mon Espace (<?= htmlspecialchars(explode(' ', $currentUser['full_name'])[0]) ?>)</span>
            </a>

            <a href="logout.php?token=<?= urlencode(csrf_token()) ?>" class="btn btn-outline" style="font-size:0.85rem; padding:0.48rem 0.85rem; border:1px solid var(--color-border); border-radius:8px; color:var(--color-text-muted);" title="Se déconnecter">
              Déconnexion
            </a>
          <?php endif; ?>
        <?php else: ?>
          <a href="login.php" class="btn btn-primary">
            <span>Rejoindre le réseau</span>
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
