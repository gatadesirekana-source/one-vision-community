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

    <!-- En-tête de section : Retour au Dashboard à gauche en haut, et titre en-dessous -->
    <div style="margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem;">
      <div style="margin-bottom: 1.25rem;">
        <a href="dashboard.php" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:0.45rem; padding:0.5rem 1.05rem; border-radius:10px; font-weight:600; background:#ffffff; border:1px solid #cbd5e1; color:#1e293b; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
          <span>Retour au Dashboard</span>
        </a>
      </div>
      <div>
        <h1 style="font-size: 1.95rem; font-weight: 800; color: #0f172a; margin: 0 0 0.4rem 0; letter-spacing: -0.02em;">
          Gestion de mon abonnement
        </h1>
        <p style="font-size: 0.98rem; color: #64748b; margin: 0;">
          Suivez votre formule, vos échéances et personnalisez votre niveau d'adhésion.
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
      <!-- Utilisateur avec abonnement actif -->
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; margin-bottom:2.5rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
        
        <!-- En-tête de la formule active -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; border-bottom:1px solid #f1f5f9; padding-bottom:1.25rem;">
          <div>
            <span style="font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:<?= ($activeSub['plan_code'] === 'animateur') ? '#ea580c' : '#2563eb' ?>; background:<?= ($activeSub['plan_code'] === 'animateur') ? '#fff7ed' : '#eff6ff' ?>; padding:0.25rem 0.7rem; border-radius:6px; display:inline-block; margin-bottom:0.5rem;">
              Formule Active • <?= ucfirst($activeSub['periodicite']) ?>
            </span>
            <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem 0;">
              <?= htmlspecialchars($activeSub['plan_nom']) ?>
            </h2>
            <div style="font-size:0.95rem; color:#64748b;">
              Montant réglé : <strong><?= number_format((float)$activeSub['prix_paye'], 2, ',', ' ') ?> €</strong> / <?= ($activeSub['periodicite'] === 'annuel') ? 'an' : 'mois' ?>
            </div>
          </div>

          <div>
            <?php if (!empty($subInfo['is_cancelled'])): ?>
              <span style="background:#fee2e2; color:#991b1b; padding:0.4rem 0.9rem; border-radius:30px; font-weight:700; font-size:0.85rem; display:inline-flex; align-items:center; gap:0.4rem;">
                <span>⚠️</span> Résiliation programmée
              </span>
            <?php else: ?>
              <span style="background:#dcfce7; color:#15803d; padding:0.4rem 0.9rem; border-radius:30px; font-weight:700; font-size:0.85rem; display:inline-flex; align-items:center; gap:0.4rem;">
                <span style="width:8px; height:8px; border-radius:50%; background:#16a34a;"></span>
                Abonnement Actif
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Détails et dates clés -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.75rem;">
          <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem;">
            <span style="font-size:0.8rem; color:#64748b; display:block; margin-bottom:0.25rem;">Date de début</span>
            <strong style="color:#0f172a; font-size:1rem;"><?= date('d/m/Y', strtotime($activeSub['date_debut'])) ?></strong>
          </div>
          <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem;">
            <span style="font-size:0.8rem; color:#64748b; display:block; margin-bottom:0.25rem;">Date d'échéance</span>
            <strong style="color:#0f172a; font-size:1rem;"><?= date('d/m/Y', strtotime($activeSub['date_fin'])) ?></strong>
            <span style="font-size:0.75rem; color:#64748b; display:block; margin-top:0.2rem;">(reste <?= $subInfo['days_left'] ?> jour<?= ($subInfo['days_left'] > 1) ? 's' : '' ?>)</span>
          </div>
          <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem;">
            <span style="font-size:0.8rem; color:#64748b; display:block; margin-bottom:0.25rem;">Renouvellement automatique</span>
            <strong style="color:<?= empty($subInfo['is_cancelled']) ? '#16a34a' : '#dc2626' ?>; font-size:1rem;">
              <?= empty($subInfo['is_cancelled']) ? 'Activé' : 'Désactivé' ?>
            </strong>
          </div>
        </div>

        <!-- Message d'information si résiliation programmée -->
        <?php if (!empty($subInfo['cancellation_notice'])): ?>
          <div style="background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; padding:1rem 1.25rem; border-radius:12px; margin-bottom:1.5rem; font-size:0.92rem;">
            📢 <strong>Information :</strong> <?= htmlspecialchars($subInfo['cancellation_notice']) ?>. Vous continuerez de profiter de vos privilèges jusqu'à cette date.
          </div>
        <?php endif; ?>

        <!-- Actions sur l'abonnement en cours -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-top:1px solid #f1f5f9; padding-top:1.25rem;">
          <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
            <?php if ($activeSub['periodicite'] === 'mensuel'): ?>
              <!-- Bouton pour passer à l'annuel -->
              <form method="POST" action="abonnements.php" style="display:inline;" onsubmit="return confirm('Confirmez-vous le passage à la formule annuelle (2 mois offerts) ?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="passer_annuel">
                <button type="submit" class="btn btn-secondary btn-sm" style="font-weight:700; color:#15803d; border-color:#86efac; background:#f0fdf4;">
                  ✨ Passer en Annuel (2 mois offerts)
                </button>
              </form>
            <?php endif; ?>

            <?php if ($activeSub['plan_code'] === 'animateur'): ?>
              <!-- Bouton pour rétrograder directement vers Membre -->
              <form method="POST" action="abonnements.php" style="display:inline;" onsubmit="return confirm('Confirmez-vous le passage à la Formule Membre (9 €/mois) ? Votre formule Animateur prendra fin immédiatement.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="souscrire">
                <input type="hidden" name="plan_code" value="membre">
                <input type="hidden" name="periodicite" value="mensuel">
                <button type="submit" class="btn btn-secondary btn-sm" style="color:#2563eb; border-color:#bfdbfe; background:#eff6ff; font-weight:700; font-size:0.85rem;">
                  🔄 Passer à la Formule Membre (9 €/mois)
                </button>
              </form>
            <?php endif; ?>
          </div>

          <div>
            <!-- Bouton de résiliation TOUJOURS ACCESSIBLE -->
            <form method="POST" action="abonnements.php" style="display:inline;" onsubmit="return confirm('Êtes-vous certain de vouloir résilier votre abonnement ? Vos privilèges actuels seront désactivés immédiatement et vous pourrez choisir une nouvelle formule.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="resilier">
              <button type="submit" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#fca5a5; font-size:0.85rem; font-weight:700; padding:0.45rem 1rem; border-radius:8px;">
                Résilier mon abonnement
              </button>
            </form>
          </div>
        </div>

      </div>

      <!-- BLOC ÉVOLUTION POUR LES MEMBRES : PASSER À ANIMATEUR -->
      <?php if ($activeSub['plan_code'] === 'membre' && $animateurPlan): ?>
        <div style="background:#ffffff; border:2px solid #f97316; border-radius:20px; padding:2.25rem 2rem; margin-bottom:2.5rem; box-shadow:0 12px 35px rgba(249,115,22,0.1); position:relative;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
            <div>
              <span style="background:#fff7ed; color:#ea580c; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; padding:0.25rem 0.7rem; border-radius:6px; display:inline-block; margin-bottom:0.4rem;">
                Évolution de formule
              </span>
              <h2 style="font-size:1.5rem; font-weight:800; color:#0f172a; margin:0;">
                Devenir Animateur One Vision
              </h2>
            </div>
            <div class="period-toggle-upgrade" style="background:#f8fafc; border:1px solid #cbd5e1; padding:0.25rem; border-radius:30px; display:inline-flex; align-items:center;">
              <button type="button" class="upgrade-period-btn active" id="btnUpgradeMonthly" onclick="setUpgradePeriod('mensuel')" style="padding:0.4rem 1rem; border-radius:25px; font-size:0.82rem; font-weight:700; border:none; cursor:pointer; background:#0f172a; color:#fff;">
                Mensuel (24 €/mois)
              </button>
              <button type="button" class="upgrade-period-btn" id="btnUpgradeYearly" onclick="setUpgradePeriod('annuel')" style="padding:0.4rem 1rem; border-radius:25px; font-size:0.82rem; font-weight:700; border:none; cursor:pointer; background:transparent; color:#64748b;">
                Annuel (239 €/an)
              </button>
            </div>
          </div>

          <p style="color:#475569; font-size:0.95rem; line-height:1.55; margin:0 0 1.5rem 0;">
            L'abonnement Animateur remplace immédiatement votre formule Membre. Vous débloquez l'Espace Animateur, programmez vos propres masterminds et disposez de votre page d'expert certifié.
          </p>

          <div style="background:#fffaf0; border:1px solid #fed7aa; border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:1.75rem;">
            <div style="font-size:0.82rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#ea580c; margin-bottom:0.75rem;">
              Avantages exclusifs Formule Animateur :
            </div>
            <ul style="list-style:none; padding:0; margin:0; display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:0.65rem; font-size:0.9rem; color:#334155;">
              <?php foreach ($animateurPlan['avantages_list'] as $av): ?>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#f97316; font-weight:900;">✓</span>
                  <span><?= htmlspecialchars($av) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <form method="POST" action="abonnements.php" onsubmit="return confirm('Confirmez-vous le passage à la formule Animateur ? Votre abonnement Membre sera remplacé automatiquement.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upgrade_animateur">
            <input type="hidden" name="periodicite" id="upgradePeriodiciteInput" value="mensuel">
            
            <button type="submit" class="btn btn-primary" style="padding:0.85rem 1.75rem; font-size:0.98rem; font-weight:700; border-radius:12px; background:linear-gradient(135deg, #f97316, #ea580c); display:inline-flex; align-items:center; gap:0.6rem; box-shadow:0 6px 18px rgba(249,115,22,0.3);">
              <span>🚀 Devenir Animateur maintenant</span>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
          </form>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <!-- 2. UTILISATEUR SANS ABONNEMENT ACTIF (OU APRÈS RÉSILIATION) : LES DEUX FORMULES EMPILÉES VERTICALEMENT -->
      
      <!-- Bannière d'information -->
      <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:2rem; display:flex; align-items:flex-start; gap:0.85rem;">
        <span style="font-size:1.6rem; line-height:1;">📢</span>
        <div>
          <strong style="color:#9a3412; font-size:1rem; display:block; margin-bottom:0.25rem;">Abonnement non actif</strong>
          <p style="color:#7c2d12; font-size:0.92rem; margin:0; line-height:1.5;">
            Vous ne bénéficiez actuellement plus d'un abonnement actif. Pour continuer à profiter de l'ensemble des masterminds, replays et salons d'échange de la communauté, veuillez choisir une formule ci-dessous :
          </p>
        </div>
      </div>

      <!-- FORMULE 1 (EN HAUT) : FORMULE MEMBRE BASIQUE À 9 € -->
      <div style="background:#ffffff; border:2px solid #2563eb; border-radius:20px; padding:2.25rem 2rem; margin-bottom:2rem; box-shadow:0 10px 30px rgba(37,99,235,0.08); position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
          <div>
            <span style="background:#eff6ff; color:#2563eb; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; padding:0.3rem 0.8rem; border-radius:6px; display:inline-block; margin-bottom:0.5rem;">
              Formule Basique • Membre One Vision
            </span>
            <h2 style="font-size:1.65rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
              Formule Membre (Basique)
            </h2>
            <p style="color:#64748b; font-size:0.95rem; margin:0;">
              Accédez à toute la communauté, assistez aux masterminds en direct et profitez des replays vidéo.
            </p>
          </div>

          <!-- Sélecteur de périodicité pour Membre -->
          <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:0.25rem; border-radius:30px; display:inline-flex; align-items:center;">
            <button type="button" id="btnMembreMonthly" onclick="setMembrePeriod('mensuel')" style="padding:0.4rem 1rem; border-radius:25px; font-size:0.82rem; font-weight:700; border:none; cursor:pointer; background:#0f172a; color:#fff;">
              Mensuel (9 €/mois)
            </button>
            <button type="button" id="btnMembreYearly" onclick="setMembrePeriod('annuel')" style="padding:0.4rem 1rem; border-radius:25px; font-size:0.82rem; font-weight:700; border:none; cursor:pointer; background:transparent; color:#64748b;">
              Annuel (89 €/an)
            </button>
          </div>
        </div>

        <!-- Tarification Membre -->
        <div style="margin-bottom:1.5rem; display:flex; align-items:baseline; gap:0.5rem;">
          <span id="priceMembreDisplay" style="font-size:2.4rem; font-weight:800; color:#0f172a; line-height:1;">
            9,00 €
          </span>
          <span style="font-size:1rem; color:#64748b; font-weight:600;">
            / <span id="periodMembreUnit">mois</span>
          </span>
          <span id="badgeMembreSavings" style="display:none; background:#dcfce7; color:#15803d; font-size:0.75rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:20px; text-transform:uppercase;">
            2 mois offerts !
          </span>
        </div>

        <!-- Liste des avantages Membre -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:1.75rem;">
          <div style="font-size:0.82rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#2563eb; margin-bottom:0.75rem;">
            Avantages inclus dans la Formule Membre :
          </div>
          <ul style="list-style:none; padding:0; margin:0; display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:0.65rem; font-size:0.9rem; color:#334155;">
            <?php if ($membrePlan && !empty($membrePlan['avantages_list'])): ?>
              <?php foreach ($membrePlan['avantages_list'] as $av): ?>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#2563eb; font-weight:900;">✓</span>
                  <span><?= htmlspecialchars($av) ?></span>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#2563eb; font-weight:900;">✓</span><span>Accès complet au Dashboard et aux salons d'échanges</span></li>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#2563eb; font-weight:900;">✓</span><span>Participation en direct à tous les lives hebdomadaires</span></li>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#2563eb; font-weight:900;">✓</span><span>Accès illimité aux rediffusions et replays vidéo</span></li>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#2563eb; font-weight:900;">✓</span><span>Réseau d'entraide entre entrepreneurs et fiches pratiques</span></li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Bouton d'action Membre -->
        <form method="POST" action="abonnements.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="souscrire">
          <input type="hidden" name="plan_code" value="membre">
          <input type="hidden" name="periodicite" id="inputMembrePeriod" value="mensuel">
          
          <button type="submit" class="btn btn-primary" style="padding:0.9rem 2rem; font-size:1rem; font-weight:700; border-radius:12px; background:#2563eb; box-shadow:0 6px 20px rgba(37,99,235,0.25); display:inline-flex; align-items:center; gap:0.6rem;">
            <span id="btnMembreLabel">✨ Choisir la Formule Membre (9 € / mois)</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
        </form>
      </div>

      <!-- FORMULE 2 (EN BAS) : FORMULE ANIMATEUR À 24 € -->
      <div style="background:#ffffff; border:2px solid #f97316; border-radius:20px; padding:2.25rem 2rem; margin-bottom:2.5rem; box-shadow:0 10px 30px rgba(249,115,22,0.1); position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
          <div>
            <span style="background:#fff7ed; color:#ea580c; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; padding:0.3rem 0.8rem; border-radius:6px; display:inline-block; margin-bottom:0.5rem;">
              Formule Animateur • Expert & Leader (Rôle Privilégié)
            </span>
            <h2 style="font-size:1.65rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
              Formule Animateur
            </h2>
            <p style="color:#64748b; font-size:0.95rem; margin:0;">
              Animez vos propres masterminds, débloquez l'Espace Animateur et développez votre influence.
            </p>
          </div>

          <!-- Sélecteur de périodicité pour Animateur -->
          <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:0.25rem; border-radius:30px; display:inline-flex; align-items:center;">
            <button type="button" id="btnAnimateurMonthly" onclick="setAnimateurPeriod('mensuel')" style="padding:0.4rem 1rem; border-radius:25px; font-size:0.82rem; font-weight:700; border:none; cursor:pointer; background:#0f172a; color:#fff;">
              Mensuel (24 €/mois)
            </button>
            <button type="button" id="btnAnimateurYearly" onclick="setAnimateurPeriod('annuel')" style="padding:0.4rem 1rem; border-radius:25px; font-size:0.82rem; font-weight:700; border:none; cursor:pointer; background:transparent; color:#64748b;">
              Annuel (239 €/an)
            </button>
          </div>
        </div>

        <!-- Tarification Animateur -->
        <div style="margin-bottom:1.5rem; display:flex; align-items:baseline; gap:0.5rem;">
          <span id="priceAnimateurDisplay" style="font-size:2.4rem; font-weight:800; color:#0f172a; line-height:1;">
            24,00 €
          </span>
          <span style="font-size:1rem; color:#64748b; font-weight:600;">
            / <span id="periodAnimateurUnit">mois</span>
          </span>
          <span id="badgeAnimateurSavings" style="display:none; background:#dcfce7; color:#15803d; font-size:0.75rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:20px; text-transform:uppercase;">
            2 mois offerts !
          </span>
        </div>

        <!-- Liste des avantages Animateur -->
        <div style="background:#fffaf0; border:1px solid #fed7aa; border-radius:14px; padding:1.25rem 1.5rem; margin-bottom:1.75rem;">
          <div style="font-size:0.82rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#ea580c; margin-bottom:0.75rem;">
            Avantages exclusifs Formule Animateur :
          </div>
          <ul style="list-style:none; padding:0; margin:0; display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:0.65rem; font-size:0.9rem; color:#334155;">
            <?php if ($animateurPlan && !empty($animateurPlan['avantages_list'])): ?>
              <?php foreach ($animateurPlan['avantages_list'] as $av): ?>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#f97316; font-weight:900;">✓</span>
                  <span><?= htmlspecialchars($av) ?></span>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#f97316; font-weight:900;">✓</span><span><strong>Tous les avantages de la Formule Membre inclus</strong></span></li>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#f97316; font-weight:900;">✓</span><span>Déblocage exclusif de votre Espace Animateur</span></li>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#f97316; font-weight:900;">✓</span><span>Création et animation de masterminds & lives</span></li>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#f97316; font-weight:900;">✓</span><span>Profil certifié avec badge officiel d'Animateur</span></li>
              <li style="display:flex; align-items:flex-start; gap:0.55rem;"><span style="color:#f97316; font-weight:900;">✓</span><span>Salon vocal dédié et modération de vos événements</span></li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Bouton d'action Animateur -->
        <form method="POST" action="abonnements.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="souscrire">
          <input type="hidden" name="plan_code" value="animateur">
          <input type="hidden" name="periodicite" id="inputAnimateurPeriod" value="mensuel">
          
          <button type="submit" class="btn btn-primary" style="padding:0.9rem 2rem; font-size:1rem; font-weight:700; border-radius:12px; background:linear-gradient(135deg, #f97316, #ea580c); box-shadow:0 6px 20px rgba(249,115,22,0.3); display:inline-flex; align-items:center; gap:0.6rem;">
            <span id="btnAnimateurLabel">🎙️ Choisir la Formule Animateur (24 € / mois)</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
        </form>
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

<script>
// Gestion dynamique de la période pour Membre
function setMembrePeriod(mode) {
  const btnM = document.getElementById('btnMembreMonthly');
  const btnY = document.getElementById('btnMembreYearly');
  const inp = document.getElementById('inputMembrePeriod');
  const priceDisp = document.getElementById('priceMembreDisplay');
  const unitDisp = document.getElementById('periodMembreUnit');
  const badgeSavings = document.getElementById('badgeMembreSavings');
  const btnLabel = document.getElementById('btnMembreLabel');

  if (mode === 'annuel') {
    btnY.style.background = '#0f172a';
    btnY.style.color = '#fff';
    btnM.style.background = 'transparent';
    btnM.style.color = '#64748b';
    inp.value = 'annuel';
    priceDisp.textContent = '89,00 €';
    unitDisp.textContent = 'an';
    badgeSavings.style.display = 'inline-block';
    btnLabel.textContent = '✨ Choisir la Formule Membre (89 € / an)';
  } else {
    btnM.style.background = '#0f172a';
    btnM.style.color = '#fff';
    btnY.style.background = 'transparent';
    btnY.style.color = '#64748b';
    inp.value = 'mensuel';
    priceDisp.textContent = '9,00 €';
    unitDisp.textContent = 'mois';
    badgeSavings.style.display = 'none';
    btnLabel.textContent = '✨ Choisir la Formule Membre (9 € / mois)';
  }
}

// Gestion dynamique de la période pour Animateur
function setAnimateurPeriod(mode) {
  const btnM = document.getElementById('btnAnimateurMonthly');
  const btnY = document.getElementById('btnAnimateurYearly');
  const inp = document.getElementById('inputAnimateurPeriod');
  const priceDisp = document.getElementById('priceAnimateurDisplay');
  const unitDisp = document.getElementById('periodAnimateurUnit');
  const badgeSavings = document.getElementById('badgeAnimateurSavings');
  const btnLabel = document.getElementById('btnAnimateurLabel');

  if (mode === 'annuel') {
    btnY.style.background = '#0f172a';
    btnY.style.color = '#fff';
    btnM.style.background = 'transparent';
    btnM.style.color = '#64748b';
    inp.value = 'annuel';
    priceDisp.textContent = '239,00 €';
    unitDisp.textContent = 'an';
    badgeSavings.style.display = 'inline-block';
    btnLabel.textContent = '🎙️ Choisir la Formule Animateur (239 € / an)';
  } else {
    btnM.style.background = '#0f172a';
    btnM.style.color = '#fff';
    btnY.style.background = 'transparent';
    btnY.style.color = '#64748b';
    inp.value = 'mensuel';
    priceDisp.textContent = '24,00 €';
    unitDisp.textContent = 'mois';
    badgeSavings.style.display = 'none';
    btnLabel.textContent = '🎙️ Choisir la Formule Animateur (24 € / mois)';
  }
}

// Gestion du toggle Upgrade (si abonné membre actif)
function setUpgradePeriod(mode) {
  const btnM = document.getElementById('btnUpgradeMonthly');
  const btnY = document.getElementById('btnUpgradeYearly');
  const inp = document.getElementById('upgradePeriodiciteInput');

  if (!btnM || !btnY || !inp) return;

  if (mode === 'annuel') {
    btnY.style.background = '#0f172a';
    btnY.style.color = '#fff';
    btnM.style.background = 'transparent';
    btnM.style.color = '#64748b';
    inp.value = 'annuel';
  } else {
    btnM.style.background = '#0f172a';
    btnM.style.color = '#fff';
    btnY.style.background = 'transparent';
    btnY.style.color = '#64748b';
    inp.value = 'mensuel';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
