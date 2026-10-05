<?php
/**
 * ONE VISION COMMUNITY — FICHE UTILISATEUR & ACTIONS D'ADMINISTRATION
 * 
 * Exigences (Section 10) :
 * - Informations, historique d'abonnements et de paiements
 * - Actions d'administration avec confirmation et journal d'actions :
 *   1. Suspendre ou réactiver un compte
 *   2. Changer manuellement le rôle ou la formule (ex: offrir un accès Animateur)
 *   3. Prolonger ou annuler un abonnement
 * - Protection absolue du compte propriétaire (ne peut être suspendu ou rétrogradé)
 */

$pageTitle = "Fiche Utilisateur — Administration One Vision";
require_once __DIR__ . '/header.php';
require_permission('voir_utilisateurs', 'users.php');

$db = get_db();
$userId = (int)($_GET['id'] ?? 0);

$stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$targetUser = $stmtUser->fetch();

if (!$targetUser) {
    set_flash('error', "Utilisateur introuvable.");
    header('Location: users.php');
    exit;
}

$isTargetOwner = is_owner($targetUser);
$isActingOwner = is_owner($currentUser);

// Traitement des actions d'administration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', "Session expirée. Veuillez actualiser la page.");
        header("Location: user_detail.php?id={$userId}");
        exit;
    }

    $action = trim($_POST['action'] ?? '');

    // ACTION 1 : SUSPENDRE OU RÉACTIVER UN COMPTE
    if ($action === 'toggle_suspension') {
        if ($isTargetOwner) {
            set_flash('error', "Impossible de suspendre le compte du Propriétaire.");
        } elseif (!user_has_permission($currentUser, 'moderer_contenu') && !user_has_permission($currentUser, 'gerer_roles') && !$isActingOwner) {
            set_flash('error', "Vous ne disposez pas des permissions pour suspendre ou réactiver un compte.");
        } else {
            $newStatut = ($targetUser['statut'] === 'suspendu') ? 'actif' : 'suspendu';
            $stmt = $db->prepare("UPDATE users SET statut = ? WHERE id = ?");
            $stmt->execute([$newStatut, $userId]);

            $logAction = ($newStatut === 'suspendu') ? 'suspension_compte' : 'reactivation_compte';
            log_admin_action($currentUser['id'], $logAction, "Utilisateur #{$userId} ({$targetUser['email']})", "Nouveau statut: {$newStatut}");

            set_flash('success', "Le compte de {$targetUser['full_name']} est maintenant {$newStatut}.");
        }
        header("Location: user_detail.php?id={$userId}");
        exit;
    }

    // ACTION 2 : CHANGER MANUELLEMENT LE RÔLE OU LA FORMULE (OFFRIR ACCÈS)
    elseif ($action === 'change_role_or_plan') {
        if ($isTargetOwner && !$isActingOwner) {
            set_flash('error', "Impossible de modifier le rôle du Propriétaire.");
        } elseif (!user_has_permission($currentUser, 'gerer_roles') && !$isActingOwner) {
            set_flash('error', "Vous ne disposez pas des permissions pour modifier les rôles.");
        } else {
            $newRole = trim($_POST['new_role'] ?? 'membre');
            $newPlanCode = trim($_POST['new_plan_code'] ?? 'membre');

            // Interdiction d'attribuer 'proprietaire' sans passer par la procédure sécurisée de transfert
            if ($newRole === 'proprietaire' && !$isTargetOwner) {
                set_flash('error', "Pour désigner un nouveau propriétaire, utilisez la procédure sécurisée de Transfert de Propriété.");
                header("Location: user_detail.php?id={$userId}");
                exit;
            }

            // Récupérer le plan ciblé
            $stmtP = $db->prepare("SELECT * FROM plans WHERE code = ?");
            $stmtP->execute([$newPlanCode]);
            $plan = $stmtP->fetch();

            if ($plan) {
                $db->beginTransaction();
                try {
                    // Mettre à jour l'utilisateur
                    $db->prepare("UPDATE users SET role = ?, subscription_plan = ?, subscription_status = 'active' WHERE id = ?")
                       ->execute([$newRole, $newPlanCode, $userId]);

                    // Vérifier s'il a un abonnement actif à mettre à jour ou à créer
                    $activeSub = get_user_active_subscription($userId);
                    if ($activeSub) {
                        $db->prepare("UPDATE subscriptions SET plan_id = ?, updated_at = datetime('now') WHERE id = ?")
                           ->execute([$plan['id'], $activeSub['id']]);
                    } else {
                        // Offrir un abonnement actif pour 30 jours
                        $dDeb = gmdate('Y-m-d H:i:s');
                        $dFin = gmdate('Y-m-d H:i:s', strtotime('+30 days'));
                        $ref = 'OFFRE-ADMIN-' . strtoupper(bin2hex(random_bytes(4)));

                        $db->prepare("
                            INSERT INTO subscriptions (user_id, plan_id, periodicite, prix_paye, statut, date_debut, date_fin, renouvellement_auto, reference_paiement)
                            VALUES (?, ?, 'mensuel', 0.00, 'actif', ?, ?, 1, ?)
                        ")->execute([$userId, $plan['id'], $dDeb, $dFin, $ref]);

                        $db->prepare("UPDATE users SET subscription_started_at = ?, subscription_expires_at = ? WHERE id = ?")
                           ->execute([$dDeb, $dFin, $userId]);
                    }

                    // Journaliser
                    log_admin_action(
                        $currentUser['id'], 
                        'attribution_manuelle_formule', 
                        "Utilisateur #{$userId} ({$targetUser['email']})", 
                        "Rôle: {$newRole}, Formule: {$plan['nom']}"
                    );

                    $db->commit();
                    set_flash('success', "Le rôle et la formule ont été mis à jour avec succès.");
                } catch (Exception $e) {
                    $db->rollBack();
                    set_flash('error', "Erreur : " . $e->getMessage());
                }
            }
        }
        header("Location: user_detail.php?id={$userId}");
        exit;
    }

    // ACTION 3 : PROLONGER OU ANNULER UN ABONNEMENT
    elseif ($action === 'manage_subscription') {
        if (!user_has_permission($currentUser, 'gerer_abonnements') && !$isActingOwner) {
            set_flash('error', "Vous ne disposez pas des permissions pour gérer les abonnements.");
        } else {
            $subAction = trim($_POST['sub_action'] ?? '');
            $activeSub = get_user_active_subscription($userId);

            if ($subAction === 'prolonger') {
                $days = (int)($_POST['prolong_days'] ?? 30);
                if ($activeSub) {
                    $curEnd = strtotime($activeSub['date_fin']);
                    $base = max(time(), $curEnd);
                    $newEnd = gmdate('Y-m-d H:i:s', strtotime("+{$days} days", $base));

                    $db->prepare("UPDATE subscriptions SET date_fin = ?, statut = 'actif', updated_at = datetime('now') WHERE id = ?")
                       ->execute([$newEnd, $activeSub['id']]);
                    $db->prepare("UPDATE users SET subscription_status = 'active', subscription_expires_at = ? WHERE id = ?")
                       ->execute([$newEnd, $userId]);

                    log_admin_action($currentUser['id'], 'prolongation_abonnement', "Utilisateur #{$userId}", "Prolongé de {$days} jours jusqu'au {$newEnd}");
                    set_flash('success', "L'abonnement a été prolongé de {$days} jours jusqu'au " . date('d/m/Y', strtotime($newEnd)) . ".");
                } else {
                    set_flash('error', "Aucun abonnement actif à prolonger. Attribuez-lui une formule d'abord.");
                }
            } elseif ($subAction === 'annuler') {
                if ($activeSub) {
                    $db->prepare("UPDATE subscriptions SET statut = 'annule', updated_at = datetime('now') WHERE id = ?")
                       ->execute([$activeSub['id']]);
                    $db->prepare("UPDATE users SET subscription_status = 'cancelled' WHERE id = ?")
                       ->execute([$userId]);

                    log_admin_action($currentUser['id'], 'annulation_abonnement', "Utilisateur #{$userId}", "Abonnement #{$activeSub['id']} annulé manuellement");
                    set_flash('success', "L'abonnement actif a été annulé.");
                }
            }
        }
        header("Location: user_detail.php?id={$userId}");
        exit;
    }
}

// Recharger les données
$stmtUser->execute([$userId]);
$targetUser = $stmtUser->fetch();
$activeSub = get_user_active_subscription($userId);
$subsHistory = get_user_subscriptions_history($userId);
$paymentsHistory = get_user_payments_history($userId);
$plans = $db->query("SELECT * FROM plans WHERE actif = 1")->fetchAll();
?>

<!-- Fil d'Ariane -->
<div style="margin-bottom:1.25rem;">
  <a href="users.php" style="font-size:0.85rem; font-weight:700; color:#2563eb; text-decoration:none;">
    ← Retour à la liste des utilisateurs
  </a>
</div>

<!-- En-tête profil utilisateur -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; margin-bottom:2rem; box-shadow:0 6px 20px rgba(0,0,0,0.03); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem;">
  <div style="display:flex; align-items:center; gap:1.25rem;">
    <img src="../<?= htmlspecialchars(ltrim($targetUser['avatar'] ?? 'img/avatar-maxime.jpg', './')) ?>" alt="" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #cbd5e1;">
    <div>
      <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap; margin-bottom:0.25rem;">
        <h1 style="font-size:1.5rem; font-weight:800; color:#0f172a; margin:0;">
          <?= htmlspecialchars($targetUser['full_name']) ?>
        </h1>
        <?php if ($isTargetOwner): ?>
          <span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.75rem; padding:0.2rem 0.6rem; border-radius:6px;">👑 Propriétaire</span>
        <?php elseif ($targetUser['role'] === 'admin_delegue'): ?>
          <span style="background:#e0f2fe; color:#0369a1; font-weight:700; font-size:0.75rem; padding:0.2rem 0.6rem; border-radius:6px;">🛡️ Admin délégué</span>
        <?php elseif ($targetUser['role'] === 'animateur'): ?>
          <span style="background:#fff7ed; color:#ea580c; font-weight:700; font-size:0.75rem; padding:0.2rem 0.6rem; border-radius:6px;">🎙️ Animateur</span>
        <?php else: ?>
          <span style="background:#f1f5f9; color:#475569; font-weight:600; font-size:0.75rem; padding:0.2rem 0.6rem; border-radius:6px;">Membre</span>
        <?php endif; ?>

        <?php if (($targetUser['statut'] ?? 'actif') === 'suspendu'): ?>
          <span style="background:#dc2626; color:#ffffff; font-weight:700; font-size:0.75rem; padding:0.2rem 0.6rem; border-radius:6px;">Compte Suspendu</span>
        <?php else: ?>
          <span style="background:#dcfce7; color:#15803d; font-weight:700; font-size:0.75rem; padding:0.2rem 0.6rem; border-radius:6px;">Compte Actif</span>
        <?php endif; ?>
      </div>
      <div style="color:#64748b; font-size:0.9rem;">
        <?= htmlspecialchars($targetUser['email']) ?> • Inscrit le <?= date('d/m/Y', strtotime($targetUser['created_at'])) ?>
      </div>
    </div>
  </div>

  <!-- Action rapide : Suspendre / Réactiver -->
  <?php if (!$isTargetOwner && (user_has_permission($currentUser, 'moderer_contenu') || $isActingOwner)): ?>
    <form method="POST" action="user_detail.php?id=<?= $userId ?>" onsubmit="return confirm('Confirmez-vous cette action sur le compte ?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_suspension">
      <?php if (($targetUser['statut'] ?? 'actif') === 'suspendu'): ?>
        <button type="submit" class="btn btn-secondary btn-sm" style="background:#10b981; color:#fff; border:none; font-weight:700;">
          ✓ Réactiver le compte
        </button>
      <?php else: ?>
        <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5; font-weight:700;">
          ⚠️ Suspendre le compte
        </button>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>

<!-- ACTIONS D'ADMINISTRATION : GESTION DES RÔLES & ABONNEMENTS -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; margin-bottom:2.5rem;">
  
  <!-- Formulaire 1 : Modifier le rôle / Formule -->
  <?php if (user_has_permission($currentUser, 'gerer_roles') || $isActingOwner): ?>
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
      <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">
        Changer le rôle ou la formule
      </h3>
      <form method="POST" action="user_detail.php?id=<?= $userId ?>" onsubmit="return confirm('Appliquer ces modifications de rôle et de formule ?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_role_or_plan">

        <div style="margin-bottom:1rem;">
          <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Rôle utilisateur</label>
          <select name="new_role" class="form-input" style="width:100%; padding:0.55rem; border:1px solid #cbd5e1; border-radius:8px;">
            <option value="membre" <?= ($targetUser['role'] === 'membre') ? 'selected' : '' ?>>Membre</option>
            <option value="animateur" <?= ($targetUser['role'] === 'animateur') ? 'selected' : '' ?>>Animateur (débloque Espace Animateur)</option>
            <?php if ($isActingOwner): ?>
              <option value="admin_delegue" <?= ($targetUser['role'] === 'admin_delegue') ? 'selected' : '' ?>>Administrateur délégué</option>
            <?php endif; ?>
          </select>
        </div>

        <div style="margin-bottom:1.25rem;">
          <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Formule associée</label>
          <select name="new_plan_code" class="form-input" style="width:100%; padding:0.55rem; border:1px solid #cbd5e1; border-radius:8px;">
            <?php foreach ($plans as $pl): ?>
              <option value="<?= htmlspecialchars($pl['code']) ?>" <?= (($activeSub['plan_code'] ?? $targetUser['subscription_plan'] ?? '') === $pl['code']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($pl['nom']) ?> (<?= number_format((float)$pl['prix_mensuel'], 0) ?> €/mois)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-secondary btn-sm" style="width:100%; font-weight:700; padding:0.6rem;">
          Enregistrer le rôle et la formule
        </button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Formulaire 2 : Prolonger / Annuler l'abonnement -->
  <?php if (user_has_permission($currentUser, 'gerer_abonnements') || $isActingOwner): ?>
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
      <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">
        Gestion de l'abonnement en cours
      </h3>
      
      <?php if ($activeSub): ?>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.75rem 1rem; margin-bottom:1.25rem; font-size:0.85rem;">
          <div>Formule : <strong><?= htmlspecialchars($activeSub['plan_nom']) ?></strong> (<?= ucfirst($activeSub['periodicite']) ?>)</div>
          <div>Échéance actuelle : <strong><?= date('d/m/Y', strtotime($activeSub['date_fin'])) ?></strong></div>
        </div>

        <form method="POST" action="user_detail.php?id=<?= $userId ?>" style="margin-bottom:0.75rem;" onsubmit="return confirm('Confirmez-vous la prolongation ?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="manage_subscription">
          <input type="hidden" name="sub_action" value="prolonger">

          <div style="display:flex; gap:0.5rem; margin-bottom:0.75rem;">
            <select name="prolong_days" class="form-input" style="flex:1; padding:0.55rem; border:1px solid #cbd5e1; border-radius:8px;">
              <option value="30">+ 30 jours (1 mois)</option>
              <option value="90">+ 90 jours (3 mois)</option>
              <option value="365">+ 365 jours (1 an)</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm" style="font-weight:700; background:#10b981; color:#fff; border:none; padding:0 1rem;">
              + Prolonger
            </button>
          </div>
        </form>

        <form method="POST" action="user_detail.php?id=<?= $userId ?>" onsubmit="return confirm('Confirmez-vous l\'annulation immédiate de cet abonnement ?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="manage_subscription">
          <input type="hidden" name="sub_action" value="annuler">
          <button type="submit" class="btn btn-outline btn-sm" style="width:100%; color:#dc2626; border-color:#fca5a5; font-size:0.82rem;">
            ✕ Annuler l'abonnement
          </button>
        </form>

      <?php else: ?>
        <p style="color:#64748b; font-size:0.88rem; margin:0;">Cet utilisateur n'a aucun abonnement actif.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<!-- HISTORIQUE DES ABONNEMENTS ET DES PAIEMENTS -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(450px, 1fr)); gap:2rem;">
  
  <!-- Table Historique des Abonnements -->
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
    <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">
      Historique des Abonnements
    </h3>

    <?php if (!empty($subsHistory)): ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.85rem;">
          <thead>
            <tr style="border-bottom:2px solid #f1f5f9; color:#64748b; font-size:0.75rem; text-transform:uppercase;">
              <th style="padding:0.5rem 0.25rem;">Formule</th>
              <th style="padding:0.5rem 0.25rem;">Périodicité</th>
              <th style="padding:0.5rem 0.25rem;">Statut</th>
              <th style="padding:0.5rem 0.25rem;">Période</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subsHistory as $sh): ?>
              <tr style="border-bottom:1px solid #f8fafc;">
                <td style="padding:0.65rem 0.25rem; font-weight:700; color:#0f172a;">
                  <?= htmlspecialchars($sh['plan_nom'] ?? 'Formule') ?>
                </td>
                <td style="padding:0.65rem 0.25rem; color:#475569;">
                  <?= ucfirst($sh['periodicite']) ?>
                </td>
                <td style="padding:0.65rem 0.25rem;">
                  <span style="font-size:0.72rem; font-weight:700; padding:0.15rem 0.45rem; border-radius:4px; background:<?= ($sh['statut'] === 'actif') ? '#dcfce7; color:#15803d' : '#f1f5f9; color:#64748b' ?>;">
                    <?= ucfirst($sh['statut']) ?>
                  </span>
                </td>
                <td style="padding:0.65rem 0.25rem; font-size:0.78rem; color:#64748b;">
                  <?= date('d/m/y', strtotime($sh['date_debut'])) ?> → <?= date('d/m/y', strtotime($sh['date_fin'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="color:#64748b; font-size:0.88rem; margin:0;">Aucun historique d'abonnement.</p>
    <?php endif; ?>
  </div>

  <!-- Table Historique des Règlements -->
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
    <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">
      Historique des Règlements
    </h3>

    <?php if (!empty($paymentsHistory)): ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.85rem;">
          <thead>
            <tr style="border-bottom:2px solid #f1f5f9; color:#64748b; font-size:0.75rem; text-transform:uppercase;">
              <th style="padding:0.5rem 0.25rem;">Date</th>
              <th style="padding:0.5rem 0.25rem;">Montant</th>
              <th style="padding:0.5rem 0.25rem;">Statut</th>
              <th style="padding:0.5rem 0.25rem;">Référence</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($paymentsHistory as $ph): ?>
              <tr style="border-bottom:1px solid #f8fafc;">
                <td style="padding:0.65rem 0.25rem; color:#64748b;">
                  <?= date('d/m/Y H:i', strtotime($ph['date_paiement'])) ?>
                </td>
                <td style="padding:0.65rem 0.25rem; font-weight:800; color:#0f172a;">
                  <?= number_format((float)$ph['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($ph['devise']) ?>
                </td>
                <td style="padding:0.65rem 0.25rem;">
                  <span style="font-size:0.72rem; font-weight:700; padding:0.15rem 0.45rem; border-radius:4px; background:<?= ($ph['statut'] === 'reussi') ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309' ?>;">
                    <?= ucfirst($ph['statut']) ?>
                  </span>
                </td>
                <td style="padding:0.65rem 0.25rem; font-family:monospace; font-size:0.75rem; color:#64748b;">
                  <?= htmlspecialchars($ph['reference_externe'] ?? '-') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p style="color:#64748b; font-size:0.88rem; margin:0;">Aucun règlement enregistré.</p>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
