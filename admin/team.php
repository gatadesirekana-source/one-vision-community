<?php
/**
 * ONE VISION COMMUNITY — ÉQUIPE D'ADMINISTRATION & DÉLÉGATION (RÉSERVÉE AU PROPRIÉTAIRE)
 * 
 * Exigences strictes (Section 10) :
 * - Page réservée exclusivement au Propriétaire (contrôle serveur strict require_owner)
 * - Nommer n'importe quel utilisateur "administrateur délégué" (par email ou liste) et lui retirer ce rôle
 * - Choisir exactement les permissions accordées avec des modèles prêts à l'emploi :
 *   • "Gestionnaire complet" (tout sauf la gestion de l'équipe d'administration)
 *   • "Modérateur" (modération du contenu et suspension de comptes)
 *   • "Support abonnements" (consultation et gestion des abonnements)
 * - Protection absolue du compte propriétaire (ne peut être ni supprimé, ni suspendu, ni rétrogradé)
 * - Fonction sécurisée de transfert de propriété avec confirmation par mot de passe
 * - Enregistrement automatique de toutes les actions dans le journal d'actions
 */

$pageTitle = "Équipe & Délégation — Administration One Vision";
require_once __DIR__ . '/header.php';
require_owner('index.php'); // Seul le Propriétaire peut accéder à cette page

$db = get_db();
$ownerId = (int)$currentUser['id'];

// Permissions configurables pour les délégués (gerer_administrateurs reste exclusif au propriétaire)
$stmtPerms = $db->query("SELECT * FROM permissions WHERE code != 'gerer_administrateurs' ORDER BY id ASC");
$allDelegablePerms = $stmtPerms->fetchAll();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', "Session expirée. Veuillez actualiser.");
        header('Location: team.php');
        exit;
    }

    $action = trim($_POST['action'] ?? '');

    // 1. NOMMER UN NOUVEL ADMINISTRATEUR DÉLÉGUÉ
    if ($action === 'nommer_delegue') {
        $userIdentifier = trim(strtolower($_POST['user_identifier'] ?? ''));
        $template = trim($_POST['template'] ?? 'gestionnaire_complet');
        $selectedPerms = $_POST['perms'] ?? [];

        // Rechercher par email ou ID
        $stmtSearch = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR id = ?");
        $stmtSearch->execute([$userIdentifier, (int)$userIdentifier]);
        $target = $stmtSearch->fetch();

        if (!$target) {
            set_flash('error', "Utilisateur introuvable avec l'identifiant ou l'email « {$userIdentifier} ».");
        } elseif (is_owner($target)) {
            set_flash('error', "Cet utilisateur est déjà le Propriétaire.");
        } else {
            $targetId = (int)$target['id'];
            $db->prepare("UPDATE users SET role = 'admin_delegue' WHERE id = ?")->execute([$targetId]);

            // Appliquer le modèle ou les permissions sélectionnées
            if (!empty($template) && in_array($template, ['gestionnaire_complet', 'moderateur', 'support_abonnements'], true)) {
                apply_permission_template($targetId, $template, $ownerId);
            } elseif (!empty($selectedPerms)) {
                set_user_permissions($targetId, $selectedPerms, $ownerId);
            } else {
                apply_permission_template($targetId, 'gestionnaire_complet', $ownerId);
            }

            log_admin_action($ownerId, 'nomination_administrateur_delegue', "Utilisateur #{$targetId} ({$target['email']})", "Nommé administrateur délégué.");
            set_flash('success', "{$target['full_name']} ({$target['email']}) a été nommé Administrateur Délégué avec succès.");
        }
        header('Location: team.php');
        exit;
    }

    // 2. METTRE À JOUR LES PERMISSIONS D'UN DÉLÉGUÉ
    elseif ($action === 'modifier_permissions') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $perms = $_POST['perms'] ?? [];

        $stmtTarget = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtTarget->execute([$targetId]);
        $target = $stmtTarget->fetch();

        if ($target && $target['role'] === 'admin_delegue') {
            set_user_permissions($targetId, $perms, $ownerId);
            set_flash('success', "Les permissions de {$target['full_name']} ont été mises à jour.");
        }
        header('Location: team.php');
        exit;
    }

    // 3. RETIRER LE RÔLE D'ADMINISTRATEUR DÉLÉGUÉ
    elseif ($action === 'retirer_delegue') {
        $targetId = (int)($_POST['target_id'] ?? 0);

        $stmtTarget = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtTarget->execute([$targetId]);
        $target = $stmtTarget->fetch();

        if ($target && $target['role'] === 'admin_delegue') {
            // Rétrograder en membre
            $db->prepare("UPDATE users SET role = 'membre' WHERE id = ?")->execute([$targetId]);
            $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$targetId]);

            log_admin_action($ownerId, 'revocation_administrateur_delegue', "Utilisateur #{$targetId} ({$target['email']})", "Rôle révoqué, retour à membre.");
            set_flash('info', "Le rôle d'administrateur délégué a été retiré à {$target['full_name']}.");
        }
        header('Location: team.php');
        exit;
    }

    // 4. TRANSFERT SÉCURISÉ DE PROPRIÉTÉ DU SITE (AVEC CONFIRMATION MOT DE PASSE)
    elseif ($action === 'transferer_propriete') {
        $newOwnerId = (int)($_POST['new_owner_id'] ?? 0);
        $passwordConfirm = $_POST['current_owner_password'] ?? '';

        $transferResult = transfer_site_ownership($ownerId, $newOwnerId, $passwordConfirm);

        if ($transferResult['success']) {
            set_flash('success', $transferResult['message']);
            header('Location: ../dashboard.php');
            exit;
        } else {
            set_flash('error', $transferResult['error'] ?? "Échec du transfert.");
            header('Location: team.php');
            exit;
        }
    }
}

// Récupérer le compte propriétaire
$ownerUser = $db->query("SELECT * FROM users WHERE role = 'proprietaire' LIMIT 1")->fetch();

// Récupérer les administrateurs délégués actuels
$delegates = $db->query("SELECT * FROM users WHERE role = 'admin_delegue' ORDER BY id ASC")->fetchAll();

// Récupérer les autres utilisateurs pouvant être nommés
$eligibleUsers = $db->query("
    SELECT id, full_name, email, role 
    FROM users 
    WHERE role NOT IN ('proprietaire', 'admin_delegue') AND statut = 'actif'
    ORDER BY full_name ASC 
    LIMIT 100
")->fetchAll();
?>

<!-- Titre -->
<div style="margin-bottom: 2rem;">
  <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.35rem;">
    <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0;">
      Équipe d'Administration & Délégation
    </h1>
    <span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.75rem; padding:0.25rem 0.65rem; border-radius:6px;">
      Réservé au Propriétaire
    </span>
  </div>
  <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
    Gérez les personnes de confiance à qui vous déléguez l'animation et l'administration du site. Configurez précisément leurs droits.
  </p>
</div>

<!-- 1. COMPTE PROPRIÉTAIRE (PROTÉGÉ) -->
<div style="background:#ffffff; border:2px solid #f59e0b; border-radius:18px; padding:1.75rem 2rem; margin-bottom:2.5rem; box-shadow:0 8px 25px rgba(245,158,11,0.06);">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="display:flex; align-items:center; gap:1.25rem;">
      <div style="position:relative;">
        <img src="../<?= htmlspecialchars(ltrim($ownerUser['avatar'] ?? 'img/avatar-cyril.jpg', './')) ?>" alt="Propriétaire" style="width:58px; height:58px; border-radius:50%; object-fit:cover; border:2px solid #f59e0b;">
        <span style="position:absolute; bottom:-4px; right:-4px; font-size:1.1rem;">👑</span>
      </div>
      <div>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <h2 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin:0;">
            <?= htmlspecialchars($ownerUser['full_name'] ?? 'Cyril D.') ?>
          </h2>
          <span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.72rem; padding:0.18rem 0.55rem; border-radius:6px; text-transform:uppercase;">
            Propriétaire du site
          </span>
        </div>
        <div style="font-size:0.88rem; color:#64748b;">
          <?= htmlspecialchars($ownerUser['email'] ?? '') ?> • Super-administrateur
        </div>
      </div>
    </div>

    <div style="font-size:0.85rem; color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; padding:0.5rem 1rem; border-radius:10px; display:inline-flex; align-items:center; gap:0.4rem; font-weight:600;">
      <span>🔒</span> Compte protégé : ne peut pas être suspendu, supprimé ou rétrogradé
    </div>
  </div>
</div>

<!-- 2. FORMULAIRE : NOMMER UN NOUVEL ADMINISTRATEUR DÉLÉGUÉ -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; margin-bottom:2.5rem; box-shadow:0 6px 20px rgba(0,0,0,0.03);">
  <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
    Nommer un Administrateur Délégué
  </h2>
  <p style="color:#64748b; font-size:0.9rem; margin:0 0 1.5rem 0;">
    Choisissez un membre et appliquez-lui un modèle de permissions prêt à l'emploi.
  </p>

  <form method="POST" action="team.php" onsubmit="return confirm('Confirmez-vous la nomination de cet administrateur délégué ?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="nommer_delegue">

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
      <div>
        <label style="font-size:0.82rem; font-weight:700; color:#334155; display:block; margin-bottom:0.35rem;">
          Sélectionner l'utilisateur (par email ou nom)
        </label>
        <select name="user_identifier" class="form-input" style="width:100%; padding:0.65rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;" required>
          <option value="">-- Choisir un utilisateur --</option>
          <?php foreach ($eligibleUsers as $eu): ?>
            <option value="<?= $eu['id'] ?>">
              <?= htmlspecialchars($eu['full_name']) ?> (<?= htmlspecialchars($eu['email']) ?>) - <?= ucfirst($eu['role']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span style="font-size:0.75rem; color:#64748b; margin-top:0.25rem; display:block;">
          Ou saisissez directement l'email ci-dessous si non présent dans la liste restreinte :
        </span>
        <input type="email" name="user_identifier" placeholder="ex. collaborateur@onevision.fr" class="form-input" style="width:100%; padding:0.5rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px; margin-top:0.35rem;">
      </div>

      <div>
        <label style="font-size:0.82rem; font-weight:700; color:#334155; display:block; margin-bottom:0.35rem;">
          Modèle de permissions prêt à l'emploi (Section 10)
        </label>
        <select name="template" class="form-input" style="width:100%; padding:0.65rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;">
          <option value="gestionnaire_complet">Gestionnaire complet (Tout sauf équipe d'administration)</option>
          <option value="moderateur">Modérateur (Modération contenu et suspension comptes)</option>
          <option value="support_abonnements">Support abonnements (Consultation & gestion cotisations)</option>
        </select>
        <span style="font-size:0.75rem; color:#64748b; margin-top:0.25rem; display:block;">
          Vous pourrez personnaliser finement chaque case à cocher après la nomination.
        </span>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="font-weight:700; padding:0.75rem 1.5rem; border-radius:10px;">
      + Nommer comme Administrateur Délégué
    </button>
  </form>
</div>

<!-- 3. LISTE DES ADMINISTRATEURS DÉLÉGUÉS ET PERMISSIONS FINES -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; margin-bottom:2.5rem; box-shadow:0 6px 20px rgba(0,0,0,0.03);">
  <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
    Administrateurs Délégués Actifs
  </h2>
  <p style="color:#64748b; font-size:0.9rem; margin:0 0 1.5rem 0;">
    Cochez ou décochez les permissions autorisées pour chaque personne de confiance.
  </p>

  <?php if (!empty($delegates)): ?>
    <div style="display:flex; flex-direction:column; gap:1.75rem;">
      <?php foreach ($delegates as $del): ?>
        <?php
          $delId = (int)$del['id'];
          $delPerms = get_user_permissions($delId);
        ?>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1.5rem;">
          
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:1rem;">
            <div style="display:flex; align-items:center; gap:0.85rem;">
              <img src="../<?= htmlspecialchars(ltrim($del['avatar'] ?? 'img/avatar-maxime.jpg', './')) ?>" alt="" style="width:44px; height:44px; border-radius:50%; object-fit:cover;">
              <div>
                <strong style="color:#0f172a; font-size:1.05rem; display:block;"><?= htmlspecialchars($del['full_name']) ?></strong>
                <span style="color:#64748b; font-size:0.85rem;"><?= htmlspecialchars($del['email']) ?></span>
              </div>
            </div>

            <!-- Bouton pour retirer le rôle -->
            <form method="POST" action="team.php" onsubmit="return confirm('Confirmez-vous le retrait du rôle d\'administrateur délégué à <?= htmlspecialchars($del['full_name']) ?> ?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="retirer_delegue">
              <input type="hidden" name="target_id" value="<?= $delId ?>">
              <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5; font-size:0.82rem;">
                ✕ Retirer le rôle administrateur
              </button>
            </form>
          </div>

          <!-- Formulaire de permissions fines (Section 3) -->
          <form method="POST" action="team.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="modifier_permissions">
            <input type="hidden" name="target_id" value="<?= $delId ?>">

            <div style="font-size:0.82rem; font-weight:800; text-transform:uppercase; color:#475569; margin-bottom:0.75rem; letter-spacing:0.04em;">
              Permissions accordées :
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.85rem; margin-bottom:1.25rem;">
              <?php foreach ($allDelegablePerms as $perm): ?>
                <?php $hasIt = in_array($perm['code'], $delPerms, true); ?>
                <label style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:0.65rem 0.85rem; display:flex; align-items:flex-start; gap:0.5rem; cursor:pointer;">
                  <input type="checkbox" name="perms[]" value="<?= htmlspecialchars($perm['code']) ?>" <?= $hasIt ? 'checked' : '' ?> style="margin-top:0.2rem; width:16px; height:16px;">
                  <div>
                    <strong style="font-size:0.85rem; color:#0f172a; display:block;"><?= htmlspecialchars($perm['nom']) ?></strong>
                    <span style="font-size:0.75rem; color:#64748b; line-height:1.3; display:block;"><?= htmlspecialchars($perm['description']) ?></span>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>

            <div style="display:flex; justify-content:flex-end;">
              <button type="submit" class="btn btn-secondary btn-sm" style="font-weight:700;">
                Mettre à jour les permissions
              </button>
            </div>
          </form>

        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="text-align:center; padding:2rem; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1; color:#64748b;">
      Aucun administrateur délégué actuellement. Vous pouvez en nommer un ci-dessus.
    </div>
  <?php endif; ?>
</div>

<!-- 4. FONCTION SÉCURISÉE DE TRANSFERT DE PROPRIÉTÉ DU SITE (Section 10) -->
<div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:18px; padding:2rem; box-shadow:0 6px 20px rgba(0,0,0,0.02);">
  <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.5rem;">
    <span style="font-size:1.6rem;">⚠️</span>
    <h2 style="font-size:1.3rem; font-weight:800; color:#9a3412; margin:0;">
      Céder la Propriété du Site (Transfert de Propriétaire)
    </h2>
  </div>
  <p style="color:#7c2d12; font-size:0.92rem; line-height:1.55; margin:0 0 1.5rem 0;">
    Cette opération transfère de façon irréversible les droits de super-administrateur du site à un autre utilisateur actif. Votre compte actuel deviendra automatiquement administrateur délégué avec tous les droits de gestion.
    <strong>Une confirmation par votre mot de passe actuel est strictement obligatoire.</strong>
  </p>

  <form method="POST" action="team.php" onsubmit="return confirm('ATTENTION : Êtes-vous ABSOLUMENT CERTAIN de vouloir transférer la propriété complète du site ?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="transferer_propriete">

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
      <div>
        <label style="font-size:0.82rem; font-weight:700; color:#7c2d12; display:block; margin-bottom:0.35rem;">
          Nouveau propriétaire désigné
        </label>
        <select name="new_owner_id" class="form-input" style="width:100%; padding:0.65rem 0.85rem; border:1px solid #fed7aa; border-radius:8px; background:#fff;" required>
          <option value="">-- Choisir le nouveau propriétaire --</option>
          <?php foreach ($delegates as $d): ?>
            <option value="<?= $d['id'] ?>">⭐ <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['email']) ?>)</option>
          <?php endforeach; ?>
          <?php foreach ($eligibleUsers as $eu): ?>
            <option value="<?= $eu['id'] ?>"><?= htmlspecialchars($eu['full_name']) ?> (<?= htmlspecialchars($eu['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label style="font-size:0.82rem; font-weight:700; color:#7c2d12; display:block; margin-bottom:0.35rem;">
          Votre mot de passe actuel (Sécurité obligatoire)
        </label>
        <input type="password" name="current_owner_password" class="form-input" style="width:100%; padding:0.65rem 0.85rem; border:1px solid #fed7aa; border-radius:8px; background:#fff;" placeholder="Votre mot de passe actuel" required autocomplete="current-password">
      </div>
    </div>

    <button type="submit" class="btn btn-outline" style="background:#dc2626; color:#ffffff; border:none; font-weight:800; padding:0.75rem 1.6rem; border-radius:10px; box-shadow:0 4px 15px rgba(220,38,38,0.25);">
      Confirmer le Transfert Définitif de Propriété
    </button>
  </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
