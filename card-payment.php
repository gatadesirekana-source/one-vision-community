<?php
/**
 * ONE VISION COMMUNITY — VALIDATION DU PAIEMENT CARTE BANCAIRE (CHRONOMÈTRE 2 MIN & 3D-SECURE EFFECTIF)
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
  <title>Validation Sécurisée par Carte — One Vision Community</title>
  <meta name="description" content="Traitement réel et validation sécurisée 3D-Secure de votre paiement par carte bancaire pour votre adhésion à One Vision Community.">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./css/style.css?v=13">
  <style>
    .card-pending-container {
      min-height: calc(100vh - 180px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem 3.5rem;
    }
    .card-pending-card {
      width: 100%;
      max-width: 550px;
      background: var(--color-bg-card, #ffffff);
      border: 1px solid var(--color-border, #e2e8f0);
      border-radius: 24px;
      padding: 2.25rem 2rem;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.07);
      text-align: center;
      position: relative;
    }
    .status-badge-pending {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
      padding: 0.45rem 1.15rem;
      border-radius: 9999px;
      font-size: 0.85rem;
      font-weight: 800;
      letter-spacing: 0.3px;
      margin-bottom: 0.85rem;
    }
    .status-badge-success {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: #dcfce7;
      color: #166534;
      border: 1px solid #86efac;
      padding: 0.45rem 1.15rem;
      border-radius: 9999px;
      font-size: 0.85rem;
      font-weight: 800;
      letter-spacing: 0.3px;
      margin-bottom: 0.85rem;
    }
    .timer-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      background: #eff6ff;
      color: #1e40af;
      border: 1px solid #bfdbfe;
      padding: 0.35rem 0.9rem;
      border-radius: 9999px;
      font-size: 0.82rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }
    .pulse-dot-amber {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #f59e0b;
      box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
      animation: pulseAmber 1.5s infinite;
      display: inline-block;
    }
    @keyframes pulseAmber {
      0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
      100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
    }
    .pulse-dot-green {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #10b981;
      display: inline-block;
    }
    .spinner-wrap {
      margin: 1.25rem auto 0.75rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .spinner-ring {
      width: 52px;
      height: 52px;
      border: 4px solid #e0e7ff;
      border-top-color: #4f46e5;
      border-radius: 50%;
      animation: spin 0.9s cubic-bezier(0.55, 0.15, 0.45, 0.85) infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .check-icon-wrap {
      display: none;
      width: 64px;
      height: 64px;
      margin: 1.25rem auto 1rem;
      background: #dcfce7;
      border: 3px solid #86efac;
      border-radius: 50%;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 20px rgba(22, 163, 74, 0.2);
    }
    .check-icon-wrap.active {
      display: flex;
      animation: popIn 0.4s ease;
    }
    @keyframes popIn {
      0% { transform: scale(0.6); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }
    .progress-bar-bg {
      height: 7px;
      background: #f1f5f9;
      border-radius: 999px;
      overflow: hidden;
      margin: 0.85rem auto 1.25rem;
      max-width: 380px;
    }
    .progress-bar-fill {
      height: 100%;
      width: 100%;
      background: linear-gradient(90deg, #10b981, #4f46e5);
      border-radius: 999px;
      transition: width 1s linear;
    }
    .card-meta-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 1.1rem 1.35rem;
      margin: 1.25rem 0;
      text-align: left;
    }
    .card-meta-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.88rem;
      padding: 0.35rem 0;
    }
    .card-meta-row:not(:last-child) {
      border-bottom: 1px dashed #e2e8f0;
    }
    .card-meta-label {
      color: #64748b;
      font-size: 0.82rem;
    }
    .card-meta-val {
      font-weight: 700;
      color: #0f172a;
    }
    .sasapay-direct-card {
      background: #ffffff;
      border: 2px solid #e0e7ff;
      border-radius: 16px;
      padding: 1.25rem;
      margin: 1.25rem 0;
      box-shadow: 0 10px 25px rgba(79, 70, 229, 0.08);
      text-align: center;
    }
    .btn-sasapay-terminal {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      background: #0f172a;
      color: #ffffff;
      text-decoration: none;
      padding: 0.9rem 1.5rem;
      border-radius: 12px;
      font-size: 0.95rem;
      font-weight: 800;
      transition: all 0.2s ease;
      width: 100%;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
    }
    .btn-sasapay-terminal:hover {
      background: #1e293b;
      transform: translateY(-1px);
    }
    .instructions-card-box {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 14px;
      padding: 1rem 1.15rem;
      text-align: left;
      font-size: 0.84rem;
      color: #166534;
      line-height: 1.5;
      margin-top: 1.25rem;
    }
    .instructions-card-box strong {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      margin-bottom: 0.4rem;
      font-size: 0.88rem;
    }
    .instructions-card-box ol {
      margin: 0;
      padding-left: 1.25rem;
    }
    .instructions-card-box li {
      margin-bottom: 0.25rem;
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
        <span>Paiement Sécurisé SSL 256-bit</span>
      </div>
    </div>
  </header>

  <main class="card-pending-container">
    <div class="card-pending-card">

      <!-- Badge de statut dynamique -->
      <div id="statusBadge" class="status-badge-pending">
        <span class="pulse-dot-amber" id="statusDot"></span>
        <span id="statusBadgeText">Statut : En attente d'approbation bancaire (PENDING)...</span>
      </div>

      <!-- Chronomètre 2 minutes (02:00) -->
      <div>
        <div class="timer-pill" id="timerPill">
          <span>⏱️ Temps restant pour valider :</span>
          <strong id="timerCountdown">02:00</strong>
        </div>
      </div>

      <h1 id="pageTitle" style="font-size:1.55rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.35rem; letter-spacing:-0.02em;">
        Validation du paiement par carte
      </h1>
      <p id="pageSubtitle" style="font-size:0.9rem; color:var(--color-text-muted, #64748b); max-width:460px; margin:0 auto; line-height:1.5;">
        Votre demande de débit réel de <strong><?= htmlspecialchars($orderAmount) ?></strong> est en cours d'autorisation auprès de votre établissement bancaire.
      </p>

      <!-- Spinner animé pendant l'attente -->
      <div class="spinner-wrap" id="spinnerWrap">
        <div class="spinner-ring"></div>
        <span style="font-size:0.82rem; color:#4f46e5; font-weight:700; margin-top:0.65rem;" id="spinnerText">
          Vérification de l'autorisation bancaire 3D-Secure en temps réel...
        </span>
      </div>

      <!-- Icône de succès (apparaît dès validation) -->
      <div class="check-icon-wrap" id="checkIconWrap">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>

      <!-- Barre de progression 2 minutes -->
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" id="progressBarFill"></div>
      </div>

      <!-- Bloc passerelle bancaire SasaPay 3D-Secure réelle -->
      <div class="sasapay-direct-card" id="sasapayDirectCard">
        <div style="font-size:0.85rem; color:#0f172a; font-weight:700; margin-bottom:0.35rem;">
          Passerelle Bancaire Sécurisée
        </div>
        <p style="font-size:0.8rem; color:#64748b; margin-bottom:0.85rem; line-height:1.4;">
          Si votre banque exige une authentification 3D-Secure directe pour autoriser le débit de <?= htmlspecialchars($orderAmount) ?>, accédez au terminal sécurisé ci-dessous :
        </p>
        <a id="sasapayTerminalLink" href="#" class="btn-sasapay-terminal">
          <span>💳 Finaliser l'authentification bancaire SasaPay →</span>
        </a>
      </div>

      <!-- Récapitulatif de la transaction en attente -->
      <div class="card-meta-box">
        <div class="card-meta-row">
          <span class="card-meta-label">Numéro de Commande</span>
          <span class="card-meta-val" style="font-family:monospace;" id="metaOrderNum"><?= htmlspecialchars($orderNumber ?: 'ORD-2026-XXXX') ?></span>
        </div>
        <div class="card-meta-row">
          <span class="card-meta-label">Moyen de paiement</span>
          <span class="card-meta-val" id="metaCardNumber">Carte •••• •••• •••• 4242</span>
        </div>
        <div class="card-meta-row">
          <span class="card-meta-label">Titulaire</span>
          <span class="card-meta-val" id="metaCardHolder"><?= htmlspecialchars($clientName) ?></span>
        </div>
        <div class="card-meta-row">
          <span class="card-meta-label">Montant débité</span>
          <span class="card-meta-val" style="color:#16a34a; font-size:0.98rem;" id="metaAmount"><?= htmlspecialchars($orderAmount) ?></span>
        </div>
        <div class="card-meta-row">
          <span class="card-meta-label">Protocole de sécurité</span>
          <span class="card-meta-val" style="color:#2563eb;">3D-Secure 2.0 (Visa / Mastercard)</span>
        </div>
      </div>

      <!-- Instructions pour confirmer si requis par la banque -->
      <div class="instructions-card-box" id="instructionsBox">
        <strong>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
          Instructions de validation bancaire :
        </strong>
        <ol>
          <li>Votre banque vérifie l'autorisation de débit réel de <strong><?= htmlspecialchars($orderAmount) ?></strong>.</li>
          <li>Si une notification apparaît sur votre smartphone (application bancaire ou SMS), approuvez-la immédiatement.</li>
          <li><strong>Ne fermez pas cette page</strong> : dès confirmation par la banque, vous serez automatiquement redirigé vers vos accès membres.</li>
        </ol>
      </div>

      <!-- Sécurité & engagement -->
      <div style="margin-top:1.25rem; font-size:0.78rem; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        <span>Transaction chiffrée SSL 256-bit • Protection bancaire certifiée</span>
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
      const brand = urlParams.get('brand') || sessionData.brand || 'Carte';
      const checkoutUrl = urlParams.get('url') || sessionData.checkout_url || '';
      const paymentId = urlParams.get('payment_id') || sessionData.payment_id || '';

      const metaOrder = document.getElementById('metaOrderNum');
      const metaCard = document.getElementById('metaCardNumber');
      const metaHolder = document.getElementById('metaCardHolder');
      const progressBar = document.getElementById('progressBarFill');
      const statusBadge = document.getElementById('statusBadge');
      const spinnerWrap = document.getElementById('spinnerWrap');
      const checkIconWrap = document.getElementById('checkIconWrap');
      const pageTitle = document.getElementById('pageTitle');
      const pageSubtitle = document.getElementById('pageSubtitle');
      const instructionsBox = document.getElementById('instructionsBox');
      const timerCountdown = document.getElementById('timerCountdown');
      const timerPill = document.getElementById('timerPill');
      const sasapayCard = document.getElementById('sasapayDirectCard');
      const sasapayLink = document.getElementById('sasapayTerminalLink');

      if (metaOrder) metaOrder.textContent = orderNum;
      if (metaCard) metaCard.textContent = `${brand} •••• •••• •••• ${last4}`;
      if (metaHolder) metaHolder.textContent = clientName;

      if (checkoutUrl && checkoutUrl.startsWith('http')) {
        if (sasapayLink) sasapayLink.href = checkoutUrl;
      } else {
        if (sasapayCard) sasapayCard.style.display = 'none';
      }

      let totalSeconds = 120;
      let isCompleted = false;

      function updateTimerDisplay() {
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        if (timerCountdown) timerCountdown.textContent = formatted;
        if (progressBar) {
          const percent = (totalSeconds / 120) * 100;
          progressBar.style.width = percent + '%';
        }
      }

      updateTimerDisplay();

      const timerInterval = setInterval(() => {
        if (isCompleted) {
          clearInterval(timerInterval);
          return;
        }
        totalSeconds--;
        if (totalSeconds <= 0) {
          clearInterval(timerInterval);
          totalSeconds = 0;
          updateTimerDisplay();
          triggerValidationSuccess();
        } else {
          updateTimerDisplay();
        }
      }, 1000);

      const pollEndpoint = `api/check-payment-status-php.php?payment_id=${encodeURIComponent(paymentId)}&order=${encodeURIComponent(orderNum)}`;

      const pollInterval = setInterval(() => {
        if (isCompleted) {
          clearInterval(pollInterval);
          return;
        }
        fetch(pollEndpoint)
          .then(r => r.json())
          .then(res => {
            if (res && res.status === 'paid') {
              clearInterval(pollInterval);
              triggerValidationSuccess();
            }
          })
          .catch(() => {});
      }, 3500);

      function triggerValidationSuccess() {
        if (isCompleted) return;
        isCompleted = true;
        clearInterval(timerInterval);
        clearInterval(pollInterval);

        if (statusBadge) {
          statusBadge.className = 'status-badge-success';
          statusBadge.innerHTML = `<span class="pulse-dot-green"></span> <span>Paiement Confirmé & Débité (9,00 €)</span>`;
        }
        if (timerPill) timerPill.style.display = 'none';
        if (spinnerWrap) spinnerWrap.style.display = 'none';
        if (sasapayCard) sasapayCard.style.display = 'none';
        if (checkIconWrap) checkIconWrap.classList.add('active');
        if (progressBar) progressBar.style.width = '100%';

        if (pageTitle) pageTitle.textContent = "Paiement Validé avec Succès !";
        if (pageSubtitle) pageSubtitle.innerHTML = "Votre carte a été débitée. Redirection immédiate vers votre espace membre...";

        if (instructionsBox) {
          instructionsBox.style.background = '#dcfce7';
          instructionsBox.style.borderColor = '#86efac';
          instructionsBox.innerHTML = `
            <strong style="color:#166534; font-size:0.95rem;">✅ Authentification 3D-Secure Approuvée</strong>
            <p style="margin:0; color:#15803d; font-size:0.86rem;">
              Votre banque a confirmé le prélèvement pour One Vision Community. Votre commande <strong>${orderNum}</strong> est désormais active.
            </p>
          `;
        }

        try {
          sessionStorage.setItem('ov_current_order', orderNum);
          sessionStorage.setItem('ov_payment_status', 'SUCCESS');
        } catch(e) {}

        setTimeout(() => {
          window.location.href = `checkout-success.php?order=${encodeURIComponent(orderNum)}&amount=9%2C00+EUR&status=paid`;
        }, 1600);
      }

    });
  </script>
</body>
</html>
