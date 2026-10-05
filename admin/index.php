<?php
/**
 * ONE VISION COMMUNITY — TABLEAU DE BORD ADMINISTRATION (INDEX)
 * 
 * Chiffres clés exigés (Section 10) :
 * - Nombre total d'utilisateurs, abonnés actifs, non abonnés ou expirés
 * - Nombre de Membres et d'Animateurs
 * - Nombre d'abonnements mensuels et annuels
 * - Revenus du mois et cumulés (depuis la table payments)
 */

$pageTitle = "Tableau de Bord — Administration One Vision";
require_once __DIR__ . '/header.php';

$db = get_db();
$nowUtc = gmdate('Y-m-d H:i:s');

// 1. Nombre total d'utilisateurs
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 2. Abonnés actifs
$stmtActive = $db->prepare("
    SELECT COUNT(DISTINCT user_id) 
    FROM subscriptions 
    WHERE statut = 'actif' AND date_fin > ?
");
$stmtActive->execute([$nowUtc]);
$activeSubscribers = (int)$stmtActive->fetchColumn();

// 3. Non abonnés ou expirés
$inactiveOrExpired = max(0, $totalUsers - $activeSubscribers);

// 4. Nombre de Membres et d'Animateurs actifs
$stmtMembre = $db->prepare("
    SELECT COUNT(*) 
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.statut = 'actif' AND s.date_fin > ? AND p.code = 'membre'
");
$stmtMembre->execute([$nowUtc]);
$activeMembresCount = (int)$stmtMembre->fetchColumn();

$stmtAnim = $db->prepare("
    SELECT COUNT(*) 
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.statut = 'actif' AND s.date_fin > ? AND p.code = 'animateur'
");
$stmtAnim->execute([$nowUtc]);
$activeAnimateursCount = (int)$stmtAnim->fetchColumn();

// 5. Nombre d'abonnements mensuels et annuels actifs
$stmtMonthly = $db->prepare("
    SELECT COUNT(*) 
    FROM subscriptions 
    WHERE statut = 'actif' AND date_fin > ? AND periodicite = 'mensuel'
");
$stmtMonthly->execute([$nowUtc]);
$monthlyCount = (int)$stmtMonthly->fetchColumn();

$stmtYearly = $db->prepare("
    SELECT COUNT(*) 
    FROM subscriptions 
    WHERE statut = 'actif' AND date_fin > ? AND periodicite = 'annuel'
");
$stmtYearly->execute([$nowUtc]);
$yearlyCount = (int)$stmtYearly->fetchColumn();

// 6. Revenus du mois et cumulés (depuis la table payments)
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(montant), 0) FROM payments WHERE statut = 'reussi'")->fetchColumn();

$currentMonth = date('Y-m');
$stmtMonthRev = $db->prepare("
    SELECT COALESCE(SUM(montant), 0) 
    FROM payments 
    WHERE statut = 'reussi' AND strftime('%Y-%m', date_paiement) = ?
");
$stmtMonthRev->execute([$currentMonth]);
$monthlyRevenue = (float)$stmtMonthRev->fetchColumn();

// Derniers paiements
$stmtRecentPay = $db->query("
    SELECT p.*, u.full_name, u.email, pl.nom as plan_nom 
    FROM payments p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN subscriptions s ON p.subscription_id = s.id
    LEFT JOIN plans pl ON s.plan_id = pl.id
    ORDER BY p.id DESC
    LIMIT 6
");
$recentPayments = $stmtRecentPay->fetchAll();

// Dernières actions administratives
$stmtRecentLogs = $db->query("
    SELECT j.*, u.full_name as admin_name 
    FROM journal_actions j
    LEFT JOIN users u ON j.admin_id = u.id
    ORDER BY j.id DESC
    LIMIT 5
");
$recentLogs = $stmtRecentLogs->fetchAll();
?>

<!-- Titre principal -->
<div style="margin-bottom: 2rem;">
  <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">
    Tableau de Bord & Métriques Clés
  </h1>
  <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
    Vue d'ensemble en temps réel de la communauté, des souscriptions et de la santé financière.
  </p>
</div>

<!-- GRILLE DES KPIS (SECTION 10) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
  
  <!-- KPI 1 : Utilisateurs & Abonnés -->
  <div class="admin-kpi-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
      <span style="font-size:0.8rem; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Utilisateurs totaux</span>
      <span style="font-size:1.4rem;">👥</span>
    </div>
    <div style="font-size:2.2rem; font-weight:900; color:#0f172a;"><?= number_format($totalUsers, 0, ',', ' ') ?></div>
    <div style="margin-top:0.75rem; font-size:0.85rem; display:flex; justify-content:space-between; color:#475569; border-top:1px solid #f1f5f9; padding-top:0.5rem;">
      <span>🟢 Actifs : <strong><?= $activeSubscribers ?></strong></span>
      <span>⚪ Expirés / Sans abo : <strong><?= $inactiveOrExpired ?></strong></span>
    </div>
  </div>

  <!-- KPI 2 : Membres vs Animateurs -->
  <div class="admin-kpi-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
      <span style="font-size:0.8rem; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Répartition Formules</span>
      <span style="font-size:1.4rem;">🎯</span>
    </div>
    <div style="display:flex; align-items:baseline; gap:0.5rem;">
      <div style="font-size:2.2rem; font-weight:900; color:#2563eb;"><?= $activeMembresCount ?></div>
      <span style="font-size:0.9rem; color:#64748b;">Membres</span>
      <span style="color:#cbd5e1; margin:0 0.25rem;">/</span>
      <div style="font-size:2.2rem; font-weight:900; color:#f97316;"><?= $activeAnimateursCount ?></div>
      <span style="font-size:0.9rem; color:#64748b;">Animateurs</span>
    </div>
    <div style="margin-top:0.75rem; font-size:0.85rem; color:#475569; border-top:1px solid #f1f5f9; padding-top:0.5rem;">
      Total abonnés actifs : <strong><?= $activeSubscribers ?></strong>
    </div>
  </div>

  <!-- KPI 3 : Mensuel vs Annuel -->
  <div class="admin-kpi-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
      <span style="font-size:0.8rem; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Périodicités</span>
      <span style="font-size:1.4rem;">📅</span>
    </div>
    <div style="display:flex; align-items:baseline; gap:0.5rem;">
      <div style="font-size:2.2rem; font-weight:900; color:#0f172a;"><?= $monthlyCount ?></div>
      <span style="font-size:0.9rem; color:#64748b;">Mensuels</span>
      <span style="color:#cbd5e1; margin:0 0.25rem;">/</span>
      <div style="font-size:2.2rem; font-weight:900; color:#10b981;"><?= $yearlyCount ?></div>
      <span style="font-size:0.9rem; color:#64748b;">Annuels</span>
    </div>
    <div style="margin-top:0.75rem; font-size:0.85rem; color:#10b981; font-weight:600; border-top:1px solid #f1f5f9; padding-top:0.5rem;">
      <?= ($activeSubscribers > 0) ? round(($yearlyCount / $activeSubscribers) * 100) : 0 ?>% d'adhésions annuelles
    </div>
  </div>

  <!-- KPI 4 : Revenus Financiers -->
  <div class="admin-kpi-card" style="border:1.5px solid #10b981;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
      <span style="font-size:0.8rem; font-weight:800; text-transform:uppercase; color:#047857; letter-spacing:0.04em;">Revenus Financiers</span>
      <span style="font-size:1.4rem;">💶</span>
    </div>
    <div style="font-size:2.2rem; font-weight:900; color:#065f46;"><?= number_format($totalRevenue, 2, ',', ' ') ?> €</div>
    <div style="margin-top:0.75rem; font-size:0.85rem; color:#047857; border-top:1px solid #d1fae5; padding-top:0.5rem;">
      Ce mois-ci (<?= date('F Y') ?>) : <strong><?= number_format($monthlyRevenue, 2, ',', ' ') ?> €</strong>
    </div>
  </div>

</div>

<!-- SECTIONS INFÉRIEURES : DERNIÈRES TRANSACTIONS & DERNIÈRES ACTIONS ADMIN -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 2rem;">
  
  <!-- Dernières Transactions -->
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.75rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
      <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0;">
        Derniers Règlements
      </h3>
      <a href="users.php" style="font-size:0.85rem; font-weight:700; color:#2563eb; text-decoration:none;">
        Voir tous les utilisateurs →
      </a>
    </div>

    <?php if (!empty($recentPayments)): ?>
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <?php foreach ($recentPayments as $pay): ?>
          <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem 1rem; display:flex; justify-content:space-between; align-items:center;">
            <div>
              <strong style="color:#0f172a; font-size:0.92rem;"><?= htmlspecialchars($pay['full_name'] ?? 'Utilisateur inconnu') ?></strong>
              <div style="font-size:0.78rem; color:#64748b;">
                <?= htmlspecialchars($pay['email'] ?? '') ?> • <?= date('d/m/Y H:i', strtotime($pay['date_paiement'])) ?>
              </div>
            </div>
            <div style="text-align:right;">
              <span style="font-weight:800; color:#0f172a; font-size:1rem;">
                +<?= number_format((float)$pay['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($pay['devise']) ?>
              </span>
              <div>
                <span style="background:#dcfce7; color:#15803d; font-size:0.72rem; font-weight:700; padding:0.15rem 0.45rem; border-radius:4px;">
                  Réussi
                </span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:#64748b; font-size:0.9rem; margin:0;">Aucun règlement enregistré pour l'instant.</p>
    <?php endif; ?>
  </div>

  <!-- Dernier Journal d'Audit -->
  <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.75rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
      <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0;">
        Journal d'Actions Administratives
      </h3>
      <a href="logs.php" style="font-size:0.85rem; font-weight:700; color:#2563eb; text-decoration:none;">
        Voir l'historique complet →
      </a>
    </div>

    <?php if (!empty($recentLogs)): ?>
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <?php foreach ($recentLogs as $log): ?>
          <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem 1rem;">
            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.25rem;">
              <strong style="color:#0f172a; font-size:0.88rem;"><?= htmlspecialchars($log['action']) ?></strong>
              <span style="font-size:0.75rem; color:#94a3b8;"><?= date('d/m/Y H:i', strtotime($log['date'])) ?></span>
            </div>
            <div style="font-size:0.8rem; color:#475569;">
              Par : <strong><?= htmlspecialchars($log['admin_name'] ?? 'Admin #' . $log['admin_id']) ?></strong> 
              <?php if (!empty($log['cible'])): ?>
                • Cible : <span style="font-family:monospace;"><?= htmlspecialchars($log['cible']) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($log['details'])): ?>
              <div style="font-size:0.78rem; color:#64748b; margin-top:0.25rem; font-style:italic;">
                <?= htmlspecialchars($log['details']) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="color:#64748b; font-size:0.9rem; margin:0;">Aucune action administrative récente.</p>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
