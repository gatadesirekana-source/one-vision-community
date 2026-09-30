<?php
/**
 * ONE VISION COMMUNITY — CONFIRMATION D'ADHÉSION & VALIDATION DU PAIEMENT
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/moneroo.php';

$db = get_db();
$orderNumber = trim($_GET['order'] ?? $_SESSION['pending_order_number'] ?? '');
$paymentId = trim($_GET['paymentId'] ?? $_GET['payment_id'] ?? $_GET['id'] ?? '');
$paymentStatusParam = strtolower(trim($_GET['paymentStatus'] ?? $_GET['status'] ?? ''));

$order = null;
$paymentError = '';
$isPending = false;

// 1. Si aucun paymentId n'est présent dans l'URL mais que nous avons le numéro de commande, retrouver le payment_id en base
if (empty($paymentId) && !empty($orderNumber)) {
    $stmtPre = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmtPre->execute([$orderNumber]);
    $preOrder = $stmtPre->fetch();
    if ($preOrder && !empty($preOrder['payment_id'])) {
        $paymentId = $preOrder['payment_id'];
        $order = $preOrder;
    }
}

// 2. Si un identifiant de paiement Moneroo est disponible, vérifier le statut officiel via l'API Moneroo
if (!empty($paymentId) && moneroo_is_configured()) {
    $verification = moneroo_verify_payment($paymentId);

    if ($verification['success'] && in_array($verification['status'], ['success', 'completed', 'paid'], true)) {
        // Retrouver la commande correspondante
        if (!$order) {
            $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ? OR order_number = ? LIMIT 1");
            $stmt->execute([$paymentId, $orderNumber]);
            $order = $stmt->fetch();
        }

        if ($order) {
            // Valider la commande
            $db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
            $order['status'] = 'paid';

            // Activer l'abonnement du membre
            $db->prepare("UPDATE users SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$order['user_id']]);

            // Connecter automatiquement le membre propriétaire de la commande confirmée
            $stmtUser = $db->prepare("SELECT id, full_name, email, role FROM users WHERE id = ?");
            $stmtUser->execute([$order['user_id']]);
            $user = $stmtUser->fetch();
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
            }
        }
    } elseif ($verification['success'] && in_array($verification['status'], ['initiated', 'pending'], true)) {
        $isPending = true;
        if (!$order) {
            $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ? OR order_number = ? LIMIT 1");
            $stmt->execute([$paymentId, $orderNumber]);
            $order = $stmt->fetch();
        }
    } else {
        $paymentError = "Le paiement n'a pas pu être confirmé par l'opérateur (Statut: " . htmlspecialchars($verification['status'] ?? 'inconnu') . ").";
        if (!$order && !empty($orderNumber)) {
            $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch();
        }
    }
}

// 2. Vérification de l'authentification et récupération de la commande
$currentUser = current_user();

if (!$order && !empty($orderNumber) && $currentUser) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$orderNumber, $currentUser['id']]);
    $order = $stmt->fetch();
}

if (!$order && $currentUser) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $order = $stmt->fetch();
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
      <!-- ÉTAT : ERREUR OU ANNULATION DE PAIEMENT -->
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#fee2e2; border:3px solid #fca5a5; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(220,38,38,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="15" y1="9" x2="9" y2="15"></line>
          <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
      </div>

      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.2); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:#dc2626; margin-bottom:1rem;">
        <span>Paiement non finalisé</span>
      </div>

      <h1 style="font-size:2rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
        Paiement interrompu
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
      <!-- ÉTAT : PAIEMENT EN ATTENTE DE VALIDATION MOBILE MONEY -->
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
        Votre transaction est en cours de traitement par votre opérateur. Si vous avez reçu un SMS ou une invite sur votre mobile, veuillez composer votre code PIN secret pour approuver le débit.
      </p>

      <div style="display:flex; flex-direction:column; gap:0.85rem;">
        <button onclick="window.location.reload();" class="btn btn-primary btn-block" style="padding:1rem 1.5rem; font-size:1.05rem; display:flex; align-items:center; justify-content:center; gap:0.5rem; border-radius:12px; cursor:pointer;">
          <span>🔄 Actualiser le statut du paiement</span>
        </button>
      </div>

    <?php else: ?>
      <!-- ÉTAT : SUCCÈS CONFIRMÉ -->
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#dcfce7; border:3px solid #86efac; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(220,38,38,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>

      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(37,99,235,0.08); border:1px solid rgba(37,99,235,0.2); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:var(--color-primary, #2563eb); margin-bottom:1rem;">
        <span>🛡️ Adhésion Confirmée Moneroo</span>
        <span>•</span>
        <span style="color:#16a34a;">Accès Immédiat</span>
      </div>

      <h1 style="font-size:2rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
        Félicitations et bienvenue !
      </h1>
      
      <p style="font-size:1.05rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 2rem; line-height:1.6;">
        Votre adhésion a bien été validée par la passerelle de paiement sécurisée. Votre accès illimité à l'Espace Membre est maintenant actif.
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
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Montant & Méthode</span>
            <div style="font-weight:700; color:#16a34a; font-size:1.05rem; margin-top:2px;">
              <?= isset($order['amount']) ? number_format((float)$order['amount'], 2, ',', ' ') . ' ' . htmlspecialchars($order['currency'] ?? 'USD') : '10,00 USD' ?>
            </div>
            <div style="color:#64748b; font-size:0.82rem;"><?= htmlspecialchars($order['payment_method'] ?? 'Moneroo') ?></div>
          </div>
          <div style="border-top:1px solid #e2e8f0; padding-top:0.75rem;">
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Numéro de Commande</span>
            <div style="font-family:monospace; font-weight:600; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($order['order_number'] ?? ('ORD-' . date('Y') . '-' . rand(100, 999))) ?></div>
          </div>
          <div style="border-top:1px solid #e2e8f0; padding-top:0.75rem;">
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Facture Officielle</span>
            <div style="font-family:monospace; font-weight:600; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($order['invoice_number'] ?? ('FAC-' . date('Y') . '-' . rand(1000, 9999))) ?></div>
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
