<?php
/**
 * ONE VISION COMMUNITY — CONFIRMATION & RETOUR PAIEMENT SASPAY
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/saspay.php';

$db = get_db();
$orderNumber = trim($_GET['order'] ?? '');
$sessionId = trim($_GET['session_id'] ?? '');

$order = null;

if (!empty($orderNumber)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
} elseif (!empty($sessionId)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE saspay_session_id = ?");
    $stmt->execute([$sessionId]);
    $order = $stmt->fetch();
}

// Si la commande n'a pas été trouvée, chercher la plus récente pour l'utilisateur connecté
if (!$order && is_logged_in()) {
    $currentUser = current_user();
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $order = $stmt->fetch();
}

$isPaymentConfirmed = false;
$orderStatus = 'not_found';

if ($order) {
    $orderStatus = $order['status'];

    // Si la commande n'est pas encore 'paid' en local, interroger SasPay
    if ($orderStatus !== 'paid') {
        $requestId = !empty($order['saspay_transaction_id']) ? $order['saspay_transaction_id'] : ($order['saspay_session_id'] ?? '');
        if (!empty($requestId)) {
            $verifyRes = saspay_check_transaction_status($requestId, $order['order_number']);
            $saspayStatus = strtoupper($verifyRes['status'] ?? 'PENDING');

            if (in_array($saspayStatus, ['PAID', 'SUCCESS', 'COMPLETED'], true)) {
                $txnId = $verifyRes['transaction_id'] ?? $order['saspay_transaction_id'] ?: ('TXN_' . uniqid());
                
                $db->prepare("
                    UPDATE orders 
                    SET status = 'paid', saspay_transaction_id = ? 
                    WHERE id = ?
                ")->execute([$txnId, $order['id']]);

                // Activation de l'abonnement du membre UNIQUEMENT APRÈS CONFIRMATION
                $db->prepare("UPDATE users SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP WHERE id = ?")
                   ->execute([$order['user_id']]);

                $orderStatus = 'paid';
            } elseif (in_array($saspayStatus, ['FAILED', 'CANCELLED', 'REJECTED'], true)) {
                $db->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
                $orderStatus = 'failed';
            }
        }
    }

    if ($orderStatus === 'paid') {
        $isPaymentConfirmed = true;
        // Connexion automatique dans la session
        $_SESSION['user_id'] = $order['user_id'];
        $_SESSION['user_name'] = $order['billing_name'];
        $_SESSION['user_email'] = $order['billing_email'];
        $_SESSION['user_role'] = 'member';
    }
}

$pageTitle = $isPaymentConfirmed ? "Paiement Validé — One Vision Community" : "Statut du Paiement — One Vision Community";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 250px); display:flex; align-items:center; justify-content:center; padding: 3.5rem 1rem;">
  <div class="auth-card" style="width:100%; max-width:640px; background:var(--color-bg-card, #ffffff); border:1px solid var(--color-border, #e2e8f0); border-radius:24px; padding:3rem 2.5rem; box-shadow:0 20px 45px rgba(0,0,0,0.06); text-align:center;">
    
    <?php if ($isPaymentConfirmed): ?>
      <!-- CAS 1 : PAIEMENT RÉELLEMENT VALIDÉ ET CONFIRMÉ -->
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#dcfce7; border:3px solid #86efac; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(22,163,74,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>

      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(37,99,235,0.08); border:1px solid rgba(37,99,235,0.2); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:var(--color-primary, #2563eb); margin-bottom:1rem;">
        <span>🛡️ Paiement Sécurisé SasPay</span>
        <span>•</span>
        <span style="color:#16a34a;">Vérifié & Validé</span>
      </div>

      <h1 style="font-size:2rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
        Félicitations et bienvenue !
      </h1>
      
      <p style="font-size:1.05rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 2rem; line-height:1.6;">
        Votre paiement de <strong><?= number_format((float)$order['amount'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?></strong> a bien été confirmé par la passerelle <strong>SasPay</strong>. Votre accès illimité à l'Espace Membre est maintenant actif.
      </p>

      <!-- Récapitulatif de Commande -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; margin-bottom:2.25rem; text-align:left;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; font-size:0.9rem;">
          <div>
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Client</span>
            <div style="font-weight:700; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($order['billing_name']) ?></div>
            <div style="color:#64748b; font-size:0.82rem;"><?= htmlspecialchars($order['billing_email']) ?></div>
          </div>
          <div>
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Montant & Formule</span>
            <div style="font-weight:700; color:#16a34a; font-size:1.05rem; margin-top:2px;"><?= number_format((float)$order['amount'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?></div>
            <div style="color:#64748b; font-size:0.82rem;">Adhésion One Vision</div>
          </div>
          <div style="border-top:1px solid #e2e8f0; padding-top:0.75rem;">
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Numéro de Commande</span>
            <div style="font-family:monospace; font-weight:600; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($order['order_number']) ?></div>
          </div>
          <div style="border-top:1px solid #e2e8f0; padding-top:0.75rem;">
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:600;">Facture Officielle</span>
            <div style="font-family:monospace; font-weight:600; color:#0f172a; margin-top:2px;"><?= htmlspecialchars($order['invoice_number']) ?></div>
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
          <a href="facture.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.6rem 1.1rem; border-radius:10px;">
            📄 Télécharger ma Facture (PDF)
          </a>
          <a href="index.php" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.6rem 1.1rem; border-radius:10px;">
            🏠 Retour à l'accueil
          </a>
        </div>
      </div>

    <?php elseif ($order && $orderStatus === 'pending'): ?>
      <?php 
      $isCardOrder = (stripos($order['payment_method'] ?? '', 'Carte') !== false);
      ?>
      <!-- CAS 2 : PAIEMENT EN ATTENTE DE CONFIRMATION (3DS OU MOBILE) -->
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#fef3c7; border:3px solid #fcd34d; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(217,119,6,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 3s linear infinite;">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      </div>

      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(217,119,6,0.1); border:1px solid rgba(217,119,6,0.25); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:#b45309; margin-bottom:1rem;">
        <?php if ($isCardOrder): ?>
          <span>⏳ En attente d'authentification bancaire...</span>
        <?php else: ?>
          <span>⏳ En attente de confirmation sur votre téléphone...</span>
        <?php endif; ?>
      </div>

      <h1 style="font-size:1.85rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
        <?php if ($isCardOrder): ?>
          Authentification 3D Secure
        <?php else: ?>
          Confirmez sur votre mobile
        <?php endif; ?>
      </h1>
      
      <p style="font-size:1rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 1.75rem; line-height:1.6;">
        <?php if ($isCardOrder): ?>
          Votre banque vérifie actuellement l'autorisation <strong>3D Secure</strong>. Veuillez confirmer l'opération via la notification push reçue dans votre <strong>application bancaire</strong> ou via le <strong>code SMS</strong> envoyé par votre banque.
        <?php else: ?>
          Une demande de paiement a été envoyée sur votre téléphone. Veuillez déverrouiller votre mobile et saisir votre <strong>code PIN secret</strong> pour valider le règlement.
        <?php endif; ?>
      </p>

      <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:14px; padding:1.2rem; margin-bottom:2rem; font-size:0.88rem; color:#92400e; text-align:left;">
        <div style="font-weight:700; margin-bottom:4px;">Commande : <?= htmlspecialchars($order['order_number']) ?></div>
        <div>Vérification en cours en temps réel auprès de SasPay... Dès que l'autorisation est accordée, cette page s'actualisera automatiquement.</div>
      </div>

      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <button type="button" onclick="location.reload();" class="btn btn-primary btn-block" style="padding:0.9rem; font-size:1rem; border-radius:12px; cursor:pointer;">
          🔄 Vérifier à nouveau maintenant
        </button>
        <a href="checkout.php" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.6rem 1rem;">
          Annuler ou changer de moyen de paiement
        </a>
      </div>

      <script>
        // Polling automatique toutes les 3 secondes
        const pollOrder = "<?= htmlspecialchars($order['order_number']) ?>";
        const pollTimer = setInterval(() => {
          fetch('api/check-payment-status.php?order=' + encodeURIComponent(pollOrder))
            .then(r => r.json())
            .then(data => {
              if (data && data.status === 'paid') {
                clearInterval(pollTimer);
                location.reload();
              } else if (data && (data.status === 'failed' || data.status === 'expired')) {
                clearInterval(pollTimer);
                location.reload();
              }
            })
            .catch(() => {});
        }, 3000);
      </script>

    <?php else: ?>
      <!-- CAS 3 : ÉCHEC OU DÉLAI DÉPASSÉ -->
      <?php 
      $isCardOrder = !empty($order) && (stripos($order['payment_method'] ?? '', 'Carte') !== false);
      ?>
      <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#fee2e2; border:3px solid #fca5a5; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(220,38,38,0.15);">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </div>

      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.25); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:#dc2626; margin-bottom:1rem;">
        <span>❌ Paiement non validé</span>
      </div>

      <h1 style="font-size:1.85rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem;">
        Paiement non abouti
      </h1>
      
      <p style="font-size:1rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 2rem; line-height:1.6;">
        <?php if ($isCardOrder): ?>
          La transaction par carte bancaire n'a pas abouti (authentification 3D Secure non confirmée, refus de la banque ou délai d'autorisation dépassé). Votre compte n'a pas été débité et aucun accès n'a été activé.
        <?php else: ?>
          La transaction n'a pas été confirmée sur votre téléphone ou a expiré. Votre compte n'a pas été débité et aucun accès n'a été activé.
        <?php endif; ?>
      </p>

      <div style="display:flex; flex-direction:column; gap:0.85rem;">
        <a href="checkout.php" class="btn btn-primary btn-block" style="padding:1rem; font-size:1.05rem; text-decoration:none; display:flex; align-items:center; justify-content:center; border-radius:12px;">
          <span>🔄 Réessayer le paiement</span>
        </a>
        <a href="index.php" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.6rem 1rem; border-radius:10px;">
          🏠 Retour à l'accueil
        </a>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

