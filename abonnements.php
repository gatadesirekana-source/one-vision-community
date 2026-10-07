<?php
/**
 * ONE VISION COMMUNITY — ESPACE GESTION DE MON ABONNEMENT (SECTION UTILISATEUR)
 * 
 * Permet au membre connecté de :
 * - Consulter son abonnement en cours (formule, prix, dates, statut, renouvellement)
 * - Résilier son abonnement immédiatement avec désactivation des privilèges
 * - Choisir parmi les deux formules empilées verticalement (Membre 9€ en haut, Animateur 24€ en bas)
 * - Passer d'un abonnement mensuel à un abonnement annuel (avec 2 mois offerts)
 * - Consulter l'historique complet de ses paiements
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/subscriptions.php';
require_once __DIR__ . '/includes/payment_service.php';

require_auth('login.php');

$db = get_db();
$currentUser = current_user();
$userId = (int)$currentUser['id'];

// Récupérer le statut de l'abonnement
$subInfo = check_user_subscription($userId);
$activeSub = get_user_active_subscription($userId);

// Récupérer les formules depuis la BDD
$plans = $db->query("SELECT * FROM plans WHERE actif = 1 ORDER BY ordre_affichage ASC")->fetchAll();
$indexedPlans = [];
foreach ($plans as $p) {
    $p['avantages_list'] = json_decode($p['avantages'], true) ?: array_filter(array_map('trim', explode("\n", $p['avantages'])));
    $indexedPlans[$p['code']] = $p;
}
$animateurPlan = $indexedPlans['animateur'] ?? null;
$membrePlan = $indexedPlans['membre'] ?? null;

$isOwnerOrAdmin = is_admin_user($currentUser);

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', "Session expirée. Veuillez actualiser et réessayer.");
        header('Location: abonnements.php');
        exit;
    }

    if ($action === 'resilier') {
        $resResult = resilier_abonnement($userId);
        if ($resResult['success']) {
            set_flash('info', $resResult['message']);
        } else {
            set_flash('error', $resResult['error'] ?? "Impossible de traiter la résiliation.");
        }
        header('Location: abonnements.php');
        exit;
    } elseif ($action === 'souscrire') {
        $planCode = trim($_POST['plan_code'] ?? 'membre');
        $periodicite = in_array($_POST['periodicite'] ?? '', ['annuel', 'mensuel'], true) ? $_POST['periodicite'] : 'mensuel';
        $targetPlan = $indexedPlans[$planCode] ?? $membrePlan;

        if (!$targetPlan) {
            set_flash('error', "La formule sélectionnée est introuvable.");
        } else {
            $creation = PaymentService::creerPaiement($currentUser, $targetPlan, $periodicite);
            if ($creation['success']) {
                $conf = PaymentService::confirmerPaiement($creation['reference']);
                if ($conf['success']) {
                    $periodLabel = ($periodicite === 'annuel') ? 'annuel' : 'mensuel';
                    set_flash('success', "Félicitations ! Votre abonnement {$targetPlan['nom']} ({$periodLabel}) est désormais actif.");
                    if ($targetPlan['code'] === 'animateur') {
                        header('Location: espace-animateur.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit;
                } else {
                    set_flash('error', $conf['error'] ?? "Erreur lors de la validation du règlement.");
                }
            } else {
                set_flash('error', $creation['error'] ?? "Erreur lors de l'initialisation du règlement.");
            }
        }
        header('Location: abonnements.php');
        exit;
    } elseif ($action === 'passer_annuel') {
        // Passer à la formule annuelle du plan actuel
        $currentPlanCode = $activeSub['plan_code'] ?? 'membre';
        $targetPlan = $indexedPlans[$currentPlanCode] ?? $membrePlan;

        $creation = PaymentService::creerPaiement($currentUser, $targetPlan, 'annuel');
        if ($creation['success']) {
            $conf = PaymentService::confirmerPaiement($creation['reference']);
            if ($conf['success']) {
                set_flash('success', "Votre abonnement est maintenant annuel ! Vous bénéficiez de 2 mois offerts.");
            } else {
                set_flash('error', $conf['error'] ?? "Erreur lors de la validation.");
            }
        } else {
            set_flash('error', $creation['error'] ?? "Erreur lors de l'initialisation.");
        }
        header('Location: abonnements.php');
        exit;
    } elseif ($action === 'upgrade_animateur') {
        // Upgrade vers Animateur
        $periodicite = in_array($_POST['periodicite'] ?? '', ['annuel', 'mensuel'], true) ? $_POST['periodicite'] : 'mensuel';
        
        $creation = PaymentService::creerPaiement($currentUser, $animateurPlan, $periodicite);
        if ($creation['success']) {
            $conf = PaymentService::confirmerPaiement($creation['reference']);
            if ($conf['success']) {
                set_flash('success', "Félicitations ! Votre compte a été promu Animateur. Votre Espace Animateur est désormais débloqué.");
                header('Location: espace-animateur.php');
                exit;
            } else {
                set_flash('error', $conf['error'] ?? "Erreur lors de la validation.");
            }
        } else {
            set_flash('error', $creation['error'] ?? "Erreur lors de l'initialisation.");
        }
        header('Location: abonnements.php');
        exit;
    }
}

// Historique des paiements
$payments = get_user_payments_history($userId);

// Masquer les 4 boutons du header sur la page abonnements (selon la demande exacte de l'utilisateur)
$isAbonnementsPage = true;
$hideHeaderNav = true;

$pageTitle = "Mon Abonnement & Cotisations — One Vision Community";
$pageDescription = "Gérez votre formule, vos échéances et personnalisez votre niveau d'adhésion.";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section sub-manage-section" style="min-height: calc(100vh - 260px); padding: 3rem 1rem; background: #f8fafc;">
  <div class="container" style="max-width: 980px; margin: 0 auto;">

    <!-- En-tête de section : Bouton Retour à gauche en haut, et titre en-dessous -->
    <div style="margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem;">
      <div style="margin-bottom: 1.25rem;">
        <a href="dashboard.php" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:0.45rem; padding:0.5rem 1.05rem; border-radius:10px; font-weight:600; background:#ffffff; border:1px solid #cbd5e1; color:#1e293b; box-shadow:0 1px 3px rgba(0,0,0,0.05); transition:all 0.2s ease;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
          <span>Retour</span>
        </a>
      </div>
      <div>
        <h1 style="font-size: 1.95rem; font-weight: 800; color: #0f172a; margin: 0 0 0.4rem 0; letter-spacing: -0.02em;">
          Gestion de mon abonnement
        </h1>
        <p style="font-size: 0.98rem; color: #64748b; margin: 0;">
          Consultez votre formule active, modifiez vos options ou téléchargez vos reçus et factures officielles.
        </p>
      </div>
    </div>

    <!-- 1. CARTE DE L'ABONNEMENT ACTUEL -->
    <?php if ($isOwnerOrAdmin): ?>
      <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:18px; padding:2rem; margin-bottom:2.5rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">
          <span style="font-size:2.2rem;">👑</span>
          <div>
            <h2 style="font-size:1.35rem; font-weight:800; color:#0f172a; margin:0;">
              Compte d'Administration Permanente
            </h2>
            <span style="font-size:0.85rem; color:#d97706; font-weight:700; text-transform:uppercase;">
              <?= is_owner($currentUser) ? 'Propriétaire du site (Super-admin)' : 'Administrateur Délégué' ?>
            </span>
          </div>
        </div>
        <p style="color:#475569; font-size:0.92rem; line-height:1.6; margin:0 0 1.25rem 0;">
          Votre compte dispose d'un accès intégral et illimité à l'Académie, à la communauté et aux outils de gestion sans aucune restriction d'abonnement.
        </p>
        <a href="admin/index.php" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:0.5rem;">
          <span>Accéder à l'Espace Administration</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
      </div>

    <?php elseif ($activeSub): ?>
      <!-- Formule unique active (conforme à la maquette) -->
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem 2.25rem; margin-bottom:2.5rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
        
        <!-- En-tête de la formule active -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.25rem; border-bottom:1px solid #f1f5f9; padding-bottom:1.5rem; margin-bottom:1.5rem;">
          <div>
            <?php if (!empty($subInfo['is_cancelled'])): ?>
              <span style="background:#fee2e2; color:#991b1b; padding:0.35rem 0.9rem; border-radius:9999px; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; display:inline-flex; align-items:center; gap:0.45rem; margin-bottom:0.75rem;">
                <span style="width:7px; height:7px; border-radius:50%; background:#ef4444;"></span>
                Résiliation programmée
              </span>
            <?php else: ?>
              <span style="background:#dcfce7; color:#15803d; padding:0.35rem 0.9rem; border-radius:9999px; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; display:inline-flex; align-items:center; gap:0.45rem; margin-bottom:0.75rem;">
                <span style="width:7px; height:7px; border-radius:50%; background:#16a34a;"></span>
                ABONNEMENT ACTIF
              </span>
            <?php endif; ?>

            <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0; letter-spacing:-0.02em;">
              <?= ($activeSub['plan_code'] === 'animateur') ? 'Formule Animateur & Expert (Abonnement Plus)' : 'Formule Membre Standard' ?>
            </h2>
            <p style="color:#64748b; font-size:0.92rem; margin:0;">
              <?php if (!empty($subInfo['is_cancelled'])): ?>
                Vos privilèges restent valides jusqu'au <strong><?= ov_format_date_fr($activeSub['date_fin']) ?></strong>.
              <?php else: ?>
                Prochain prélèvement automatique le <strong><?= ov_format_date_fr($activeSub['date_fin']) ?></strong>.
              <?php endif; ?>
            </p>
          </div>

          <div style="text-align:right;">
            <div style="font-size:2.35rem; font-weight:900; color:#0f172a; letter-spacing:-0.03em; line-height:1.1;">
              <?= number_format((float)$activeSub['prix_paye'], 2, ',', ' ') ?> €
            </div>
            <span style="font-size:0.85rem; color:#64748b; font-weight:600;">/ <?= ($activeSub['periodicite'] === 'annuel') ? 'an' : 'mois' ?> TTC</span>
          </div>
        </div>

        <!-- Détails 3 colonnes -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.5rem; font-size:0.9rem; margin-bottom:1.5rem;">
          <div>
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em; display:block; margin-bottom:0.35rem;">Date de souscription</span>
            <div style="font-weight:700; color:#0f172a; font-size:0.98rem;"><?= ov_format_date_fr($activeSub['date_debut']) ?></div>
          </div>
          <div>
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em; display:block; margin-bottom:0.35rem;">Renouvellement</span>
            <div style="font-weight:700; color:<?= empty($subInfo['is_cancelled']) ? '#15803d' : '#dc2626' ?>; font-size:0.98rem;">
              <?= empty($subInfo['is_cancelled']) ? 'Automatique (Résiliable 1 clic)' : 'Résiliation programmée' ?>
            </div>
          </div>
          <div>
            <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em; display:block; margin-bottom:0.35rem;">Mode de paiement</span>
            <div style="font-weight:700; color:#0f172a; font-size:0.98rem; display:flex; align-items:center; gap:0.45rem;">
              <svg width="18" height="14" viewBox="0 0 24 18" fill="none" style="vertical-align:middle; flex-shrink:0;"><rect width="24" height="18" rx="3" fill="#2563eb"/><rect y="4" width="24" height="3" fill="#1e293b"/><rect x="3" y="11" width="6" height="3" rx="1" fill="#cbd5e1"/></svg>
              <span>Visa terminant par 4242</span>
            </div>
          </div>
        </div>

        <!-- Boutons d'action : pilules conformes à la maquette -->
        <div style="display:flex; justify-content:flex-end; align-items:center; gap:1rem; flex-wrap:wrap; border-top:1px solid #f1f5f9; padding-top:1.5rem;">
          <a href="choisir-abonnement.php" style="background:#f1f5f9; border:1.5px solid #cbd5e1; color:#0f172a; font-weight:700; font-size:0.92rem; padding:0.65rem 1.6rem; border-radius:9999px; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; transition:all 0.2s ease; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            Changer de formule d'abonnement
          </a>
          <form method="POST" action="abonnements.php" style="display:inline; margin:0;" onsubmit="return confirm('Êtes-vous certain de vouloir résilier votre abonnement ?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resilier">
            <button type="submit" style="background:#fef2f2; border:1.5px solid #fecaca; color:#dc2626; font-weight:700; font-size:0.92rem; padding:0.65rem 1.6rem; border-radius:9999px; cursor:pointer; display:inline-flex; align-items:center; gap:0.5rem; transition:all 0.2s ease;">
              Résilier mon abonnement
            </button>
          </form>
        </div>

      </div>

    <?php else: ?>
      <!-- Utilisateur sans abonnement actif : carte unique invitant au choix de formule -->
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem 2.25rem; margin-bottom:2.5rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.25rem; border-bottom:1px solid #f1f5f9; padding-bottom:1.5rem; margin-bottom:1.5rem;">
          <div>
            <span style="font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; background:#fee2e2; color:#dc2626; padding:0.35rem 0.9rem; border-radius:9999px; display:inline-flex; align-items:center; gap:0.45rem; margin-bottom:0.75rem;">
              <span style="width:7px; height:7px; border-radius:50%; background:#dc2626; display:inline-block;"></span>
              Abonnement Inactif
            </span>
            <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0; letter-spacing:-0.02em;">
              Aucun abonnement en cours
            </h2>
            <p style="color:#64748b; font-size:0.92rem; margin:0;">
              Vous ne bénéficiez actuellement plus d'un abonnement actif.
            </p>
          </div>
        </div>

        <p style="color:#475569; font-size:0.95rem; line-height:1.6; margin:0 0 1.5rem 0;">
          Pour continuer à profiter de l'ensemble des masterminds, replays vidéo, salons d'échange et outils de l'Académie, choisissez une formule d'abonnement.
        </p>

        <div style="display:flex; justify-content:flex-end; gap:1rem; border-top:1px solid #f1f5f9; padding-top:1.5rem;">
          <a href="choisir-abonnement.php" style="background:#f1f5f9; border:1.5px solid #cbd5e1; color:#0f172a; font-weight:700; font-size:0.92rem; padding:0.65rem 1.6rem; border-radius:9999px; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; transition:all 0.2s ease;">
            <span>Changer de formule d'abonnement</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- 3. HISTORIQUE DES PAIEMENTS -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
      <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 1.25rem 0;">
        Historique de mes paiements
      </h2>

      <?php if (!empty($payments)): ?>
        <div style="overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem;">
            <thead>
              <tr style="border-bottom:2px solid #f1f5f9; color:#64748b; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
                <th style="padding:0.75rem 0.5rem;">Date</th>
                <th style="padding:0.75rem 0.5rem;">Formule</th>
                <th style="padding:0.75rem 0.5rem;">Périodicité</th>
                <th style="padding:0.75rem 0.5rem;">Montant</th>
                <th style="padding:0.75rem 0.5rem;">Statut</th>
                <th style="padding:0.75rem 0.5rem;">Référence</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $pay): ?>
                <tr style="border-bottom:1px solid #f8fafc;">
                  <td style="padding:0.85rem 0.5rem; color:#334155;">
                    <?= date('d/m/Y H:i', strtotime($pay['date_paiement'])) ?>
                  </td>
                  <td style="padding:0.85rem 0.5rem; font-weight:700; color:#0f172a;">
                    <?= htmlspecialchars($pay['plan_nom'] ?? 'Formule One Vision') ?>
                  </td>
                  <td style="padding:0.85rem 0.5rem; color:#64748b;">
                    <?= ucfirst($pay['periodicite'] ?? 'mensuel') ?>
                  </td>
                  <td style="padding:0.85rem 0.5rem; font-weight:700; color:#0f172a;">
                    <?= number_format((float)$pay['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($pay['devise']) ?>
                  </td>
                  <td style="padding:0.85rem 0.5rem;">
                    <?php if ($pay['statut'] === 'reussi'): ?>
                      <span style="background:#dcfce7; color:#15803d; font-size:0.75rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">
                        Réglé
                      </span>
                    <?php else: ?>
                      <span style="background:#fef3c7; color:#b45309; font-size:0.75rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">
                        <?= htmlspecialchars(ucfirst($pay['statut'])) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="padding:0.85rem 0.5rem; font-family:monospace; font-size:0.82rem; color:#64748b;">
                    <?= htmlspecialchars($pay['reference_externe'] ?? '-') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:#64748b; font-size:0.9rem; margin:0;">
          Aucun paiement enregistré pour l'instant.
        </p>
      <?php endif; ?>
    </div>

  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
