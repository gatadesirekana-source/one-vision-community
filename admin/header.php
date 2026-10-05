<?php
/**
 * ONE VISION COMMUNITY — HEADER ESPACE ADMINISTRATION
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/permissions.php';

// Contrôle strict de niveau admin côté serveur
require_admin_access('../login.php');

$currentUser = current_user();
$isOwner = is_owner($currentUser);

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? "Administration — One Vision Community") ?></title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css?v=5">
  <style>
    .admin-nav-tabs {
      display: flex;
      gap: 0.5rem;
      background: #ffffff;
      padding: 0.5rem;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      flex-wrap: wrap;
      margin-bottom: 2rem;
    }
    .admin-nav-link {
      padding: 0.6rem 1.1rem;
      font-size: 0.88rem;
      font-weight: 700;
      color: #64748b;
      text-decoration: none;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      transition: all 0.2s;
    }
    .admin-nav-link:hover {
      color: #0f172a;
      background: #f1f5f9;
    }
    .admin-nav-link.active {
      color: #ffffff;
      background: #0f172a;
    }
    .admin-kpi-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
  </style>
</head>
<body class="dashboard-body" style="background:#f8fafc;">

  <!-- TOPBAR ADMIN -->
  <header class="dash-topbar" style="border-bottom: 2px solid #0f172a;">
    <div class="dash-topbar-left">
      <a href="../index.php" class="logo" aria-label="One Vision Community">
        <div class="logo-text">
          <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
          <span class="logo-sub" style="color:#d97706; font-weight:800;">Administration</span>
        </div>
      </a>
      <span style="background:<?= $isOwner ? '#fef3c7' : '#e0f2fe' ?>; color:<?= $isOwner ? '#b45309' : '#0369a1' ?>; font-size:0.75rem; font-weight:800; padding:0.25rem 0.65rem; border-radius:6px; text-transform:uppercase;">
        <?= $isOwner ? '👑 Propriétaire' : '🛡️ Administrateur délégué' ?>
      </span>
    </div>

    <div class="dash-topbar-right">
      <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:0.4rem; font-weight:700;">
        <span>Mon Espace Membre</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
      </a>
      <div style="display:flex; align-items:center; gap:0.6rem;">
        <img src="<?= htmlspecialchars($currentUser['avatar'] ?? '../img/avatar-cyril.jpg') ?>" alt="Admin" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:1.5px solid #cbd5e1;">
        <span style="font-size:0.88rem; font-weight:700; color:#0f172a;"><?= htmlspecialchars($currentUser['full_name']) ?></span>
      </div>
      <a href="../logout.php?token=<?= urlencode(csrf_token()) ?>" class="btn btn-outline btn-sm" style="font-size:0.8rem; padding:0.4rem 0.75rem; color:#ef4444; border-color:#fca5a5;">
        Déconnexion
      </a>
    </div>
  </header>

  <!-- NAVIGATION SECONDAIRE ADMIN -->
  <div class="container" style="max-width: 1200px; margin: 1.5rem auto 0 auto; padding: 0 1rem;">
    <nav class="admin-nav-tabs">
      <a href="index.php" class="admin-nav-link <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
        <span>📊 Tableau de bord</span>
      </a>

      <?php if (user_has_permission($currentUser, 'voir_utilisateurs')): ?>
        <a href="users.php" class="admin-nav-link <?= ($currentPage === 'users.php' || $currentPage === 'user_detail.php') ? 'active' : '' ?>">
          <span>👥 Utilisateurs & Abonnés</span>
        </a>
      <?php endif; ?>

      <?php if (user_has_permission($currentUser, 'voir_profils_membres')): ?>
        <a href="onboarding_profils.php" class="admin-nav-link <?= ($currentPage === 'onboarding_profils.php' || $currentPage === 'onboarding_detail.php') ? 'active' : '' ?>">
          <span>📋 Profils des membres</span>
        </a>
      <?php endif; ?>

      <?php if (user_has_permission($currentUser, 'modifier_tarifs')): ?>
        <a href="plans.php" class="admin-nav-link <?= ($currentPage === 'plans.php') ? 'active' : '' ?>">
          <span>💎 Gestion des Formules</span>
        </a>
      <?php endif; ?>

      <?php if ($isOwner): ?>
        <a href="team.php" class="admin-nav-link <?= ($currentPage === 'team.php') ? 'active' : '' ?>" style="color:<?= ($currentPage === 'team.php') ? '#fff' : '#b45309' ?>; background:<?= ($currentPage === 'team.php') ? '#b45309' : 'transparent' ?>;">
          <span>👑 Équipe & Délégation</span>
        </a>
      <?php endif; ?>

      <?php if ($isOwner || user_has_permission($currentUser, 'voir_utilisateurs')): ?>
        <a href="logs.php" class="admin-nav-link <?= ($currentPage === 'logs.php') ? 'active' : '' ?>">
          <span>📜 Journal d'actions</span>
        </a>
      <?php endif; ?>
    </nav>

    <!-- Messages Flash -->
    <?= render_flash() ?>
  </div>

  <div class="container" style="max-width: 1200px; margin: 0 auto 3rem auto; padding: 0 1rem;">
