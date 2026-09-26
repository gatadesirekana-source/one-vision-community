<?php
/**
 * ONE VISION COMMUNITY — CONFIRMATION D'ADHÉSION
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

$db = get_db();
$orderNumber = trim($_GET['order'] ?? '');

$order = null;

if (!empty($orderNumber)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
}

// Si la commande n'a pas été trouvée, chercher la plus récente pour l'utilisateur connecté
if (!$order && is_logged_in()) {
    $currentUser = current_user();
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $order = $stmt->fetch();
}

if ($order) {
    if ($order['status'] !== 'paid') {
        $db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
        $db->prepare("UPDATE users SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$order['user_id']]);
        $order['status'] = 'paid';
    }

    // Connexion automatique dans la session
    $_SESSION['user_id'] = $order['user_id'];
    $_SESSION['user_name'] = $order['billing_name'];
    $_SESSION['user_email'] = $order['billing_email'];
    $_SESSION['user_role'] = 'member';
}

$pageTitle = "Adhésion Confirmée — One Vision Community";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 250px); display:flex; align-items:center; justify-content:center; padding: 3.5rem 1rem;">
  <div class="auth-card" style="width:100%; max-width:640px; background:var(--color-bg-card, #ffffff); border:1px solid var(--color-border, #e2e8f0); border-radius:24px; padding:3rem 2.5rem; box-shadow:0 20px 45px rgba(0,0,0,0.06); text-align:center;">
    
    <!-- Icône de Succès -->
    <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#dcfce7; border:3px solid #86efac; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(22,163,74,0.15);">
      <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    </div>

    <!-- Badge Confirmation -->
    <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(37,99,235,0.08); border:1px solid rgba(37,99,235,0.2); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:var(--color-primary, #2563eb); margin-bottom:1rem;">
      <span>🛡️ Adhésion Confirmée</span>
      <span>•</span>
      <span style="color:#16a34a;">Accès Immédiat</span>
    </div>

    <h1 style="font-size:2rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.75rem; letter-spacing:-0.02em;">
      Félicitations et bienvenue !
    </h1>
    
    <p style="font-size:1.05rem; color:var(--color-text-muted, #64748b); max-width:480px; margin:0 auto 2rem; line-height:1.6;">
      Votre adhésion a bien été validée. Votre accès illimité à l'Espace Membre est maintenant actif.
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
          <div style="color:#64748b; font-size:0.82rem;">Adhésion One Vision</div>
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

  </div>
</main>

<script>
  // Confirmer l'état payé dans le localStorage
  localStorage.setItem('ov_has_paid', 'true');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
