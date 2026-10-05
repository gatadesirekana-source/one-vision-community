<?php
/**
 * ONE VISION COMMUNITY — FICHE DÉTAILLÉE D'UN MEMBRE (ONBOARDING & RÉPONSES)
 * 
 * Fonctionnalités exigées (Section 7) :
 * - Fiche complète avec toutes les réponses du membre au questionnaire (13 questions réparties en 5 étapes)
 * - Statut du profil onboarding, dates et canal de rappel
 * - Consentement marketing avec rappel RGPD
 * - Détails de son abonnement actuel et historique de paiements
 * - Lien direct pour lui écrire par email (retargeting / accueil)
 * - Protégé côté serveur par require_permission('voir_profils_membres')
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/onboarding.php';
require_once __DIR__ . '/../includes/questionnaire_data.php';

require_admin_access('../login.php');
require_permission('voir_profils_membres', 'onboarding_profils.php');

$db = get_db();
$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    set_flash('error', "Identifiant de membre invalide.");
    header('Location: onboarding_profils.php');
    exit;
}

// 1. Récupération de l'utilisateur
$stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$member = $stmtUser->fetch();

if (!$member) {
    set_flash('error', "Membre introuvable.");
    header('Location: onboarding_profils.php');
    exit;
}

// 2. Profil onboarding
$onboarding = get_user_onboarding_profile($userId);

// 3. Toutes les réponses données par le membre
$rawResponses = get_user_questionnaire_responses($userId);
$answersByQ = [];
foreach ($rawResponses as $r) {
    $answersByQ[$r['question_id']][] = $r['reponse'];
}

// 4. Dernier abonnement et formule
$stmtSub = $db->prepare("
    SELECT s.*, p.nom AS plan_nom, p.code AS plan_code, p.prix_mensuel, p.prix_annuel
    FROM subscriptions s
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC
    LIMIT 1
");
$stmtSub->execute([$userId]);
$lastSub = $stmtSub->fetch();

$isSubActive = ($lastSub && $lastSub['statut'] === 'actif' && strtotime($lastSub['date_fin']) > time());

// 5. Historique des paiements
$stmtPayments = $db->prepare("
    SELECT * FROM payments 
    WHERE user_id = ? 
    ORDER BY id DESC
");
$stmtPayments->execute([$userId]);
$payments = $stmtPayments->fetchAll();

$allSteps = get_questionnaire_steps();

$pageTitle = "Fiche Membre : " . htmlspecialchars($member['full_name']) . " — Administration";
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 2rem;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
    <a href="onboarding_profils.php" style="color:#2563eb; text-decoration:none; font-weight:700; font-size:0.92rem; display:inline-flex; align-items:center; gap:0.4rem;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
      <span>Retour à la liste des profils</span>
    </a>

    <div style="display:flex; gap:0.75rem;">
      <a href="mailto:<?= htmlspecialchars($member['email']) ?>" class="btn btn-primary btn-sm" style="font-weight:700; display:inline-flex; align-items:center; gap:0.4rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        <span>Envoyer un email</span>
      </a>
      <a href="user_detail.php?id=<?= $member['id'] ?>" class="btn btn-secondary btn-sm" style="font-weight:700;">
        Fiche compte utilisateur →
      </a>
    </div>
  </div>

  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; box-shadow:0 6px 20px rgba(0,0,0,0.03);">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.5rem;">
      
      <!-- Identité -->
      <div style="display:flex; align-items:center; gap:1.25rem;">
        <img src="../<?= htmlspecialchars(ltrim($member['avatar'] ?? 'img/avatar-cyril.jpg', './')) ?>" alt="" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #cbd5e1;">
        <div>
          <div style="display:flex; align-items:center; gap:0.6rem;">
            <h1 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0;">
              <?= htmlspecialchars($member['full_name']) ?>
            </h1>
            <span style="background:<?= $isSubActive ? '#ecfdf5' : '#f1f5f9' ?>; color:<?= $isSubActive ? '#047857' : '#64748b' ?>; font-size:0.75rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:6px; text-transform:uppercase;">
              <?= $isSubActive ? 'Abonné actif' : 'Non abonné' ?>
            </span>
          </div>
          <div style="color:#64748b; font-size:0.92rem; margin-top:0.25rem;">
            <?= htmlspecialchars($member['email']) ?> • Inscrit le <?= date('d/m/Y à H:i', strtotime($member['created_at'])) ?> UTC
          </div>
        </div>
      </div>

      <!-- Statuts rapides -->
      <div style="display:flex; gap:1rem; flex-wrap:wrap;">
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:0.75rem 1.25rem; min-width:140px;">
          <div style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:#64748b;">Questionnaire</div>
          <div style="font-size:1rem; font-weight:800; color:#0f172a; margin-top:0.2rem;">
            <?php if (($onboarding['statut'] ?? '') === 'termine'): ?>
              <span style="color:#059669;">✓ Terminé</span>
            <?php elseif (($onboarding['statut'] ?? '') === 'mineur'): ?>
              <span style="color:#dc2626;">Moins de 18 ans</span>
            <?php else: ?>
              <span style="color:#d97706;">⏳ En cours</span>
            <?php endif; ?>
          </div>
        </div>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:0.75rem 1.25rem; min-width:140px;">
          <div style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:#64748b;">Abonnement</div>
          <div style="font-size:1rem; font-weight:800; color:#0f172a; margin-top:0.2rem;">
            <?php if ($isSubActive): ?>
              <?= htmlspecialchars($lastSub['plan_nom']) ?> (<?= ucfirst($lastSub['periodicite']) ?>)
            <?php else: ?>
              <span style="color:#94a3b8; font-style:italic;">Aucun</span>
            <?php endif; ?>
          </div>
        </div>

        <div style="background:<?= ((int)($onboarding['consentement_marketing'] ?? 0) === 1) ? '#ecfdf5' : '#f8fafc' ?>; border:1px solid <?= ((int)($onboarding['consentement_marketing'] ?? 0) === 1) ? '#a7f3d0' : '#e2e8f0' ?>; border-radius:12px; padding:0.75rem 1.25rem; min-width:160px;">
          <div style="font-size:0.72rem; font-weight:800; text-transform:uppercase; color:<?= ((int)($onboarding['consentement_marketing'] ?? 0) === 1) ? '#047857' : '#64748b' ?>;">Opt-in Marketing</div>
          <div style="font-size:1rem; font-weight:800; color:<?= ((int)($onboarding['consentement_marketing'] ?? 0) === 1) ? '#047857' : '#64748b' ?>; margin-top:0.2rem;">
            <?= ((int)($onboarding['consentement_marketing'] ?? 0) === 1) ? '✓ OUI (Autorisé)' : 'NON (Refusé)' ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- RAPPEL RGPD EXPLICITE -->
<?php if ((int)($onboarding['consentement_marketing'] ?? 0) !== 1): ?>
  <div style="background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; color:#991b1b; padding:1rem 1.25rem; border-radius:12px; margin-bottom:2rem; font-size:0.92rem;">
    <strong>Attention RGPD :</strong> Ce membre n'a pas donné son consentement pour recevoir des emails d'informations ou d'offres promotionnelles. Ne lui envoyez aucun email de prospection commerciale ou de relance non sollicitée.
  </div>
<?php else: ?>
  <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-left:4px solid #10b981; color:#065f46; padding:1rem 1.25rem; border-radius:12px; margin-bottom:2rem; font-size:0.92rem;">
    <strong>Consentement Marketing accordé :</strong> Le membre a accepté de recevoir par email des informations et des offres One Vision (le <?= date('d/m/Y à H:i', strtotime($onboarding['date_consentement'] ?? $onboarding['created_at'])) ?> UTC).
  </div>
<?php endif; ?>

<!-- GRILLE EN 2 COLONNES : DÉTAILS DU QUESTIONNAIRE & ABONNEMENT -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:2rem; align-items:start;">
  
  <!-- COLONNE GAUCHE : TOUTES LES QUESTIONS ET RÉPONSES (Section 7) -->
  <div>
    <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 1.25rem 0;">
      📝 Réponses complètes au questionnaire
    </h2>

    <?php if (empty($answersByQ) && empty($onboarding['tranche_age'])): ?>
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:2rem; text-align:center; color:#64748b;">
        Ce membre a initié son compte mais n'a pas encore répondu à la première question.
      </div>
    <?php else: ?>

      <div style="display:flex; flex-direction:column; gap:1.5rem;">
        <?php foreach ($allSteps as $stepIndex => $step): ?>
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
            
            <!-- En-tête de l'étape -->
            <div style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:1rem 1.5rem; display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:#2563eb; letter-spacing:0.04em;">
                  <?= htmlspecialchars($step['badge']) ?>
                </span>
                <h3 style="font-size:1.05rem; font-weight:800; color:#0f172a; margin:0.15rem 0 0 0;">
                  <?= htmlspecialchars($step['title']) ?>
                </h3>
              </div>
            </div>

            <!-- Liste des questions de cette étape -->
            <div style="padding:1.5rem; display:flex; flex-direction:column; gap:1.5rem;">
              <?php foreach ($step['questions'] as $q): ?>
                <?php
                  $qId = $q['id'];
                  $userAnswers = $answersByQ[$qId] ?? [];
                  $isAnswered = !empty($userAnswers);
                ?>
                <div style="border-bottom:1px solid #f1f5f9; padding-bottom:1.25rem;">
                  <div style="display:flex; justify-content:space-between; align-items:baseline; gap:0.5rem; margin-bottom:0.6rem;">
                    <div style="font-size:0.95rem; font-weight:700; color:#0f172a;">
                      <span style="color:#64748b; font-weight:800; margin-right:0.35rem;"><?= htmlspecialchars($qId) ?></span>
                      <?= htmlspecialchars($q['question']) ?>
                    </div>
                    <span style="font-size:0.72rem; color:#64748b; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:4px; font-weight:600; white-space:nowrap;">
                      <?= ($q['type'] === 'single') ? 'Choix unique' : 'Choix multiple' ?>
                    </span>
                  </div>

                  <!-- Réponses données -->
                  <div>
                    <?php if ($isAnswered): ?>
                      <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                        <?php foreach ($userAnswers as $ans): ?>
                          <span style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:0.88rem; font-weight:600; padding:0.35rem 0.75rem; border-radius:8px; display:inline-flex; align-items:center; gap:0.35rem;">
                            <span>✓</span> <?= htmlspecialchars($ans) ?>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span style="color:#94a3b8; font-size:0.85rem; font-style:italic;">
                        Non répondu (étape non atteinte)
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>

  <!-- COLONNE DROITE : ABONNEMENT & HISTORIQUE (Section 7) -->
  <div>
    <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 1.25rem 0;">
      💳 Abonnement & Historique
    </h2>

    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.02); margin-bottom:1.5rem;">
      <h3 style="font-size:1rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">
        Formule en cours
      </h3>

      <?php if ($lastSub): ?>
        <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.88rem;">
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem;">
            <span style="color:#64748b;">Formule</span>
            <strong style="color:#0f172a;"><?= htmlspecialchars($lastSub['plan_nom'] ?? 'Membre') ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem;">
            <span style="color:#64748b;">Périodicité</span>
            <strong style="color:#0f172a;"><?= ucfirst($lastSub['periodicite']) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem;">
            <span style="color:#64748b;">Statut</span>
            <strong style="color:<?= $isSubActive ? '#059669' : '#dc2626' ?>;">
              <?= $isSubActive ? 'Actif' : ucfirst($lastSub['statut']) ?>
            </strong>
          </div>
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem;">
            <span style="color:#64748b;">Montant payé</span>
            <strong style="color:#0f172a;"><?= number_format((float)$lastSub['prix_paye'], 2) ?> €</strong>
          </div>
          <div style="display:flex; justify-content:space-between; border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem;">
            <span style="color:#64748b;">Date début</span>
            <strong style="color:#0f172a;"><?= date('d/m/Y', strtotime($lastSub['date_debut'])) ?></strong>
          </div>
          <div style="display:flex; justify-content:space-between;">
            <span style="color:#64748b;">Échéance</span>
            <strong style="color:#0f172a;"><?= date('d/m/Y', strtotime($lastSub['date_fin'])) ?></strong>
          </div>
        </div>
      <?php else: ?>
        <div style="color:#64748b; font-size:0.9rem; font-style:italic;">
          Aucun abonnement souscrit à ce jour.
        </div>
      <?php endif; ?>
    </div>

    <!-- Historique des paiements -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
      <h3 style="font-size:1rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">
        Transactions & Règlements
      </h3>

      <?php if (!empty($payments)): ?>
        <div style="display:flex; flex-direction:column; gap:0.75rem;">
          <?php foreach ($payments as $p): ?>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.75rem; font-size:0.83rem;">
              <div style="display:flex; justify-content:space-between; font-weight:700; color:#0f172a;">
                <span><?= number_format((float)$p['montant'], 2) ?> <?= htmlspecialchars($p['devise']) ?></span>
                <span style="color:<?= $p['statut'] === 'reussi' ? '#059669' : '#dc2626' ?>; font-size:0.75rem; text-transform:uppercase;">
                  <?= htmlspecialchars($p['statut']) ?>
                </span>
              </div>
              <div style="color:#64748b; font-size:0.75rem; margin-top:0.25rem;">
                Réf: <?= htmlspecialchars($p['reference_externe']) ?> • <?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="color:#64748b; font-size:0.88rem; font-style:italic;">
          Aucune transaction enregistrée.
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
