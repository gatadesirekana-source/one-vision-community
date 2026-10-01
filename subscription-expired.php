<?php
/**
 * ONE VISION COMMUNITY — PAGE DE RENOUVELLEMENT D'ABONNEMENT EXPIRÉ
 * Affichée automatiquement lorsque la période de souscription d'un membre est épuisée.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/subscriptions.php';

require_auth('login.php');

$db = get_db();
$currentUser = current_user();

if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Vérifier si l'utilisateur est déjà actif (auquel cas rediriger vers dashboard)
$sub = check_user_subscription((int)$currentUser['id']);
if ($sub['is_active']) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Traitement POST : Renouvellement manuel ou instantané
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = "Session expirée. Veuillez actualiser la page et réessayer.";
    } else {
        $action = $_POST['action'];

        // OPTION 1 : Renouvellement instantané avec la carte enregistrée
        if ($action === 'renew_saved_card') {
            if (empty($currentUser['card_last4'])) {
                $error = "Aucune carte bancaire enregistrée. Veuillez renseigner une carte ci-dessous.";
            } else {
                $result = process_recurring_charge($currentUser);
                if ($result['success']) {
                    set_flash('success', "Votre adhésion a été renouvelée avec succès ! Votre accès est à nouveau 100% actif.");
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = "Le prélèvement sur votre carte a échoué (" . htmlspecialchars($result['error']) . "). Veuillez utiliser une nouvelle carte ci-dessous.";
                }
            }
        }

        // OPTION 2 : Renouvellement avec nouvelle carte bancaire
        if ($action === 'renew_new_card') {
            $cleanNumber = preg_replace('/\D/', '', $_POST['cardNumber'] ?? '');
            $cardExp = trim($_POST['cardExp'] ?? '');
            $cardCvc = trim($_POST['cardCvc'] ?? '');
            $cardHolder = trim($_POST['cardHolder'] ?? '') ?: $currentUser['full_name'];

            // Validation de la carte
            if (strlen($cleanNumber) < 13 || strlen($cleanNumber) > 19) {
                $error = "Numéro de carte bancaire incomplet (16 chiffres attendus).";
            } else {
                // Algorithme de Luhn
                $sum = 0;
                $shouldDouble = false;
                for ($i = strlen($cleanNumber) - 1; $i >= 0; $i--) {
                    $digit = (int)$cleanNumber[$i];
                    if ($shouldDouble) {
                        $digit *= 2;
                        if ($digit > 9) $digit -= 9;
                    }
                    $sum += $digit;
                    $shouldDouble = !$shouldDouble;
                }

                if ($sum % 10 !== 0) {
                    $error = "Numéro de carte bancaire invalide : échec du contrôle de sécurité bancaire (Luhn).";
                } elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $cardExp, $mExp)) {
                    $error = "Date d'expiration invalide (format attendu : MM/AA).";
                } else {
                    $expMonth = (int)$mExp[1];
                    $expYear = 2000 + (int)$mExp[2];
                    $curYear = (int)date('Y');
                    $curMonth = (int)date('n');

                    if ($expYear < $curYear || ($expYear === $curYear && $expMonth < $curMonth)) {
                        $error = "Cette carte bancaire est expirée.";
                    } elseif (strlen(preg_replace('/\D/', '', $cardCvc)) < 3) {
                        $error = "Cryptogramme CVC incomplet (3 chiffres requis).";
                    } else {
                        // Détection de la marque
                        $brand = 'Carte Bancaire';
                        if (preg_match('/^4/', $cleanNumber)) $brand = 'Visa';
                        elseif (preg_match('/^(5[1-5]|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)/', $cleanNumber)) $brand = 'Mastercard';
                        elseif (preg_match('/^3[47]/', $cleanNumber)) $brand = 'American Express';

                        // Sauvegarde de la nouvelle carte sur le compte
                        $last4 = substr($cleanNumber, -4);
                        $stmtUpdate = $db->prepare("
                            UPDATE users 
                            SET card_last4 = ?, 
                                card_brand = ?, 
                                card_exp = ?, 
                                card_holder = ?,
                                auto_renew = 1 
                            WHERE id = ?
                        ");
                        $stmtUpdate->execute([$last4, $brand, $cardExp, $cardHolder, $currentUser['id']]);

                        // Recharger l'utilisateur
                        $stmtFresh = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmtFresh->execute([$currentUser['id']]);
                        $freshUser = $stmtFresh->fetch();

                        // Effectuer le prélèvement immédiat
                        $result = process_recurring_charge($freshUser);
                        if ($result['success']) {
                            set_flash('success', "Paiement validé avec succès ! Votre accès membre est réactivé.");
                            header('Location: dashboard.php');
                            exit;
                        } else {
                            $error = "Erreur lors du règlement : " . $result['error'];
                        }
                    }
                }
            }
        }
    }
}

$pageTitle = "Renouvellement d'Adhésion — One Vision Community";
$pageDescription = "Votre abonnement mensuel est arrivé à échéance. Renouvelez votre adhésion en 1 clic pour continuer à profiter de l'Académie.";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section auth-section" style="min-height: calc(100vh - 220px); display:flex; align-items:center; justify-content:center; padding: 3rem 1rem;">
  <div class="auth-card" style="width:100%; max-width:620px; background:var(--color-bg-card, #ffffff); border:1px solid var(--color-border, #e2e8f0); border-radius:24px; padding:2.5rem 2.25rem; box-shadow:0 20px 45px rgba(0,0,0,0.08); text-align:center;">

    <!-- Icône Échéance -->
    <div style="width:84px; height:84px; margin:0 auto 1.5rem; background:#fef2f2; border:3px solid #fecaca; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(239,68,68,0.15);">
      <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
      </svg>
    </div>

    <!-- Badge Statut -->
    <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); padding:0.4rem 1rem; border-radius:30px; font-size:0.82rem; font-weight:700; color:#dc2626; margin-bottom:1rem;">
      <span>🔒 Accès Suspendu • Période de 30 Jours Écoulée</span>
    </div>

    <h1 style="font-size:1.85rem; font-weight:800; color:var(--color-text-main, #0f172a); margin-bottom:0.6rem; letter-spacing:-0.02em;">
      Votre abonnement est arrivé à échéance
    </h1>

    <p style="font-size:0.98rem; color:var(--color-text-muted, #64748b); max-width:500px; margin:0 auto 1.75rem; line-height:1.6;">
      Bonjour <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>. Votre période de souscription mensuelle a pris fin. Renouvelez votre adhésion de <strong>9,00 €</strong> pour réactiver instantanément vos accès aux Masterminds, Lives hebdomadaires et salons d'entraide.
    </p>

    <?php if (!empty($error)): ?>
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:0.9rem 1.15rem; border-radius:12px; margin-bottom:1.5rem; font-size:0.88rem; text-align:left; display:flex; align-items:center; gap:0.6rem;">
        <span style="font-size:1.2rem;">⚠️</span>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <!-- OPTION 1 : RENOUVELLEMENT EN 1 CLIC SI CARTE ENREGISTRÉE -->
    <?php if (!empty($currentUser['card_last4'])): ?>
      <div style="background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:18px; padding:1.5rem; margin-bottom:2rem; text-align:left;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
          <div style="display:flex; align-items:center; gap:0.75rem;">
            <div style="width:42px; height:28px; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:0.75rem; color:#1434cb;">
              <?= htmlspecialchars($currentUser['card_brand'] ?: 'VISA') ?>
            </div>
            <div>
              <div style="font-weight:700; color:#0f172a; font-size:0.92rem;">
                Carte <?= htmlspecialchars($currentUser['card_brand'] ?: 'Bancaire') ?> •••• <?= htmlspecialchars($currentUser['card_last4']) ?>
              </div>
              <div style="font-size:0.78rem; color:#64748b;">
                Exp. <?= htmlspecialchars($currentUser['card_exp'] ?: 'Valide') ?> • Prélèvement sécurisé
              </div>
            </div>
          </div>
          <span style="font-weight:800; font-size:1.15rem; color:#16a34a;">9,00 €</span>
        </div>

        <form method="POST" action="subscription-expired.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="renew_saved_card">
          <button type="submit" class="btn btn-primary btn-block" style="padding:0.95rem 1.25rem; font-size:1rem; border-radius:12px; display:flex; align-items:center; justify-content:center; gap:0.5rem; width:100%; cursor:pointer;">
            <span>⚡ Renouveler immédiatement pour 9,00 €</span>
          </button>
        </form>
      </div>

      <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
        <div style="flex:1; height:1px; background:#e2e8f0;"></div>
        <span style="font-size:0.78rem; color:#94a3b8; font-weight:600; text-transform:uppercase;">Ou utiliser un autre moyen de paiement</span>
        <div style="flex:1; height:1px; background:#e2e8f0;"></div>
      </div>
    <?php endif; ?>

    <!-- OPTION 2 : SAISIR UNE NOUVELLE CARTE BANCAIRE -->
    <div style="text-align:left; background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.5rem; margin-bottom:1.5rem;">
      <h3 style="font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between;">
        <span>Payer par nouvelle Carte Bancaire</span>
        <span style="font-size:0.8rem; color:#16a34a; font-weight:700;">9,00 € / mois</span>
      </h3>

      <form method="POST" action="subscription-expired.php" id="renewCardForm" style="display:flex; flex-direction:column; gap:1rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="renew_new_card">

        <div>
          <label class="form-label" style="font-size:0.82rem; font-weight:600; color:#334155; margin-bottom:0.35rem; display:block;">Nom du titulaire</label>
          <input type="text" name="cardHolder" class="form-input" style="width:100%; padding:0.75rem 1rem; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem;" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
        </div>

        <div>
          <label class="form-label" style="font-size:0.82rem; font-weight:600; color:#334155; margin-bottom:0.35rem; display:block;">Numéro de carte bancaire</label>
          <input type="text" name="cardNumber" id="renewCardNumber" class="form-input" placeholder="4242 •••• •••• ••••" style="width:100%; padding:0.75rem 1rem; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem; font-family:monospace;" required>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div>
            <label class="form-label" style="font-size:0.82rem; font-weight:600; color:#334155; margin-bottom:0.35rem; display:block;">Expiration (MM/AA)</label>
            <input type="text" name="cardExp" id="renewCardExp" class="form-input" placeholder="12/28" maxlength="5" style="width:100%; padding:0.75rem 1rem; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem; text-align:center;" required>
          </div>
          <div>
            <label class="form-label" style="font-size:0.82rem; font-weight:600; color:#334155; margin-bottom:0.35rem; display:block;">Cryptogramme CVC</label>
            <input type="text" name="cardCvc" id="renewCardCvc" class="form-input" placeholder="123" maxlength="4" style="width:100%; padding:0.75rem 1rem; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem; text-align:center;" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="padding:0.95rem 1.25rem; font-size:1rem; border-radius:12px; margin-top:0.5rem; display:flex; align-items:center; justify-content:center; gap:0.5rem; cursor:pointer;">
          <span>🔒 Valider le règlement de 9,00 € et réactiver</span>
        </button>
      </form>
    </div>

    <!-- OPTION 3 : LIEN VERS CHECKOUT POUR MOBILE MONEY -->
    <div style="background:#f1f5f9; border-radius:14px; padding:1rem; font-size:0.85rem; color:#475569; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; text-align:left;">
      <div>
        <strong>Vous souhaitez payer par Mobile Money ?</strong>
        <div style="color:#64748b; font-size:0.78rem;">Wave, Orange Money, MTN, Moov (5 900 FCFA)</div>
      </div>
      <a href="checkout.php?method=mobile_money" class="btn btn-secondary btn-sm" style="text-decoration:none; padding:0.5rem 0.9rem; border-radius:8px; font-size:0.8rem;">
        Payer par Mobile Money →
      </a>
    </div>

    <div style="margin-top:1.75rem; font-size:0.82rem; color:#94a3b8;">
      Besoin d'aide ? <a href="support.php" style="color:var(--color-primary, #2563eb); text-decoration:none;">Contacter le support</a> • <a href="logout.php" style="color:#64748b; text-decoration:none;">Se déconnecter</a>
    </div>

  </div>
</main>

<script>
  // Formatage automatique des champs de carte sur la page de renouvellement
  const numInp = document.getElementById('renewCardNumber');
  const expInp = document.getElementById('renewCardExp');
  const cvcInp = document.getElementById('renewCardCvc');

  if (numInp) {
    numInp.addEventListener('input', (e) => {
      let raw = e.target.value.replace(/\D/g, '').substring(0, 19);
      let parts = raw.match(/.{1,4}/g);
      e.target.value = parts ? parts.join(' ') : '';
    });
  }

  if (expInp) {
    expInp.addEventListener('input', (e) => {
      let raw = e.target.value.replace(/\D/g, '').substring(0, 4);
      if (raw.length >= 3) {
        e.target.value = raw.substring(0, 2) + '/' + raw.substring(2);
      } else {
        e.target.value = raw;
      }
    });
  }

  if (cvcInp) {
    cvcInp.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
    });
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
