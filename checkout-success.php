<?php
/**
 * ONE VISION COMMUNITY — CONFIRMATION D'ADHÉSION & VALIDATION SASPAY
 * Documentation : https://docs.saspay.me/
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/saspay.php';

$db = get_db();
$orderNumber = trim($_GET['order'] ?? $_SESSION['pending_order_number'] ?? '');
$sessionId = trim($_GET['session_id'] ?? $_GET['id'] ?? $_SESSION['pending_session_id'] ?? '');

$order = null;
$isPending = false;
$paymentError = '';

if (!empty($orderNumber)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
}

$currentUser = current_user();

// Si aucune commande par numéro, chercher la plus récente pour l'utilisateur connecté
if (!$order && $currentUser) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $order = $stmt->fetch();
}

// Récupérer le session ID depuis la commande si disponible
if ($order && empty($sessionId) && !empty($order['payment_id'])) {
    $sessionId = $order['payment_id'];
}

// 1. Si la commande est déjà marquée comme payée (ex. Carte bancaire ou Webhook préalable)
if ($order && $order['status'] === 'paid') {
    $db->prepare("
        UPDATE users 
        SET subscription_status = 'active', 
            subscription_started_at = COALESCE(subscription_started_at, CURRENT_TIMESTAMP),
            subscription_expires_at = datetime('now', '+30 days'),
            next_billing_date = date('now', '+30 days'),
            last_billing_date = date('now'),
            failed_renewals_count = 0
        WHERE id = ?
    ")->execute([$order['user_id']]);

    if (!$currentUser) {
        $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->execute([$order['user_id']]);
        $user = $stmtUser->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $currentUser = $user;
        }
    }
} elseif (!empty($sessionId) && strpos($sessionId, 'CARD-') === false && saspay_is_configured()) {
    // 2. Vérification auprès de l'API SasPay pour les sessions Mobile Money
    $verification = saspay_verify_checkout_session($sessionId);

    if ($verification['success']) {
        if ($verification['is_paid']) {
            if ($order) {
                $db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
                $order['status'] = 'paid';

                $db->prepare("
                    UPDATE users 
                    SET subscription_status = 'active', 
                        subscription_started_at = COALESCE(subscription_started_at, CURRENT_TIMESTAMP),
                        subscription_expires_at = datetime('now', '+30 days'),
                        next_billing_date = date('now', '+30 days'),
                        last_billing_date = date('now'),
                        failed_renewals_count = 0
                    WHERE id = ?
                ")->execute([$order['user_id']]);

                // Connecter l'utilisateur
                $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmtUser->execute([$order['user_id']]);
                $user = $stmtUser->fetch();
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $currentUser = $user;
                }
            }
        } elseif ($verification['status'] === 'PENDING') {
            $isPending = true;
        } else {
            $paymentError = "La transaction n'a pas été validée par l'opérateur (Statut : " . htmlspecialchars($verification['status']) . ").";
        }
    }
}

// Si la commande existe et que l'utilisateur n'est pas encore connecté en session, le connecter
if ($order && !$currentUser && !empty($order['user_id'])) {
    $stmtUser = $db->prepare("SELECT id, full_name, email, role FROM users WHERE id = ?");
    $stmtUser->execute([$order['user_id']]);
    $user = $stmtUser->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $currentUser = $user;
    }
}

// Si toujours aucune commande et utilisateur non connecté, rediriger vers login
if (!$order && !$currentUser) {
    header('Location: login.php');
    exit;
}

$pageTitle = $isPending ? "Paiement en Cours — One Vision Community" : ($paymentError ? "Erreur de Paiement — One Vision Community" : "Adhésion Confirmée — One Vision Community");
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 250px); display:flex; align-items:center; justify-content:center; padding: 3.5rem 1rem;">
  <div class="auth-card" style="width:100%; max-width:640px; background:var(--color-bg-card, #ffffff); border:1px solid var(--color-border, #e2e8f0); border-radius:24px; padding:3rem 2.5rem; box-shadow:0 20px 45px rgba(0,0,0,0.06); text-align:center;">
    
    <?php if ($paymentError): ?>
      <!-- ÉTAT : ERREUR DE TRANSACTION -->
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#fee2e2; border:3px solid #fca5a5; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(220,38,38,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="15" y1="9" x2="9" y2="15"></line>
          <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
      </div>

      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.2); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:#dc2626; margin-bottom:1rem;">
        <span>Paiement interrompu</span>
      </div>

      <h1 style="font-size:2rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
        Paiement non finalisé
      </h1>
      
      <p style="font-size:1.05rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 2rem; line-height:1.6;">
        <?= htmlspecialchars($paymentError) ?>
      </p>

      <div style="display:flex; flex-direction:column; gap:0.85rem;">
        <a href="checkout.php" class="btn btn-primary btn-block" style="padding:1rem 1.5rem; font-size:1.05rem; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:0.5rem; border-radius:12px;">
          <span>🔄 Réessayer le paiement sécurisé</span>
        </a>
        <a href="support.php" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.6rem 1.1rem; border-radius:10px;">
          Contacter le support d'aide
        </a>
      </div>

    <?php elseif ($isPending): ?>
      <!-- ÉTAT : TRANSACTION EN COURS SUR MOBILE OU 3D-SECURE -->
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#fef3c7; border:3px solid #fde68a; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(217,119,6,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      </div>

      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(217,119,6,0.08); border:1px solid rgba(217,119,6,0.2); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:#d97706; margin-bottom:1rem;">
        <span>⏳ Validation en cours</span>
      </div>

      <h1 style="font-size:2rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
        Paiement en attente
      </h1>
      
      <p style="font-size:1.05rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 2rem; line-height:1.6;">
        Votre transaction est en cours de confirmation auprès de SasPay et de votre opérateur. Si vous avez reçu une demande de validation sur votre mobile, veuillez composer votre code secret.
      </p>

      <div style="display:flex; flex-direction:column; gap:0.85rem;">
        <button onclick="window.location.reload();" class="btn btn-primary btn-block" style="padding:1rem 1.5rem; font-size:1.05rem; display:flex; align-items:center; justify-content:center; gap:0.5rem; border-radius:12px; cursor:pointer;">
          <span>🔄 Actualiser le statut du paiement</span>
        </button>
      </div>

      <script>
        // Actualisation automatique toutes les 4 secondes tant que le paiement est en attente
        setTimeout(function() {
          window.location.reload();
        }, 4000);
      </script>

    <?php else: ?>
      <!-- ÉTAT : SUCCÈS CONFIRMÉ -->
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#dcfce7; border:3px solid #86efac; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(22,163,74,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>

      <!-- Badge Confirmation -->
      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(37,99,235,0.08); border:1px solid rgba(37,99,235,0.2); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:var(--color-primary, #2563eb); margin-bottom:1rem;">
        <span>🛡️ <?= (!empty($order['payment_method']) && stripos($order['payment_method'], 'Carte') !== false) ? 'Paiement Carte Validé 3D-Secure' : 'Adhésion Confirmée SasPay' ?></span>
        <span>•</span>
        <span style="color:#16a34a;">Accès Membre Actif</span>
      </div>

      <h1 style="font-size:2rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
        Félicitations et bienvenue !
      </h1>
      
      <p style="font-size:1.05rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 2rem; line-height:1.6;">
        Votre adhésion a bien été validée avec succès. Vos accès à la communauté et à l'ensemble des modules sont maintenant débloqués pour une période de 30 jours.
      </p>

      <!-- Récapitulatif de Commande -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; margin-bottom:2.25rem; text-align:left;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; font-size:0.9rem;">
          <div>
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Client</span>
            <div style="font-weight:700; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($order['billing_name'] ?? 'Membre One Vision') ?></div>
            <div style="color:#64748b; font-size:0.82rem;"><?= htmlspecialchars($order['billing_email'] ?? '') ?></div>
          </div>
          <div>
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Montant & Formule</span>
            <div style="font-weight:700; color:#16a34a; font-size:1.05rem; margin-top:2px;">
              <?= isset($order['amount']) ? number_format((float)$order['amount'], 2, ',', ' ') . ' ' . htmlspecialchars($order['currency'] ?? 'EUR') : '9,00 €' ?>
            </div>
            <div style="color:#64748b; font-size:0.82rem;"><?= htmlspecialchars($order['payment_method'] ?? 'Adhésion One Vision') ?></div>
          </div>
          <div style="border-top:1px solid #e2e8f0; padding-top:0.75rem;">
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Période de Validité</span>
            <div style="font-weight:700; color:#0f172a; margin-top:2px;">
              30 jours (jusqu'au <?= date('d/m/Y', strtotime('+30 days')) ?>)
            </div>
            <div style="color:#64748b; font-size:0.78rem;">Renouvellement mensuel automatique</div>
          </div>
          <div style="border-top:1px solid #e2e8f0; padding-top:0.75rem;">
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Numéro de Facture</span>
            <div style="font-family:monospace; font-weight:600; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($order['invoice_number'] ?? ('FAC-' . date('Y') . '-' . rand(1000, 9999))) ?></div>
            <div style="font-family:monospace; font-size:0.75rem; color:#64748b;"><?= htmlspecialchars($order['order_number'] ?? '') ?></div>
          </div>
        </div>
      </div>

      <!-- Boutons d'Action -->
      <div style="display:flex; flex-direction:column; gap:0.85rem;">
        <a href="dashboard.php" class="btn btn-primary btn-block" style="padding:1rem 1.5rem; font-size:1.05rem; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:0.5rem; border-radius:12px;">
          <span>🎯 Accéder immédiatement à mon Dashboard</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>

        <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
          <?php if (!empty($order['id'])): ?>
            <a href="facture.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.6rem 1.1rem; border-radius:10px;">
              📄 Télécharger ma Facture (PDF)
            </a>
          <?php endif; ?>
          <a href="index.php" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.6rem 1.1rem; border-radius:10px;">
            🏠 Retour à l'accueil
          </a>
        </div>
      </div>

      <script>
        // Confirmer l'état payé dans le localStorage
        localStorage.setItem('ov_has_paid', 'true');
      </script>

    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
