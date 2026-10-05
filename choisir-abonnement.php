<?php
/**
 * ONE VISION COMMUNITY — CHOIX D'ABONNEMENT (PAGE NORMALE SANS POP-UP)
 * 
 * Présente côte à côte les deux formules (Membre et Animateur) avec :
 * - Leurs tarifs issus directement de la base de données (table plans)
 * - Leurs avantages complets
 * - Le sélecteur dynamique Mensuel / Annuel (avec badge "2 mois offerts" et calcul d'économie)
 * - Alerte spécifique si l'abonnement précédent a expiré
 * - Parcours de paiement fluide et simulé via PaymentService
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/subscriptions.php';
require_once __DIR__ . '/includes/payment_service.php';
require_once __DIR__ . '/includes/onboarding.php';

$db = get_db();
$currentUser = current_user();
$isLoggedIn = is_logged_in();

$isExpired = false;
$hasActiveSub = false;
$isMinor = false;
$wantsToAnimate = false;

if ($isLoggedIn) {
    $userId = (int)$currentUser['id'];
    $isMinor = is_user_minor($userId);

    $subStatus = check_user_subscription($userId);
    if ($subStatus['is_active']) {
        $hasActiveSub = true;
    } elseif ($subStatus['status'] === 'expired') {
        $isExpired = true;
    }

    // Si mineur : aucun abonnement ne doit être proposé
    if ($isMinor) {
        // Rendu direct du message bienveillant plus bas
    } elseif (!$hasActiveSub && !is_admin_user($currentUser)) {
        // Redirection vers le questionnaire si non terminé
        if (!has_user_completed_questionnaire($userId)) {
            header('Location: questionnaire.php');
            exit;
        }
    }

    $wantsToAnimate = user_wants_to_animate($userId);
}

// Récupération dynamique des formules depuis la BDD (jamais écrites en dur)
$stmtPlans = $db->query("SELECT * FROM plans WHERE actif = 1 ORDER BY ordre_affichage ASC");
$plans = $stmtPlans->fetchAll();

// Indexer par code pour accès rapide
$indexedPlans = [];
foreach ($plans as $p) {
    $p['avantages_list'] = json_decode($p['avantages'], true) ?: array_filter(array_map('trim', explode("\n", $p['avantages'])));
    $indexedPlans[$p['code']] = $p;
}

$membrePlan = $indexedPlans['membre'] ?? null;
$animateurPlan = $indexedPlans['animateur'] ?? null;

$error = '';

// Traitement de la souscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = "Session expirée. Veuillez rafraîchir la page et réessayer.";
    } elseif ($isMinor) {
        $error = "La souscription n'est pas autorisée.";
    } elseif (!$isLoggedIn) {
        // Enregistrer l'intention dans la session et renvoyer vers l'inscription/connexion
        $_SESSION['pending_plan'] = trim($_POST['plan_code'] ?? 'membre');
        $_SESSION['pending_period'] = trim($_POST['periodicite'] ?? 'mensuel');
        header('Location: register.php');
        exit;
    } else {
        $planCode = trim($_POST['plan_code'] ?? 'membre');
        $periodicite = in_array($_POST['periodicite'] ?? '', ['annuel', 'mensuel'], true) ? $_POST['periodicite'] : 'mensuel';

        $targetPlan = $indexedPlans[$planCode] ?? null;

        if (!$targetPlan) {
            $error = "La formule sélectionnée est invalide.";
        } else {
            // Création puis confirmation du paiement via le service isolé
            $creation = PaymentService::creerPaiement($currentUser, $targetPlan, $periodicite);

            if (!$creation['success']) {
                $error = $creation['error'] ?? "Erreur lors de l'initialisation du règlement.";
            } else {
                $confirmation = PaymentService::confirmerPaiement($creation['reference']);

                if (!$confirmation['success']) {
                    $error = $confirmation['error'] ?? "Erreur lors de la validation du règlement.";
                } else {
                    $periodLabel = ($periodicite === 'annuel') ? 'annuel' : 'mensuel';
                    set_flash('success', "Félicitations ! Votre abonnement {$targetPlan['nom']} ({$periodLabel}) est désormais actif. Bienvenue dans l'aventure One Vision !");

                    // Redirection systématique vers le dashboard avec rôle et abonnement mis à jour
                    header('Location: dashboard.php');
                    exit;
                }
            }
        }
    }
}

$pageTitle = "Choisir mon abonnement — One Vision Community";
$pageDescription = "Choisissez votre formule Membre ou Animateur pour accéder à la communauté d'entrepreneurs One Vision.";
$hideHeaderNav = true; // Épuration du header : masquer Abonnements, Mon Espace et Déconnexion
require_once __DIR__ . '/includes/header.php';
?>

<main class="section sub-choice-section" style="min-height: calc(100vh - 200px); padding: 2rem 1rem; background: #f8fafc;">
  <div class="container" style="max-width: 960px; margin: 0 auto;">

    <?php if ($isMinor): ?>
      <!-- Écran bienveillant pour les mineurs (Section 3) -->
      <div style="text-align: center; max-width: 520px; margin: 2rem auto; background: #ffffff; border-radius: 20px; padding: 2.5rem 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🌱</div>
        <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 0.75rem 0;">
          Merci pour votre intérêt !
        </h1>
        <p style="font-size: 0.95rem; color: #475569; line-height: 1.6; margin-bottom: 1.5rem;">
          La communauté <strong>One Vision</strong> est réservée aux adultes (18 ans et plus). Nous ne pouvons pas vous proposer d'abonnement pour le moment.
          <br><br>
          Prenez le temps d'apprendre et de mûrir vos projets. Nous serons ravis de vous accueillir dès votre majorité !
        </p>
        <a href="logout.php?redirect=index.php&quiet=1" class="btn btn-secondary" style="padding: 0.7rem 1.5rem; font-weight: 700; border-radius: 10px; display: inline-block;">
          Retourner à l'accueil
        </a>
      </div>
    <?php else: ?>

    <!-- Message d'alerte si l'abonnement a expiré (Section 4) -->
    <?php if ($isExpired): ?>
      <div class="alert alert-warning" style="background:#fffbeb; border:1px solid #fef3c7; color:#92400e; padding:1rem 1.25rem; border-radius:12px; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.85rem; box-shadow:0 4px 15px rgba(245,158,11,0.08);">
        <span style="font-size:1.5rem;">⏳</span>
        <div>
          <h2 style="margin:0 0 0.2rem 0; font-size:1.05rem; font-weight:800; color:#b45309;">
            Votre abonnement a expiré, renouvelez-le pour continuer.
          </h2>
          <p style="margin:0; font-size:0.88rem; color:#78350f;">
            Vos accès aux salons et sessions live sont temporairement suspendus. Choisissez une formule ci-dessous pour les réactiver immédiatement sans perte de vos données.
          </p>
        </div>
      </div>
    <?php elseif ($hasActiveSub): ?>
      <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:0.85rem 1rem; border-radius:10px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
        <div style="font-size:0.9rem;">
          <strong>Vous disposez déjà d'un abonnement actif.</strong> Vous pouvez faire évoluer votre formule ou gérer vos options dans votre espace dédié.
        </div>
        <a href="abonnements.php" class="btn btn-secondary btn-sm" style="background:#ffffff; color:#047857; border:1px solid #059669; font-weight:700; font-size:0.85rem; padding:0.4rem 0.85rem;">
          Gérer mon abonnement →
        </a>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem;">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- En-tête de présentation -->
    <div style="text-align: center; max-width: 680px; margin: 0 auto 1.75rem auto;">
      <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.4rem 0; letter-spacing: -0.025em;">
        Choisissez votre formule d'adhésion
      </h1>
      <p style="font-size: 0.95rem; color: #64748b; line-height: 1.5; margin: 0;">
        Rejoignez un collectif d'entrepreneurs ambitieux qui partagent leurs victoires, leurs blocages et leurs stratégies réelles chaque semaine.
      </p>

      <!-- Sélecteur Mensuel / Annuel (Section 5) -->
      <div class="period-toggle-wrapper" style="display:inline-flex; align-items:center; background:#ffffff; border:1px solid #cbd5e1; padding:0.25rem; border-radius:50px; margin-top:1.15rem; box-shadow:0 3px 10px rgba(0,0,0,0.03);">
        <button type="button" class="period-btn active" id="btnPeriodMonthly" onclick="switchPeriod('mensuel')" style="padding:0.45rem 1.15rem; border-radius:40px; font-weight:700; font-size:0.88rem; border:none; cursor:pointer; background:#0f172a; color:#ffffff; transition:all 0.2s;">
          Mensuel
        </button>
        <button type="button" class="period-btn" id="btnPeriodYearly" onclick="switchPeriod('annuel')" style="padding:0.45rem 1.15rem; border-radius:40px; font-weight:700; font-size:0.88rem; border:none; cursor:pointer; background:transparent; color:#64748b; transition:all 0.2s; display:inline-flex; align-items:center; gap:0.4rem;">
          <span>Annuel</span>
          <span style="background:#dcfce7; color:#15803d; font-size:0.72rem; font-weight:800; padding:0.15rem 0.45rem; border-radius:16px;">
            2 mois offerts
          </span>
        </button>
      </div>
    </div>

    <!-- Grille des formules côte à côte -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; align-items: stretch; margin-bottom: 2.25rem;">
      
      <?php
      // Définition des blocs de cartes
      $cardMembre = function() use ($membrePlan) {
        if (!$membrePlan) return;
        ?>
        <div class="pricing-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.75rem 1.6rem; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 8px 24px rgba(0,0,0,0.04); position:relative;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
              <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#2563eb; background:#eff6ff; padding:0.25rem 0.65rem; border-radius:6px;">
                Formule Membre
              </span>
            </div>

            <h3 style="font-size:1.35rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
              <?= htmlspecialchars($membrePlan['nom']) ?>
            </h3>
            <p style="font-size:0.88rem; color:#64748b; margin:0 0 1.15rem 0; line-height:1.45; font-weight:500;">
              Rejoignez la communauté, apprenez et connectez-vous
            </p>

            <!-- Bloc Prix Dynamique Membre -->
            <div style="margin-bottom:1.15rem; padding-bottom:1.15rem; border-bottom:1px solid #f1f5f9;">
              <!-- Affichage Mensuel -->
              <div class="price-box-monthly" style="display:block;">
                <span style="font-size:2.25rem; font-weight:900; color:#0f172a; letter-spacing:-0.03em;">
                  <?= number_format((float)$membrePlan['prix_mensuel'], 0) ?> €
                </span>
                <span style="font-size:0.88rem; color:#64748b; font-weight:600;">/ mois</span>
                <div style="font-size:0.78rem; color:#94a3b8; margin-top:0.2rem;">Sans engagement de durée</div>
              </div>
              <!-- Affichage Annuel -->
              <div class="price-box-yearly" style="display:none;">
                <div style="display:flex; align-items:baseline; gap:0.35rem;">
                  <span style="font-size:2.25rem; font-weight:900; color:#0f172a; letter-spacing:-0.03em;">
                    <?= number_format((float)$membrePlan['prix_annuel'], 0) ?> €
                  </span>
                  <span style="font-size:0.88rem; color:#64748b; font-weight:600;">/ an</span>
                </div>
                <div style="display:flex; align-items:center; gap:0.4rem; margin-top:0.3rem; flex-wrap:wrap;">
                  <span style="background:#dcfce7; color:#15803d; font-weight:700; font-size:0.74rem; padding:0.18rem 0.45rem; border-radius:6px;">
                    Économie de <?= round(((float)$membrePlan['prix_mensuel'] * 12) - (float)$membrePlan['prix_annuel']) ?> € / an
                  </span>
                  <span style="font-size:0.78rem; color:#475569; font-weight:600;">
                    Soit ~<?= number_format((float)$membrePlan['prix_annuel'] / 12, 2, ',', ' ') ?> € / mois
                  </span>
                </div>
              </div>
            </div>

            <!-- Liste des Avantages Membre (Section 2 & 5) -->
            <div style="margin-bottom:1.5rem;">
              <div style="font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; color:#475569; margin-bottom:0.75rem;">
                Ce qui est inclus :
              </div>
              <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.55rem; font-size:0.86rem; color:#334155;">
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#2563eb; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Accès à la communauté</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#2563eb; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Échanges quotidiens</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#2563eb; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Participation aux lives et masterminds des animateurs</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#2563eb; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Replays</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#2563eb; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Profil personnel</span>
                </li>
              </ul>
            </div>
          </div>

          <form method="POST" action="choisir-abonnement.php" style="margin-top:auto;">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_code" value="membre">
            <input type="hidden" name="periodicite" class="input-form-period" value="mensuel">
            
            <button type="submit" class="btn btn-secondary" style="width:100%; padding:0.75rem 1rem; font-size:0.92rem; font-weight:700; border-radius:10px; border:1.5px solid #cbd5e1; justify-content:center; display:flex; align-items:center; gap:0.45rem; cursor:pointer;">
              <span>Rejoindre en Formule Membre</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
          </form>
        </div>
        <?php
      };

      $cardAnimateur = function() use ($animateurPlan, $wantsToAnimate) {
        if (!$animateurPlan) return;
        ?>
        <div class="pricing-card" style="background:#ffffff; border:2px solid #f97316; border-radius:18px; padding:1.75rem 1.6rem; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 10px 30px rgba(249,115,22,0.1); position:relative;">
          
          <!-- Badge Recommandé -->
          <div style="position:absolute; top:-12px; right:20px; background:linear-gradient(135deg, #f97316, #ea580c); color:#ffffff; font-weight:800; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.05em; padding:0.25rem 0.75rem; border-radius:24px; box-shadow:0 3px 10px rgba(249,115,22,0.25);">
            ⭐ Formule Recommandée
          </div>

          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
              <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#ea580c; background:#fff7ed; padding:0.25rem 0.65rem; border-radius:6px;">
                Formule Animateur & Expert
              </span>
            </div>

            <?php if ($wantsToAnimate): ?>
              <div style="background:#fff7ed; border:1px solid #fdba74; color:#9a3412; padding:0.65rem 0.85rem; border-radius:8px; font-weight:700; font-size:0.86rem; margin-bottom:1rem; display:flex; align-items:center; gap:0.45rem;">
                <span>✨</span>
                <span>Vous voulez partager votre expertise ? Cette formule est faite pour vous.</span>
              </div>
            <?php endif; ?>

            <h3 style="font-size:1.35rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
              <?= htmlspecialchars($animateurPlan['nom']) ?>
            </h3>
            <p style="font-size:0.88rem; color:#64748b; margin:0 0 1.15rem 0; line-height:1.45; font-weight:500;">
              Partagez votre expertise, animez vos lives et vos masterminds
            </p>

            <!-- Bloc Prix Dynamique Animateur -->
            <div style="margin-bottom:1.15rem; padding-bottom:1.15rem; border-bottom:1px solid #f1f5f9;">
              <!-- Affichage Mensuel -->
              <div class="price-box-monthly" style="display:block;">
                <span style="font-size:2.25rem; font-weight:900; color:#f97316; letter-spacing:-0.03em;">
                  <?= number_format((float)$animateurPlan['prix_mensuel'], 0) ?> €
                </span>
                <span style="font-size:0.88rem; color:#64748b; font-weight:600;">/ mois</span>
                <div style="font-size:0.78rem; color:#94a3b8; margin-top:0.2rem;">Sans engagement de durée</div>
              </div>
              <!-- Affichage Annuel -->
              <div class="price-box-yearly" style="display:none;">
                <div style="display:flex; align-items:baseline; gap:0.35rem;">
                  <span style="font-size:2.25rem; font-weight:900; color:#f97316; letter-spacing:-0.03em;">
                    <?= number_format((float)$animateurPlan['prix_annuel'], 0) ?> €
                  </span>
                  <span style="font-size:0.88rem; color:#64748b; font-weight:600;">/ an</span>
                </div>
                <div style="display:flex; align-items:center; gap:0.4rem; margin-top:0.3rem; flex-wrap:wrap;">
                  <span style="background:#dcfce7; color:#15803d; font-weight:700; font-size:0.74rem; padding:0.18rem 0.45rem; border-radius:6px;">
                    Économie de <?= round(((float)$animateurPlan['prix_mensuel'] * 12) - (float)$animateurPlan['prix_annuel']) ?> € / an
                  </span>
                  <span style="font-size:0.78rem; color:#475569; font-weight:600;">
                    Soit ~<?= number_format((float)$animateurPlan['prix_annuel'] / 12, 2, ',', ' ') ?> € / mois
                  </span>
                </div>
              </div>
            </div>

            <!-- Liste des Avantages Animateur (Section 2 & 5) -->
            <div style="margin-bottom:1.5rem;">
              <div style="font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; color:#ea580c; margin-bottom:0.75rem;">
                Tout ce qui est inclus dans Membre, plus :
              </div>
              <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.55rem; font-size:0.86rem; color:#334155;">
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#f97316; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Création de lives et de masterminds</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#f97316; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Espace Animateur</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#f97316; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Page d'expert</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#f97316; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Certifications et badge</span>
                </li>
                <li style="display:flex; align-items:flex-start; gap:0.55rem; line-height:1.4;">
                  <span style="color:#f97316; font-weight:900; font-size:0.95rem; line-height:1;">✓</span>
                  <span>Statistiques de ses lives</span>
                </li>
              </ul>
            </div>
          </div>

          <form method="POST" action="choisir-abonnement.php" style="margin-top:auto;">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_code" value="animateur">
            <input type="hidden" name="periodicite" class="input-form-period" value="mensuel">
            
            <button type="submit" class="btn btn-primary" style="width:100%; padding:0.75rem 1rem; font-size:0.92rem; font-weight:700; border-radius:10px; background:linear-gradient(135deg, #f97316, #ea580c); justify-content:center; display:flex; align-items:center; gap:0.45rem; box-shadow:0 4px 12px rgba(249,115,22,0.25); cursor:pointer;">
              <span>Choisir la Formule Animateur</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
          </form>
        </div>
        <?php
      };

      // Si l'utilisateur a indiqué vouloir animer, on présente Animateur en premier
      if ($wantsToAnimate) {
        $cardAnimateur();
        $cardMembre();
      } else {
        $cardMembre();
        $cardAnimateur();
      }
      ?>

    </div>

    <!-- Garanties et engagement de confiance -->
    <div style="max-width: 860px; margin: 0 auto; padding: 2rem; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; text-align: center;">
      <div>
        <div style="font-size: 1.8rem; margin-bottom: 0.35rem;">🔒</div>
        <strong style="color: #0f172a; font-size: 0.95rem; display: block; margin-bottom: 0.2rem;">Sans engagement</strong>
        <span style="font-size: 0.82rem; color: #64748b;">Résiliez en un clic à tout moment depuis votre profil.</span>
      </div>
      <div>
        <div style="font-size: 1.8rem; margin-bottom: 0.35rem;">⚡</div>
        <strong style="color: #0f172a; font-size: 0.95rem; display: block; margin-bottom: 0.2rem;">Accès immédiat</strong>
        <span style="font-size: 0.82rem; color: #64748b;">Débloquez l'accès aux salons et replays instantanément.</span>
      </div>
      <div>
        <div style="font-size: 1.8rem; margin-bottom: 0.35rem;">🤝</div>
        <strong style="color: #0f172a; font-size: 0.95rem; display: block; margin-bottom: 0.2rem;">Communauté solidaire</strong>
        <span style="font-size: 0.82rem; color: #64748b;">Entrepreneurs actifs et bienveillants prêts à s'entraider.</span>
      </div>
    </div>

    <?php endif; ?>

  </div>
</main>

<script>
function switchPeriod(mode) {
  const btnMonthly = document.getElementById('btnPeriodMonthly');
  const btnYearly = document.getElementById('btnPeriodYearly');
  const monthlyBoxes = document.querySelectorAll('.price-box-monthly');
  const yearlyBoxes = document.querySelectorAll('.price-box-yearly');
  const periodInputs = document.querySelectorAll('.input-form-period');

  if (mode === 'annuel') {
    btnYearly.style.background = '#0f172a';
    btnYearly.style.color = '#ffffff';
    btnMonthly.style.background = 'transparent';
    btnMonthly.style.color = '#64748b';

    monthlyBoxes.forEach(el => el.style.display = 'none');
    yearlyBoxes.forEach(el => el.style.display = 'block');
    periodInputs.forEach(el => el.value = 'annuel');
  } else {
    btnMonthly.style.background = '#0f172a';
    btnMonthly.style.color = '#ffffff';
    btnYearly.style.background = 'transparent';
    btnYearly.style.color = '#64748b';

    monthlyBoxes.forEach(el => el.style.display = 'block');
    yearlyBoxes.forEach(el => el.style.display = 'none');
    periodInputs.forEach(el => el.value = 'mensuel');
  }
}
</script>

  <!-- JavaScript -->
  <script src="./js/main.js?v=4"></script>
</body>
</html>
