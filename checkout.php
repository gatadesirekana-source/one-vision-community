<?php
/**
 * ONE VISION COMMUNITY — PAGE DE CHECKOUT RETIRÉE -> REDIRECTION CONNEXION / ESPACE MEMBRE
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $isCsrfValid = verify_csrf_token($csrfToken);

    // Permettre également la soumission depuis la page statique checkout.html hébergée sur le même serveur
    if (!$isCsrfValid) {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (
            (!empty($host) && (strpos($referer, $host) !== false || strpos($origin, $host) !== false)) ||
            strpos($referer, 'checkout.html') !== false ||
            strpos($origin, 'localhost') !== false ||
            strpos($origin, '127.0.0.1') !== false
        ) {
            $isCsrfValid = true;
        }
    }

    if (!$isCsrfValid) {
        $error = "Session de formulaire expirée. Veuillez actualiser la page et réessayer.";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    } else {
        $action = trim($_POST['action'] ?? '');
        $name = trim($_POST['checkoutName'] ?? ($currentUser['full_name'] ?? ''));
        $email = trim(strtolower($_POST['checkoutEmail'] ?? ($currentUser['email'] ?? '')));
        $password = $_POST['checkoutPassword'] ?? '';
        $method = trim($_POST['paymentMethod'] ?? 'card');

        // Pour l'initialisation du QR Code Mobile Money, prévoir des valeurs par défaut si non encore renseignées
        if ($action === 'init_momo' || $method === 'mobile_money') {
            if (empty($name)) {
                $name = 'Membre One Vision';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = 'membre.' . substr(md5(uniqid()), 0, 8) . '@onevision.academy';
            }
        }

        // Validation de base nom + email
        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Veuillez renseigner un nom valide et une adresse email valide.";
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        } else {
            try {
                $userId = null;
                if ($currentUser) {
                    $userId = $currentUser['id'];
                } else {
                    $stmt = $db->prepare("SELECT id, full_name, email, role, password FROM users WHERE LOWER(email) = ?");
                    $stmt->execute([$email]);
                    $existing = $stmt->fetch();

                    if ($existing) {
                        if (!empty($password) && !password_verify($password, $existing['password']) && $method !== 'mobile_money' && $action !== 'init_momo') {
                            throw new Exception("Un compte associé à cette adresse email existe déjà. Veuillez renseigner votre mot de passe pour renouveler votre adhésion ou vous connecter.");
                        }
                        $userId = $existing['id'];
                    } else {
                        if (empty($password)) {
                            if ($method === 'mobile_money' || $action === 'init_momo') {
                                $password = 'OV-' . bin2hex(random_bytes(4)) . '!';
                            } else {
                                throw new Exception("Veuillez choisir un mot de passe d'au moins 6 caractères pour créer votre compte membre.");
                            }
                        } elseif (strlen($password) < 6) {
                            throw new Exception("Veuillez choisir un mot de passe d'au moins 6 caractères pour créer votre compte membre.");
                        }
                        $reg = register_user($name, $email, $password, [
                            'job_title'           => 'Membre One Vision Community',
                            'subscription_status' => 'pending'
                        ]);
                        if ($reg['success']) {
                            $userId = $reg['user_id'];
                        } else {
                            throw new Exception($reg['error']);
                        }
                    }
                }

                // Génération des numéros uniques de commande et de facture
                do {
                    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                    $checkOrd = $db->prepare("SELECT 1 FROM orders WHERE order_number = ? LIMIT 1");
                    $checkOrd->execute([$orderNumber]);
                } while ($checkOrd->fetch());

                do {
                    $invoiceNumber = 'OV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                    $checkInv = $db->prepare("SELECT 1 FROM orders WHERE invoice_number = ? LIMIT 1");
                    $checkInv->execute([$invoiceNumber]);
                } while ($checkInv->fetch());

                $plan = trim($_POST['subscription_plan'] ?? $_GET['plan'] ?? 'member');
                if ($plan !== 'creator') {
                    $plan = 'member';
                }

                // =========================================================================
                // CAS 1 : MOYEN DE PAIEMENT MOBILE MONEY
                // =========================================================================
                if ($action === 'init_momo' || $method === 'mobile_money') {
                    $orderAmountXof = ($plan === 'creator') ? 19000.00 : 5900.00;
                    $paymentId = 'MOMO-' . strtoupper(bin2hex(random_bytes(6)));

                    $stmt = $db->prepare("
                        INSERT INTO orders (
                            order_number, user_id, amount, currency, status,
                            payment_method, billing_name, billing_email, billing_country, invoice_number, 
                            payment_id, plan
                        ) VALUES (
                            ?, ?, ?, 'XOF', 'paid',
                            'Mobile Money (Wave / MoMo)', ?, ?, 'Afrique', ?, 
                            ?, ?
                        )
                    ");
                    $stmt->execute([
                        $orderNumber,
                        $userId,
                        $orderAmountXof,
                        $name,
                        $email,
                        $invoiceNumber,
                        $paymentId,
                        $plan
                    ]);

                    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $nextBillingDate = date('Y-m-d', strtotime('+30 days'));
                    $todayDate = date('Y-m-d');

                    $db->prepare("
                        UPDATE users 
                        SET subscription_status = 'active', 
                            subscription_plan = ?,
                            subscription_started_at = CURRENT_TIMESTAMP,
                            subscription_expires_at = ?,
                            auto_renew = 1,
                            last_billing_date = ?,
                            next_billing_date = ?,
                            failed_renewals_count = 0
                        WHERE id = ?
                    ")->execute([$plan, $expiresAt, $todayDate, $nextBillingDate, $userId]);

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'member';
                    $_SESSION['subscription_plan'] = $plan;
                    $_SESSION['pending_order_number'] = $orderNumber;

                    $successUrl = 'checkout-success.php?order=' . urlencode($orderNumber) . '&mode=momo';

                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success'      => true,
                            'status'       => 'paid',
                            'order_number' => $orderNumber,
                            'redirect_url' => $successUrl
                        ]);
                        exit;
                    }

                    header('Location: ' . $successUrl);
                    exit;
                }

                // =========================================================================
                // CAS 2 : PAIEMENT PAR CARTE BANCAIRE (VALIDATION SÉCURISÉE DIRECTE)
                // =========================================================================
                if ($method === 'card') {

                    $cleanNumber = preg_replace('/\D/', '', $_POST['cardNumber'] ?? '');
                    $cardExp = trim($_POST['cardExp'] ?? '');
                    $cardCvc = trim($_POST['cardCvc'] ?? '');
                    $cardHolder = trim($_POST['cardHolder'] ?? '') ?: $name;

                    // 1. Contrôle de longueur du numéro
                    if (strlen($cleanNumber) < 13 || strlen($cleanNumber) > 19) {
                        throw new Exception("Numéro de carte bancaire invalide (longueur incorrecte, 16 chiffres attendus).");
                    }

                    // 2. Contrôle de l'algorithme de sécurité bancaire (Formule de Luhn)
                    $sum = 0;
                    $shouldDouble = false;
                    for ($i = strlen($cleanNumber) - 1; $i >= 0; $i--) {
                        $digit = (int)$cleanNumber[$i];
                        if ($shouldDouble) {
                            $digit *= 2;
                            if ($digit > 9) {
                                $digit -= 9;
                            }
                        }
                        $sum += $digit;
                        $shouldDouble = !$shouldDouble;
                    }
                    if ($sum % 10 !== 0) {
                        throw new Exception("Numéro de carte bancaire invalide : échec du contrôle de sécurité bancaire (Luhn).");
                    }

                    // 3. Détection du réseau de carte
                    $cardBrand = 'Carte Bancaire';
                    if (preg_match('/^4/', $cleanNumber)) {
                        $cardBrand = 'Visa';
                    } elseif (preg_match('/^(5[1-5]|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)/', $cleanNumber)) {
                        $cardBrand = 'Mastercard';
                    } elseif (preg_match('/^3[47]/', $cleanNumber)) {
                        $cardBrand = 'American Express';
                    }

                    // 4. Contrôle de la date d'expiration
                    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $cardExp, $mExp)) {
                        throw new Exception("Date d'expiration invalide (format requis : MM/AA, ex. 12/28).");
                    }
                    $expMonth = (int)$mExp[1];
                    $expYear = 2000 + (int)$mExp[2];
                    $curYear = (int)date('Y');
                    $curMonth = (int)date('n');

                    if ($expYear < $curYear || ($expYear === $curYear && $expMonth < $curMonth)) {
                        throw new Exception("Cette carte bancaire est expirée.");
                    }
                    if ($expYear > $curYear + 25) {
                        throw new Exception("Date d'expiration de la carte invalide.");
                    }

                    // 5. Contrôle du cryptogramme CVC
                    $cleanCvc = preg_replace('/\D/', '', $cardCvc);
                    $expectedCvcLen = ($cardBrand === 'American Express') ? 4 : 3;
                    if (strlen($cleanCvc) < 3 || strlen($cleanCvc) > 4) {
                        throw new Exception("Le cryptogramme CVC est incomplet ({$expectedCvcLen} chiffres au dos de la carte).");
                    }

                    // 6. Contrôle du titulaire de la carte
                    if (strlen($cardHolder) < 2) {
                        throw new Exception("Veuillez renseigner le nom complet du titulaire de la carte.");
                    }

                    $paymentId = 'CARD-' . strtoupper(bin2hex(random_bytes(6)));
                    $orderAmountEur = ($plan === 'creator') ? 29.00 : 9.00;

                    $stmt = $db->prepare("
                        INSERT INTO orders (
                            order_number, user_id, amount, currency, status,
                            payment_method, billing_name, billing_email, billing_country, invoice_number, 
                            payment_id, plan
                        ) VALUES (
                            ?, ?, ?, 'EUR', 'paid',
                            ?, ?, ?, 'France', ?, 
                            ?, ?
                        )
                    ");
                    $stmt->execute([
                        $orderNumber,
                        $userId,
                        $orderAmountEur,
                        'Carte bancaire (' . $cardBrand . ' 3D-Secure)',
                        $cardHolder,
                        $email,
                        $invoiceNumber,
                        $paymentId,
                        $plan
                    ]);

                    // Activation immédiate de l'abonnement du membre avec enregistrement de la carte pour prélèvements automatiques mensuels
                    $last4 = substr($cleanNumber, -4);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $nextBillingDate = date('Y-m-d', strtotime('+30 days'));
                    $todayDate = date('Y-m-d');

                    $stmtUserUpdate = $db->prepare("
                        UPDATE users 
                        SET subscription_status = 'active', 
                            subscription_plan = ?,
                            subscription_started_at = CURRENT_TIMESTAMP,
                            subscription_expires_at = ?,
                            auto_renew = 1,
                            card_last4 = ?,
                            card_brand = ?,
                            card_exp = ?,
                            card_holder = ?,
                            last_billing_date = ?,
                            next_billing_date = ?,
                            failed_renewals_count = 0
                        WHERE id = ?
                    ");
                    $stmtUserUpdate->execute([
                        $plan,
                        $expiresAt,
                        $last4,
                        $cardBrand,
                        $cardExp,
                        $cardHolder,
                        $todayDate,
                        $nextBillingDate,
                        $userId
                    ]);

                    // Connexion automatique de la session
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'member';
                    $_SESSION['subscription_plan'] = $plan;
                    $_SESSION['pending_order_number'] = $orderNumber;

                    $successUrl = 'checkout-success.php?order=' . urlencode($orderNumber) . '&mode=card';

                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success'      => true,
                            'status'       => 'paid',
                            'order_number' => $orderNumber,
                            'redirect_url' => $successUrl
                        ]);
                        exit;
                    }

                    header('Location: ' . $successUrl);
                    exit;
                }
        } catch (Exception $e) {
            $error = $e->getMessage();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        }
    }
}
}

$selectedPlan = ($_GET['plan'] ?? $_POST['subscription_plan'] ?? ($currentUser['subscription_plan'] ?? 'member')) === 'creator' ? 'creator' : 'member';
$pageTitle = ($selectedPlan === 'creator') 
    ? "Paiement Sécurisé — Formule Créateur Host (29€/mois) — One Vision Community"
    : "Paiement Sécurisé — Formule Membre (9€/mois) — One Vision Community";
$pageDescription = ($selectedPlan === 'creator')
    ? "Activez votre statut Créateur Host pour 29€ par mois. Animez et diffusez vos lives dans le calendrier officiel."
    : "Finalisez votre adhésion à One Vision Community pour 9€ par mois. Sans engagement, résiliable en 1 clic. Accès immédiat.";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%236366f1'/><circle cx='16' cy='16' r='9' stroke='white' stroke-width='2.5' fill='none'/><circle cx='16' cy='16' r='4' fill='white'/></svg>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./css/style.css?v=11">
</head>
<body class="checkout-body">

  <!-- EN-TÊTE MINIMALISTE SÉCURISÉ -->
  <header class="checkout-header">
    <div class="container checkout-header-inner">
      <a href="index.php" class="logo" aria-label="Retour à l'accueil One Vision Community">

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
      
      <!-- GRILLE CHECKOUT 2 COLONNES CÔTE À CÔTE -->
      <div id="checkoutFormView" class="checkout-grid">
        
        <!-- COLONNE GAUCHE : FORMULAIRE DE PAIEMENT -->
        <div class="checkout-form-column">
          <div class="checkout-card">
            
            <div class="checkout-steps-badge" id="checkoutStepsNav">
              <div class="checkout-step-pill active" id="stepPill1" style="cursor:pointer;" title="Cliquez pour revenir à vos identifiants">
                <span class="checkout-step-num" id="stepPillNum1">1</span>
                <span>1. Vos identifiants</span>
              </div>
              <svg class="checkout-step-divider" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
              <div class="checkout-step-pill" id="stepPill2" style="cursor:pointer;" title="Cliquez pour passer directement au paiement sécurisé">
                <span class="checkout-step-num" id="stepPillNum2">2</span>
                <span>2. Paiement sécurisé</span>
              </div>
            </div>

            <h1 class="checkout-title" id="checkoutMainTitle">Finaliser votre adhésion</h1>
            <p class="checkout-subtitle" id="checkoutMainSubtitle">Choisissez votre formule et activez votre accès instantané à la communauté.</p>

            <form id="checkoutPaymentForm" action="checkout.php" method="POST" novalidate>
              <?= csrf_field() ?>
              
              <!-- ÉTAPE 1 : IDENTIFIANTS DU COMPTE & CHOIX DE LA FORMULE -->
              <div id="checkoutStep1" class="checkout-step-pane">
                
                <!-- SÉLECTION DE LA FORMULE D'ADHÉSION -->
                <div class="form-section-title" style="margin-bottom:0.75rem;">
                  <span class="section-number">★</span>
                  <span>Choisissez votre formule</span>
                </div>

                <input type="hidden" name="subscription_plan" id="subscriptionPlanInput" value="<?= htmlspecialchars($selectedPlan) ?>">

                <div class="checkout-plan-selector-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem; margin-bottom:1.5rem;">
                  <!-- Plan Membre 9€ -->
                  <div class="plan-card-option <?= ($selectedPlan === 'creator') ? '' : 'selected' ?>" id="planOptionMember" data-plan="member" role="button" tabindex="0" style="border:2px solid <?= ($selectedPlan === 'creator') ? '#e2e8f0' : '#2563eb' ?>; background:<?= ($selectedPlan === 'creator') ? '#ffffff' : '#f0f7ff' ?>; border-radius:14px; padding:1rem; cursor:pointer; position:relative; transition:all 0.2s ease;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                      <div style="display:flex; align-items:center; gap:0.45rem;">
                        <input type="radio" name="planRadio" id="radioPlanMember" value="member" <?= ($selectedPlan === 'creator') ? '' : 'checked' ?> style="accent-color:#2563eb; width:17px; height:17px; cursor:pointer;">
                        <strong style="font-size:0.95rem; color:#0f172a;">Membre</strong>
                      </div>
                      <span style="font-size:0.72rem; background:#f1f5f9; color:#475569; padding:2px 7px; border-radius:6px; font-weight:700;">Participant</span>
                    </div>
                    <div style="margin-bottom:0.4rem;">
                      <span style="font-size:1.4rem; font-weight:900; color:#0f172a;">9 €</span>
                      <span style="font-size:0.78rem; color:#64748b;">/ mois</span>
                      <div style="font-size:0.75rem; color:#64748b; font-weight:600;">~5 900 FCFA / mois</div>
                    </div>
                    <p style="font-size:0.76rem; color:#64748b; line-height:1.4; margin:0;">
                      Participez à tous les Lives & Masterminds, salons 24/7 et fiches outils.
                    </p>
                  </div>

                  <!-- Plan Créateur 29€ -->
                  <div class="plan-card-option <?= ($selectedPlan === 'creator') ? 'selected' : '' ?>" id="planOptionCreator" data-plan="creator" role="button" tabindex="0" style="border:2px solid <?= ($selectedPlan === 'creator') ? '#e63946' : '#e2e8f0' ?>; background:<?= ($selectedPlan === 'creator') ? '#fff7ed' : '#ffffff' ?>; border-radius:14px; padding:1rem; cursor:pointer; position:relative; transition:all 0.2s ease;">
                    <div style="position:absolute; top:-9px; right:10px; background:linear-gradient(135deg, #e63946, #f97316); color:#fff; font-size:0.65rem; font-weight:800; padding:2px 7px; border-radius:10px; letter-spacing:0.04em;">
                      👑 PRO HOST
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                      <div style="display:flex; align-items:center; gap:0.45rem;">
                        <input type="radio" name="planRadio" id="radioPlanCreator" value="creator" <?= ($selectedPlan === 'creator') ? 'checked' : '' ?> style="accent-color:#e63946; width:17px; height:17px; cursor:pointer;">
                        <strong style="font-size:0.95rem; color:#0f172a;">Créateur Host</strong>
                      </div>
                    </div>
                    <div style="margin-bottom:0.4rem;">
                      <span style="font-size:1.4rem; font-weight:900; color:#e63946;">29 €</span>
                      <span style="font-size:0.78rem; color:#64748b;">/ mois</span>
                      <div style="font-size:0.75rem; color:#e63946; font-weight:700;">~19 000 FCFA / mois</div>
                    </div>
                    <p style="font-size:0.76rem; color:#334155; line-height:1.4; margin:0;">
                      <strong>Créez & animez vos propres Lives</strong> dans le calendrier officiel + Badge Vérifié.
                    </p>
                  </div>
                </div>

                <div class="form-section-title">
                  <span class="section-number">1</span>
                  <span>Vos identifiants de compte</span>
                </div>

                <div class="form-group">
                  <label for="checkoutName" class="form-label">Nom complet</label>
                  <input type="text" id="checkoutName" name="checkoutName" class="form-input" placeholder="ex. Alexandre Martin" value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>" required autocomplete="name">
                  <div class="field-error" id="nameError">Veuillez renseigner votre nom complet.</div>
                </div>

                <div class="form-group">
                  <label for="checkoutEmail" class="form-label">Adresse email professionnelle ou personnelle</label>
                  <input type="email" id="checkoutEmail" name="checkoutEmail" class="form-input" placeholder="ex. alexandre@monprojet.fr" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required autocomplete="email">
                  <div class="field-error" id="emailError">Veuillez renseigner une adresse email valide.</div>
                </div>

                <?php if (!$currentUser): ?>
                <div class="form-group">
                  <label for="checkoutPassword" class="form-label">Mot de passe de votre espace</label>
                  <input type="password" id="checkoutPassword" name="checkoutPassword" class="form-input" placeholder="Au moins 6 caractères" minlength="6" required autocomplete="new-password">
                  <div class="field-error" id="passwordError">Le mot de passe doit comporter au moins 6 caractères.</div>
                </div>
                <?php endif; ?>

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
                    <div style="width:32px; height:32px; border-radius:50%; background:#e0e7ff; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                    <div>
                      <strong id="step2SummaryName"><?= htmlspecialchars($currentUser['full_name'] ?? 'Membre') ?></strong>
                      <span id="step2SummaryEmail" style="display:block; font-size:0.78rem; color:#64748b;"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
                    </div>
                  </div>
                  <button type="button" id="backToStep1Btn" class="step2-edit-btn" style="display:inline-flex; align-items:center; gap:5px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg><span>Modifier</span></button>
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
                      <span>Visa, Mastercard, CB</span>
                    </div>
                    <div class="card-icons-row" id="cardBadgesRow">
                      <svg class="pay-logo pay-logo-cb" id="badgeCb" viewBox="0 0 38 24" width="38" height="24" fill="none" aria-label="Carte Bancaire CB"><rect width="38" height="24" rx="4" fill="#009975"/><path d="M19 0H34C36.2091 0 38 1.79086 38 4V20C38 22.2091 36.2091 24 34 24H19V0Z" fill="#0F4C81"/><text x="19" y="16.5" font-family="sans-serif" font-weight="900" font-size="12" fill="#ffffff" text-anchor="middle" letter-spacing="1">CB</text></svg>
                      <svg class="pay-logo pay-logo-visa" id="badgeVisa" viewBox="0 0 38 24" width="38" height="24" fill="none" aria-label="Visa"><rect width="38" height="24" rx="4" fill="#FFFFFF" stroke="#E2E8F0"/><path d="M15.2 16.8L17.3 7.2H19.7L17.6 16.8H15.2ZM24.4 7.4C23.9 7.2 23.1 7 22.1 7C19.6 7 17.8 8.3 17.8 10.2C17.8 11.6 19.1 12.4 20 12.9C20.9 13.4 21.3 13.7 21.3 14.1C21.3 14.8 20.5 15.1 19.7 15.1C18.8 15.1 18.2 14.9 17.4 14.6L17.1 14.4L16.8 16.3C17.4 16.6 18.4 16.8 19.5 16.8C22.1 16.8 23.9 15.5 23.9 13.5C23.9 12.4 23.2 11.5 21.7 10.8C20.8 10.3 20.2 10 20.2 9.5C20.2 9.1 20.7 8.6 21.7 8.6C22.6 8.6 23.2 8.8 23.7 9L23.9 9.1L24.4 7.4ZM30.8 7.2H28.8C28.2 7.2 27.7 7.4 27.5 8L23.7 16.8H26.3L26.8 15.3H30.1L30.4 16.8H32.7L30.8 7.2ZM27.5 13.4L28.9 9.6L29.7 13.4H27.5ZM12.6 7.2L10.2 13.7L9.9 12.3C9.4 10.8 7.9 9 6.2 8.1L8.5 16.8H11.2L15.1 7.2H12.6Z" fill="#1434CB"/><path d="M8.2 7.2H4.2L4.1 7.4C7.3 8.2 9.5 10.1 10.4 12.5L9.4 7.9C9.2 7.3 8.8 7.2 8.2 7.2Z" fill="#F7B600"/></svg>
                      <svg class="pay-logo pay-logo-mc" id="badgeMc" viewBox="0 0 38 24" width="38" height="24" fill="none" aria-label="Mastercard"><rect width="38" height="24" rx="4" fill="#0F172A"/><circle cx="14.5" cy="12" r="6.8" fill="#EB001B"/><circle cx="23.5" cy="12" r="6.8" fill="#F79E1B"/><path d="M19 7.48C20.7 8.7 21.8 10.22 21.8 12C21.8 13.78 20.7 15.3 19 16.52C17.3 15.3 16.2 13.78 16.2 12C16.2 10.22 17.3 8.7 19 7.48Z" fill="#FF5F00"/></svg>
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
                      <!-- Orange Money SVG Logo -->
                      <svg class="pay-logo pay-logo-orange" viewBox="0 0 54 28" width="48" height="25" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Orange Money">
                        <rect width="54" height="28" rx="4" fill="#FF7900"/>
                        <g transform="translate(3, 4.5)">
                          <path d="M6.8 2H2C0.9 2 0 2.9 0 4s0.9 2 2 2h2.5L0.5 10c-0.8 0.8-0.8 2.2 0 3s2.2 0.8 3 0l4-4V11.5c0 1.1 0.9 2 2 2s2-0.9 2-2V4c0-1.1-0.9-2-2-2h-2.7z" fill="#000000" transform="scale(0.82)"/>
                          <path d="M10 16h4.8c1.1 0 2-0.9 2-2s-0.9-2-2-2h-2.5l4-4c0.8-0.8 0.8-2.2 0-3s-2.2-0.8-3 0l-4 4V6.5c0-1.1-0.9-2-2-2s-2 0.9-2 2V14c0 1.1 0.9 2 2 2h2.7z" fill="#FFFFFF" transform="scale(0.82)"/>
                        </g>
                        <text x="35" y="13" font-family="system-ui, -apple-system, sans-serif" font-weight="900" font-size="8" fill="#FFFFFF" text-anchor="middle" letter-spacing="-0.3">orange</text>
                        <text x="35" y="21.5" font-family="system-ui, -apple-system, sans-serif" font-weight="800" font-size="6.5" fill="#000000" text-anchor="middle" letter-spacing="-0.2">money</text>
                      </svg>
                      <!-- MTN MoMo SVG Logo -->
                      <svg class="pay-logo pay-logo-mtn" viewBox="0 0 54 28" width="48" height="25" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="MTN MoMo">
                        <rect width="54" height="28" rx="4" fill="#FFCC00"/>
                        <ellipse cx="27" cy="14" rx="22" ry="11" fill="#002855"/>
                        <text x="27" y="18.5" font-family="system-ui, -apple-system, 'Arial Black', sans-serif" font-weight="900" font-size="12" fill="#FFCC00" text-anchor="middle" letter-spacing="0.5">MTN</text>
                      </svg>
                      <!-- Wave SVG Logo -->
                      <svg class="pay-logo pay-logo-wave" viewBox="0 0 54 28" width="48" height="25" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Wave">
                        <rect width="54" height="28" rx="4" fill="#1DC4FF"/>
                        <g transform="translate(3, 2.5)">
                          <ellipse cx="9" cy="11.5" rx="7" ry="8.5" fill="#0F172A"/>
                          <ellipse cx="9" cy="12.5" rx="4.8" ry="6.2" fill="#FFFFFF"/>
                          <circle cx="7.2" cy="8.2" r="1.1" fill="#FFFFFF"/>
                          <circle cx="7.2" cy="8.2" r="0.55" fill="#0F172A"/>
                          <circle cx="10.8" cy="8.2" r="1.1" fill="#FFFFFF"/>
                          <circle cx="10.8" cy="8.2" r="0.55" fill="#0F172A"/>
                          <polygon points="8,9.8 10,9.8 9,11.6" fill="#FF9900"/>
                          <ellipse cx="6.5" cy="19.2" rx="1.8" ry="0.8" fill="#FF9900"/>
                          <ellipse cx="11.5" cy="19.2" rx="1.8" ry="0.8" fill="#FF9900"/>
                        </g>
                        <text x="35.5" y="18" font-family="system-ui, -apple-system, sans-serif" font-weight="900" font-size="11" fill="#FFFFFF" text-anchor="middle" letter-spacing="-0.3">wave</text>
                      </svg>
                      <!-- Moov Money SVG Logo -->
                      <svg class="pay-logo pay-logo-moov" viewBox="0 0 54 28" width="48" height="25" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Moov Money">
                        <rect width="54" height="28" rx="4" fill="#005BAA"/>
                        <circle cx="9.5" cy="14" r="5" fill="#8DC63F"/>
                        <path d="M9.5 9C12.2 9 14.5 11.2 14.5 14C14.5 16.8 12.2 19 9.5 19C10.8 17.6 11.7 15.8 11.7 14C11.7 12.2 10.8 10.4 9.5 9Z" fill="#FFFFFF"/>
                        <text x="33.5" y="13.5" font-family="system-ui, -apple-system, 'Arial Black', sans-serif" font-weight="900" font-size="8" fill="#FFFFFF" text-anchor="middle" letter-spacing="0.2">MOOV</text>
                        <text x="33.5" y="21" font-family="system-ui, -apple-system, sans-serif" font-weight="800" font-size="5.5" fill="#8DC63F" text-anchor="middle" letter-spacing="0.6">MONEY</text>
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- BLOC 1 : FORMULAIRE CARTE BANCAIRE -->
                <div class="card-details-box" id="cardDetailsBox">
                  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.15rem; padding-bottom:0.75rem; border-bottom:1px solid #e2e8f0;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                      <strong style="color:#0f172a; font-size:0.95rem;">Paiement Sécurisé par Carte Bancaire</strong>
                    </div>
                    <span style="font-size:0.75rem; background:#eff6ff; color:#2563eb; padding:3px 8px; border-radius:6px; font-weight:700;">3D-Secure 2.0</span>
                  </div>

                  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:0.9rem 1rem; margin-bottom:1rem; font-size:0.86rem; color:#475569; display:flex; align-items:flex-start; gap:0.6rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <div>
                      Règlement sécurisé par <strong>Visa, Mastercard ou CB</strong>. Vos informations bancaires sont chiffrées selon les normes de sécurité PCI-DSS et authentifiées via <strong>3D-Secure</strong> auprès de votre banque.
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="cardNumber" class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                      <span>Numéro de carte bancaire</span>
                      <span id="detectedBrandLabel" style="font-size:0.78rem; font-weight:800; color:#2563eb; transition:all 0.25s ease;"></span>
                    </label>
                    <div class="input-icon-wrapper">
                      <input type="text" id="cardNumber" name="cardNumber" class="form-input" placeholder="4532 •••• •••• 4242" maxlength="19" inputmode="numeric" autocomplete="cc-number">
                      <div id="cardDetectedBadge" class="input-icon-right" style="display:flex; align-items:center; justify-content:center; pointer-events:none; transition:all 0.25s ease;">
                        <svg id="defaultCardSvg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2">
                          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                          <line x1="1" y1="10" x2="23" y2="10"></line>
                        </svg>
                        <div id="activeBrandSvg" style="display:none;"></div>
                      </div>
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
                        <span class="cvc-tooltip-icon" title="3 chiffres au dos de votre carte" style="display:inline-flex; align-items:center; justify-content:center;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></span>
                      </div>
                      <div class="field-error" id="cvcError">3 ou 4 chiffres requis.</div>
                    </div>
                  </div>

                  <div class="form-group" style="margin-bottom:0;">
                    <label for="cardHolder" class="form-label">Nom du titulaire de la carte</label>
                    <input type="text" id="cardHolder" name="cardHolder" class="form-input" placeholder="ex. Alexandre Martin" value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>">
                    <div class="field-error" id="cardHolderError">Nom du titulaire requis.</div>
                  </div>
                </div>

                <!-- BLOC 2 : MOYEN DE PAIEMENT MOBILE MONEY -->
                <div class="momo-details-box" id="mobileMoneyDetailsBox" style="display:none; background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.35rem; margin-bottom:1.25rem;">
                  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.15rem; padding-bottom:0.75rem; border-bottom:1px solid #e2e8f0;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                      <strong style="color:#0f172a; font-size:0.95rem;">Paiement Mobile Money Sécurisé</strong>
                    </div>
                    <span id="momoPriceBadge" style="font-size:0.78rem; background:#eff6ff; color:#1d4ed8; padding:3px 10px; border-radius:8px; font-weight:800;">5 900 FCFA / mois</span>
                  </div>

                  <!-- Guide Mobile Money -->
                  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.15rem; font-size:0.88rem; color:#334155; line-height:1.55;">
                    💡 <strong>Pour un paiement réussi :</strong> Règlement de <strong>5 900 FCFA</strong> par <strong>Wave, Orange Money, MTN MoMo ou Moov Money</strong>. Dès votre clic, votre adhésion est immédiatement activée en toute sécurité.
                  </div>
                </div>

                <!-- Message d'erreur global -->
                <div id="checkoutGlobalError" class="checkout-alert-error" style="display:none; background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:0.85rem 1rem; margin-bottom:1.15rem; color:#991b1b; font-size:0.88rem; align-items:flex-start; gap:0.6rem;">
                  <svg style="flex-shrink:0; margin-top:2px;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                  <div id="checkoutGlobalErrorText" style="line-height:1.45;">Une erreur est survenue lors de la validation.</div>
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
                    <span>Accès instantané à l'Espace Membre dès validation</span>
                  </div>
                </div>

              </div> <!-- Fin #checkoutStep2 -->
            </form>

          </div> <!-- Fin .checkout-card -->
        </div> <!-- Fin .checkout-form-column -->

        <!-- COLONNE DROITE : RÉCAPITULATIF DE COMMANDE -->
        <div class="checkout-summary-column">
          <?php require __DIR__ . '/includes/checkout-summary-carousel.php'; ?>
        </div>

      </div>
    </div>
  </main>

  <!-- MODAL DE VÉRIFICATION BANCAIRE 3D-SECURE (CARTE BANCAIRE) -->
  <div id="threeDSecureModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(15,23,42,0.72); backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:1.25rem;">
    <div style="background:#ffffff; border-radius:22px; max-width:440px; width:100%; padding:2.25rem 2rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); text-align:center; border:1px solid #e2e8f0;">
      <div style="display:flex; justify-content:center; align-items:center; gap:0.6rem; margin-bottom:1.25rem;">
        <span id="threeDSBadgeCb" style="font-weight:800; font-size:0.8rem; color:#0f172a; background:#f1f5f9; padding:4px 8px; border-radius:6px; transition:all 0.3s ease;">CB</span>
        <span id="threeDSBadgeVisa" style="font-weight:900; font-size:0.8rem; color:#1434cb; background:#eff6ff; padding:4px 8px; border-radius:6px; transition:all 0.3s ease;">VISA</span>
        <span id="threeDSBadgeMc" style="font-weight:800; font-size:0.8rem; color:#ea580c; background:#fff7ed; padding:4px 8px; border-radius:6px; transition:all 0.3s ease;">MASTERCARD</span>
        <span style="font-size:0.75rem; background:#ecfdf5; color:#059669; font-weight:700; padding:4px 8px; border-radius:6px;">3D-SECURE 2.0</span>
      </div>

      <div id="threeDSIconPending" style="width:72px; height:72px; margin:0 auto 1.25rem; background:#eff6ff; border:3px solid #bfdbfe; border-radius:50%; display:flex; align-items:center; justify-content:center;">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation:spinner-spin 1s linear infinite;">
          <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/>
        </svg>
      </div>

      <div id="threeDSIconSuccess" style="display:none; width:72px; height:72px; margin:0 auto 1.25rem; background:#dcfce7; border:3px solid #86efac; border-radius:50%; align-items:center; justify-content:center;">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>

      <h3 id="threeDSTitle" style="font-size:1.25rem; font-weight:800; color:#0f172a; margin-bottom:0.5rem;">Authentification 3D-Secure</h3>
      <p id="threeDSDesc" style="font-size:0.88rem; color:#64748b; line-height:1.5; margin-bottom:1.35rem;">
        Communication sécurisée avec votre banque émettrice pour valider votre souscription de <strong>9,00 €</strong>...
      </p>

      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:0.9rem; font-size:0.82rem; color:#475569; margin-bottom:1.35rem; text-align:left;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem;">
          <span>Titulaire :</span>
          <strong id="threeDSHolder" style="color:#0f172a;">-</strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem;">
          <span>Numéro de carte :</span>
          <span id="threeDSCardMasked" style="font-family:monospace; font-weight:700; color:#0f172a;">•••• •••• •••• 4242</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span>Montant débité :</span>
          <strong style="color:#16a34a; font-size:0.95rem;">9,00 €</strong>
        </div>
      </div>

      <div style="width:100%; height:4px; background:#e2e8f0; border-radius:2px; overflow:hidden;">
        <div id="threeDSProgressFill" style="width:25%; height:100%; background:#2563eb; transition:width 0.4s ease;"></div>
      </div>
    </div>
  </div>

  <style>
    @keyframes spinner-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes momo-pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.35); opacity: 0.6; } }
    .live-pulse-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #10b981;
      box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
      animation: momo-pulse 2s infinite;
    }
    .scan-bracket {
      position: absolute;
      width: 22px;
      height: 22px;
      border: 3px solid #0284c7;
      pointer-events: none;
      transition: border-color 0.3s ease;
      z-index: 2;
    }
    .sb-tl { top: 10px; left: 10px; border-right: none; border-bottom: none; border-top-left-radius: 6px; }
    .sb-tr { top: 10px; right: 10px; border-left: none; border-bottom: none; border-top-right-radius: 6px; }
    .sb-bl { bottom: 10px; left: 10px; border-right: none; border-top: none; border-bottom-left-radius: 6px; }
    .sb-br { bottom: 10px; right: 10px; border-left: none; border-top: none; border-bottom-right-radius: 6px; }
    .momo-op-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    }
  </style>

  <script src="./js/main.js?v=22"></script>
</body>
</html>
