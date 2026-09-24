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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

$pageTitle = "Connexion à votre Espace Membre — One Vision Community";
$pageDescription = "Accédez à vos sessions lives, vos replays et salons d'échanges d'entrepreneurs.";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 280px); display:flex; align-items:center; justify-content:center; padding: 3rem 1rem;">
  <div class="auth-card" style="width:100%; max-width:480px; background:var(--color-bg-card, #ffffff); border:1px solid var(--color-border, #e2e8f0); border-radius:18px; padding:2.5rem 2rem; box-shadow:0 12px 35px rgba(0,0,0,0.06);">
    
    <div style="text-align:center; margin-bottom:2rem;">
      <span class="badge badge-accent mb-2" style="display:inline-block; margin-bottom:0.75rem; font-size:0.8rem; padding:0.35rem 0.85rem; border-radius:30px; background:rgba(37,99,235,0.08); color:var(--color-primary, #2563eb); font-weight:600;">
        Espace Membre Officiel
      </span>
      <h1 style="font-size:1.8rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.5rem; letter-spacing:-0.02em;">
        Bon retour parmi nous
      </h1>
      <p style="font-size:0.95rem; color:var(--color-text-muted, #64748b);">
        Saisissez vos identifiants pour rejoindre vos masterminds et salons.
      </p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="auth-form" style="display:flex; flex-direction:column; gap:1.25rem;">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email" style="display:block; font-size:0.9rem; font-weight:600; margin-bottom:0.4rem; color:var(--color-text-main, #1e293b);">
          Adresse email professionnelle
        </label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          required 
          value="<?= $emailValue ?>" 
          placeholder="nom@entreprise.com"
          style="width:100%; padding:0.85rem 1rem; border:1px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.95rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
          onfocus="this.style.borderColor='var(--color-primary, #2563eb)'"
          onblur="this.style.borderColor='var(--color-border, #cbd5e1)'"
        >
      </div>

      <div class="form-group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
          <label for="password" style="font-size:0.9rem; font-weight:600; color:var(--color-text-main, #1e293b);">
            Mot de passe
          </label>
          <a href="support.php" style="font-size:0.82rem; color:var(--color-primary, #2563eb); text-decoration:none;">
            Mot de passe oublié ?
          </a>
        </div>
        <input 
          type="password" 
          id="password" 
          name="password" 
          required 
          placeholder="••••••••"
          style="width:100%; padding:0.85rem 1rem; border:1px solid var(--color-border, #cbd5e1); border-radius:10px; font-size:0.95rem; outline:none; transition:border-color 0.2s; background:#ffffff;"
          onfocus="this.style.borderColor='var(--color-primary, #2563eb)'"
          onblur="this.style.borderColor='var(--color-border, #cbd5e1)'"
        >
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:0.95rem; font-size:1rem; font-weight:700; border-radius:10px; margin-top:0.5rem; justify-content:center;">
        <span>Accéder à mon espace membre</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <!-- Comptes de démonstration pour test rapide -->
    <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px dashed var(--color-border, #e2e8f0); text-align:center;">
      <p style="font-size:0.82rem; color:var(--color-text-muted, #64748b); margin-bottom:0.75rem;">
        🚀 <strong>Comptes de test pré-configurés :</strong>
      </p>
      <div style="display:flex; flex-wrap:wrap; gap:0.5rem; justify-content:center;">
        <button type="button" onclick="fillTestAccount('cyril@onevisioncommunity.fr', 'password123')" style="font-size:0.78rem; padding:0.35rem 0.65rem; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; cursor:pointer;">
          👑 Cyril D. (Fondateur)
        </button>
        <button type="button" onclick="fillTestAccount('katahana@onevisioncommunity.fr', 'password123')" style="font-size:0.78rem; padding:0.35rem 0.65rem; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; cursor:pointer;">
          💼 Katahana (Membre)
        </button>
        <button type="button" onclick="fillTestAccount('sophie@onevisioncommunity.fr', 'password123')" style="font-size:0.78rem; padding:0.35rem 0.65rem; border-radius:6px; border:1px solid #cbd5e1; background:#f8fafc; cursor:pointer;">
          🎙️ Sophie L. (Speaker)
        </button>
      </div>
    </div>

    <div style="margin-top:1.75rem; text-align:center; font-size:0.9rem; color:var(--color-text-muted, #64748b);">
      Pas encore membre ? 
      <a href="checkout.php" style="color:var(--color-primary, #2563eb); font-weight:700; text-decoration:none;">
        Rejoindre pour 9€/mois
      </a>
    </div>

  </div>
</main>

<script>
function fillTestAccount(email, password) {
  document.getElementById('email').value = email;
  document.getElementById('password').value = password;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
