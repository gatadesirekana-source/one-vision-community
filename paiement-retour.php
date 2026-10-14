<?php
/**
 * ONE VISION COMMUNITY — RETOUR DE PAIEMENT SASPAY (RETURN_URL)
 * 
 * Traite le retour du client après la validation de son paiement sur la passerelle SasPay.
 * Vérifie l'état réel de la transaction de façon sécurisée côté backend et met à jour l'abonnement.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/payment_service.php';
require_once __DIR__ . '/includes/saspay.php';

$reference = trim($_GET['ref'] ?? '');
$db = get_db();

$payment = null;
if (!empty($reference)) {
    $stmt = $db->prepare("SELECT p.*, u.email, u.prenom, u.nom FROM payments p JOIN users u ON p.user_id = u.id WHERE p.reference_externe = ? OR p.session_id = ?");
    $stmt->execute([$reference, $reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}

$status = 'pending';
$message = "Vérification de votre règlement en cours...";
$planNom = "Abonnement One Vision";

if (!$payment) {
    $status = 'error';
    $message = "Référence de transaction introuvable. Si votre compte a été débité, veuillez contacter le support.";
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

    // Confirmation du paiement via le service
    $confirmation = PaymentService::confirmerPaiement($reference);

    if ($confirmation['success']) {
        $status = 'success';
        $planNom = $confirmation['plan_nom'] ?? 'One Vision';
        $periodLabel = ($confirmation['periodicite'] ?? 'mensuel') === 'annuel' ? 'annuel' : 'mensuel';
        set_flash('success', "Félicitations ! Votre règlement a été confirmé. Votre abonnement {$planNom} ({$periodLabel}) est désormais actif.");

        // Redirection automatique vers le dashboard après 2 secondes
        $redirectUrl = ($confirmation['new_role'] ?? '') === 'animateur' ? 'espace-animateur.php' : 'dashboard.php';
        header("Refresh: 2; url={$redirectUrl}");
    } else {
        if (!empty($confirmation['pending'])) {
            $status = 'pending';
            $message = "Votre paiement a été pris en compte par votre opérateur et est en cours de validation finale. Votre compte sera activé automatiquement sous quelques instants.";
        } else {
            $status = 'error';
            $message = $confirmation['error'] ?? "Le paiement n'a pas pu être validé. Veuillez réessayer ou contacter notre assistance.";
        }
    }
}

$pageTitle = "Confirmation de Paiement — One Vision Community";
$hideHeaderNav = true;
require_once __DIR__ . '/includes/header.php';
?>

<main class="section" style="min-height: calc(100vh - 200px); display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; background: #f8fafc;">
  <div class="container" style="max-width: 580px; margin: 0 auto; text-align: center;">

    <div style="background: #ffffff; border-radius: 24px; padding: 3rem 2rem; box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

      <?php if ($status === 'success'): ?>
        <div style="width: 80px; height: 80px; border-radius: 50%; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem auto; box-shadow: 0 0 0 10px #f0fdf4;">
          ✓
        </div>
        <h1 style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0;">
          Paiement Confirmé !
        </h1>
        <p style="font-size: 1.05rem; color: #475569; line-height: 1.6; margin-bottom: 2rem;">
          Bienvenue dans l'aventure <strong>One Vision Community</strong> ! Votre formule <strong><?= htmlspecialchars($planNom) ?></strong> est active.
        </p>
        <p style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 1.5rem;">
          Redirection automatique vers votre espace membre...
        </p>
        <a href="dashboard.php" class="btn btn-primary" style="padding: 0.85rem 2rem; font-weight: 700; border-radius: 12px; display: inline-block;">
          Accéder à mon tableau de bord →
        </a>

      <?php elseif ($status === 'pending'): ?>
        <div style="width: 80px; height: 80px; border-radius: 50%; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin: 0 auto 1.5rem auto;">
          ⏳
        </div>
        <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0;">
          Paiement en cours de confirmation
        </h1>
        <p style="font-size: 1rem; color: #475569; line-height: 1.6; margin-bottom: 2rem;">
          <?= htmlspecialchars($message) ?>
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
          <a href="paiement-retour.php?ref=<?= urlencode($reference) ?>" class="btn btn-secondary" style="padding: 0.8rem 1.75rem; font-weight: 700; border-radius: 12px;">
            Actualiser le statut ↻
          </a>
          <a href="dashboard.php" class="btn btn-primary" style="padding: 0.8rem 1.75rem; font-weight: 700; border-radius: 12px;">
            Accéder à mon espace
          </a>
        </div>

      <?php else: ?>
        <div style="width: 80px; height: 80px; border-radius: 50%; background: #fef2f2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1.5rem auto;">
          ✕
        </div>
        <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0;">
          Paiement non finalisé
        </h1>
        <p style="font-size: 1rem; color: #64748b; line-height: 1.6; margin-bottom: 2rem;">
          <?= htmlspecialchars($message) ?>
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
          <a href="choisir-abonnement.php" class="btn btn-primary" style="padding: 0.8rem 1.75rem; font-weight: 700; border-radius: 12px;">
            Réessayer de souscrire
          </a>
          <a href="support.php" class="btn btn-secondary" style="padding: 0.8rem 1.75rem; font-weight: 700; border-radius: 12px;">
            Contacter le support
          </a>
        </div>
      <?php endif; ?>

    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
