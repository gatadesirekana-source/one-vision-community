<?php
/**
 * ONE VISION COMMUNITY — PAGE DE CONNEXION MEMBRE
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

// Si déjà connecté, rediriger directement vers le dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$emailValue = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = "Session de formulaire expirée. Veuillez actualiser la page et réessayer.";
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $emailValue = htmlspecialchars($email);

        $loginResult = login_user($email, $password);

        if ($loginResult['success']) {
            set_flash('success', 'Ravi de vous revoir parmi nous ! Vous êtes connecté.');
            $redirectTo = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: {$redirectTo}");
            exit;
        } else {
            $error = $loginResult['error'];
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

    <div class="modal-header">
      <div class="modal-header-top">
        <h1 class="modal-title">Espace Connexion</h1>
        <span class="modal-badge-login">MEMBRES</span>
      </div>
      <p class="modal-subtitle">Accédez à vos salons d'échanges, masterminds et replays HD.</p>
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

      <div class="modal-switch-mode">
        Nouveau ici ? <a href="checkout.php">Rejoindre pour 9€/mois</a>
      </div>
    </form>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
