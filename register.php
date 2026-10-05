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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = "Session de formulaire expirée. Veuillez actualiser la page et réessayer.";
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    } else {
        $fullNameVal = trim($_POST['full_name'] ?? '');
        $emailVal = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        require_once __DIR__ . '/includes/onboarding.php';

        $result = register_user($fullNameVal, $emailVal, $password, [
            'company' => '',
            'job_title' => 'Entrepreneur & Membre One Vision',
            'subscription_status' => 'none'
        ]);

        if ($result['success']) {
            $userId = (int)$result['user_id'];
            ensure_user_onboarding_profile($userId);

            set_flash('success', "✨ Inscription réussie ! Bienvenue dans la communauté.");
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'redirect' => 'questionnaire.php']);
                exit;
            }
            header('Location: questionnaire.php');
            exit;
        } else {
            $error = $result['error'];
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        }
    }
}

$pageTitle = "Inscription Membre — One Vision Community";
$pageDescription = "Créez votre compte membre et rejoignez la communauté d'entrepreneurs.";
$hideHeaderNav = true;
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 200px); display:flex; align-items:center; justify-content:center; padding: 1.75rem 1rem;">
  <div class="auth-card" style="width:100%; max-width:420px; background:var(--color-bg-card, #ffffff); border:1px solid var(--color-border, #e2e8f0); border-radius:18px; padding:1.65rem 1.6rem; box-shadow:0 12px 35px rgba(0,0,0,0.06);">
    
    <div style="text-align:center; margin-bottom:1rem;">
      <h1 style="font-size:1.45rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.35rem; letter-spacing:-0.02em;">
        Rejoindre la communauté
      </h1>
      <p style="font-size:0.86rem; color:var(--color-text-muted, #64748b); line-height:1.45; margin:0;">
        Créez votre profil pour accéder immédiatement aux lives hebdomadaires et aux salons.
      </p>
    </div>

    <!-- Onglets Connexion / Inscription -->
    <div class="auth-tabs" role="tablist">
      <a href="login.php" class="auth-tab-btn" role="tab" style="text-decoration:none; text-align:center;">Connexion</a>
      <a href="register.php" class="auth-tab-btn active" role="tab" style="text-decoration:none; text-align:center;">Inscription</a>
    </div>

    <!-- Bouton Google / Gmail -->
    <button type="button" class="btn-google-auth google-auth-btn" id="googleRegisterBtn">
      <svg width="18" height="18" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.15z"/>
        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
      </svg>
      <span>S'inscrire avec Google</span>
    </button>

    <div class="auth-divider">
      <span>ou avec votre email</span>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.65rem 0.85rem; border-radius:10px; margin-bottom:1rem; font-size:0.85rem; display:flex; align-items:center; gap:0.4rem;">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php" class="auth-form" style="display:flex; flex-direction:column; gap:0.75rem;">
      <?= csrf_field() ?>

      <div class="form-group" style="margin-bottom:0;">
        <label for="full_name" style="display:block; font-size:0.82rem; font-weight:700; margin-bottom:0.25rem; color:var(--color-text-main, #1e293b);">
          Nom & Prénom <span style="color:#ef4444;">*</span>
        </label>
        <input 
          type="text" 
          id="full_name" 
          name="full_name" 
          required 
          value="<?= htmlspecialchars($fullNameVal) ?>" 
          placeholder="Ex: Maxime Robert"
          style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.9rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
        >
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label for="email" style="display:block; font-size:0.82rem; font-weight:700; margin-bottom:0.25rem; color:var(--color-text-main, #1e293b);">
          Adresse email professionnelle <span style="color:#ef4444;">*</span>
        </label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          required 
          value="<?= htmlspecialchars($emailVal) ?>" 
          placeholder="maxime@monprojet.fr"
          style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.9rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
        >
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label for="password" style="display:block; font-size:0.82rem; font-weight:700; margin-bottom:0.25rem; color:var(--color-text-main, #1e293b);">
          Mot de passe (8 caractères min., avec lettres et chiffres) <span style="color:#ef4444;">*</span>
        </label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          required 
          placeholder="••••••••"
          style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.9rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
        >
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem; font-size:0.95rem; font-weight:700; border-radius:10px; margin-top:0.4rem; justify-content:center;">
        <span>Finaliser mon inscription</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <div style="margin-top:1.25rem; text-align:center; font-size:0.85rem; color:var(--color-text-muted, #64748b);">
      Déjà membre ? 
      <a href="login.php" style="color:var(--color-primary, #2563eb); font-weight:700; text-decoration:none;">
        Connectez-vous ici
      </a>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
