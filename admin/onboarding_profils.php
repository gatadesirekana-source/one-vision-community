<?php
/**
 * ONE VISION COMMUNITY — PROFILS DES MEMBRES & ONBOARDING (ADMINISTRATION)
 * 
 * Fonctionnalités exigées (Section 7) :
 * - Liste des personnes ayant commencé le questionnaire avec nom, email, tranche d'âge, situation, domaines, objectifs, statut questionnaire, abonnement, consentement marketing
 * - Recherche par nom/email
 * - Filtres : tranche d'âge, situation, domaine, intention d'animer (Q8), questionnaire terminé/non, abonné/non, consentement marketing, source de découverte, sujet d'intérêt
 * - Export CSV de la liste filtrée avec rappel RGPD explicite
 * - Tableau de statistiques et funnel (démarré, terminé, abonné avec taux de passage)
 * - Répartition par tranche d'âge, situation, domaine, objectif, sujet, source de découverte
 * - Protégé côté serveur par require_permission('voir_profils_membres')
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/onboarding.php';
require_once __DIR__ . '/../includes/questionnaire_data.php';

// Contrôle strict de permission côté serveur
require_admin_access('../login.php');
require_permission('voir_profils_membres', 'index.php');

$db = get_db();
$currentUser = current_user();

// Paramètres de filtrage & recherche
$search = trim($_GET['q'] ?? '');
$filterTrancheAge = trim($_GET['tranche_age'] ?? '');
$filterSituation = trim($_GET['situation'] ?? '');
$filterDomaine = trim($_GET['domaine'] ?? '');
$filterIntentionAnimer = trim($_GET['intention_animer'] ?? '');
$filterStatut = trim($_GET['statut'] ?? '');
$filterAbonne = trim($_GET['abonne'] ?? '');
$filterConsentement = trim($_GET['consentement'] ?? '');
$filterSource = trim($_GET['source'] ?? '');
$filterSujet = trim($_GET['sujet_interet'] ?? '');

// Construction des clauses WHERE
$whereClauses = ["po.id IS NOT NULL"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(LOWER(u.full_name) LIKE ? OR LOWER(u.email) LIKE ?)";
    $params[] = '%' . strtolower($search) . '%';
    $params[] = '%' . strtolower($search) . '%';
}

if (!empty($filterTrancheAge)) {
    $whereClauses[] = "po.tranche_age = ?";
    $params[] = $filterTrancheAge;
}

if (!empty($filterSituation)) {
    $whereClauses[] = "po.situation = ?";
    $params[] = $filterSituation;
}

if (!empty($filterDomaine)) {
    $whereClauses[] = "EXISTS (SELECT 1 FROM reponses_questionnaire rq WHERE rq.user_id = u.id AND rq.question_id = 'q3' AND rq.reponse = ?)";
    $params[] = $filterDomaine;
}

if ($filterIntentionAnimer === 'oui') {
    $whereClauses[] = "EXISTS (SELECT 1 FROM reponses_questionnaire rq WHERE rq.user_id = u.id AND rq.question_id = 'q8' AND rq.reponse LIKE '%Animer mes propres lives%')";
} elseif ($filterIntentionAnimer === 'non') {
    $whereClauses[] = "NOT EXISTS (SELECT 1 FROM reponses_questionnaire rq WHERE rq.user_id = u.id AND rq.question_id = 'q8' AND rq.reponse LIKE '%Animer mes propres lives%')";
}

if ($filterStatut === 'termine') {
    $whereClauses[] = "po.statut = 'termine'";
} elseif ($filterStatut === 'en_cours') {
    $whereClauses[] = "po.statut = 'en_cours'";
} elseif ($filterStatut === 'mineur') {
    $whereClauses[] = "po.statut = 'mineur'";
}

if ($filterAbonne === 'oui') {
    $whereClauses[] = "(s.statut = 'actif' AND s.date_fin > datetime('now'))";
} elseif ($filterAbonne === 'non') {
    $whereClauses[] = "(s.id IS NULL OR s.statut != 'actif' OR s.date_fin <= datetime('now'))";
}

if ($filterConsentement === 'oui') {
    $whereClauses[] = "po.consentement_marketing = 1";
} elseif ($filterConsentement === 'non') {
    $whereClauses[] = "(po.consentement_marketing = 0 OR po.consentement_marketing IS NULL)";
}

if (!empty($filterSource)) {
    $whereClauses[] = "po.source_decouverte = ?";
    $params[] = $filterSource;
}

if (!empty($filterSujet)) {
    $whereClauses[] = "EXISTS (SELECT 1 FROM reponses_questionnaire rq WHERE rq.user_id = u.id AND rq.question_id = 'q10' AND rq.reponse = ?)";
    $params[] = $filterSujet;
}

$whereSql = implode(' AND ', $whereClauses);

// Requête principale
$sql = "
    SELECT 
        u.id AS user_id,
        u.full_name,
        u.email,
        u.created_at AS user_created_at,
        u.role AS user_role,
        po.statut AS onboarding_statut,
        po.tranche_age,
        po.situation,
        po.consentement_marketing,
        po.date_consentement,
        po.canal_rappel_prefere,
        po.source_decouverte,
        po.date_debut AS onboarding_date_debut,
        po.date_fin AS onboarding_date_fin,
        s.id AS sub_id,
        s.statut AS sub_statut,
        s.periodicite AS sub_periodicite,
        s.date_fin AS sub_date_fin,
        p.nom AS plan_nom,
        p.code AS plan_code,
        (
            SELECT GROUP_CONCAT(rq.reponse, ', ')
            FROM reponses_questionnaire rq
            WHERE rq.user_id = u.id AND rq.question_id = 'q3'
        ) AS domaines_list,
        (
            SELECT GROUP_CONCAT(rq.reponse, ', ')
            FROM reponses_questionnaire rq
            WHERE rq.user_id = u.id AND rq.question_id = 'q6'
        ) AS objectifs_list,
        (
            SELECT COUNT(*) 
            FROM reponses_questionnaire rq
            WHERE rq.user_id = u.id AND rq.question_id = 'q8' AND rq.reponse LIKE '%Animer mes propres lives%'
        ) AS veut_animer
    FROM profils_onboarding po
    JOIN users u ON po.user_id = u.id
    LEFT JOIN subscriptions s ON s.id = (
        SELECT id FROM subscriptions WHERE user_id = u.id ORDER BY id DESC LIMIT 1
    )
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE {$whereSql}
    ORDER BY po.updated_at DESC
";

$stmtMain = $db->prepare($sql);
$stmtMain->execute($params);
$membres = $stmtMain->fetchAll();

// ==========================================
// EXPORT CSV (Section 7)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Journalisation de l'action admin
    log_admin_action((int)$currentUser['id'], 'export_csv_profils_onboarding', 'profils_onboarding', 'Export CSV avec colonne consentement marketing');

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="profils_membres_onboarding_' . gmdate('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM pour bon affichage sous Excel
    fputs($output, "\xEF\xBB\xBF");

    // En-têtes CSV
    fputcsv($output, [
        'ID Membre',
        'Nom complet',
        'Email',
        'Date Inscription (UTC)',
        'Statut Questionnaire',
        'Tranche d\'âge',
        'Situation',
        'Domaines d\'évolution',
        'Objectifs à 6 mois',
        'Intention d\'animer',
        'Source de découverte',
        'Canal de rappel préféré',
        'CONSENTEMENT MARKETING (Opt-in)',
        'Date Consentement (UTC)',
        'Formule Abonnement',
        'Périodicité',
        'Statut Abonnement',
        'Échéance Abonnement (UTC)',
        'Date Début Questionnaire (UTC)',
        'Date Fin Questionnaire (UTC)'
    ], ';');

    foreach ($membres as $m) {
        $subLabel = 'Aucun';
        $subStatus = 'non_abonne';
        if (!empty($m['sub_id']) && $m['sub_statut'] === 'actif' && strtotime($m['sub_date_fin']) > time()) {
            $subLabel = ($m['plan_nom'] ?? 'Membre') . ' (' . ucfirst($m['sub_periodicite'] ?? 'mensuel') . ')';
            $subStatus = 'actif';
        } elseif (!empty($m['sub_id'])) {
            $subLabel = ($m['plan_nom'] ?? 'Membre') . ' (' . ($m['sub_statut'] ?? 'inactif') . ')';
            $subStatus = $m['sub_statut'] ?? 'inactif';
        }

        $row = [
            $m['user_id'],
            $m['full_name'],
            $m['email'],
            $m['user_created_at'],
            $m['onboarding_statut'],
            $m['tranche_age'] ?? 'Non renseigné',
            $m['situation'] ?? 'Non renseigné',
            $m['domaines_list'] ?? '',
            $m['objectifs_list'] ?? '',
            ((int)$m['veut_animer'] > 0) ? 'OUI' : 'NON',
            $m['source_decouverte'] ?? '',
            $m['canal_rappel_prefere'] ?? '',
            ((int)$m['consentement_marketing'] === 1) ? 'OUI (Consentement accordé)' : 'NON (Pas de démarchage)',
            $m['date_consentement'] ?? '',
            $m['plan_nom'] ?? '',
            $m['sub_periodicite'] ?? '',
            $subStatus,
            $m['sub_date_fin'] ?? '',
            $m['onboarding_date_debut'] ?? '',
            $m['onboarding_date_fin'] ?? ''
        ];
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}

// ==========================================
// CALCUL DU FUNNEL & DES STATISTIQUES GLOBALES (Section 7)
// ==========================================
// 1. Funnel
$totalDemarre = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM profils_onboarding")->fetchColumn();
$totalTermine = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM profils_onboarding WHERE statut = 'termine'")->fetchColumn();
$totalAbonne = (int)$db->query("
    SELECT COUNT(DISTINCT po.user_id) 
    FROM profils_onboarding po
    JOIN subscriptions s ON s.user_id = po.user_id
    WHERE po.statut = 'termine' AND s.statut = 'actif' AND s.date_fin > datetime('now')
")->fetchColumn();
$totalConsentement = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM profils_onboarding WHERE consentement_marketing = 1")->fetchColumn();

$tauxCompletion = $totalDemarre > 0 ? round(($totalTermine / $totalDemarre) * 100, 1) : 0;
$tauxConversionPostOnboarding = $totalTermine > 0 ? round(($totalAbonne / $totalTermine) * 100, 1) : 0;
$tauxConversionGlobal = $totalDemarre > 0 ? round(($totalAbonne / $totalDemarre) * 100, 1) : 0;

// 2. Distributions
// Tranche d'âge
$statsAge = $db->query("
    SELECT COALESCE(tranche_age, 'Non renseigné') AS label, COUNT(*) AS count
    FROM profils_onboarding
    WHERE tranche_age IS NOT NULL AND tranche_age != ''
    GROUP BY tranche_age
    ORDER BY count DESC
")->fetchAll();

// Situation
$statsSituation = $db->query("
    SELECT COALESCE(situation, 'Non renseigné') AS label, COUNT(*) AS count
    FROM profils_onboarding
    WHERE situation IS NOT NULL AND situation != ''
    GROUP BY situation
    ORDER BY count DESC
")->fetchAll();

// Domaines (Q3)
$statsDomaines = $db->query("
    SELECT reponse AS label, COUNT(*) AS count
    FROM reponses_questionnaire
    WHERE question_id = 'q3'
    GROUP BY reponse
    ORDER BY count DESC
")->fetchAll();

// Objectifs (Q6)
$statsObjectifs = $db->query("
    SELECT reponse AS label, COUNT(*) AS count
    FROM reponses_questionnaire
    WHERE question_id = 'q6'
    GROUP BY reponse
    ORDER BY count DESC
")->fetchAll();

// Sujets souhaités (Q10)
$statsSujets = $db->query("
    SELECT reponse AS label, COUNT(*) AS count
    FROM reponses_questionnaire
    WHERE question_id = 'q10'
    GROUP BY reponse
    ORDER BY count DESC
")->fetchAll();

// Source de découverte (Q12 / colonne source_decouverte)
$statsSource = $db->query("
    SELECT COALESCE(source_decouverte, 'Non renseigné') AS label, COUNT(*) AS count
    FROM profils_onboarding
    WHERE source_decouverte IS NOT NULL AND source_decouverte != ''
    GROUP BY source_decouverte
    ORDER BY count DESC
")->fetchAll();

$pageTitle = "Profils des membres — Administration One Vision";
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 2rem;">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
    <div>
      <h1 style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0 0 0.35rem 0;">
        📋 Profils des Membres & Parcours d'Accueil
      </h1>
      <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
        Découvrez qui sont vos membres, leurs besoins, leurs ambitions et gérez le ciblage dans le respect de leur consentement.
      </p>
    </div>

    <!-- Export CSV -->
    <div>
      <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-secondary" style="font-weight:700; display:inline-flex; align-items:center; gap:0.5rem; padding:0.65rem 1.25rem; border-radius:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Exporter la liste filtrée (CSV)</span>
      </a>
    </div>
  </div>
</div>

<!-- RAPPEL RGPD ET CONFORMITÉ (Section 7) -->
<div style="background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; color:#92400e; padding:1rem 1.25rem; border-radius:12px; margin-bottom:2rem; display:flex; align-items:flex-start; gap:0.85rem; box-shadow:0 2px 10px rgba(245,158,11,0.05);">
  <span style="font-size:1.35rem; line-height:1;">⚖️</span>
  <div style="font-size:0.92rem; line-height:1.55;">
    <strong style="color:#b45309; font-weight:800;">Règle de conformité RGPD & Retargeting :</strong>
    Seules les personnes ayant explicitement coché la case de consentement marketing (mention <strong>« OUI »</strong>) peuvent faire l'objet d'emails promotionnels, d'offres ou de retargeting commercial. Les personnes ayant refusé ou non consenti ne doivent recevoir que les emails strictement opérationnels et transactionnels.
  </div>
</div>

<!-- 1. ENTONNOIR DE CONVERSION (FUNNEL) (Section 7) -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.75rem 2rem; margin-bottom:2.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
  <h2 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 1.25rem 0; display:flex; align-items:center; gap:0.5rem;">
    <span>⚡</span> Entonnoir de conversion du parcours d'accueil
  </h2>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; align-items:stretch;">
    <!-- Étape 1 : Démarré -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem;">
      <div style="font-size:0.78rem; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.04em; margin-bottom:0.35rem;">
        1. Ont démarré
      </div>
      <div style="font-size:2.2rem; font-weight:900; color:#0f172a;">
        <?= number_format($totalDemarre) ?>
      </div>
      <div style="font-size:0.82rem; color:#64748b; margin-top:0.25rem;">
        Membres ayant ouvert le questionnaire
      </div>
    </div>

    <!-- Étape 2 : Terminé -->
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:14px; padding:1.25rem;">
      <div style="font-size:0.78rem; font-weight:800; text-transform:uppercase; color:#2563eb; letter-spacing:0.04em; margin-bottom:0.35rem;">
        2. Ont terminé
      </div>
      <div style="font-size:2.2rem; font-weight:900; color:#1d4ed8;">
        <?= number_format($totalTermine) ?>
      </div>
      <div style="font-size:0.82rem; color:#1e40af; font-weight:700; margin-top:0.25rem;">
        Taux de complétion : <?= $tauxCompletion ?> %
      </div>
    </div>

    <!-- Étape 3 : Abonné -->
    <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:14px; padding:1.25rem;">
      <div style="font-size:0.78rem; font-weight:800; text-transform:uppercase; color:#059669; letter-spacing:0.04em; margin-bottom:0.35rem;">
        3. Ont souscrit un abonnement
      </div>
      <div style="font-size:2.2rem; font-weight:900; color:#047857;">
        <?= number_format($totalAbonne) ?>
      </div>
      <div style="font-size:0.82rem; color:#065f46; font-weight:700; margin-top:0.25rem;">
        Post-questionnaire : <?= $tauxConversionPostOnboarding ?> % (Global: <?= $tauxConversionGlobal ?> %)
      </div>
    </div>

    <!-- Opt-in Marketing -->
    <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:14px; padding:1.25rem;">
      <div style="font-size:0.78rem; font-weight:800; text-transform:uppercase; color:#9333ea; letter-spacing:0.04em; margin-bottom:0.35rem;">
        Consentement Marketing
      </div>
      <div style="font-size:2.2rem; font-weight:900; color:#7e22ce;">
        <?= number_format($totalConsentement) ?>
      </div>
      <div style="font-size:0.82rem; color:#6b21a8; font-weight:700; margin-top:0.25rem;">
        <?= $totalTermine > 0 ? round(($totalConsentement / $totalTermine) * 100, 1) : 0 ?> % des profils terminés
      </div>
    </div>
  </div>
</div>

<!-- 2. TABLEAU DE STATISTIQUES & RÉPARTITIONS (Section 7) -->
<details style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.5rem 2rem; margin-bottom:2.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
  <summary style="font-size:1.15rem; font-weight:800; color:#0f172a; cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center;">
    <span style="display:flex; align-items:center; gap:0.5rem;">
      <span>📊</span> Consulter les statistiques détaillées (Âge, Situation, Domaines, Ambitions, Sujets, Sources)
    </span>
    <span style="font-size:0.85rem; color:#2563eb; font-weight:700;">Afficher / Masquer</span>
  </summary>

  <div style="margin-top:1.5rem; display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.75rem;">
    
    <!-- Répartition par tranche d'âge -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
      <h3 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">🎂 Tranches d'âge</h3>
      <div style="display:flex; flex-direction:column; gap:0.6rem;">
        <?php foreach ($statsAge as $sa): ?>
          <?php $pct = $totalDemarre > 0 ? round(($sa['count'] / $totalDemarre) * 100) : 0; ?>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.2rem;">
              <span style="color:#334155; font-weight:600;"><?= htmlspecialchars($sa['label']) ?></span>
              <strong style="color:#0f172a;"><?= $sa['count'] ?> (<?= $pct ?>%)</strong>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
              <div style="height:100%; width:<?= $pct ?>%; background:#2563eb;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Répartition par situation -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
      <h3 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">🚀 Situation actuelle</h3>
      <div style="display:flex; flex-direction:column; gap:0.6rem;">
        <?php foreach ($statsSituation as $ss): ?>
          <?php $pct = $totalDemarre > 0 ? round(($ss['count'] / $totalDemarre) * 100) : 0; ?>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.2rem;">
              <span style="color:#334155; font-weight:600;"><?= htmlspecialchars($ss['label']) ?></span>
              <strong style="color:#0f172a;"><?= $ss['count'] ?> (<?= $pct ?>%)</strong>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
              <div style="height:100%; width:<?= $pct ?>%; background:#059669;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Répartition par domaines d'évolution -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
      <h3 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">💼 Domaines d'évolution</h3>
      <div style="display:flex; flex-direction:column; gap:0.6rem;">
        <?php foreach ($statsDomaines as $sd): ?>
          <?php $pct = $totalDemarre > 0 ? round(($sd['count'] / $totalDemarre) * 100) : 0; ?>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.2rem;">
              <span style="color:#334155; font-weight:600;"><?= htmlspecialchars($sd['label']) ?></span>
              <strong style="color:#0f172a;"><?= $sd['count'] ?></strong>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
              <div style="height:100%; width:<?= min(100, $pct * 2) ?>%; background:#8b5cf6;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Répartition par ambitions à 6 mois -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
      <h3 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">🎯 Ambitions à 6 mois</h3>
      <div style="display:flex; flex-direction:column; gap:0.6rem;">
        <?php foreach ($statsObjectifs as $so): ?>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.2rem;">
              <span style="color:#334155; font-weight:600;"><?= htmlspecialchars($so['label']) ?></span>
              <strong style="color:#0f172a;"><?= $so['count'] ?></strong>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
              <div style="height:100%; width:<?= min(100, $so['count'] * 15) ?>%; background:#ea580c;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Sujets souhaités pour lives/masterminds -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
      <h3 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">💡 Sujets de lives souhaités</h3>
      <div style="display:flex; flex-direction:column; gap:0.6rem;">
        <?php foreach ($statsSujets as $sj): ?>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.2rem;">
              <span style="color:#334155; font-weight:600;"><?= htmlspecialchars($sj['label']) ?></span>
              <strong style="color:#0f172a;"><?= $sj['count'] ?></strong>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
              <div style="height:100%; width:<?= min(100, $sj['count'] * 15) ?>%; background:#0284c7;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Source de découverte -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
      <h3 style="font-size:0.95rem; font-weight:800; color:#0f172a; margin:0 0 1rem 0;">🌐 Comment ils nous ont connus</h3>
      <div style="display:flex; flex-direction:column; gap:0.6rem;">
        <?php foreach ($statsSource as $src): ?>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.2rem;">
              <span style="color:#334155; font-weight:600;"><?= htmlspecialchars($src['label']) ?></span>
              <strong style="color:#0f172a;"><?= $src['count'] ?></strong>
            </div>
            <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
              <div style="height:100%; width:<?= min(100, $src['count'] * 20) ?>%; background:#d97706;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</details>

<!-- 3. BARRE DE RECHERCHE ET FILTRES (Section 7) -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1.75rem 2rem; margin-bottom:2rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
  <form method="GET" action="onboarding_profils.php">
    
    <!-- Ligne de recherche texte -->
    <div style="margin-bottom:1.25rem;">
      <label style="font-size:0.82rem; font-weight:800; text-transform:uppercase; color:#475569; display:block; margin-bottom:0.4rem; letter-spacing:0.04em;">
        Rechercher par nom ou email
      </label>
      <div style="display:flex; gap:0.75rem;">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ex: Jean Dupont, sophie@example.com..." class="form-input" style="flex:1; padding:0.65rem 1rem; border:1px solid #cbd5e1; border-radius:10px; font-size:0.95rem;">
        <button type="submit" class="btn btn-primary" style="font-weight:700; padding:0.65rem 1.5rem; border-radius:10px;">
          Rechercher
        </button>
        <?php if (!empty($_GET)): ?>
          <a href="onboarding_profils.php" class="btn btn-outline" style="font-weight:700; padding:0.65rem 1.25rem; border-radius:10px; border-color:#cbd5e1; color:#64748b;">
            Réinitialiser
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Grille des filtres sélecteurs -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; align-items:flex-end;">
      
      <!-- Tranche d'âge -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Tranche d'âge</label>
        <select name="tranche_age" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Toutes</option>
          <option value="18-24 ans" <?= $filterTrancheAge === '18-24 ans' ? 'selected' : '' ?>>18-24 ans</option>
          <option value="25-34 ans" <?= $filterTrancheAge === '25-34 ans' ? 'selected' : '' ?>>25-34 ans</option>
          <option value="35-44 ans" <?= $filterTrancheAge === '35-44 ans' ? 'selected' : '' ?>>35-44 ans</option>
          <option value="45-54 ans" <?= $filterTrancheAge === '45-54 ans' ? 'selected' : '' ?>>45-54 ans</option>
          <option value="55 ans et plus" <?= $filterTrancheAge === '55 ans et plus' ? 'selected' : '' ?>>55 ans et plus</option>
          <option value="Moins de 18 ans" <?= $filterTrancheAge === 'Moins de 18 ans' ? 'selected' : '' ?>>Moins de 18 ans</option>
        </select>
      </div>

      <!-- Situation -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Situation actuelle</label>
        <select name="situation" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Toutes</option>
          <option value="J'ai une idée et je cherche à la concrétiser" <?= $filterSituation === "J'ai une idée et je cherche à la concrétiser" ? 'selected' : '' ?>>J'ai une idée</option>
          <option value="Je me lance en ce moment" <?= $filterSituation === "Je me lance en ce moment" ? 'selected' : '' ?>>Je me lance</option>
          <option value="J'ai déjà une activité et je veux la faire grandir" <?= $filterSituation === "J'ai déjà une activité et je veux la faire grandir" ? 'selected' : '' ?>>Activité existante</option>
          <option value="Je veux développer mes compétences et ma carrière" <?= $filterSituation === "Je veux développer mes compétences et ma carrière" ? 'selected' : '' ?>>Compétences / Carrière</option>
          <option value="Je cherche ma voie" <?= $filterSituation === "Je cherche ma voie" ? 'selected' : '' ?>>Je cherche ma voie</option>
        </select>
      </div>

      <!-- Domaine (Q3) -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Domaine</label>
        <select name="domaine" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Tous</option>
          <option value="Commerce et vente" <?= $filterDomaine === 'Commerce et vente' ? 'selected' : '' ?>>Commerce & vente</option>
          <option value="Conseil, coaching, services" <?= $filterDomaine === 'Conseil, coaching, services' ? 'selected' : '' ?>>Conseil, coaching</option>
          <option value="Digital et technologie" <?= $filterDomaine === 'Digital et technologie' ? 'selected' : '' ?>>Digital & tech</option>
          <option value="Agriculture et agro-alimentaire" <?= $filterDomaine === 'Agriculture et agro-alimentaire' ? 'selected' : '' ?>>Agriculture</option>
          <option value="Éducation et formation" <?= $filterDomaine === 'Éducation et formation' ? 'selected' : '' ?>>Éducation</option>
          <option value="Créativité et communication" <?= $filterDomaine === 'Créativité et communication' ? 'selected' : '' ?>>Créativité</option>
          <option value="Autre" <?= $filterDomaine === 'Autre' ? 'selected' : '' ?>>Autre</option>
        </select>
      </div>

      <!-- Intention d'animer (Q8) -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Intention d'animer (Q8)</label>
        <select name="intention_animer" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Tous</option>
          <option value="oui" <?= $filterIntentionAnimer === 'oui' ? 'selected' : '' ?>>⭐ Oui (veut animer)</option>
          <option value="non" <?= $filterIntentionAnimer === 'non' ? 'selected' : '' ?>>Non</option>
        </select>
      </div>

      <!-- Statut Questionnaire -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Questionnaire</label>
        <select name="statut" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Tous</option>
          <option value="termine" <?= $filterStatut === 'termine' ? 'selected' : '' ?>>Terminé</option>
          <option value="en_cours" <?= $filterStatut === 'en_cours' ? 'selected' : '' ?>>En cours (quitté)</option>
          <option value="mineur" <?= $filterStatut === 'mineur' ? 'selected' : '' ?>>Moins de 18 ans</option>
        </select>
      </div>

      <!-- Abonné -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Abonnement</label>
        <select name="abonne" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Tous</option>
          <option value="oui" <?= $filterAbonne === 'oui' ? 'selected' : '' ?>>Abonné actif</option>
          <option value="non" <?= $filterAbonne === 'non' ? 'selected' : '' ?>>Non abonné</option>
        </select>
      </div>

      <!-- Consentement Marketing -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Consentement Email</label>
        <select name="consentement" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Tous</option>
          <option value="oui" <?= $filterConsentement === 'oui' ? 'selected' : '' ?>>OUI (Accepté)</option>
          <option value="non" <?= $filterConsentement === 'non' ? 'selected' : '' ?>>NON (Refusé/Non coché)</option>
        </select>
      </div>

      <!-- Source de découverte -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Source découverte</label>
        <select name="source" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Toutes</option>
          <option value="Réseaux sociaux" <?= $filterSource === 'Réseaux sociaux' ? 'selected' : '' ?>>Réseaux sociaux</option>
          <option value="Recommandation d'un proche" <?= $filterSource === "Recommandation d'un proche" ? 'selected' : '' ?>>Recommandation</option>
          <option value="Un live ou un événement" <?= $filterSource === 'Un live ou un événement' ? 'selected' : '' ?>>Live ou événement</option>
          <option value="Recherche sur internet" <?= $filterSource === 'Recherche sur internet' ? 'selected' : '' ?>>Recherche internet</option>
          <option value="Autre" <?= $filterSource === 'Autre' ? 'selected' : '' ?>>Autre</option>
        </select>
      </div>

      <!-- Sujet d'intérêt (Q10) -->
      <div>
        <label style="font-size:0.75rem; font-weight:700; color:#64748b; display:block; margin-bottom:0.25rem;">Sujet d'intérêt (Q10)</label>
        <select name="sujet_interet" class="form-input" style="width:100%; padding:0.5rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">Tous</option>
          <option value="Lancer et structurer son projet" <?= $filterSujet === 'Lancer et structurer son projet' ? 'selected' : '' ?>>Lancer son projet</option>
          <option value="Trouver des clients et vendre" <?= $filterSujet === 'Trouver des clients et vendre' ? 'selected' : '' ?>>Trouver des clients</option>
          <option value="Gérer ses finances" <?= $filterSujet === 'Gérer ses finances' ? 'selected' : '' ?>>Finances</option>
          <option value="Développer son mindset" <?= $filterSujet === 'Développer son mindset' ? 'selected' : '' ?>>Mindset</option>
          <option value="Construire son réseau" <?= $filterSujet === 'Construire son réseau' ? 'selected' : '' ?>>Réseau</option>
          <option value="Utiliser les outils digitaux" <?= $filterSujet === 'Utiliser les outils digitaux' ? 'selected' : '' ?>>Outils digitaux</option>
          <option value="Leadership et prise de parole" <?= $filterSujet === 'Leadership et prise de parole' ? 'selected' : '' ?>>Leadership</option>
        </select>
      </div>

    </div>

  </form>
</div>

<!-- 4. LISTE TABLEAU DES MEMBRES (Section 7) -->
<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
  
  <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
    <div style="font-size:0.95rem; font-weight:800; color:#0f172a;">
      <?= count($membres) ?> profil<?= count($membres) > 1 ? 's' : '' ?> trouvé<?= count($membres) > 1 ? 's' : '' ?>
    </div>
    <div style="font-size:0.82rem; color:#64748b;">
      Cliquez sur une ligne ou sur « Voir la fiche » pour inspecter toutes les réponses
    </div>
  </div>

  <?php if (!empty($membres)): ?>
    <div style="overflow-x:auto;">
      <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.88rem;">
        <thead>
          <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569; font-size:0.76rem; text-transform:uppercase; letter-spacing:0.04em;">
            <th style="padding:0.85rem 1.25rem;">Membre</th>
            <th style="padding:0.85rem 1rem;">Tranche d'âge</th>
            <th style="padding:0.85rem 1rem;">Situation</th>
            <th style="padding:0.85rem 1rem;">Domaines (Q3)</th>
            <th style="padding:0.85rem 1rem;">Statut Onboarding</th>
            <th style="padding:0.85rem 1rem;">Abonnement</th>
            <th style="padding:0.85rem 1rem;">Consentement</th>
            <th style="padding:0.85rem 1.25rem; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($membres as $m): ?>
            <?php
              $isSubActive = (!empty($m['sub_id']) && $m['sub_statut'] === 'actif' && strtotime($m['sub_date_fin']) > time());
              $isMineur = ($m['onboarding_statut'] === 'mineur' || $m['tranche_age'] === 'Moins de 18 ans');
              $hasConsent = ((int)$m['consentement_marketing'] === 1);
            ?>
            <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
              
              <!-- Membre -->
              <td style="padding:1rem 1.25rem;">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <div>
                    <a href="onboarding_detail.php?id=<?= $m['user_id'] ?>" style="font-weight:800; color:#0f172a; text-decoration:none; font-size:0.95rem;">
                      <?= htmlspecialchars($m['full_name']) ?>
                    </a>
                    <?php if ((int)$m['veut_animer'] > 0): ?>
                      <span style="background:#fff7ed; color:#ea580c; border:1px solid #fdba74; font-size:0.68rem; font-weight:800; padding:0.12rem 0.4rem; border-radius:4px; margin-left:0.35rem;">
                        🎤 Veut animer
                      </span>
                    <?php endif; ?>
                    <div style="color:#64748b; font-size:0.8rem; margin-top:0.15rem;">
                      <?= htmlspecialchars($m['email']) ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Tranche d'âge -->
              <td style="padding:1rem 1rem; color:#334155; font-weight:600;">
                <?php if ($isMineur): ?>
                  <span style="background:#fee2e2; color:#b91c1c; padding:0.2rem 0.5rem; border-radius:6px; font-weight:800; font-size:0.78rem;">
                    Moins de 18 ans
                  </span>
                <?php else: ?>
                  <?= htmlspecialchars($m['tranche_age'] ?? '—') ?>
                <?php endif; ?>
              </td>

              <!-- Situation -->
              <td style="padding:1rem 1rem; color:#475569; max-width:200px;">
                <span style="display:inline-block; font-size:0.83rem; line-height:1.35;">
                  <?= htmlspecialchars($m['situation'] ?? '—') ?>
                </span>
              </td>

              <!-- Domaines -->
              <td style="padding:1rem 1rem; color:#475569; max-width:220px;">
                <span style="display:inline-block; font-size:0.82rem; line-height:1.35; color:#334155;">
                  <?= htmlspecialchars($m['domaines_list'] ?: '—') ?>
                </span>
              </td>

              <!-- Statut Questionnaire -->
              <td style="padding:1rem 1rem;">
                <?php if ($m['onboarding_statut'] === 'termine'): ?>
                  <span style="background:#ecfdf5; color:#047857; font-weight:800; font-size:0.78rem; padding:0.25rem 0.65rem; border-radius:20px; display:inline-flex; align-items:center; gap:0.3rem;">
                    <span>✓</span> Terminé
                  </span>
                <?php elseif ($isMineur): ?>
                  <span style="background:#fee2e2; color:#b91c1c; font-weight:800; font-size:0.78rem; padding:0.25rem 0.65rem; border-radius:20px;">
                    Mineur (Bloqué)
                  </span>
                <?php else: ?>
                  <span style="background:#fef3c7; color:#b45309; font-weight:800; font-size:0.78rem; padding:0.25rem 0.65rem; border-radius:20px; display:inline-flex; align-items:center; gap:0.3rem;">
                    <span>⏳</span> En cours
                  </span>
                <?php endif; ?>
              </td>

              <!-- Abonnement -->
              <td style="padding:1rem 1rem;">
                <?php if ($isSubActive): ?>
                  <div style="font-weight:800; color:<?= ($m['plan_code'] === 'animateur') ? '#ea580c' : '#2563eb' ?>; font-size:0.85rem;">
                    <?= htmlspecialchars($m['plan_nom'] ?? 'Membre') ?>
                  </div>
                  <div style="font-size:0.75rem; color:#64748b;">
                    <?= ucfirst($m['sub_periodicite'] ?? 'mensuel') ?> • Actif
                  </div>
                <?php elseif (!empty($m['sub_id'])): ?>
                  <span style="background:#f1f5f9; color:#64748b; font-weight:700; font-size:0.75rem; padding:0.2rem 0.5rem; border-radius:6px;">
                    Expiré (<?= htmlspecialchars($m['plan_nom'] ?? 'Abonné') ?>)
                  </span>
                <?php else: ?>
                  <span style="color:#94a3b8; font-size:0.82rem; font-style:italic;">
                    Aucun abonnement
                  </span>
                <?php endif; ?>
              </td>

              <!-- Consentement Marketing -->
              <td style="padding:1rem 1rem;">
                <?php if ($hasConsent): ?>
                  <span style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-weight:800; font-size:0.75rem; padding:0.25rem 0.6rem; border-radius:6px; display:inline-flex; align-items:center; gap:0.25rem;">
                    <span>✓</span> OUI
                  </span>
                <?php else: ?>
                  <span style="background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; font-weight:700; font-size:0.75rem; padding:0.25rem 0.6rem; border-radius:6px;">
                    NON
                  </span>
                <?php endif; ?>
              </td>

              <!-- Actions -->
              <td style="padding:1rem 1.25rem; text-align:right;">
                <a href="onboarding_detail.php?id=<?= $m['user_id'] ?>" class="btn btn-secondary btn-sm" style="font-weight:700; font-size:0.8rem; padding:0.4rem 0.85rem; border-radius:8px;">
                  Voir la fiche →
                </a>
              </td>

            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div style="text-align:center; padding:3.5rem 1.5rem; color:#64748b;">
      <div style="font-size:2.5rem; margin-bottom:0.75rem;">🔍</div>
      <strong style="color:#0f172a; font-size:1.1rem; display:block; margin-bottom:0.35rem;">
        Aucun profil ne correspond à vos critères
      </strong>
      <p style="margin:0; font-size:0.9rem;">
        Essayez d'élargir vos filtres ou de réinitialiser la recherche.
      </p>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
