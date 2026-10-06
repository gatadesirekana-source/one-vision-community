<?php
/**
 * ONE VISION COMMUNITY — INSCRIPTION MEMBRE
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

if (is_logged_in()) {
    header('Location: questionnaire.php');
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

<main class="section auth-section" style="min-height: calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding: 2.5rem 1rem; background: #0f172a08;">
  <div class="auth-card">
    
    <!-- Bouton Fermer ✕ -->
    <a href="index.php" class="modal-close-btn" aria-label="Fermer et retourner à l'accueil">✕</a>

    <!-- En-tête de la carte -->
    <div style="margin-bottom: 1.25rem; text-align: left;">
      <h1 style="font-size: 1.55rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0; letter-spacing: -0.02em;">
        Inscription Membre
      </h1>
      <p style="font-size: 0.9rem; color: #64748b; margin: 0; line-height: 1.45;">
        Rejoignez le réseau One Vision et accédez à tous vos espaces.
      </p>
    </div>

    <!-- Onglets Connexion / Inscription -->
    <div class="auth-tabs" role="tablist">
      <a href="login.php" class="auth-tab-btn" role="tab">Connexion</a>
      <a href="register.php" class="auth-tab-btn active" role="tab">Inscription</a>
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
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1rem; border-radius:12px; margin-bottom:1.15rem; font-size:0.875rem; display:flex; align-items:center; gap:0.5rem;">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="full_name" class="form-label">Nom complet</label>
        <input 
          type="text" 
          id="full_name" 
          name="full_name" 
          required 
          value="<?= htmlspecialchars($fullNameVal ?: 'Gkd') ?>" 
          placeholder="Gkd"
          class="form-input"
          autocomplete="name"
        >
      </div>

      <div class="form-group">
        <label for="email" class="form-label">Adresse email</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          required 
          value="<?= htmlspecialchars($emailVal ?: 'gatadesirekana@gmail.com') ?>" 
          placeholder="gatadesirekana@gmail.com"
          class="form-input"
          autocomplete="email"
        >
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Mot de passe</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          required 
          placeholder="Au moins 6 caractères"
          class="form-input"
          autocomplete="new-password"
        >
      </div>

      <button type="submit" class="btn btn-primary modal-submit-btn">
        S'inscrire
      </button>
    </form>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const googleBtn = document.getElementById('googleRegisterBtn');
  if (googleBtn) {
    googleBtn.addEventListener('click', function() {
      const emailInput = document.getElementById('email');
      const nameInput = document.getElementById('full_name');
      const emailVal = emailInput && emailInput.value ? emailInput.value : 'gatadesirekana@gmail.com';
      const nameVal = nameInput && nameInput.value ? nameInput.value : 'Gkd';

      const formData = new FormData();
      formData.append('action', 'google_auth');
      formData.append('email', emailVal);
      formData.append('full_name', nameVal);

      fetch('login.php?action=google_auth', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          window.location.href = 'questionnaire.php';
        }
      })
      .catch(() => {
        window.location.href = 'questionnaire.php';
      });
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
