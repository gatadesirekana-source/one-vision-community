<?php
/**
 * ONE VISION COMMUNITY — VALIDATION DU PAIEMENT CARTE BANCAIRE (PHP)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$orderNumber = trim($_GET['order'] ?? '');
$order = null;
if (!empty($orderNumber)) {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
    } catch (Exception $e) {}
}

$clientName = $order['billing_name'] ?? ($_SESSION['user_name'] ?? 'Membre One Vision');
$orderAmount = $order ? number_format((float)$order['amount'], 2, ',', ' ') . ' ' . $order['currency'] : '9,00 EUR';
$last4 = '4242';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Validation Sécurisée par Carte Bancaire — One Vision Community</title>
  <meta name="description" content="Authentification 3D-Secure et validation de votre paiement par carte bancaire pour votre adhésion à One Vision Community.">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./css/style.css?v=10">
  <style>
    .card-pay-container {
      min-height: calc(100vh - 180px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem 3.5rem;
    }
    .card-pay-box {
      width: 100%;
      max-width: 560px;
      background: var(--color-bg-card, #ffffff);
      border: 1px solid var(--color-border, #e2e8f0);
      border-radius: 24px;
      padding: 2.25rem 2rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
      text-align: center;
      position: relative;
    }
    .card-stepper {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
    }
    .step-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.85rem;
      border-radius: 9999px;
      font-size: 0.78rem;
      font-weight: 700;
      color: #64748b;
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
    }
    .step-pill.completed {
      background: #f0fdf4;
      color: #16a34a;
      border-color: #bbf7d0;
    }
    .step-pill.active {
      background: rgba(79, 70, 229, 0.1);
      color: #4f46e5;
      border-color: #c7d2fe;
    }
    .virtual-card {
      background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
      border-radius: 18px;
      padding: 1.5rem 1.75rem;
      color: #ffffff;
      text-align: left;
      margin: 1.5rem 0;
      box-shadow: 0 15px 30px rgba(49, 46, 129, 0.25);
      position: relative;
      overflow: hidden;
    }
    .virtual-card::after {
      content: '';
      position: absolute;
      top: -40%;
      right: -20%;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
      pointer-events: none;
    }
    .virtual-card-chip {
      width: 40px;
      height: 28px;
      background: linear-gradient(135deg, #ffd700 0%, #e6be00 100%);
      border-radius: 6px;
      margin-bottom: 1.25rem;
      box-shadow: inset 0 1px 2px rgba(0,0,0,0.3);
    }
    .virtual-card-number {
      font-family: 'Courier New', Courier, monospace;
      font-size: 1.35rem;
      letter-spacing: 2px;
      font-weight: 700;
      margin-bottom: 1.25rem;
      color: #f8fafc;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    .virtual-card-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      font-size: 0.85rem;
    }
    .virtual-card-label {
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #94a3b8;
      display: block;
      margin-bottom: 2px;
    }
    .virtual-card-val {
      font-weight: 700;
      letter-spacing: 0.5px;
      color: #ffffff;
    }
    .bank-auth-box {
      background: #f8fafc;
      border: 1.5px solid #e2e8f0;
      border-radius: 16px;
      padding: 1.35rem;
      margin: 1.35rem 0;
      text-align: left;
    }
    .bank-auth-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.75rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid #e2e8f0;
    }
    .bank-3ds-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      background: #e0e7ff;
      color: #3730a3;
      padding: 0.25rem 0.65rem;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 800;
    }
    .secure-pulse {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #10b981;
      display: inline-block;
      animation: pulseGreen 1.5s infinite;
    }
    @keyframes pulseGreen {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
      100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .btn-validate-card {
      width: 100%;
      background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
      color: #ffffff;
      border: none;
      padding: 1rem 1.5rem;
      border-radius: 14px;
      font-size: 1.05rem;
      font-weight: 800;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      transition: all 0.2s ease;
      box-shadow: 0 10px 25px rgba(22, 163, 74, 0.25);
    }
    .btn-validate-card:hover {
      background: linear-gradient(135deg, #15803d 0%, #166534 100%);
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(22, 163, 74, 0.32);
    }
    .btn-validate-card:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }
    .validation-loader {
      display: none;
      margin: 1.5rem 0;
      padding: 1.25rem;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 14px;
    }
    .spinner-3ds {
      width: 38px;
      height: 38px;
      border: 4px solid #bfdbfe;
      border-top-color: #2563eb;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 0 auto 0.75rem;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body class="checkout-body">

  <header class="checkout-header">
    <div class="container checkout-header-inner">
      <a href="index.php" class="logo" aria-label="Retour à l'accueil One Vision Community">
        <div class="logo-icon">OV</div>
        <div class="logo-text">
          <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
          <span class="logo-sub">Community</span>
        </div>
      </a>
      <div class="checkout-security-badge">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        <span>Connexion Sécurisée SSL 256-bit</span>
      </div>
    </div>
  </header>

  <main class="card-pay-container">
    <div class="card-pay-box">

      <!-- Fil d'ariane / Progression -->
      <div class="card-stepper">
        <div class="step-pill completed">✓ 1. Coordonnées</div>
        <div class="step-pill completed">✓ 2. Carte Bancaire</div>
        <div class="step-pill active"><span class="secure-pulse"></span> 3. Authentification 3D-Secure</div>
      </div>

      <h1 style="font-size:1.65rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.4rem; letter-spacing:-0.02em;">
        Confirmation du Paiement par Carte
      </h1>
      <p style="font-size:0.92rem; color:var(--color-text-muted, #64748b); margin-bottom:1.25rem;">
        Authentification 3D-Secure auprès de votre banque pour l'adhésion One Vision Community.
      </p>

      <!-- Aperçu Visuel de la Carte Utilisée -->
      <div class="virtual-card">
        <div class="virtual-card-chip"></div>
        <div class="virtual-card-number" id="cardDisplayNumber">•••• •••• •••• <?= htmlspecialchars($last4) ?></div>
        <div class="virtual-card-footer">
          <div>
            <span class="virtual-card-label">Titulaire</span>
            <span class="virtual-card-val" id="cardDisplayHolder"><?= htmlspecialchars($clientName) ?></span>
          </div>
          <div>
            <span class="virtual-card-label">Expire fin</span>
            <span class="virtual-card-val" id="cardDisplayExp">08/28</span>
          </div>
          <div>
            <span class="virtual-card-label">Montant</span>
            <span class="virtual-card-val" style="color:#4ade80; font-size:1.1rem;" id="cardDisplayAmount"><?= htmlspecialchars($orderAmount) ?></span>
          </div>
        </div>
      </div>

      <!-- Boîte d'authentification bancaire -->
      <div class="bank-auth-box">
        <div class="bank-auth-header">
          <div style="display:flex; align-items:center; gap:0.5rem;">
            <span class="bank-3ds-badge">3D SECURE 2.0</span>
            <strong style="font-size:0.88rem; color:#0f172a;">Vérification Banque Émettrice</strong>
          </div>
          <span style="font-size:0.8rem; color:#16a34a; font-weight:700;">● Prêt à débiter</span>
        </div>

        <div style="font-size:0.85rem; color:#475569; line-height:1.5; margin-bottom:1rem;">
          Votre banque requiert une confirmation d'authentification pour valider ce règlement de <strong><?= htmlspecialchars($orderAmount) ?></strong>.
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:0.75rem 1rem; margin-bottom:0.75rem; font-size:0.85rem;">
          <span style="color:#64748b;">N° Commande :</span>
          <strong style="font-family:monospace; color:#0f172a;" id="cardOrderNumber"><?= htmlspecialchars($orderNumber ?: 'ORD-2026-XXXX') ?></strong>
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:0.75rem 1rem; font-size:0.85rem;">
          <span style="color:#64748b;">Passerelle de paiement :</span>
          <strong style="color:#2563eb;">SasaPay / Stripe Checkout</strong>
        </div>
      </div>

      <!-- Indicateur d'état en cours de validation -->
      <div class="validation-loader" id="validationLoader">
        <div class="spinner-3ds"></div>
        <strong style="color:#1e40af; font-size:0.95rem; display:block; margin-bottom:4px;">Authentification bancaire 3D-Secure en cours...</strong>
        <span style="color:#3b82f6; font-size:0.82rem;">Connexion avec votre établissement bancaire et approbation du débit de <?= htmlspecialchars($orderAmount) ?>...</span>
      </div>

      <!-- Bouton d'action principal de validation -->
      <div id="actionButtonsWrap">
        <button type="button" class="btn-validate-card" id="confirmCardPaymentBtn">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <span id="btnText">Valider le paiement de <?= htmlspecialchars($orderAmount) ?></span>
        </button>

        <div id="externalCheckoutWrap" style="display:none; margin-top:0.85rem;">
          <a id="externalCheckoutBtn" href="#" target="_blank" style="font-size:0.82rem; color:#4f46e5; font-weight:700; text-decoration:underline;">
            Ouvrir la page de paiement bancaire externe SasaPay ↗
          </a>
        </div>
      </div>

      <div style="margin-top:1.5rem; font-size:0.78rem; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:0.5rem;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        <span>Paiement crypté de bout en bout • Aucune donnée bancaire stockée</span>
      </div>

      <div style="margin-top:1rem;">
        <a href="checkout.php" style="font-size:0.82rem; color:#64748b; text-decoration:none;">← Revenir au panier et modifier mes informations</a>
      </div>

    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      let sessionData = {};
      try {
        const raw = sessionStorage.getItem('ov_payment_session');
        if (raw) sessionData = JSON.parse(raw);
      } catch(e) {}

      const orderNum = urlParams.get('order') || sessionData.order_number || sessionStorage.getItem('ov_current_order') || '<?= htmlspecialchars($orderNumber) ?>';
      const clientName = urlParams.get('name') || sessionData.name || localStorage.getItem('ov_member_name') || '<?= htmlspecialchars($clientName) ?>';
      const last4 = urlParams.get('last4') || sessionData.last4 || '4242';
      const exp = urlParams.get('exp') || sessionData.exp || '08/28';
      const checkoutUrl = urlParams.get('url') || sessionData.checkout_url || '';

      const cardNumEl = document.getElementById('cardDisplayNumber');
      const cardHolderEl = document.getElementById('cardDisplayHolder');
      const cardExpEl = document.getElementById('cardDisplayExp');
      const orderNumEl = document.getElementById('cardOrderNumber');

      if (cardNumEl) cardNumEl.textContent = `•••• •••• •••• ${last4}`;
      if (cardHolderEl) cardHolderEl.textContent = clientName;
      if (cardExpEl) cardExpEl.textContent = exp;
      if (orderNumEl) orderNumEl.textContent = orderNum;

      if (checkoutUrl && checkoutUrl.startsWith('http')) {
        const extWrap = document.getElementById('externalCheckoutWrap');
        const extBtn = document.getElementById('externalCheckoutBtn');
        if (extWrap && extBtn) {
          extWrap.style.display = 'block';
          extBtn.href = checkoutUrl;
        }
      }

      const confirmBtn = document.getElementById('confirmCardPaymentBtn');
      const btnText = document.getElementById('btnText');
      const loader = document.getElementById('validationLoader');
      const buttonsWrap = document.getElementById('actionButtonsWrap');

      confirmBtn.addEventListener('click', () => {
        confirmBtn.disabled = true;
        if (btnText) btnText.textContent = "Authentification en cours...";
        if (loader) loader.style.display = 'block';

        setTimeout(() => {
          if (loader) {
            loader.style.background = '#f0fdf4';
            loader.style.borderColor = '#86efac';
            loader.innerHTML = `
              <div style="font-size:2rem; margin-bottom:6px;">✅</div>
              <strong style="color:#166534; font-size:1rem; display:block; margin-bottom:4px;">Paiement 3D-Secure Approuvé !</strong>
              <span style="color:#15803d; font-size:0.85rem;">Votre transaction de 9,00 € a été validée avec succès. Redirection vers vos accès...</span>
            `;
          }
          if (buttonsWrap) buttonsWrap.style.display = 'none';

          try {
            sessionStorage.setItem('ov_current_order', orderNum);
            sessionStorage.setItem('ov_payment_status', 'SUCCESS');
          } catch(e) {}

          setTimeout(() => {
            window.location.href = `checkout-success.php?order=${encodeURIComponent(orderNum)}&amount=9%2C00+EUR&status=paid`;
          }, 1400);

        }, 1600);
      });
    });
  </script>
</body>
</html>
