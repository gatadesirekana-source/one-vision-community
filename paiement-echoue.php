<?php
/**
 * ONE VISION COMMUNITY — PAGE PAIEMENT ÉCHOUÉ
 * 
 * Affichée lorsque SasPay ou l'opérateur notifie un échec, une annulation ou un non-règlement.
 * Permet au client de recommencer son paiement en un clic.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$planCode = strtolower(trim($_GET['plan'] ?? ($_SESSION['last_payment_plan'] ?? 'membre')));
$periodicite = strtolower(trim($_GET['periodicite'] ?? ($_SESSION['last_payment_period'] ?? 'mensuel')));
$planNom = ($planCode === 'animateur') ? "Formule Animateur & Expert" : "Formule Membre Standard";
$retryUrl = 'choisir-abonnement.php?plan=' . urlencode($planCode) . '&periodicite=' . urlencode($periodicite);

$pageTitle = "Paiement Échoué — One Vision Community";
$hideHeaderNav = true;
require_once __DIR__ . '/includes/header.php';
?>

<main class="section" style="min-height: calc(100vh - 200px); display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; background: #f8fafc;">
  <div class="container" style="max-width: 580px; margin: 0 auto; text-align: center;">

    <div style="background: #ffffff; border-radius: 24px; padding: 3rem 2rem; box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

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
        La transaction n'a pas pu être validée par votre opérateur ou l'agrégateur SasPay.
        <br>
        <span style="font-size: 0.88rem; color: #64748b;">
          Rassurez-vous : aucun montant n'a été prélevé sur votre compte. Vous pouvez recommencer le paiement en toute sérénité.
        </span>
      </p>

      <div style="background: #fff7ed; border: 1px solid #ffedd5; border-radius: 14px; padding: 1.15rem; text-align: left; margin-bottom: 2rem; font-size: 0.88rem;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem;">
          <span style="color:#9a3412;">Formule choisie :</span>
          <strong style="color:#0f172a;"><?= htmlspecialchars($planNom) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between;">
          <span style="color:#9a3412;">Statut passerelle :</span>
          <strong style="color:#dc2626;">Règlement non reçu / annulé</strong>
        </div>
      </div>

      <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="<?= htmlspecialchars($retryUrl) ?>" class="btn btn-primary" style="padding: 0.95rem 2rem; font-weight: 800; font-size: 1rem; border-radius: 12px; display: inline-flex; align-items:center; gap:0.5rem; text-decoration:none; background: #0f172a;">
          <span>🔄 Recommencer le paiement</span>
        </a>
        <a href="support.php" class="btn btn-secondary" style="padding: 0.95rem 1.75rem; font-weight: 700; border-radius: 12px; text-decoration:none;">
          Contacter le support
        </a>
      </div>

    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
