<?php
/**
 * ONE VISION COMMUNITY — RETOUR DE PAIEMENT SASPAY (RETURN_URL)
 * 
 * Traite le retour du client après la validation de son paiement sur la passerelle SasPay.
 * Interroge en temps réel l'API SasPay pour vérifier si le paiement a été bel et bien reçu.
 * Affiche clairement :
 * - "Paiement Confirmé !" avec le bouton "Continuer vers le dashboard" si réussi.
 * - "Paiement échoué" avec le bouton "Recommencer le paiement" si non reçu ou refusé.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/payment_service.php';
require_once __DIR__ . '/includes/saspay.php';

$reference = trim($_GET['ref'] ?? $_GET['reference'] ?? $_GET['session_id'] ?? $_GET['id'] ?? $_GET['checkout_session_id'] ?? '');

if (empty($reference) && !empty($_SESSION['last_payment_ref'])) {
    $reference = $_SESSION['last_payment_ref'];
}

$db = get_db();
$payment = null;

if (!empty($reference)) {
    $stmt = $db->prepare("
        SELECT p.*, u.email, u.full_name 
        FROM payments p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.reference_externe = ? OR p.session_id = ?
        ORDER BY p.id DESC LIMIT 1
    ");
    $stmt->execute([$reference, $reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fallback : si l'utilisateur est connecté et qu'on n'a pas trouvé par référence, chercher son dernier paiement
if (!$payment && is_logged_in()) {
    $stmt = $db->prepare("
        SELECT p.*, u.email, u.full_name 
        FROM payments p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = ? 
        ORDER BY p.id DESC LIMIT 1
    ");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($payment) {
        $reference = $payment['reference_externe'] ?: $payment['session_id'];
    }
}

$planCodeTarget = strtolower(trim($_GET['plan'] ?? ($_SESSION['last_payment_plan'] ?? 'membre')));
$periodTarget = strtolower(trim($_GET['periodicite'] ?? ($_SESSION['last_payment_period'] ?? 'mensuel')));
$retryUrl = 'choisir-abonnement.php?plan=' . urlencode($planCodeTarget) . '&periodicite=' . urlencode($periodTarget);

$status = 'failed';
$message = "Nous n'avons pas reçu le paiement. Le règlement n'a pas pu être validé par SasPay.";
$planNom = ($planCodeTarget === 'animateur') ? "Formule Animateur & Expert" : "Formule Membre Standard";
$redirectUrl = 'dashboard.php';
$redirectLabel = "votre tableau de bord";
$btnLabel = "Continuer vers le dashboard →";
$isAnimateur = ($planCodeTarget === 'animateur');

if (!$payment && empty($reference)) {
    $status = 'failed';
    $message = "Aucun paiement en cours n'a été détecté. Si vous avez été débité, veuillez contacter le support avec vos informations.";
} else {
    // Si l'utilisateur n'est pas connecté dans cette session, mais qu'il vient de payer, on connecte la session
    if (!is_logged_in() && !empty($payment['user_id'])) {
        $stmtU = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtU->execute([(int)$payment['user_id']]);
        $u = $stmtU->fetch(PDO::FETCH_ASSOC);
        if ($u && ($u['statut'] ?? 'actif') !== 'suspendu') {
            $_SESSION['user_id'] = (int)$u['id'];
            $_SESSION['user_email'] = $u['email'];
            $_SESSION['user_role'] = $u['role'] ?? 'membre';
        }
    }

    // Détection d'un échec explicite passé dans l'URL (ex: webhook ou redirection d'annulation)
    if (isset($_GET['status']) && in_array(strtolower($_GET['status']), ['failed', 'cancelled', 'cancel', 'echoue', 'annule'], true)) {
        $status = 'failed';
        $message = "La transaction a été annulée ou rejetée par l'opérateur. Aucun montant n'a été prélevé sur votre compte.";
        if ($payment) {
            $db->prepare("UPDATE payments SET statut = 'echoue' WHERE id = ?")->execute([(int)$payment['id']]);
        }
    } else {
        // Confirmation et vérification du paiement auprès de SasPay via le service isolé
        $refToConfirm = $reference ?: ($payment['reference_externe'] ?? '');
        $confirmation = PaymentService::confirmerPaiement($refToConfirm);

        if ($confirmation['success']) {
            $status = 'success';
            $planNom = $confirmation['plan_nom'] ?? $planNom;
            $periodLabel = ($confirmation['periodicite'] ?? $periodTarget) === 'annuel' ? 'annuel' : 'mensuel';
            set_flash('success', "Félicitations ! Votre règlement a été bel et bien reçu et validé par SasPay. Votre abonnement {$planNom} ({$periodLabel}) est actif.");

            $planCodeFinal = strtolower(trim($_GET['plan'] ?? ($confirmation['plan_code'] ?? $planCodeTarget)));
            $isAnimateur = ($planCodeFinal === 'animateur') || (($confirmation['new_role'] ?? '') === 'animateur');
            $redirectUrl = $isAnimateur ? 'espace-animateur.php' : 'dashboard.php';
            $redirectLabel = $isAnimateur ? "votre Espace Animateur" : "votre tableau de bord";
            $btnLabel = $isAnimateur ? "Continuer vers l'Espace Animateur →" : "Continuer vers le dashboard →";
        } elseif (!empty($confirmation['failed'])) {
            $status = 'failed';
            $message = $confirmation['error'] ?? "Nous n'avons pas reçu le paiement. Le règlement a échoué auprès de SasPay.";
        } else {
            // En cas de statut encore PENDING alors que l'utilisateur est revenu sur le site
            // Signifie que l'utilisateur a quitté la page de paiement sans valider
            $status = 'failed';
            $message = "Nous n'avons pas reçu la validation de votre paiement. Si vous avez annulé ou si la transaction n'a pas abouti, vous pouvez recommencer en toute sécurité.";
        }
    }
}

$pageTitle = ($status === 'success') ? "Paiement Confirmé — One Vision Community" : "Paiement Échoué — One Vision Community";
$hideHeaderNav = true;
require_once __DIR__ . '/includes/header.php';
?>

<main class="section" style="min-height: calc(100vh - 200px); display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; background: #f8fafc;">
  <div class="container" style="max-width: 580px; margin: 0 auto; text-align: center;">

    <div style="background: #ffffff; border-radius: 24px; padding: 3rem 2rem; box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

      <?php if ($status === 'success'): ?>
        <!-- ========================================== -->
        <!-- CAS 1 : PAIEMENT RÉUSSI & REÇU PAR SASPAY -->
        <!-- ========================================== -->
        <div style="width: 80px; height: 80px; border-radius: 50%; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem auto; box-shadow: 0 0 0 10px #f0fdf4;">
          ✓
        </div>
        <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0;">
          Paiement Confirmé !
        </h1>
        <p style="font-size: 1.05rem; color: #15803d; font-weight: 600; margin: 0 0 1rem 0;">
          ✓ Votre règlement a été bel et bien reçu et validé par SasPay.
        </p>
        <p style="font-size: 0.98rem; color: #475569; line-height: 1.6; margin-bottom: 2rem;">
          Bienvenue dans l'aventure <strong>One Vision Community</strong> ! Votre formule <strong><?= htmlspecialchars($planNom) ?></strong> est désormais active.
        </p>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem; text-align: left; margin-bottom: 2rem; font-size: 0.88rem;">
          <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
            <span style="color:#64748b;">Formule activée :</span>
            <strong style="color:#0f172a;"><?= htmlspecialchars($planNom) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
            <span style="color:#64748b;">Statut transaction :</span>
            <strong style="color:#16a34a;">● Validée par SasPay</strong>
          </div>
          <div style="display:flex; justify-content:space-between;">
            <span style="color:#64748b;">Référence :</span>
            <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-size:0.78rem; color:#0f172a;"><?= htmlspecialchars($reference ?: 'PAY-CONFIRMED') ?></code>
          </div>
        </div>

        <p style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 1.5rem;">
          Redirection automatique vers <?= htmlspecialchars($redirectLabel) ?> dans 3 secondes...
        </p>
        <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn btn-primary" style="padding: 0.95rem 2.2rem; font-weight: 800; font-size: 1rem; border-radius: 12px; display: inline-flex; align-items:center; gap:0.5rem; text-decoration:none; <?= $isAnimateur ? 'background: linear-gradient(135deg, #f97316, #ea580c);' : '' ?>">
          <span><?= htmlspecialchars($btnLabel) ?></span>
        </a>
        <script>
          setTimeout(function() {
            window.location.href = "<?= $redirectUrl ?>";
          }, 3000);
        </script>

      <?php else: ?>
        <!-- ========================================== -->
        <!-- CAS 2 : PAIEMENT ÉCHOUÉ / NON REÇU PAR SASPAY -->
        <!-- ========================================== -->
        <div style="width: 80px; height: 80px; border-radius: 50%; background: #fef2f2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem auto; box-shadow: 0 0 0 10px #fff1f2;">
          ✕
        </div>
        <h1 style="font-size: 1.85rem; font-weight: 800; color: #991b1b; margin: 0 0 0.65rem 0;">
          Paiement échoué
        </h1>
        <p style="font-size: 1.05rem; color: #b91c1c; font-weight: 600; margin: 0 0 1rem 0;">
          Nous n'avons pas reçu le paiement.
        </p>
        <p style="font-size: 0.98rem; color: #475569; line-height: 1.6; margin-bottom: 1.75rem;">
          <?= htmlspecialchars($message) ?>
          <br>
          <span style="font-size: 0.88rem; color: #64748b;">
            Aucun montant n'a été prélevé sur votre compte. Vous pouvez relancer la transaction dès maintenant.
          </span>
        </p>

        <div style="background: #fff7ed; border: 1px solid #ffedd5; border-radius: 14px; padding: 1.15rem; text-align: left; margin-bottom: 2rem; font-size: 0.88rem;">
          <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem;">
            <span style="color:#9a3412;">Formule sélectionnée :</span>
            <strong style="color:#0f172a;"><?= htmlspecialchars($planNom) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between;">
            <span style="color:#9a3412;">Agrégateur SasPay :</span>
            <strong style="color:#dc2626;">Règlement non finalisé</strong>
          </div>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
          <a href="<?= htmlspecialchars($retryUrl) ?>" class="btn btn-primary" style="padding: 0.95rem 2rem; font-weight: 800; font-size: 1rem; border-radius: 12px; display: inline-flex; align-items:center; gap:0.5rem; text-decoration:none; background: #0f172a;">
            <span>🔄 Recommencer le paiement</span>
          </a>
          <a href="support.php" class="btn btn-secondary" style="padding: 0.95rem 1.75rem; font-weight: 700; border-radius: 12px; text-decoration:none;">
            Besoin d'aide ?
          </a>
        </div>
      <?php endif; ?>

    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
