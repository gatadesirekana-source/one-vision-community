<?php
/**
 * ONE VISION COMMUNITY — LISTE DES UTILISATEURS & GESTION (ADMIN)
 * 
 * Fonctionnalités exigées (Section 10) :
 * - Colonnes : nom, email, rôle, formule, périodicité, statut d'abonnement, dates début/fin, inscription
 * - Recherche par nom ou email
 * - Filtres : abonné / non abonné, Membre / Animateur, mensuel / annuel, statut
 * - Pagination et export CSV de la liste filtrée
 */

$pageTitle = "Gestion des Utilisateurs — Administration One Vision";
require_once __DIR__ . '/header.php';
require_permission('voir_utilisateurs', 'index.php');

$db = get_db();
$nowUtc = gmdate('Y-m-d H:i:s');

// Paramètres de filtrage & recherche
$search = trim($_GET['q'] ?? '');
$filterAbonne = trim($_GET['abonne'] ?? 'all');
$filterFormule = trim($_GET['formule'] ?? 'all');
$filterPeriod = trim($_GET['periodicite'] ?? 'all');
$filterStatut = trim($_GET['statut'] ?? 'all');

// Construction de la requête SQL avec jointure sur le dernier abonnement
$whereClauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(LOWER(u.full_name) LIKE ? OR LOWER(u.email) LIKE ?)";
    $params[] = '%' . strtolower($search) . '%';
    $params[] = '%' . strtolower($search) . '%';
}

if ($filterAbonne === 'abonne') {
    $whereClauses[] = "(s.statut = 'actif' AND s.date_fin > datetime('now'))";
} elseif ($filterAbonne === 'non_abonne') {
    $whereClauses[] = "(s.id IS NULL OR s.statut != 'actif' OR s.date_fin <= datetime('now'))";
}

if ($filterFormule === 'membre') {
    $whereClauses[] = "(p.code = 'membre')";
} elseif ($filterFormule === 'animateur') {
    $whereClauses[] = "(p.code = 'animateur')";
}

if ($filterPeriod === 'mensuel') {
    $whereClauses[] = "(s.periodicite = 'mensuel')";
} elseif ($filterPeriod === 'annuel') {
    $whereClauses[] = "(s.periodicite = 'annuel')";
}

if ($filterStatut === 'actif') {
    $whereClauses[] = "(s.statut = 'actif' AND s.date_fin > datetime('now'))";
} elseif ($filterStatut === 'expire') {
    $whereClauses[] = "(s.statut = 'expire' OR (s.statut = 'actif' AND s.date_fin <= datetime('now')))";
} elseif ($filterStatut === 'annule') {
    $whereClauses[] = "(s.statut = 'annule' OR s.renouvellement_auto = 0)";
} elseif ($filterStatut === 'aucun') {
    $whereClauses[] = "(s.id IS NULL)";
}

$whereSql = implode(' AND ', $whereClauses);

// Sous-requête pour n'obtenir que le dernier abonnement par utilisateur
$baseQuery = "
    FROM users u
    LEFT JOIN (
        SELECT s1.* 
        FROM subscriptions s1
        JOIN (
            SELECT user_id, MAX(id) as max_id 
            FROM subscriptions 
            GROUP BY user_id
        ) s2 ON s1.id = s2.max_id
    ) s ON u.id = s.user_id
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE {$whereSql}
";

// Export CSV si demandé
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sqlExport = "
        SELECT 
            u.id, u.full_name, u.email, u.role, u.statut as statut_compte,
            p.nom as formule, s.periodicite,
            CASE 
                WHEN s.id IS NULL THEN 'aucun'
                WHEN s.statut = 'actif' AND s.date_fin > datetime('now') THEN 'actif'
                WHEN s.statut = 'expire' OR (s.statut = 'actif' AND s.date_fin <= datetime('now')) THEN 'expiré'
                WHEN s.statut = 'annule' THEN 'annulé'
                ELSE s.statut
            END as statut_abonnement,
            s.date_debut, s.date_fin, u.created_at as date_inscription
        {$baseQuery}
        ORDER BY u.id DESC
    ";
    $stmtExp = $db->prepare($sqlExport);
    $stmtExp->execute($params);
    $exportRows = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="utilisateurs_one_vision_' . date('Ymd_His') . '.csv"');
    
    $out = fopen('php://output', 'w');
    // BOM UTF-8 pour Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Entêtes
    fputcsv($out, ['ID', 'Nom', 'Email', 'Rôle', 'Statut Compte', 'Formule', 'Périodicité', 'Statut Abonnement', 'Date Début', 'Date Fin', 'Date Inscription'], ';');
    
    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['full_name'],
            $row['email'],
            $row['role'],
            $row['statut_compte'],
            $row['formule'] ?? 'Aucune',
            $row['periodicite'] ?? '-',
            $row['statut_abonnement'],
            $row['date_debut'] ? date('d/m/Y', strtotime($row['date_debut'])) : '-',
            $row['date_fin'] ? date('d/m/Y', strtotime($row['date_fin'])) : '-',
            $row['date_inscription'] ? date('d/m/Y H:i', strtotime($row['date_inscription'])) : '-'
        ], ';');
    }
    fclose($out);
    exit;
}

// Pagination
$countStmt = $db->prepare("SELECT COUNT(*) {$baseQuery}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$limit = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalRows / $limit));
$offset = ($page - 1) * $limit;

// Requête de récupération avec pagination
$sqlSelect = "
    SELECT 
        u.id, u.full_name, u.email, u.avatar, u.role, u.statut as statut_compte,
        p.nom as formule_nom, p.code as formule_code,
        s.id as subscription_id, s.periodicite, s.prix_paye, s.statut as sub_statut,
        s.date_debut, s.date_fin, s.renouvellement_auto,
        u.created_at as date_inscription
    {$baseQuery}
    ORDER BY u.id DESC
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $db->prepare($sqlSelect);
$stmt->execute($params);
$usersList = $stmt->fetchAll();
?>

<!-- Titre et Export CSV -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem 0;">
      Liste des Utilisateurs
    </h1>
    <span style="font-size:0.9rem; color:#64748b;">
      <?= number_format($totalRows, 0, ',', ' ') ?> utilisateur<?= ($totalRows > 1) ? 's trouvés' : ' trouvé' ?>
    </span>
  </div>

  <div>
    <!-- Bouton Export CSV -->
    <?php
      $csvParams = $_GET;
      $csvParams['export'] = 'csv';
      $csvUrl = 'users.php?' . http_build_query($csvParams);
    ?>
    <a href="<?= htmlspecialchars($csvUrl) ?>" class="btn btn-secondary btn-sm" style="font-weight:700; display:inline-flex; align-items:center; gap:0.45rem;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
      <span>Exporter en CSV</span>
    </a>
  </div>
</div>

<!-- BARRE DE RECHERCHE ET FILTRES (SECTION 10) -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem; margin-bottom:1.75rem; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
  <form method="GET" action="users.php" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; align-items:end;">
    
    <!-- Recherche texte -->
    <div style="grid-column: span 2;">
      <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Recherche (nom ou email)</label>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ex: Cyril, alexandre@..." class="form-input" style="width:100%; padding:0.55rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;">
    </div>

    <!-- Filtre Abonné / Non abonné -->
    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Adhésion</label>
      <select name="abonne" class="form-input" style="width:100%; padding:0.55rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;">
        <option value="all" <?= ($filterAbonne === 'all') ? 'selected' : '' ?>>Tous</option>
        <option value="abonne" <?= ($filterAbonne === 'abonne') ? 'selected' : '' ?>>Abonné actif</option>
        <option value="non_abonne" <?= ($filterAbonne === 'non_abonne') ? 'selected' : '' ?>>Non abonné</option>
      </select>
    </div>

    <!-- Filtre Membre / Animateur -->
    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Formule</label>
      <select name="formule" class="form-input" style="width:100%; padding:0.55rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;">
        <option value="all" <?= ($filterFormule === 'all') ? 'selected' : '' ?>>Toutes</option>
        <option value="membre" <?= ($filterFormule === 'membre') ? 'selected' : '' ?>>Membre</option>
        <option value="animateur" <?= ($filterFormule === 'animateur') ? 'selected' : '' ?>>Animateur</option>
      </select>
    </div>

    <!-- Filtre Mensuel / Annuel -->
    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Périodicité</label>
      <select name="periodicite" class="form-input" style="width:100%; padding:0.55rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;">
        <option value="all" <?= ($filterPeriod === 'all') ? 'selected' : '' ?>>Toutes</option>
        <option value="mensuel" <?= ($filterPeriod === 'mensuel') ? 'selected' : '' ?>>Mensuel</option>
        <option value="annuel" <?= ($filterPeriod === 'annuel') ? 'selected' : '' ?>>Annuel</option>
      </select>
    </div>

    <!-- Filtre Statut -->
    <div>
      <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.3rem;">Statut abonnement</label>
      <select name="statut" class="form-input" style="width:100%; padding:0.55rem 0.85rem; border:1px solid #cbd5e1; border-radius:8px;">
        <option value="all" <?= ($filterStatut === 'all') ? 'selected' : '' ?>>Tous statuts</option>
        <option value="actif" <?= ($filterStatut === 'actif') ? 'selected' : '' ?>>Actif</option>
        <option value="expire" <?= ($filterStatut === 'expire') ? 'selected' : '' ?>>Expiré</option>
        <option value="annule" <?= ($filterStatut === 'annule') ? 'selected' : '' ?>>Annulé / Résilié</option>
        <option value="aucun" <?= ($filterStatut === 'aucun') ? 'selected' : '' ?>>Aucun</option>
      </select>
    </div>

    <!-- Boutons Appliquer / Réinitialiser -->
    <div style="display:flex; gap:0.5rem;">
      <button type="submit" class="btn btn-primary btn-sm" style="flex:1; padding:0.6rem; font-weight:700;">Filtrer</button>
      <a href="users.php" class="btn btn-secondary btn-sm" style="padding:0.6rem;">Réinit.</a>
    </div>

  </form>
</div>

<!-- TABLEAU DES UTILISATEURS (SECTION 10) -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.03); margin-bottom:1.5rem;">
  <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.88rem;">
      <thead>
        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
          <th style="padding:0.9rem 1rem;">Utilisateur</th>
          <th style="padding:0.9rem 0.75rem;">Rôle</th>
          <th style="padding:0.9rem 0.75rem;">Formule</th>
          <th style="padding:0.9rem 0.75rem;">Périodicité</th>
          <th style="padding:0.9rem 0.75rem;">Statut Abo</th>
          <th style="padding:0.9rem 0.75rem;">Début / Fin</th>
          <th style="padding:0.9rem 0.75rem;">Inscription</th>
          <th style="padding:0.9rem 1rem; text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($usersList)): ?>
          <?php foreach ($usersList as $u): ?>
            <?php
              $isSubActive = ($u['sub_statut'] === 'actif' && !empty($u['date_fin']) && $u['date_fin'] > $nowUtc);
              $isSubExpired = ($u['sub_statut'] === 'expire' || ($u['sub_statut'] === 'actif' && !empty($u['date_fin']) && $u['date_fin'] <= $nowUtc));
              $isSubCancelled = ($u['sub_statut'] === 'annule' || (int)($u['renouvellement_auto'] ?? 1) === 0);
              
              if ($isSubActive) {
                  $statusBadge = '<span style="background:#dcfce7; color:#15803d; font-size:0.75rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">Actif</span>';
                  if ($isSubCancelled) {
                      $statusBadge .= ' <span style="background:#fee2e2; color:#991b1b; font-size:0.7rem; font-weight:600; padding:0.15rem 0.4rem; border-radius:4px;">Fin programmée</span>';
                  }
              } elseif ($isSubExpired) {
                  $statusBadge = '<span style="background:#fef3c7; color:#b45309; font-size:0.75rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">Expiré</span>';
              } elseif (!empty($u['sub_statut']) && $u['sub_statut'] === 'annule') {
                  $statusBadge = '<span style="background:#fee2e2; color:#991b1b; font-size:0.75rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">Annulé</span>';
              } else {
                  $statusBadge = '<span style="background:#f1f5f9; color:#64748b; font-size:0.75rem; font-weight:600; padding:0.2rem 0.55rem; border-radius:6px;">Aucun</span>';
              }

              $roleBadge = match($u['role']) {
                  'proprietaire' => '<span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.75rem; padding:0.2rem 0.55rem; border-radius:6px;">👑 Propriétaire</span>',
                  'admin_delegue' => '<span style="background:#e0f2fe; color:#0369a1; font-weight:700; font-size:0.75rem; padding:0.2rem 0.55rem; border-radius:6px;">🛡️ Délégué</span>',
                  'animateur' => '<span style="background:#fff7ed; color:#ea580c; font-weight:700; font-size:0.75rem; padding:0.2rem 0.55rem; border-radius:6px;">🎙️ Animateur</span>',
                  default => '<span style="background:#f1f5f9; color:#475569; font-weight:600; font-size:0.75rem; padding:0.2rem 0.55rem; border-radius:6px;">Membre</span>'
              };
            ?>
            <tr style="border-bottom:1px solid #f1f5f9; <?= ($u['statut_compte'] === 'suspendu') ? 'background:#fff1f2;' : '' ?>">
              <!-- Utilisateur -->
              <td style="padding:0.9rem 1rem;">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                  <img src="../<?= htmlspecialchars(ltrim($u['avatar'] ?? 'img/avatar-maxime.jpg', './')) ?>" alt="" style="width:34px; height:34px; border-radius:50%; object-fit:cover;">
                  <div>
                    <strong style="color:#0f172a; display:block; font-size:0.9rem;">
                      <?= htmlspecialchars($u['full_name']) ?>
                      <?php if ($u['statut_compte'] === 'suspendu'): ?>
                        <span style="background:#dc2626; color:#fff; font-size:0.65rem; padding:0.15rem 0.35rem; border-radius:4px; margin-left:0.3rem;">Suspendu</span>
                      <?php endif; ?>
                    </strong>
                    <span style="font-size:0.8rem; color:#64748b;"><?= htmlspecialchars($u['email']) ?></span>
                  </div>
                </div>
              </td>

              <!-- Rôle -->
              <td style="padding:0.9rem 0.75rem;">
                <?= $roleBadge ?>
              </td>

              <!-- Formule -->
              <td style="padding:0.9rem 0.75rem;">
                <?php if (!empty($u['formule_nom'])): ?>
                  <strong style="color:<?= ($u['formule_code'] === 'animateur') ? '#ea580c' : '#2563eb' ?>;">
                    <?= htmlspecialchars($u['formule_nom']) ?>
                  </strong>
                <?php else: ?>
                  <span style="color:#94a3b8;">-</span>
                <?php endif; ?>
              </td>

              <!-- Périodicité -->
              <td style="padding:0.9rem 0.75rem; color:#475569;">
                <?= !empty($u['periodicite']) ? ucfirst($u['periodicite']) : '-' ?>
              </td>

              <!-- Statut Abonnement -->
              <td style="padding:0.9rem 0.75rem;">
                <?= $statusBadge ?>
              </td>

              <!-- Dates début et fin -->
              <td style="padding:0.9rem 0.75rem; font-size:0.8rem; color:#64748b;">
                <?php if (!empty($u['date_debut']) && !empty($u['date_fin'])): ?>
                  <?= date('d/m/Y', strtotime($u['date_debut'])) ?> → <strong><?= date('d/m/Y', strtotime($u['date_fin'])) ?></strong>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>

              <!-- Date inscription -->
              <td style="padding:0.9rem 0.75rem; font-size:0.8rem; color:#64748b;">
                <?= date('d/m/Y', strtotime($u['date_inscription'])) ?>
              </td>

              <!-- Action Fiche -->
              <td style="padding:0.9rem 1rem; text-align:right;">
                <a href="user_detail.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" style="font-size:0.8rem; padding:0.35rem 0.75rem; font-weight:700;">
                  Fiche →
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" style="padding:2.5rem; text-align:center; color:#64748b;">
              Aucun utilisateur ne correspond à ces critères de recherche.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
  <div style="display:flex; justify-content:center; gap:0.5rem; align-items:center;">
    <?php if ($page > 1): ?>
      <?php
        $prevParams = $_GET;
        $prevParams['page'] = $page - 1;
      ?>
      <a href="users.php?<?= http_build_query($prevParams) ?>" class="btn btn-secondary btn-sm">« Précédent</a>
    <?php endif; ?>

    <span style="font-size:0.88rem; color:#64748b; padding:0 0.5rem;">
      Page <strong><?= $page ?></strong> sur <strong><?= $totalPages ?></strong>
    </span>

    <?php if ($page < $totalPages): ?>
      <?php
        $nextParams = $_GET;
        $nextParams['page'] = $page + 1;
      ?>
      <a href="users.php?<?= http_build_query($nextParams) ?>" class="btn btn-secondary btn-sm">Suivant »</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
