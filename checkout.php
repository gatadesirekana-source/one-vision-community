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
                        'job_title' => 'Membre One Vision Community'
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

            // Créer une session SasPay en arrière-plan pour traçabilité de l'API
            $saspaySessionId = '';
            try {
                $returnUrl = APP_URL . '/checkout-success.php?order=' . urlencode($orderNumber);
                $saspaySession = saspay_create_checkout_session([
                    'amount'         => 9.00,
                    'currency'       => 'EUR',
                    'description'    => 'Adhésion One Vision Community (9€/mois)',
                    'customer_email' => $email,
                    'customer_name'  => $name,
                    'customer_phone' => $momoPhone,
                    'return_url'     => $returnUrl,
                    'metadata'       => [
                        'order_number' => $orderNumber,
                        'user_id'      => $userId,
                        'method'       => $method,
                        'operator'     => $momoOperator,
                        'country'      => $momoCountry
                    ]
                ]);
                if (!empty($saspaySession['session_id'])) {
                    $saspaySessionId = $saspaySession['session_id'];
                }
            } catch (Throwable $t) {
                // silencieux
            }

            $paymentMethodLabel = ($method === 'card') 
                ? 'Carte Bancaire Sécurisée (3D-Secure)' 
                : 'Mobile Money (' . $momoOperator . ' - ' . $momoCountry . ')';

            $stmt = $db->prepare("
                INSERT INTO orders (
                    order_number, user_id, amount, currency, status,
                    payment_method, billing_name, billing_email, billing_country, invoice_number, 
                    saspay_session_id, momo_phone, momo_operator, momo_country
                ) VALUES (
                    ?, ?, 9.00, 'EUR', 'paid',
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $orderNumber,
                $userId,
                $paymentMethodLabel,
                $name,
                $email,
                $momoCountry ?: 'France',
                $invoiceNumber,
                $saspaySessionId,
                $momoPhone,
                $momoOperator,
                $momoCountry
            ]);

            $orderId = $db->lastInsertId();

            // Activer immédiatement l'abonnement du membre
            $db->prepare("UPDATE users SET subscription_status = 'active' WHERE id = ?")->execute([$userId]);

            // Mettre en session l'utilisateur
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'member';

            $redirectUrl = 'checkout-success.php?order=' . urlencode($orderNumber);

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success'      => true,
                    'redirect_url' => $redirectUrl,
                    'order_number' => $orderNumber,
                    'order_id'     => $orderId
                ]);
                exit;
            }

            header('Location: ' . $redirectUrl);
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

  <link rel="stylesheet" href="./css/style.css?v=6">
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
            
            <div class="checkout-steps-badge">
              <span class="step-badge active">Étape unique : Adhésion & Activation</span>
            </div>

            <h1 class="checkout-title">Finaliser votre adhésion</h1>
            <p class="checkout-subtitle">Remplissez vos informations pour activer votre accès instantané à la communauté.</p>

            <form id="checkoutPaymentForm" method="POST" action="checkout.php" novalidate>
              <?= csrf_field() ?>
              
              <!-- 1. IDENTIFIANTS DU COMPTE -->
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

              <?php if (!$currentUser): ?>
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
              <?php endif; ?>

              <!-- 2. PAIEMENT SÉCURISÉ -->
              <div class="form-section-title" style="margin-top:1.8rem;">
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
                    <span>Visa, Mastercard, CB, Amex (3D-Secure)</span>
                  </div>
                  <div class="card-icons-row">
                    <span class="card-chip-icon">CB</span>
                    <span class="card-chip-icon">VISA</span>
                    <span class="card-chip-icon">MC</span>
                  </div>
                </div>

                <!-- Option 2 : Paiement Mobile / Mobile Money -->
                <div class="payment-method-item" id="methodMobileMoney" data-method="mobile_money" role="button" tabindex="0">
                  <div class="payment-method-radio"></div>
                  <div class="payment-method-info">
                    <strong>Paiement Mobile / Mobile Money (SasPay)</strong>
                    <span>Orange Money, MTN MoMo, Wave, Moov, Airtel</span>
                  </div>
                  <div class="momo-badges-row">
                    <span class="momo-chip-badge badge-orange">Orange</span>
                    <span class="momo-chip-badge badge-mtn">MTN</span>
                    <span class="momo-chip-badge badge-wave">Wave</span>
                    <span class="momo-chip-badge badge-moov">Moov</span>
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
                  <div style="display:flex; gap:4px;">
                    <span class="card-chip-icon">CB</span>
                    <span class="card-chip-icon">VISA</span>
                    <span class="card-chip-icon">MC</span>
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

                <div style="margin-top:1.15rem; padding:0.75rem 1rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; display:flex; align-items:center; gap:0.6rem; font-size:0.82rem; color:#15803d;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                  <span>Chiffrement SSL 256-bit • Protection bancaire 3D-Secure active</span>
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
                  <div class="saspay-header-price" id="saspayHeaderAmount">5904 XAF</div>
                </div>

                <!-- 1. Sélection du Pays -->
                <label class="saspay-field-label" for="saspayCountrySelect">Pays</label>
                <div class="saspay-country-select-wrapper">
                  <select id="saspayCountrySelect" name="momoCountry" class="saspay-country-select" aria-label="Choisir votre pays">
                    <option value="Cameroun" data-currency="XAF" data-amount="5904" data-fee="267" data-total="6171" data-prefix="+237" selected>🇨🇲 Cameroun</option>
                    <option value="Côte d'Ivoire" data-currency="XOF" data-amount="5900" data-fee="100" data-total="6000" data-prefix="+225">🇨🇮 Côte d'Ivoire</option>
                    <option value="Sénégal" data-currency="XOF" data-amount="5900" data-fee="100" data-total="6000" data-prefix="+221">🇸🇳 Sénégal</option>
                    <option value="Bénin" data-currency="XOF" data-amount="5900" data-fee="100" data-total="6000" data-prefix="+229">🇧🇯 Bénin</option>
                    <option value="Burkina Faso" data-currency="XOF" data-amount="5900" data-fee="100" data-total="6000" data-prefix="+226">🇧🇫 Burkina Faso</option>
                    <option value="Mali" data-currency="XOF" data-amount="5900" data-fee="100" data-total="6000" data-prefix="+223">🇲🇱 Mali</option>
                    <option value="Togo" data-currency="XOF" data-amount="5900" data-fee="100" data-total="6000" data-prefix="+228">🇹🇬 Togo</option>
                    <option value="Guinée" data-currency="GNF" data-amount="84000" data-fee="1500" data-total="85500" data-prefix="+224">🇬🇳 Guinée</option>
                    <option value="RDC" data-currency="USD" data-amount="9.80" data-fee="0.20" data-total="10.00" data-prefix="+243">🇨🇩 RDC</option>
                    <option value="Congo" data-currency="XAF" data-amount="5904" data-fee="267" data-total="6171" data-prefix="+242">🇨🇬 Congo</option>
                    <option value="Gabon" data-currency="XAF" data-amount="5904" data-fee="267" data-total="6171" data-prefix="+241">🇬🇦 Gabon</option>
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
                    <span id="saspayBreakdownFee">+267 XAF</span>
                  </div>
                  <div class="saspay-breakdown-row saspay-breakdown-total">
                    <span>Total à payer</span>
                    <strong id="saspayBreakdownTotal">6 171 XAF</strong>
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
                <span id="submitPaymentText">Payer 9,00 € par Carte Bancaire →</span>
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

            </form>
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
                <span class="price-val">9,00 €</span>
              </div>
              <div class="pricing-line">
                <span>Frais d'activation</span>
                <span class="price-free">OFFERTS (0 €)</span>
              </div>
              <div class="pricing-line total-line">
                <span>Total à régler aujourd'hui</span>
                <span class="total-amount">9,00 € <span class="recur-text">/ mois</span></span>
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

        <div class="processing-progress-bar-wrap">
          <div class="processing-progress-bar-fill" id="processingProgressBar"></div>
        </div>

        <span style="font-size:0.78rem; color:#94a3b8; display:block;">
          Paiement sécurisé chiffré SSL 256-bit • SasPay & 3D-Secure
        </span>

      </div>
    </div>
  </main>

  <script src="./js/main.js?v=5"></script>
</body>
</html>
