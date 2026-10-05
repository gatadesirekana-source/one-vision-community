<?php
/**
 * ONE VISION COMMUNITY — JOURNAL D'AUDIT DES ACTIONS ADMINISTRATIVES
 * 
 * Trace chronologique de toutes les actions sensibles effectuées par le propriétaire
 * et les administrateurs délégués (Section 10).
 */

$pageTitle = "Journal d'Actions — Administration One Vision";
require_once __DIR__ . '/header.php';

if (!$isOwner && !user_has_permission($currentUser, 'voir_utilisateurs')) {
    set_flash('error', "Accès refusé au journal d'audit.");
    header('Location: index.php');
    exit;
}

$db = get_db();

$search = trim($_GET['q'] ?? '');
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where = "(LOWER(j.action) LIKE ? OR LOWER(j.cible) LIKE ? OR LOWER(j.details) LIKE ? OR LOWER(u.full_name) LIKE ?)";
    $q = '%' . strtolower($search) . '%';
    $params = [$q, $q, $q, $q];
}

$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM journal_actions j
    LEFT JOIN users u ON j.admin_id = u.id
    WHERE {$where}
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$limit = 30;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalRows / $limit));
$offset = ($page - 1) * $limit;

$stmtLogs = $db->prepare("
    SELECT j.*, u.full_name as admin_name, u.email as admin_email, u.avatar as admin_avatar, u.role as admin_role
    FROM journal_actions j
    LEFT JOIN users u ON j.admin_id = u.id
    WHERE {$where}
    ORDER BY j.id DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stmtLogs->execute($params);
$logs = $stmtLogs->fetchAll();
?>

<!-- Titre -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:2rem;">
  <div>
    <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem 0;">
      Journal d'Actions Administratives
    </h1>
    <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
      Traçabilité complète des modifications de formules, rôles, permissions et statuts de comptes.
    </p>
  </div>

  <form method="GET" action="logs.php" style="display:flex; gap:0.5rem;">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher une action, cible..." class="form-input" style="padding:0.55rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px; width:240px;">
    <button type="submit" class="btn btn-secondary btn-sm" style="font-weight:700;">Filtrer</button>
  </form>
</div>

<!-- Tableau du journal -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.03); margin-bottom:1.5rem;">
  <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.88rem;">
      <thead>
        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
          <th style="padding:0.85rem 1rem;">Date & Heure</th>
          <th style="padding:0.85rem 0.75rem;">Administrateur</th>
          <th style="padding:0.85rem 0.75rem;">Action</th>
          <th style="padding:0.85rem 0.75rem;">Cible</th>
          <th style="padding:0.85rem 1rem;">Détails</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($logs)): ?>
          <?php foreach ($logs as $l): ?>
            <tr style="border-bottom:1px solid #f1f5f9;">
              <td style="padding:0.85rem 1rem; color:#64748b; font-size:0.82rem; white-space:nowrap;">
                <?= date('d/m/Y H:i:s', strtotime($l['date'])) ?>
              </td>
              <td style="padding:0.85rem 0.75rem;">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                  <img src="../<?= htmlspecialchars(ltrim($l['admin_avatar'] ?? 'img/avatar-cyril.jpg', './')) ?>" alt="" style="width:26px; height:26px; border-radius:50%; object-fit:cover;">
                  <div>
                    <strong style="color:#0f172a; font-size:0.85rem; display:block;"><?= htmlspecialchars($l['admin_name'] ?? 'Admin #' . $l['admin_id']) ?></strong>
                    <span style="font-size:0.75rem; color:#64748b;"><?= htmlspecialchars($l['admin_role'] ?? '') ?></span>
                  </div>
                </div>
              </td>
              <td style="padding:0.85rem 0.75rem;">
                <span style="background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:0.78rem; padding:0.2rem 0.55rem; border-radius:6px; font-family:monospace;">
                  <?= htmlspecialchars($l['action']) ?>
                </span>
              </td>
              <td style="padding:0.85rem 0.75rem; color:#334155; font-weight:600;">
                <?= htmlspecialchars($l['cible']) ?>
              </td>
              <td style="padding:0.85rem 1rem; color:#475569; font-size:0.82rem;">
                <?= htmlspecialchars($l['details']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="padding:2.5rem; text-align:center; color:#64748b;">
              Aucune action enregistrée dans le journal.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <div style="display:flex; justify-content:center; gap:0.5rem; align-items:center;">
    <?php if ($page > 1): ?>
      <a href="logs.php?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">« Précédent</a>
    <?php endif; ?>
    <span style="font-size:0.88rem; color:#64748b;">Page <?= $page ?> sur <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
      <a href="logs.php?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">Suivant »</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
