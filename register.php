<?php
/**
 * ONE VISION COMMUNITY — INSCRIPTION MEMBRE
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$fullNameVal = '';
$emailVal = '';
$companyVal = '';
$jobTitleVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullNameVal = trim($_POST['full_name'] ?? '');
    $emailVal = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $companyVal = trim($_POST['company'] ?? '');
    $jobTitleVal = trim($_POST['job_title'] ?? '');

    $result = register_user($fullNameVal, $emailVal, $password, [
        'company' => $companyVal,
        'job_title' => $jobTitleVal ?: 'Entrepreneur & Membre One Vision'
    ]);

    if ($result['success']) {
        set_flash('success', 'Bienvenue dans One Vision Community ! Votre compte a été créé avec succès.');
        header('Location: dashboard.php');
        exit;
    } else {
        $error = $result['error'];
    }
}

$pageTitle = "Inscription Membre — One Vision Community";
$pageDescription = "Créez votre compte membre et rejoignez la communauté d'entrepreneurs pour 9€/mois.";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 280px); display:flex; align-items:center; justify-content:center; padding: 3rem 1rem;">
  <div class="auth-card" style="width:100%; max-width:520px; background:var(--color-bg-card, #ffffff); border:1px solid var(--color-border, #e2e8f0); border-radius:18px; padding:2.5rem 2rem; box-shadow:0 12px 35px rgba(0,0,0,0.06);">
    
    <div style="text-align:center; margin-bottom:2rem;">
      <span class="badge badge-accent mb-2" style="display:inline-block; margin-bottom:0.75rem; font-size:0.8rem; padding:0.35rem 0.85rem; border-radius:30px; background:rgba(37,99,235,0.08); color:var(--color-primary, #2563eb); font-weight:600;">
        Adhésion sans engagement • 9€/mois
      </span>
      <h1 style="font-size:1.8rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.5rem; letter-spacing:-0.02em;">
        Rejoindre la communauté
      </h1>
      <p style="font-size:0.95rem; color:var(--color-text-muted, #64748b);">
        Créez votre profil pour accéder immédiatement aux lives hebdomadaires et aux salons.
      </p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php" class="auth-form" style="display:flex; flex-direction:column; gap:1.15rem;">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="full_name" style="display:block; font-size:0.9rem; font-weight:600; margin-bottom:0.4rem; color:var(--color-text-main, #1e293b);">
          Nom & Prénom <span style="color:#ef4444;">*</span>
        </label>
        <input 
          type="text" 
          id="full_name" 
          name="full_name" 
          required 
          value="<?= htmlspecialchars($fullNameVal) ?>" 
          placeholder="Ex: Maxime Robert"
          style="width:100%; padding:0.85rem 1rem; border:1px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.95rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
        >
      </div>

      <div class="form-group">
        <label for="email" style="display:block; font-size:0.9rem; font-weight:600; margin-bottom:0.4rem; color:var(--color-text-main, #1e293b);">
          Adresse email professionnelle <span style="color:#ef4444;">*</span>
        </label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          required 
          value="<?= htmlspecialchars($emailVal) ?>" 
          placeholder="maxime@monprojet.fr"
          style="width:100%; padding:0.85rem 1rem; border:1px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.95rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
        >
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label for="company" style="display:block; font-size:0.88rem; font-weight:600; margin-bottom:0.4rem; color:var(--color-text-main, #1e293b);">
            Entreprise / Projet
          </label>
          <input 
            type="text" 
            id="company" 
            name="company" 
            value="<?= htmlspecialchars($companyVal) ?>" 
            placeholder="Ex: Studio Pulse"
            style="width:100%; padding:0.85rem 1rem; border:1px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.95rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
          >
        </div>

        <div class="form-group">
          <label for="job_title" style="display:block; font-size:0.88rem; font-weight:600; margin-bottom:0.4rem; color:var(--color-text-main, #1e293b);">
            Votre rôle / Titre
          </label>
          <input 
            type="text" 
            id="job_title" 
            name="job_title" 
            value="<?= htmlspecialchars($jobTitleVal) ?>" 
            placeholder="Ex: Consultant SaaS"
            style="width:100%; padding:0.85rem 1rem; border:1px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.95rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
          >
        </div>
      </div>

      <div class="form-group">
        <label for="password" style="display:block; font-size:0.9rem; font-weight:600; margin-bottom:0.4rem; color:var(--color-text-main, #1e293b);">
          Mot de passe (6 caractères min.) <span style="color:#ef4444;">*</span>
        </label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          required 
          placeholder="••••••••"
          style="width:100%; padding:0.85rem 1rem; border:1px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.95rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
        >
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.95rem; font-size:1rem; font-weight:700; border-radius:10px; margin-top:0.75rem; justify-content:center;">
        <span>Finaliser mon inscription</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <div style="margin-top:1.75rem; text-align:center; font-size:0.9rem; color:var(--color-text-muted, #64748b);">
      Déjà membre ? 
      <a href="login.php" style="color:var(--color-primary, #2563eb); font-weight:700; text-decoration:none;">
        Connectez-vous ici
      </a>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
