<?php
/**
 * ONE VISION COMMUNITY — PAGE DE CONNEXION MEMBRE
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

// Si déjà connecté, rediriger directement vers le dashboard
if (is_logged_in() && !isset($_GET['demo'])) {
    header('Location: dashboard.php');
    exit;
}

// Démo / Test rapide en 1 clic pour inspecter tous les états en local
if (isset($_GET['demo'])) {
    $db = get_db();
    $demo = $_GET['demo'];
    if ($demo === 'animateur') {
        $stmt = $db->query("SELECT * FROM users WHERE role = 'animateur' OR role = 'proprietaire' LIMIT 1");
        $u = $stmt->fetch();
    } elseif ($demo === 'expired') {
        $stmt = $db->query("SELECT * FROM users WHERE subscription_status = 'expired' LIMIT 1");
        $u = $stmt->fetch();
    } else { // membre standard
        $stmt = $db->query("SELECT * FROM users WHERE (role = 'membre' OR role = 'member') AND subscription_status = 'active' LIMIT 1");
        $u = $stmt->fetch();
    }
    if ($u) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['user_name'] = $u['full_name'];
        $_SESSION['user_email'] = $u['email'];
        $_SESSION['user_role'] = $u['role'];
        if ($demo === 'expired') {
            header('Location: subscription-expired.php');
            exit;
        }
        header('Location: dashboard.php');
        exit;
    }
}

$error = '';
$emailValue = '';

// Connexion / Inscription rapide avec Google / Gmail
if ((isset($_GET['action']) && $_GET['action'] === 'google_auth') || (isset($_POST['action']) && $_POST['action'] === 'google_auth')) {
    $db = get_db();
    $googleEmail = trim(strtolower($_POST['email'] ?? 'alexandre.martin@gmail.com'));
    $googleName = trim($_POST['full_name'] ?? 'Alexandre Martin');

    require_once __DIR__ . '/includes/onboarding.php';

    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
    $stmt->execute([$googleEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        $dummyPassword = bin2hex(random_bytes(6)) . '1A';
        $regResult = register_user($googleName, $googleEmail, $dummyPassword, [
            'company' => 'Google Workspace',
            'job_title' => 'Membre One Vision',
            'avatar' => './img/avatar-alexandre.jpg',
            'subscription_status' => 'none'
        ]);
        if ($regResult['success']) {
            $userId = (int)$regResult['user_id'];
            ensure_user_onboarding_profile($userId);

            $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();
        }
    }

    if ($user) {
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'] ?? 'membre';

        $redir = check_onboarding_redirect($user) ?: 'dashboard.php';

        set_flash('success', "👋 Connexion réussie ! Bienvenue.");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'redirect' => $redir, 'user' => $user['full_name']]);
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $emailValue = htmlspecialchars($email);

        $loginResult = login_user($email, $password);

        if ($loginResult['success']) {
            $user = $loginResult['user'];
            require_once __DIR__ . '/includes/permissions.php';
            require_once __DIR__ . '/includes/subscriptions.php';
            require_once __DIR__ . '/includes/onboarding.php';

            $redir = check_onboarding_redirect($user);
            if ($redir !== null) {
                unset($_SESSION['redirect_after_login']);
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'redirect' => $redir]);
                    exit;
                }
                header("Location: {$redir}");
                exit;
            }

            set_flash('success', 'Ravi de vous revoir parmi nous ! Vous êtes connecté.');
            $redirectTo = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
            unset($_SESSION['redirect_after_login']);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'redirect' => $redirectTo]);
                exit;
            }
            header("Location: {$redirectTo}");
            exit;
        } else {
            $error = $loginResult['error'];
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        }
    }
}

$pageTitle = "Espace Connexion — One Vision Community";
$pageDescription = "Accédez à vos salons d'échanges, masterminds et replays HD.";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 240px); display:flex; align-items:center; justify-content:center; padding: 3rem 1rem; background: #0f172a10;">
  <div class="modal-card" style="box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(15, 23, 42, 0.05); position: relative; margin: 0 auto;">
    <a href="index.php" class="modal-close-btn" aria-label="Fermer et retourner à l'accueil">✕</a>

    <div class="modal-header" style="margin-bottom:0.75rem;">
      <div class="modal-header-top">
        <h1 class="modal-title">Espace Connexion</h1>
      </div>
      <p class="modal-subtitle">Accédez à vos salons d'échanges, masterminds et replays HD.</p>
    </div>

    <!-- Onglets Connexion / Inscription -->
    <div class="auth-tabs" role="tablist">
      <a href="login.php" class="auth-tab-btn active" role="tab" style="text-decoration:none; text-align:center;">Connexion</a>
      <a href="register.php" class="auth-tab-btn" role="tab" style="text-decoration:none; text-align:center;">Inscription</a>
    </div>

    <!-- ACCÈS RAPIDE DÉMO / TEST EN 1 CLIC (PHP) -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:0.95rem; margin-bottom:1.25rem;">
      <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#475569; letter-spacing:0.04em; margin-bottom:0.6rem; text-align:center;">
        Accès Rapide Démo & Parcours :
      </div>
      <div style="display:flex; flex-direction:column; gap:0.45rem;">
        <a href="login.php?demo=animateur" class="btn btn-secondary btn-sm" style="font-size:0.82rem; padding:0.45rem 0.6rem; font-weight:700; width:100%; justify-content:flex-start; text-decoration:none; text-align:left; color:#c2410c; background:#fff7ed; border-color:#fed7aa;">
          🌟 <span><strong>Animateur</strong> (Abonnement Plus • 24€/m)</span>
        </a>
        <a href="login.php?demo=membre" class="btn btn-secondary btn-sm" style="font-size:0.82rem; padding:0.45rem 0.6rem; font-weight:700; width:100%; justify-content:flex-start; text-decoration:none; text-align:left; color:#1e293b;">
          💼 <span><strong>Membre Standard</strong> (Actif • 9€/m)</span>
        </a>
        <a href="questionnaire.php" class="btn btn-secondary btn-sm" style="font-size:0.82rem; padding:0.45rem 0.6rem; font-weight:700; width:100%; justify-content:flex-start; text-decoration:none; text-align:left; color:#1e293b;">
          📋 <span><strong>Nouveau Membre</strong> (Passer le Questionnaire)</span>
        </a>
        <a href="choisir-abonnement.php" class="btn btn-secondary btn-sm" style="font-size:0.82rem; padding:0.45rem 0.6rem; font-weight:700; width:100%; justify-content:flex-start; text-decoration:none; text-align:left; color:#1e293b;">
          💳 <span><strong>Choix Abonnement</strong> (Grille 9€ vs 24€)</span>
        </a>
        <a href="login.php?demo=expired" class="btn btn-secondary btn-sm" style="font-size:0.82rem; padding:0.45rem 0.6rem; font-weight:700; width:100%; justify-content:flex-start; text-decoration:none; text-align:left; color:#b91c1c; background:#fef2f2; border-color:#fecaca;">
          ⏳ <span><strong>Compte Expiré</strong> (Aperçu restreint & relance)</span>
        </a>
      </div>
    </div>

    <!-- Bouton Google / Gmail -->
    <button type="button" class="btn-google-auth google-auth-btn" id="googleLoginBtn">
      <svg width="18" height="18" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.15z"/>
        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
      </svg>
      <span>Continuer avec Google</span>
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

    <form method="POST" action="login.php">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email" class="form-label">Adresse email</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          required 
          value="<?= $emailValue ?>" 
          placeholder="ex. alexandre@monprojet.fr"
          class="form-input"
          autocomplete="email"
        >
      </div>

      <div class="form-group">
        <div style="display:flex; justify-content:space-between; align-items:baseline;">
          <label for="password" class="form-label">Mot de passe</label>
          <a href="support.php" style="font-size:0.8rem; color:#475569; text-decoration:underline;">
            Oublié ?
          </a>
        </div>
        <input 
          type="password" 
          id="password" 
          name="password" 
          required 
          placeholder="Votre mot de passe"
          class="form-input"
          autocomplete="current-password"
        >
      </div>

      <button type="submit" class="btn btn-primary modal-submit-btn">
        <span>Me connecter à mon espace</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      </button>

      <div class="modal-footer-notes">
        <span style="color:#f97316;">⚡</span> Connexion rapide et chiffrée • Accès immédiat aux salons 24/7
      </div>
    </form>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
