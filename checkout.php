<?php
/**
 * ONE VISION COMMUNITY — PAIEMENT SÉCURISÉ & ADHÉSION 9€/MOIS (PHP & SQLITE)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/saspay.php';

$db = get_db();
$currentUser = current_user();

$success = false;
$createdOrder = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['checkoutName'] ?? '');
    $email = trim(strtolower($_POST['checkoutEmail'] ?? ''));
    $password = $_POST['checkoutPassword'] ?? '';
    $method = trim($_POST['paymentMethod'] ?? 'card');
    $momoCountry = trim($_POST['momoCountry'] ?? 'Cameroun');
    $momoOperator = trim($_POST['momoOperator'] ?? 'MTN MoMo');
    $momoPhone = trim($_POST['momoPhone'] ?? '');
    $momoAmount = trim($_POST['momoAmount'] ?? '5904');
    $momoCurrency = trim($_POST['momoCurrency'] ?? 'XAF');

    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez renseigner un nom valide et une adresse email valide.";
    } else {
        try {
            $userId = null;

            if ($currentUser) {
                $userId = $currentUser['id'];
            } else {
                // Vérifier si l'utilisateur existe déjà
                $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
                $stmt->execute([$email]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $userId = $existing['id'];
                } else {
                    $pwd = !empty($password) ? $password : 'Member2026!';
                    $reg = register_user($name, $email, $pwd, [
                        'job_title'           => 'Membre One Vision Community',
                        'subscription_status' => 'pending' // L'abonnement reste PENDING jusqu'à confirmation réelle
                    ]);
                    if ($reg['success']) {
                        $userId = $reg['user_id'];
                    } else {
                        throw new Exception($reg['error']);
                    }
                }
            }

            // Générer les numéros de commande et de facture
            $randomNum = rand(100, 999);
            $orderNumber = 'ORD-' . date('Y') . '-' . $randomNum . '-' . strtoupper(substr(uniqid(), -4));
            $invoiceNumber = 'OV-' . date('Y') . '-' . str_pad($randomNum, 4, '0', STR_PAD_LEFT);

            // Déclencher l'appel d'initiation SasPay (C2B Mobile Money ou Session de Checkout)
            $saspaySessionId = '';
            $saspayRequestId = '';
            $saspayCheckoutUrl = '';
            try {
                $returnUrl = APP_URL . '/checkout-success.php?order=' . urlencode($orderNumber);
                
                if (defined('SASPAY_TEST_OVERRIDE_AMOUNT') && SASPAY_TEST_OVERRIDE_AMOUNT !== null) {
                    $orderAmount = (float)SASPAY_TEST_OVERRIDE_AMOUNT;
                    $orderCurrency = defined('SASPAY_TEST_OVERRIDE_CURRENCY') ? SASPAY_TEST_OVERRIDE_CURRENCY : 'XOF';
                } else {
                    $orderAmount = ($method === 'mobile_money' && !empty($momoAmount)) ? (float)$momoAmount : 9.00;
                    $orderCurrency = ($method === 'mobile_money' && !empty($momoCurrency)) ? $momoCurrency : 'EUR';
                }

                if ($method === 'mobile_money') {
                    // Appel C2B / SoftPay Mobile Money
                    $momoRes = saspay_initiate_c2b([
                        'amount'         => $orderAmount,
                        'currency'       => $orderCurrency,
                        'operator'       => $momoOperator,
                        'country'        => $momoCountry,
                        'phone_number'   => $momoPhone,
                        'order_number'   => $orderNumber,
                        'customer_email' => $email,
                        'customer_name'  => $name,
                        'return_url'     => $returnUrl,
                        'metadata'       => [
                            'order_number' => $orderNumber,
                            'user_id'      => $userId,
                            'method'       => 'mobile_money',
                            'operator'     => $momoOperator,
                            'country'      => $momoCountry
                        ]
                    ]);

                    if (!$momoRes['success']) {
                        throw new Exception($momoRes['error'] ?? "Impossible d'initier la demande de paiement avec l'opérateur sélectionné.");
                    }

                    if (!empty($momoRes['checkout_request_id'])) {
                        $saspayRequestId = $momoRes['checkout_request_id'];
                        $saspaySessionId = $momoRes['session_id'] ?? $momoRes['checkout_request_id'];
                    }
                    if (!empty($momoRes['checkout_url'])) {
                        $saspayCheckoutUrl = $momoRes['checkout_url'];
                    }
                } else {
                    // Session de Checkout Carte Bancaire
                    $saspaySession = saspay_create_checkout_session([
                        'amount'         => $orderAmount,
                        'currency'       => $orderCurrency,
                        'description'    => 'Adhésion One Vision Community (9€/mois)',
                        'customer_email' => $email,
                        'customer_name'  => $name,
                        'customer_phone' => $momoPhone,
                        'return_url'     => $returnUrl,
                        'metadata'       => [
                            'order_number' => $orderNumber,
                            'user_id'      => $userId,
                            'method'       => 'card'
                        ]
                    ]);

                    if (!$saspaySession['success'] && empty($saspaySession['checkout_url'])) {
                        throw new Exception($saspaySession['error'] ?? "Impossible d'ouvrir la session de paiement par carte.");
                    }

                    if (!empty($saspaySession['session_id'])) {
                        $saspaySessionId = $saspaySession['session_id'];
                        $saspayRequestId = $saspaySession['session_id'];
                    }
                    if (!empty($saspaySession['checkout_url'])) {
                        $saspayCheckoutUrl = $saspaySession['checkout_url'];
                    }
                }
            } catch (Throwable $t) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'error'   => $t->getMessage()
                    ]);
                    exit;
                }
                throw $t;
            }

            $paymentMethodLabel = ($method === 'card') 
                ? 'Carte Bancaire Sécurisée (3D-Secure)' 
                : 'Mobile Money (' . $momoOperator . ' - ' . $momoCountry . ')';

            // CRITIQUE : La commande est enregistrée avec le statut 'pending' (JAMAIS 'paid' dès l'initiation)
            $stmt = $db->prepare("
                INSERT INTO orders (
                    order_number, user_id, amount, currency, status,
                    payment_method, billing_name, billing_email, billing_country, invoice_number, 
                    saspay_session_id, saspay_transaction_id, momo_phone, momo_operator, momo_country
                ) VALUES (
                    ?, ?, ?, ?, 'pending',
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $orderNumber,
                $userId,
                $orderAmount,
                $orderCurrency,
                $paymentMethodLabel,
                $name,
                $email,
                $momoCountry ?: 'France',
                $invoiceNumber,
                $saspaySessionId,
                $saspayRequestId,
                $momoPhone,
                $momoOperator,
                $momoCountry
            ]);

            $orderId = $db->lastInsertId();

            // CRITIQUE : L'ABONNEMENT N'EST PAS ACTIVÉ ICI.
            // Il sera activé UNIQUEMENT quand le callback IPN ou le polling confirmera le statut 'paid'.

            // Mettre en session temporaire les identifiants
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'member';

            // Réponse AJAX pour le frontend : statut 'pending' obligatoire (jamais de redirection de succès immédiate)
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                $pendingMsg = ($method === 'card') 
                    ? "En attente d'authentification bancaire..." 
                    : "En attente de confirmation sur votre téléphone...";

                echo json_encode([
                    'success'             => true,
                    'status'              => 'pending',
                    'method'              => $method,
                    'order_number'        => $orderNumber,
                    'order_id'            => $orderId,
                    'checkout_request_id' => $saspayRequestId,
                    'checkout_url'        => $saspayCheckoutUrl,
                    'poll_url'            => 'api/check-order-status.php?order=' . urlencode($orderNumber),
                    'message'             => $pendingMsg
                ]);
                exit;
            }

            // Fallback non-AJAX : redirige vers l'URL de paiement ou la page de confirmation
            if (!empty($saspayCheckoutUrl)) {
                header('Location: ' . $saspayCheckoutUrl);
                exit;
            }
            header('Location: payment.php?order=' . urlencode($orderNumber));
            exit;

        } catch (Exception $e) {
            $error = "Erreur lors de la validation du paiement : " . $e->getMessage();
        }
    }
}

$pageTitle = "Paiement Sécurisé — One Vision Community (9€/mois)";
$pageDescription = "Finalisez votre adhésion à One Vision Community pour 9€ par mois. Sans engagement, résiliable en 1 clic. Accès immédiat.";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./css/style.css?v=9">
</head>
<body class="checkout-body">

  <!-- EN-TÊTE MINIMALISTE SÉCURISÉ -->
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
        <span>Paiement Chiffré SSL 256-bit</span>
      </div>

      <a href="index.php" class="checkout-cancel-link">
        Annuler et revenir
      </a>
    </div>
  </header>

  <!-- CONTENEUR PRINCIPAL DU CHECKOUT -->
  <main class="checkout-main">
    <div class="container">
      
      <?php if (!empty($error)): ?>
        <div style="max-width:800px; margin:0 auto 1.5rem; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:1rem 1.25rem; border-radius:12px; font-size:0.95rem; display:flex; align-items:center; gap:0.75rem;">
          <span>⚠️</span>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- VUE FORMULAIRE DE PAIEMENT -->
      <div id="checkoutFormView" class="checkout-grid">
        
        <!-- COLONNE GAUCHE : FORMULAIRE DE PAIEMENT -->
        <div class="checkout-form-column">
          <div class="checkout-card">
            
            <div class="checkout-steps-badge" id="checkoutStepsNav">
              <div class="checkout-step-pill active" id="stepPill1">
                <span class="checkout-step-num" id="stepPillNum1">1</span>
                <span>1. Vos identifiants</span>
              </div>
              <span class="checkout-step-divider">→</span>
              <div class="checkout-step-pill" id="stepPill2">
                <span class="checkout-step-num" id="stepPillNum2">2</span>
                <span>2. Paiement sécurisé</span>
              </div>
            </div>

            <h1 class="checkout-title" id="checkoutMainTitle">Finaliser votre adhésion</h1>
            <p class="checkout-subtitle" id="checkoutMainSubtitle">Remplissez vos informations pour activer votre accès instantané à la communauté.</p>

            <form id="checkoutPaymentForm" method="POST" action="checkout.php" novalidate>
              <?= csrf_field() ?>
              
              <!-- ÉTAPE 1 : IDENTIFIANTS DU COMPTE (PAGE COMPACTE / CAPTURE) -->
              <div id="checkoutStep1" class="checkout-step-pane">
                <div class="form-section-title">
                  <span class="section-number">1</span>
                  <span>Vos identifiants de compte</span>
                </div>

                <div class="form-group">
                  <label for="checkoutName" class="form-label">Nom complet</label>
                  <input 
                    type="text" 
                    id="checkoutName" 
                    name="checkoutName"
                    class="form-input" 
                    placeholder="ex. Alexandre Martin" 
                    value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>"
                    required 
                    autocomplete="name"
                  >
                  <div class="field-error" id="nameError">Veuillez renseigner votre nom complet.</div>
                </div>

                <div class="form-group">
                  <label for="checkoutEmail" class="form-label">Adresse email professionnelle ou personnelle</label>
                  <input 
                    type="email" 
                    id="checkoutEmail" 
                    name="checkoutEmail"
                    class="form-input" 
                    placeholder="ex. alexandre@monprojet.fr" 
                    value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>"
                    required 
                    autocomplete="email"
                  >
                  <div class="field-error" id="emailError">Veuillez renseigner une adresse email valide.</div>
                </div>

                <div class="form-group">
                  <label for="checkoutPassword" class="form-label">Mot de passe de votre espace</label>
                  <input 
                    type="password" 
                    id="checkoutPassword" 
                    name="checkoutPassword"
                    class="form-input" 
                    placeholder="Au moins 6 caractères" 
                    minlength="6" 
                    required 
                    autocomplete="new-password"
                  >
                  <div class="field-error" id="passwordError">Le mot de passe doit comporter au moins 6 caractères.</div>
                </div>

                <div class="step1-info-badge">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                  <span>Vos identifiants permettront d'activer immédiatement votre espace membre personnel.</span>
                </div>

                <!-- Bouton Continuer Étape 1 -->
                <button type="button" id="goToStep2Btn" class="btn btn-primary checkout-submit-btn" style="margin-top:1.15rem;">
                  <span>Continuer vers le paiement</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                  </svg>
                </button>
              </div>

              <!-- ÉTAPE 2 : INFORMATIONS DE PAIEMENT SÉCURISÉ (APPARAÎT APRÈS AVOIR CLIQUÉ SUR CONTINUER) -->
              <div id="checkoutStep2" class="checkout-step-pane" style="display:none;">
                
                <!-- Résumé des identifiants saisis avec option modifier -->
                <div class="step2-member-summary">
                  <div class="step2-member-data">
                    <span style="font-size:1.15rem;">👤</span>
                    <div>
                      <strong id="step2SummaryName"><?= htmlspecialchars($currentUser['full_name'] ?? 'Membre') ?></strong>
                      <span id="step2SummaryEmail" style="display:block; font-size:0.78rem; color:#64748b;"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
                    </div>
                  </div>
                  <button type="button" id="backToStep1Btn" class="step2-edit-btn">
                    ✏️ Modifier
                  </button>
                </div>

                <!-- 2. PAIEMENT SÉCURISÉ -->
                <div class="form-section-title">
                  <span class="section-number">2</span>
                  <span>Informations de paiement sécurisé</span>
                </div>

                <input type="hidden" name="paymentMethod" id="paymentMethodHidden" value="card">

              <!-- Sélecteur de méthode de paiement -->
              <div class="payment-methods-selector" id="paymentMethodsSelector">
                <!-- Option 1 : Carte bancaire -->
                <div class="payment-method-item selected" id="methodCard" data-method="card" role="button" tabindex="0">
                  <div class="payment-method-radio"></div>
                  <div class="payment-method-info">
                    <strong>Carte bancaire</strong>
                    <span>Visa, Mastercard, CB (3D-Secure)</span>
                  </div>
                  <div class="card-icons-row">
                    <svg class="pay-logo pay-logo-cb" viewBox="0 0 38 24" width="38" height="24" fill="none" aria-label="Carte Bancaire CB"><rect width="38" height="24" rx="4" fill="#009975"/><path d="M19 0H34C36.2091 0 38 1.79086 38 4V20C38 22.2091 36.2091 24 34 24H19V0Z" fill="#0F4C81"/><text x="19" y="16.5" font-family="sans-serif" font-weight="900" font-size="12" fill="#ffffff" text-anchor="middle" letter-spacing="1">CB</text></svg>
                    <svg class="pay-logo pay-logo-visa" viewBox="0 0 38 24" width="38" height="24" fill="none" aria-label="Visa"><rect width="38" height="24" rx="4" fill="#FFFFFF" stroke="#E2E8F0"/><path d="M15.2 16.8L17.3 7.2H19.7L17.6 16.8H15.2ZM24.4 7.4C23.9 7.2 23.1 7 22.1 7C19.6 7 17.8 8.3 17.8 10.2C17.8 11.6 19.1 12.4 20 12.9C20.9 13.4 21.3 13.7 21.3 14.1C21.3 14.8 20.5 15.1 19.7 15.1C18.8 15.1 18.2 14.9 17.4 14.6L17.1 14.4L16.8 16.3C17.4 16.6 18.4 16.8 19.5 16.8C22.1 16.8 23.9 15.5 23.9 13.5C23.9 12.4 23.2 11.5 21.7 10.8C20.8 10.3 20.2 10 20.2 9.5C20.2 9.1 20.7 8.6 21.7 8.6C22.6 8.6 23.2 8.8 23.7 9L23.9 9.1L24.4 7.4ZM30.8 7.2H28.8C28.2 7.2 27.7 7.4 27.5 8L23.7 16.8H26.3L26.8 15.3H30.1L30.4 16.8H32.7L30.8 7.2ZM27.5 13.4L28.9 9.6L29.7 13.4H27.5ZM12.6 7.2L10.2 13.7L9.9 12.3C9.4 10.8 7.9 9 6.2 8.1L8.5 16.8H11.2L15.1 7.2H12.6Z" fill="#1434CB"/><path d="M8.2 7.2H4.2L4.1 7.4C7.3 8.2 9.5 10.1 10.4 12.5L9.4 7.9C9.2 7.3 8.8 7.2 8.2 7.2Z" fill="#F7B600"/></svg>
                    <svg class="pay-logo pay-logo-mc" viewBox="0 0 38 24" width="38" height="24" fill="none" aria-label="Mastercard"><rect width="38" height="24" rx="4" fill="#0F172A"/><circle cx="14.5" cy="12" r="6.8" fill="#EB001B"/><circle cx="23.5" cy="12" r="6.8" fill="#F79E1B"/><path d="M19 7.48C20.7 8.7 21.8 10.22 21.8 12C21.8 13.78 20.7 15.3 19 16.52C17.3 15.3 16.2 13.78 16.2 12C16.2 10.22 17.3 8.7 19 7.48Z" fill="#FF5F00"/></svg>
                  </div>
                </div>

                <!-- Option 2 : Paiement Mobile / Mobile Money -->
                <div class="payment-method-item" id="methodMobileMoney" data-method="mobile_money" role="button" tabindex="0">
                  <div class="payment-method-radio"></div>
                  <div class="payment-method-info">
                    <strong>Paiement Mobile / Mobile Money</strong>
                    <span>Orange Money, MTN MoMo, Wave, Moov</span>
                  </div>
                  <div class="momo-badges-row">
                    <svg class="pay-logo pay-logo-orange" viewBox="0 0 44 24" width="38" height="22" fill="none" aria-label="Orange Money"><rect width="44" height="24" rx="4" fill="#000000"/><rect x="3" y="3.5" width="17" height="17" rx="2" fill="#FF7900"/><text x="23" y="11.5" font-family="sans-serif" font-weight="900" font-size="6.5" fill="#FF7900">orange</text><text x="23" y="18" font-family="sans-serif" font-weight="800" font-size="5.5" fill="#FFFFFF">money</text></svg>
                    <svg class="pay-logo pay-logo-mtn" viewBox="0 0 44 24" width="38" height="22" fill="none" aria-label="MTN MoMo"><rect width="44" height="24" rx="4" fill="#FFCC00"/><ellipse cx="11.5" cy="12" rx="8" ry="7.5" fill="#002F6C"/><text x="11.5" y="14.5" font-family="sans-serif" font-weight="900" font-size="6" fill="#FFCC00" text-anchor="middle">MTN</text><text x="22" y="15.5" font-family="sans-serif" font-weight="900" font-size="8.5" fill="#002F6C" letter-spacing="-0.5">MoMo</text></svg>
                    <svg class="pay-logo pay-logo-wave" viewBox="0 0 44 24" width="38" height="22" fill="none" aria-label="Wave"><rect width="44" height="24" rx="4" fill="#1DC4FF"/><g transform="translate(3, 2.5) scale(0.8)"><path d="M12 2C9.5 2 7.5 4 7.5 6.5C7.5 7.7 8 8.8 8.7 9.6C8 10.9 7.5 12.6 7.5 14.6C7.5 18.5 9.5 21.6 12 21.6C14.5 21.6 16.5 18.5 16.5 14.6C16.5 12.6 16 10.9 15.3 9.6C16 8.8 16.5 7.7 16.5 6.5C16.5 4 14.5 2 12 2Z" fill="#FFFFFF"/><circle cx="10.5" cy="5.5" r="0.8" fill="#1DC4FF"/><circle cx="13.5" cy="5.5" r="0.8" fill="#1DC4FF"/><path d="M11 7L12 8.2L13 7Z" fill="#FF9900"/></g><text x="20" y="15.5" font-family="sans-serif" font-weight="900" font-size="9.5" fill="#FFFFFF" letter-spacing="-0.5">wave</text></svg>
                    <svg class="pay-logo pay-logo-moov" viewBox="0 0 46 24" width="40" height="22" fill="none" aria-label="Moov Money"><rect width="46" height="24" rx="4" fill="#005BAA"/><circle cx="10" cy="12" r="6" fill="#F37021"/><text x="10" y="15.2" font-family="sans-serif" font-weight="900" font-size="8" fill="#FFFFFF" text-anchor="middle">M</text><text x="18" y="13" font-family="sans-serif" font-weight="900" font-size="6.5" fill="#FFFFFF">moov</text><text x="18" y="19" font-family="sans-serif" font-weight="800" font-size="5" fill="#F37021">MONEY</text></svg>
                  </div>
                </div>
              </div>

              <!-- BLOC 1 : FORMULAIRE CARTE BANCAIRE INTÉGRÉ DIRECTEMENT SUR LA PAGE -->
              <div class="card-details-box" id="cardDetailsBox">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.15rem; padding-bottom:0.75rem; border-bottom:1px solid #e2e8f0;">
                  <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:1.25rem;">💳</span>
                    <strong style="color:#0f172a; font-size:0.95rem;">Paiement Direct par Carte Bancaire</strong>
                  </div>
                  <div class="card-icons-row">
                    <svg class="pay-logo pay-logo-cb" viewBox="0 0 38 24" width="30" height="19" fill="none" aria-label="CB"><rect width="38" height="24" rx="4" fill="#009975"/><path d="M19 0H34C36.2091 0 38 1.79086 38 4V20C38 22.2091 36.2091 24 34 24H19V0Z" fill="#0F4C81"/><text x="19" y="16.5" font-family="sans-serif" font-weight="900" font-size="12" fill="#ffffff" text-anchor="middle" letter-spacing="1">CB</text></svg>
                    <svg class="pay-logo pay-logo-visa" viewBox="0 0 38 24" width="30" height="19" fill="none" aria-label="Visa"><rect width="38" height="24" rx="4" fill="#FFFFFF" stroke="#E2E8F0"/><path d="M15.2 16.8L17.3 7.2H19.7L17.6 16.8H15.2ZM24.4 7.4C23.9 7.2 23.1 7 22.1 7C19.6 7 17.8 8.3 17.8 10.2C17.8 11.6 19.1 12.4 20 12.9C20.9 13.4 21.3 13.7 21.3 14.1C21.3 14.8 20.5 15.1 19.7 15.1C18.8 15.1 18.2 14.9 17.4 14.6L17.1 14.4L16.8 16.3C17.4 16.6 18.4 16.8 19.5 16.8C22.1 16.8 23.9 15.5 23.9 13.5C23.9 12.4 23.2 11.5 21.7 10.8C20.8 10.3 20.2 10 20.2 9.5C20.2 9.1 20.7 8.6 21.7 8.6C22.6 8.6 23.2 8.8 23.7 9L23.9 9.1L24.4 7.4ZM30.8 7.2H28.8C28.2 7.2 27.7 7.4 27.5 8L23.7 16.8H26.3L26.8 15.3H30.1L30.4 16.8H32.7L30.8 7.2ZM27.5 13.4L28.9 9.6L29.7 13.4H27.5ZM12.6 7.2L10.2 13.7L9.9 12.3C9.4 10.8 7.9 9 6.2 8.1L8.5 16.8H11.2L15.1 7.2H12.6Z" fill="#1434CB"/><path d="M8.2 7.2H4.2L4.1 7.4C7.3 8.2 9.5 10.1 10.4 12.5L9.4 7.9C9.2 7.3 8.8 7.2 8.2 7.2Z" fill="#F7B600"/></svg>
                    <svg class="pay-logo pay-logo-mc" viewBox="0 0 38 24" width="30" height="19" fill="none" aria-label="Mastercard"><rect width="38" height="24" rx="4" fill="#0F172A"/><circle cx="14.5" cy="12" r="6.8" fill="#EB001B"/><circle cx="23.5" cy="12" r="6.8" fill="#F79E1B"/><path d="M19 7.48C20.7 8.7 21.8 10.22 21.8 12C21.8 13.78 20.7 15.3 19 16.52C17.3 15.3 16.2 13.78 16.2 12C16.2 10.22 17.3 8.7 19 7.48Z" fill="#FF5F00"/></svg>
                  </div>
                </div>

                <div class="form-group">
                  <label for="cardNumber" class="form-label">Numéro de carte bancaire</label>
                  <div class="input-icon-wrapper">
                    <input type="text" id="cardNumber" name="cardNumber" class="form-input" placeholder="4532 •••• •••• 4242" maxlength="19" inputmode="numeric">
                    <svg class="input-icon-right" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2">
                      <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                      <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                  </div>
                  <div class="field-error" id="cardError">Numéro de carte requis (16 chiffres).</div>
                </div>

                <div class="form-row-dual">
                  <div class="form-group">
                    <label for="cardExp" class="form-label">Expiration (MM/AA)</label>
                    <input type="text" id="cardExp" name="cardExp" class="form-input" placeholder="MM/AA" maxlength="5" inputmode="numeric">
                    <div class="field-error" id="expError">Date invalide (ex: 08/28).</div>
                  </div>

                  <div class="form-group">
                    <label for="cardCvc" class="form-label">CVC / Cryptogramme</label>
                    <div class="input-icon-wrapper">
                      <input type="text" id="cardCvc" name="cardCvc" class="form-input" placeholder="123" maxlength="4" inputmode="numeric">
                      <span class="cvc-tooltip-icon" title="3 chiffres au dos de votre carte">?</span>
                    </div>
                    <div class="field-error" id="cvcError">3 ou 4 chiffres requis.</div>
                  </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                  <label for="cardHolder" class="form-label">Nom du titulaire de la carte</label>
                  <input type="text" id="cardHolder" name="cardHolder" class="form-input" placeholder="ex. Alexandre Martin" value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>">
                </div>
              </div>

              <!-- BLOC 2 : WIDGET MOBILE MONEY EMBARQUÉ (REPRODUCTION EXACTE DU WIDGET SASPAY) -->
              <div class="saspay-widget-card" id="mobileMoneyDetailsBox" style="display:none;">
                
                <!-- En-tête du widget SasPay -->
                <div class="saspay-widget-header">
                  <div class="saspay-header-brand">
                    <div class="saspay-brand-avatar">ON</div>
                    <div class="saspay-brand-info">
                      <span class="saspay-brand-title">Adhésion One Vision Communit...</span>
                      <span class="saspay-brand-sub">à One Vision</span>
                    </div>
                  </div>
                  <div class="saspay-header-price" id="saspayHeaderAmount">5 904 XAF</div>
                </div>

                <!-- 1. Sélection du Pays -->
                <label class="saspay-field-label" for="saspayCountrySelect">Pays</label>
                <div class="saspay-country-select-wrapper">
                  <select id="saspayCountrySelect" name="momoCountry" class="saspay-country-select" aria-label="Choisir votre pays">
                    <option value="Cameroun" data-currency="XAF" data-amount="5904" data-fee="0" data-total="5904" data-prefix="+237" selected>🇨🇲 Cameroun</option>
                    <option value="Côte d'Ivoire" data-currency="XOF" data-amount="5900" data-fee="0" data-total="5900" data-prefix="+225">🇨🇮 Côte d'Ivoire</option>
                    <option value="Sénégal" data-currency="XOF" data-amount="5900" data-fee="0" data-total="5900" data-prefix="+221">🇸🇳 Sénégal</option>
                    <option value="Bénin" data-currency="XOF" data-amount="5900" data-fee="0" data-total="5900" data-prefix="+229">🇧🇯 Bénin</option>
                    <option value="Burkina Faso" data-currency="XOF" data-amount="5900" data-fee="0" data-total="5900" data-prefix="+226">🇧🇫 Burkina Faso</option>
                    <option value="Mali" data-currency="XOF" data-amount="5900" data-fee="0" data-total="5900" data-prefix="+223">🇲🇱 Mali</option>
                    <option value="Togo" data-currency="XOF" data-amount="5900" data-fee="0" data-total="5900" data-prefix="+228">🇹🇬 Togo</option>
                    <option value="Guinée" data-currency="GNF" data-amount="84000" data-fee="0" data-total="84000" data-prefix="+224">🇬🇳 Guinée</option>
                    <option value="RDC" data-currency="USD" data-amount="9.80" data-fee="0.00" data-total="9.80" data-prefix="+243">🇨🇩 RDC</option>
                    <option value="Congo" data-currency="XAF" data-amount="5904" data-fee="0" data-total="5904" data-prefix="+242">🇨🇬 Congo</option>
                    <option value="Gabon" data-currency="XAF" data-amount="5904" data-fee="0" data-total="5904" data-prefix="+241">🇬🇦 Gabon</option>
                    <option value="France" data-currency="EUR" data-amount="9.00" data-fee="0.00" data-total="9.00" data-prefix="+33">🌍 International (EUR)</option>
                  </select>
                  <span class="saspay-select-arrow">▼</span>
                </div>

                <!-- 2. Moyen de paiement selon le pays -->
                <label class="saspay-field-label">Moyen de paiement</label>
                <div class="saspay-methods-grid" id="saspayMethodsGrid">
                  <!-- Injecté dynamiquement par JavaScript selon le pays -->
                </div>
                <input type="hidden" name="momoOperator" id="saspaySelectedOperator" value="MTN MoMo">

                <!-- 3. Numéro de téléphone -->
                <label class="saspay-field-label" for="saspayPhoneInput">Numéro de téléphone</label>
                <div class="saspay-phone-wrapper">
                  <div class="saspay-phone-prefix" id="saspayPhonePrefix">📱 +237</div>
                  <input type="tel" id="saspayPhoneInput" name="momoPhone" class="saspay-phone-input" placeholder="67 12 34 56 7" inputmode="tel">
                </div>
                <div class="field-error" id="momoPhoneError" style="margin-top:-0.9rem; margin-bottom:1rem;">Numéro de téléphone Mobile Money requis.</div>

                <input type="hidden" name="momoAmount" id="momoAmountHidden" value="5904">
                <input type="hidden" name="momoCurrency" id="momoCurrencyHidden" value="XAF">

                <!-- 4. Récapitulatif tarifaire exact -->
                <div class="saspay-breakdown-box">
                  <div class="saspay-breakdown-row">
                    <span>Montant</span>
                    <strong id="saspayBreakdownAmount">5 904 XAF</strong>
                  </div>
                  <div class="saspay-breakdown-row">
                    <span>Frais</span>
                    <span id="saspayBreakdownFee">0 XAF</span>
                  </div>
                  <div class="saspay-breakdown-row saspay-breakdown-total">
                    <span>Total à payer</span>
                    <strong id="saspayBreakdownTotal">5 904 XAF</strong>
                  </div>
                </div>

                <!-- 5. Footer officiel SasPay -->
                <div class="saspay-footer-badge">
                  <span>🔒 Paiement sécurisé propulsé par</span>
                  <span class="saspay-footer-logo">⚡ SasPay</span>
                </div>

              </div>

              <!-- Bouton de paiement CTA Principal -->
              <button type="submit" id="submitPaymentBtn" class="btn btn-primary checkout-submit-btn">
                <span id="submitPaymentText">Payer 9,00 € par Carte Bancaire</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                  <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
              </button>

              <div class="checkout-guarantee-note">
                <div class="guarantee-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <span>Sans engagement • Annulation en 1 clic • Facture PDF immédiate</span>
                </div>
                <div class="guarantee-item">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <span>Validation directe sans redirection externe • Accès instantané</span>
                </div>
              </div>

              </div> <!-- Fin #checkoutStep2 -->
            </form>

            <!-- ZONE D'ATTENTE & QR CODE EMBARQUÉE DIRECTEMENT SUR LA PAGE DE CHECKOUT (SANS REDIRECTION) -->
            <div id="checkoutWaitingArea" class="checkout-waiting-area" style="display:none; padding: 1.5rem 0.5rem; text-align: center;">
              
              <!-- ÉTAT 1 : EN ATTENTE / SCAN DU QR CODE -->
              <div id="inpageStatePending">
                <div class="processing-pulse-ring" style="margin: 0 auto 1.25rem;">
                  <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5" id="inpageSpinnerIcon">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                  </svg>
                </div>

                <h3 class="waiting-title" id="inpageWaitingTitle" style="font-size:1.3rem; font-weight:800; color:#0f172a; margin-bottom:0.5rem;">
                  ⏳ En attente de confirmation sur votre téléphone...
                </h3>
                <p class="waiting-desc" id="inpageWaitingDesc" style="font-size:0.9rem; color:#64748b; max-width:440px; margin:0 auto 1.25rem; line-height:1.5;">
                  Une demande a été initiée auprès de SasPay. Scannez le QR Code ci-dessous ou confirmez l'invite sur votre mobile.
                </p>

                <!-- BLOC QR CODE GÉNÉRÉ LOCALEMENT EN CANVAS SANS DÉPENDANCE EXTERNE -->
                <div id="inpageQrSection" style="margin: 1.25rem auto; padding: 1.5rem; background: #ffffff; border: 2px solid #e0e7ff; border-radius: 20px; box-shadow: 0 10px 30px rgba(79, 70, 229, 0.08); text-align: center; max-width: 440px;">
                  <div style="display:inline-flex; align-items:center; gap:0.4rem; background:#eef2ff; color:#4338ca; padding:0.35rem 0.85rem; border-radius:20px; font-size:0.8rem; font-weight:700; margin-bottom:0.75rem;">
                    <span>📸</span>
                    <span>QR Code de Paiement Instantané</span>
                  </div>

                  <h4 style="font-size:1.05rem; font-weight:800; color:#0f172a; margin-bottom:0.35rem;">
                    Scannez avec votre téléphone (Wave ou Mobile)
                  </h4>
                  <p style="font-size:0.82rem; color:#64748b; margin-bottom:1rem; line-height:1.45; max-width:360px; margin-left:auto; margin-right:auto;">
                    1. Ouvrez l'application <strong>Wave</strong> ou votre scanner mobile<br>
                    2. Appuyez sur <strong>Scanner</strong> et visez ce QR Code<br>
                    3. Confirmez avec votre code PIN secret
                  </p>

                  <!-- Rendu local du QR Code par QRCode.js -->
                  <div id="inpageQrCanvasWrap" style="display:inline-flex; align-items:center; justify-content:center; padding:14px; background:#ffffff; border-radius:16px; box-shadow: 0 4px 18px rgba(0,0,0,0.08); border:1.5px solid #e2e8f0; margin-bottom:0.85rem; min-width:220px; min-height:220px;">
                    <div id="inpageQrCanvas"></div>
                  </div>

                  <div>
                    <a id="inpageDirectLinkBtn" href="#" target="_blank" rel="noopener noreferrer" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.82rem; font-weight:700; color:#4f46e5; text-decoration:none; padding:0.5rem 1rem; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px;">
                      <span>📱 Ouvrir directement sur cet appareil →</span>
                    </a>
                  </div>
                </div>

                <div class="processing-timer-badge" style="display:inline-flex; align-items:center; gap:0.4rem; background:#fef3c7; color:#b45309; padding:0.4rem 0.9rem; border-radius:20px; font-size:0.82rem; font-weight:700; margin-bottom:1rem;">
                  <span>⏳</span>
                  <span id="inpageTimerText">Temps restant pour valider : 03:00</span>
                </div>

                <div class="processing-progress-bar-wrap" style="height:6px; background:#f1f5f9; border-radius:999px; overflow:hidden; max-width:320px; margin:0 auto 1.25rem;">
                  <div class="processing-progress-bar-fill" id="inpageProgressBar" style="height:100%; width:30%; background:linear-gradient(90deg, #6366f1, #10b981); transition:width 0.4s ease;"></div>
                </div>

                <div style="margin-top:1.25rem;">
                  <button type="button" id="inpageCancelBtn" class="btn btn-secondary" style="font-size:0.85rem; padding:0.5rem 1.25rem;">
                    ← Revenir au formulaire
                  </button>
                </div>
              </div>

              <!-- ÉTAT 2 : SUCCÈS CONFIRMÉ EN DIRECT (SANS REDIRECTION NI RECHARGEMENT) -->
              <div id="inpageStateSuccess" style="display:none; text-align:center; padding: 1.5rem 0;">
                <div style="width:72px; height:72px; background:#dcfce7; color:#15803d; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2.4rem; margin:0 auto 1rem; box-shadow:0 8px 25px rgba(22, 163, 74, 0.2);">
                  ✓
                </div>
                <h3 style="font-size:1.5rem; font-weight:800; color:#15803d; margin-bottom:0.5rem;">
                  Paiement validé avec succès !
                </h3>
                <p style="font-size:0.95rem; color:#475569; max-width:440px; margin:0 auto 1.5rem; line-height:1.5;">
                  Votre transaction a été confirmée en temps réel par SasPay. Votre adhésion à <strong>One Vision Community</strong> est active !
                </p>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem; max-width:420px; margin:0 auto 1.5rem; text-align:left; font-size:0.88rem;">
                  <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                    <span style="color:#64748b;">N° Commande :</span>
                    <strong style="color:#0f172a;" id="inpageSuccessOrder">ORD-2026</strong>
                  </div>
                  <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                    <span style="color:#64748b;">Montant réglé :</span>
                    <strong style="color:#15803d;" id="inpageSuccessAmount">
                      <?= (defined('SASPAY_TEST_OVERRIDE_AMOUNT') && SASPAY_TEST_OVERRIDE_AMOUNT !== null) ? htmlspecialchars(SASPAY_TEST_OVERRIDE_AMOUNT) . ' ' . htmlspecialchars(SASPAY_TEST_OVERRIDE_CURRENCY) : '9,00 €' ?>
                    </strong>
                  </div>
                  <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                    <span style="color:#64748b;">Statut :</span>
                    <span style="color:#15803d; font-weight:700; background:#dcfce7; padding:2px 8px; border-radius:6px;">Confirmé & Payé</span>
                  </div>
                  <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Accès membre :</span>
                    <strong style="color:#4f46e5;">Immédiat</strong>
                  </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:0.75rem; max-width:380px; margin:0 auto;">
                  <a href="dashboard.php" class="btn btn-primary btn-lg" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:0.5rem; font-weight:700;">
                    <span>Accéder à mon Dashboard Membre</span>
                    <span>→</span>
                  </a>
                  <a href="index.php" style="color:#64748b; font-size:0.85rem; text-decoration:none;">
                    Retourner à la page d'accueil
                  </a>
                </div>
              </div>

            </div>

          </div>
        </div>

        <!-- COLONNE DROITE : RÉCAPITULATIF DE COMMANDE -->
        <div class="checkout-summary-column">
          <div class="summary-card">
            
            <div class="summary-header">
              <span class="summary-pill">Accès Membre Illimité</span>
              <h2 class="summary-title">One Vision Community</h2>
              <p class="summary-desc">L'espace d'entraide, de lives interactifs et de partenariats des entrepreneurs ambitieux.</p>
            </div>

            <ul class="summary-features-list">
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span><strong>4 Lives & Masterminds</strong> interactifs en visio par mois</span>
              </li>
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span><strong>Salons d'échanges privés</strong> par thématiques 24h/24 & 7j/7</span>
              </li>
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span><strong>Replays intégraux HD</strong> et bibliothèque de fiches outils</span>
              </li>
              <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span><strong>Réseau qualifié</strong> : +1 200 pairs actifs prêts à collaborer</span>
              </li>
            </ul>

            <div class="summary-pricing-box">
              <div class="pricing-line">
                <span>Adhésion mensuelle</span>
                <span class="price-val">
                  <?php if (defined('SASPAY_TEST_OVERRIDE_AMOUNT') && SASPAY_TEST_OVERRIDE_AMOUNT !== null): ?>
                    <?= htmlspecialchars(SASPAY_TEST_OVERRIDE_AMOUNT) ?> <?= htmlspecialchars(SASPAY_TEST_OVERRIDE_CURRENCY) ?> <small>(Essai réel)</small>
                  <?php else: ?>
                    9,00 € <small>(~5 900 FCFA)</small>
                  <?php endif; ?>
                </span>
              </div>
              <div class="pricing-line">
                <span>Frais d'activation</span>
                <span class="price-free">OFFERTS (0 €)</span>
              </div>
              <div class="pricing-line total-line">
                <span>Total à régler aujourd'hui</span>
                <span class="total-amount">
                  <?php if (defined('SASPAY_TEST_OVERRIDE_AMOUNT') && SASPAY_TEST_OVERRIDE_AMOUNT !== null): ?>
                    <?= htmlspecialchars(SASPAY_TEST_OVERRIDE_AMOUNT) ?> <?= htmlspecialchars(SASPAY_TEST_OVERRIDE_CURRENCY) ?> <span class="recur-text">(Essai réel)</span>
                  <?php else: ?>
                    9,00 € <span class="recur-text">/ mois</span>
                  <?php endif; ?>
                </span>
              </div>
            </div>

            <div class="summary-testimonial">
              <div class="testimonial-stars">★★★★★</div>
              <p class="testimonial-quote">« À 9€ par mois, le retour sur investissement est immédiat dès la première session de co-working. Je ne regrette qu'une chose : ne pas avoir rejoint plus tôt ! »</p>
              <div class="testimonial-author">
                <img src="./img/avatar-aurore.jpg" alt="Aurore M." class="author-avatar" width="34" height="34">
                <div>
                  <div class="author-name">Aurore M.</div>
                  <div class="author-role">Fondatrice Studio Créatif • Membre One Vision</div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>

    </div>

    <!-- MODALE DE TRAITEMENT ET VALIDATION INTERACTIVE DU PAIEMENT -->
    <div id="paymentProcessingModal" class="payment-processing-overlay" aria-hidden="true">
      <div class="payment-processing-card">
        
        <div class="processing-pulse-ring" id="processingPulseRing">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5" id="processingSpinnerIcon">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
          </svg>
        </div>

        <h3 class="processing-title" id="processingTitle">Validation du paiement en cours...</h3>
        <p class="processing-desc" id="processingDesc">
          Connexion à la passerelle sécurisée SasPay...
        </p>

        <div class="processing-device-alert" id="processingDeviceAlert" style="display:none;">
          <span style="font-size:1.6rem; line-height:1;">📲</span>
          <div>
            <strong style="color:#15803d; font-size:0.9rem; display:block; margin-bottom:2px;" id="processingAlertTitle">Notification envoyée sur votre téléphone</strong>
            <span style="color:#166534; font-size:0.82rem; line-height:1.4; display:block;" id="processingAlertMsg">
              Veuillez saisir votre code PIN Mobile Money sur votre mobile pour approuver le paiement.
            </span>
          </div>
        </div>

        <!-- BLOC QR CODE WAVE EMBARQUÉ DANS LA MODALE -->
        <div class="processing-qr-card" id="processingQrCard" style="display:none; margin: 1rem 0 1.25rem; padding: 1.25rem; background: #ffffff; border: 2px solid #e0e7ff; border-radius: 18px; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.08); text-align: center;">
          <div style="display:inline-flex; align-items:center; gap:0.4rem; background:#eef2ff; color:#4338ca; padding:0.35rem 0.85rem; border-radius:20px; font-size:0.8rem; font-weight:700; margin-bottom:0.75rem;">
            <span>📸</span>
            <span>Scan Wave Instantané</span>
          </div>
          <h4 style="font-size:1.05rem; font-weight:800; color:#0f172a; margin-bottom:0.35rem;">
            Scannez ce QR Code avec votre téléphone
          </h4>
          <p style="font-size:0.82rem; color:#64748b; margin-bottom:1rem; line-height:1.45; max-width:340px; margin-left:auto; margin-right:auto;">
            1. Ouvrez l'application <strong>Wave</strong> sur votre mobile<br>
            2. Appuyez sur <strong>Scanner</strong> et pointez vers ce QR Code<br>
            3. Validez avec votre code PIN secret
          </p>

          <div style="display:inline-block; padding:12px; background:#ffffff; border-radius:14px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border:1px solid #e2e8f0; margin-bottom:0.85rem;">
            <img id="processingQrImg" src="" alt="QR Code Wave" style="width:190px; height:190px; display:block; border-radius:8px;" />
          </div>

          <div>
            <a id="processingQrDirectBtn" href="#" target="_blank" rel="noopener noreferrer" style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.82rem; font-weight:700; color:#4f46e5; text-decoration:none; padding:0.45rem 0.9rem; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px;">
              <span>📱 Ouvrir Wave directement sur cet appareil →</span>
            </a>
          </div>
        </div>

        <div class="processing-timer-badge" id="processingTimerBadge" style="display:none;">
          <span id="processingTimerIcon">⏳</span>
          <span id="processingTimerText">En attente de validation sur votre téléphone...</span>
        </div>

        <div class="processing-progress-bar-wrap">
          <div class="processing-progress-bar-fill" id="processingProgressBar"></div>
        </div>

        <div class="processing-actions" id="processingActions" style="display:none;">
          <button type="button" class="processing-retry-btn" id="processingRetryBtn" style="display:none;">🔄 Réessayer le paiement</button>
          <button type="button" class="processing-cancel-btn" id="processingCancelBtn">Annuler la demande</button>
        </div>

        <span style="font-size:0.78rem; color:#94a3b8; display:block; margin-top:1rem;">
          Paiement sécurisé chiffré SSL 256-bit • SasPay & 3D-Secure
        </span>

      </div>
    </div>
  </main>

  <script>
    window.SASPAY_CONFIG = {
      testOverrideActive: <?= (defined('SASPAY_TEST_OVERRIDE_AMOUNT') && SASPAY_TEST_OVERRIDE_AMOUNT !== null) ? 'true' : 'false' ?>,
      testOverrideAmount: <?= (defined('SASPAY_TEST_OVERRIDE_AMOUNT') && SASPAY_TEST_OVERRIDE_AMOUNT !== null) ? json_encode(SASPAY_TEST_OVERRIDE_AMOUNT) : 'null' ?>,
      testOverrideCurrency: <?= json_encode(defined('SASPAY_TEST_OVERRIDE_CURRENCY') ? SASPAY_TEST_OVERRIDE_CURRENCY : 'XOF') ?>
    };
  </script>
  <script src="./js/qrcode.min.js"></script>
  <script src="./js/main.js?v=12"></script>
</body>
</html>
