<?php
/**
 * ONE VISION COMMUNITY — GESTION DYNAMIQUE DES FORMULES & TARIFS (ADMIN)
 * 
 * Permet à l'administrateur de :
 * - Modifier les prix mensuels et annuels sans toucher au code (Section 1)
 * - Mettre à jour la liste des avantages (affichés en temps réel sur choisir-abonnement et abonnements)
 * - Activer ou désactiver une formule
 * - Configurer l'identifiant d'offre externe optionnel (sans mention de prestataire)
 * - Enregistrer chaque modification dans le journal d'actions (Section 10)
 */

$pageTitle = "Gestion des Formules — Administration One Vision";
require_once __DIR__ . '/header.php';
require_permission('modifier_tarifs', 'index.php');

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', "Session expirée. Veuillez actualiser.");
        header('Location: plans.php');
        exit;
    }

    $planId = (int)($_POST['plan_id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $prixMensuel = (float)($_POST['prix_mensuel'] ?? 0);
    $prixAnnuel = (float)($_POST['prix_annuel'] ?? 0);
    $actif = isset($_POST['actif']) ? 1 : 0;
    $identifiantExterne = trim($_POST['identifiant_offre_externe'] ?? '');
    $avantagesRaw = trim($_POST['avantages'] ?? '');

    // Conversion des avantages ligne par ligne en JSON
    $avantagesList = array_values(array_filter(array_map('trim', explode("\n", $avantagesRaw))));
    $avantagesJson = json_encode($avantagesList, JSON_UNESCAPED_UNICODE);

    if (empty($nom) || $prixMensuel <= 0 || $prixAnnuel <= 0) {
        set_flash('error', "Veuillez renseigner un nom valide et des tarifs mensuel et annuel supérieurs à zéro.");
    } else {
        $stmtPlan = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $stmtPlan->execute([$planId]);
        $existingPlan = $stmtPlan->fetch();

        if ($existingPlan) {
            $stmtUp = $db->prepare("
                UPDATE plans 
                SET nom = ?, prix_mensuel = ?, prix_annuel = ?, avantages = ?, actif = ?, identifiant_offre_externe = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmtUp->execute([$nom, $prixMensuel, $prixAnnuel, $avantagesJson, $actif, $identifiantExterne ?: null, $planId]);

            // Enregistrer dans le journal d'audit
            log_admin_action(
                $currentUser['id'],
                'modification_formule',
                "Formule: {$existingPlan['code']}",
                "Prix mensuel: {$prixMensuel}€, Annuel: {$prixAnnuel}€, Actif: {$actif}"
            );

            set_flash('success', "La formule « {$nom} » a été mise à jour avec succès. Les nouveaux prix et avantages sont effectifs immédiatement.");
        }
    }
    header('Location: plans.php');
    exit;
}

// Récupérer toutes les formules
$plans = $db->query("SELECT * FROM plans ORDER BY ordre_affichage ASC")->fetchAll();
?>

<!-- Titre -->
<div style="margin-bottom: 2rem;">
  <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">
    Gestion des Formules & Tarifs
  </h1>
  <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
    Ajustez les prix, la liste des avantages et le statut d'activation des formules sans modifier le code source.
  </p>
</div>

<!-- Grille des formulaires de modification -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(460px, 1fr)); gap:2rem;">
  <?php foreach ($plans as $p): ?>
    <?php
      $avList = json_decode($p['avantages'], true) ?: array_filter(array_map('trim', explode("\n", $p['avantages'])));
      $avText = implode("\n", $avList);
    ?>
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; box-shadow:0 6px 20px rgba(0,0,0,0.03);">
      
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid #f1f5f9; padding-bottom:1rem;">
        <div>
          <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#64748b;">
            Code : <code><?= htmlspecialchars($p['code']) ?></code>
          </span>
          <h2 style="font-size:1.4rem; font-weight:800; color:#0f172a; margin:0.2rem 0 0 0;">
            <?= htmlspecialchars($p['nom']) ?>
          </h2>
        </div>
        <div>
          <span style="font-size:0.75rem; font-weight:700; padding:0.2rem 0.6rem; border-radius:20px; background:<?= $p['actif'] ? '#dcfce7; color:#15803d;' : '#fee2e2; color:#991b1b;' ?>">
            <?= $p['actif'] ? '● Formule Active' : '✕ Formule Désactivée' ?>
          </span>
        </div>
      </div>

      <form method="POST" action="plans.php">
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">

        <div style="margin-bottom:1.25rem;">
          <label style="font-size:0.82rem; font-weight:700; color:#475569; display:block; margin-bottom:0.35rem;">
            Nom commercial de la formule
          </label>
          <input type="text" name="nom" class="form-input" style="width:100%; padding:0.6rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;" value="<?= htmlspecialchars($p['nom']) ?>" required>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.25rem;">
          <div>
            <label style="font-size:0.82rem; font-weight:700; color:#475569; display:block; margin-bottom:0.35rem;">
              Prix Mensuel (€)
            </label>
            <input type="number" step="0.5" name="prix_mensuel" class="form-input" style="width:100%; padding:0.6rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;" value="<?= htmlspecialchars($p['prix_mensuel']) ?>" required>
          </div>
          <div>
            <label style="font-size:0.82rem; font-weight:700; color:#475569; display:block; margin-bottom:0.35rem;">
              Prix Annuel (€)
            </label>
            <input type="number" step="0.5" name="prix_annuel" class="form-input" style="width:100%; padding:0.6rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;" value="<?= htmlspecialchars($p['prix_annuel']) ?>" required>
          </div>
        </div>

        <div style="margin-bottom:1.25rem;">
          <label style="font-size:0.82rem; font-weight:700; color:#475569; display:block; margin-bottom:0.35rem;">
            Liste des avantages (un par ligne)
          </label>
          <textarea name="avantages" class="form-input" rows="7" style="width:100%; padding:0.65rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.88rem; line-height:1.5;"><?= htmlspecialchars($avText) ?></textarea>
          <span style="font-size:0.75rem; color:#64748b; display:block; margin-top:0.25rem;">
            Ces avantages s'afficheront directement sur les pages de choix d'abonnement et l'espace animateur.
          </span>
        </div>

        <div style="margin-bottom:1.5rem;">
          <label style="font-size:0.82rem; font-weight:700; color:#475569; display:block; margin-bottom:0.35rem;">
            Identifiant d'offre externe (optionnel, pour futur prestataire)
          </label>
          <input type="text" name="identifiant_offre_externe" class="form-input" style="width:100%; padding:0.6rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;" value="<?= htmlspecialchars($p['identifiant_offre_externe'] ?? '') ?>" placeholder="ex: price_plan_member_standard">
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:1.25rem;">
          <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.88rem; color:#334155; cursor:pointer;">
            <input type="checkbox" name="actif" value="1" <?= $p['actif'] ? 'checked' : '' ?> style="width:18px; height:18px;">
            <span>Formule active et visible</span>
          </label>

          <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700; padding:0.6rem 1.25rem;">
            Enregistrer les modifications
          </button>
        </div>
      </form>

    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
