<?php
/**
 * ONE VISION COMMUNITY — CRÉER UN LIVE OU UN MASTERMIND (PHP & BDD SQLITE)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/subscriptions.php';
require_once __DIR__ . '/includes/permissions.php';

// Protection : seuls les Animateurs et administrateurs peuvent planifier un live officiel (Section 3)
require_animateur_access('abonnements.php');

$currentUser = current_user();
$db = get_db();

$success = false;
$createdLive = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification obligatoire et stricte du token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = "Session de formulaire expirée. Veuillez actualiser la page et réessayer.";
    } else {
        $format = trim($_POST['liveFormat'] ?? 'Live Thématique');
        $title = trim($_POST['liveTitle'] ?? '');
        $categoryTag = trim($_POST['liveCategoryTag'] ?? 'Retour d\'Expérience');
        $desc = trim($_POST['liveDesc'] ?? '');
        $resources = trim($_POST['liveResources'] ?? '');
        $date = trim($_POST['liveDate'] ?? '');
        $time = trim($_POST['liveTime'] ?? '19h00');
        $duration = trim($_POST['liveDuration'] ?? '1h00');
        $section = trim($_POST['liveTabSection'] ?? 'current');
        $author = trim($_POST['liveAuthor'] ?? ($currentUser['full_name'] ?? 'Membre'));
        $role = trim($_POST['liveRole'] ?? ($currentUser['job_title'] ?? 'Membre One Vision'));
        $avatar = trim($_POST['liveAvatar'] ?? ($currentUser['avatar'] ?? './img/avatar-maxime.jpg'));

        if (empty($title) || empty($desc) || empty($date)) {
            $error = "Veuillez remplir au minimum le titre, la date et la description de votre intervention.";
        } else {
            // Anti-collision stricte pour éviter 2 sessions sur le même créneau horaire
            $checkTimeNorm = str_replace(':', 'h', $time);
            $checkTimeAlt = str_replace('h', ':', $time);

            $stmtConflict = $db->prepare("
                SELECT id, title, author_name, scheduled_time, scheduled_date 
                FROM lives 
                WHERE scheduled_date = ? 
                  AND (scheduled_time = ? OR scheduled_time = ?)
            ");
            $stmtConflict->execute([$date, $checkTimeNorm, $checkTimeAlt]);
            $conflict = $stmtConflict->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                $error = "Conflit d'horaire : Le créneau du " . htmlspecialchars($date) . " à " . htmlspecialchars($time) . " est déjà réservé par " . htmlspecialchars($conflict['author_name']) . " (« " . htmlspecialchars($conflict['title']) . " »). Veuillez choisir un autre créneau libre dans le calendrier.";
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit;
                }
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO lives (
                            user_id, title, description, format, category_tag, section,
                            scheduled_date, scheduled_time, duration, author_name, author_role, author_avatar, resources, is_live_now, attendees_count
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1
                        )
                    ");
                    $stmt->execute([
                        $currentUser['id'] ?? null,
                        $title,
                        $desc,
                        $format,
                        $categoryTag,
                        $section,
                        $date,
                        $time,
                        $duration,
                        $author,
                        $role,
                        $avatar,
                        $resources
                    ]);

                    $liveId = $db->lastInsertId();

                    $createdLive = [
                        'id' => $liveId,
                        'title' => $title,
                        'format' => $format,
                        'date' => $date,
                        'time' => $time,
                        'duration' => $duration,
                        'author' => $author,
                        'role' => $role,
                        'section' => $section
                    ];

                    $success = true;

                    // Si requête AJAX
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'live' => $createdLive]);
                        exit;
                    }

                    set_flash('success', "Votre session « " . htmlspecialchars($title) . " » a été enregistrée avec succès dans le calendrier !");

                } catch (Exception $e) {
                    error_log("Database error in creer-live: " . $e->getMessage());
                    $error = "Une erreur est survenue lors de l'enregistrement de votre session. Veuillez vérifier vos données et réessayer.";
                }
            }
        }
    }
}

$isCreateLivePage = true;
$pageTitle = "Créer un Live ou un Mastermind — One Vision Community";
$pageDescription = "Proposez une session live ou un retour d'expérience à la communauté. Enregistrement direct dans le calendrier officiel des membres.";
$bodyClass = "create-live-body";

// Vérification de la formule : la création est réservée à la formule Créateur (29€/mois) ou admin/speaker/animateur
$userPlan = $currentUser['subscription_plan'] ?? 'member';
$isCreator = ($userPlan === 'creator') || is_animateur_user($currentUser);

// Permettre au testeur/admin de basculer facilement de rôle en local
if (isset($_GET['test_plan'])) {
    $sw = ($_GET['test_plan'] === 'creator') ? 'creator' : 'member';
    $db->prepare("UPDATE users SET subscription_plan = ? WHERE id = ?")->execute([$sw, $currentUser['id']]);
    header('Location: creer-live.php');
    exit;
}

// Récupération de tous les lives programmés pour alimenter le calendrier Calendly des disponibilités (30 jours)
$bookedLivesStmt = $db->query("
    SELECT id, title, scheduled_date, scheduled_time, duration, author_name, author_avatar, format 
    FROM lives 
    WHERE scheduled_date IS NOT NULL AND scheduled_date != ''
    ORDER BY scheduled_date ASC, scheduled_time ASC
");
$allBookedLives = $bookedLivesStmt->fetchAll(PDO::FETCH_ASSOC);

$bookedByDate = [];
foreach ($allBookedLives as $bl) {
    $bDate = trim($bl['scheduled_date']);
    $bTime = str_replace(':', 'h', trim($bl['scheduled_time']));
    if (!isset($bookedByDate[$bDate])) {
        $bookedByDate[$bDate] = [];
    }
    $bookedByDate[$bDate][$bTime] = $bl;
}

$standardTimeSlots = [
    '10h00' => '10h00 (Matinée)',
    '11h30' => '11h30 (Midi)',
    '14h00' => '14h00 (Début d\'après-midi)',
    '16h00' => '16h00 (Après-midi)',
    '18h00' => '18h00 (Fin de journée)',
    '19h00' => '19h00 (Soirée - Créneau privilégié)',
    '20h30' => '20h30 (Session nocturne)'
];

$calendarDays = [];
$todayDt = new DateTime('today');
$frDays = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
$frDaysFull = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$frMonths = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$frMonthsShort = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

for ($i = 0; $i < 30; $i++) {
    $dayObj = clone $todayDt;
    $dayObj->modify("+{$i} day");
    $dStr = $dayObj->format('Y-m-d');
    $wDay = (int)$dayObj->format('w');
    $mNum = (int)$dayObj->format('m');
    $dNum = $dayObj->format('d');
    $yNum = $dayObj->format('Y');

    $slotsForDay = [];
    $occupiedCount = 0;
    foreach ($standardTimeSlots as $sKey => $sLabel) {
        $isOccupied = isset($bookedByDate[$dStr][$sKey]);
        $liveData = $isOccupied ? $bookedByDate[$dStr][$sKey] : null;
        if ($isOccupied) $occupiedCount++;
        $slotsForDay[] = [
            'time' => $sKey,
            'label' => $sLabel,
            'occupied' => $isOccupied,
            'live' => $liveData
        ];
    }

    $freeCount = count($standardTimeSlots) - $occupiedCount;

    $calendarDays[] = [
        'date' => $dStr,
        'day_short' => $frDays[$wDay],
        'day_full' => $frDaysFull[$wDay],
        'day_num' => $dNum,
        'month_short' => $frMonthsShort[$mNum],
        'month_full' => $frMonths[$mNum],
        'year' => $yNum,
        'full_label' => "{$frDaysFull[$wDay]} {$dNum} {$frMonths[$mNum]} {$yNum}",
        'slots' => $slotsForDay,
        'occupied_count' => $occupiedCount,
        'free_count' => $freeCount,
        'is_full' => ($freeCount === 0),
        'is_today' => ($i === 0)
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

  <!-- MODAL DE CONFIRMATION D'ABANDON DE CRÉATION DE LIVE -->
  <div id="confirmQuitLiveModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(15,23,42,0.65); backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:1.25rem;">
    <div style="background:#ffffff; border-radius:20px; max-width:440px; width:100%; padding:2rem 1.75rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); text-align:center; border:1px solid #e2e8f0;">
      <div style="width:60px; height:60px; margin:0 auto 1.15rem; background:#fee2e2; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#dc2626;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </div>
      <h3 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin-bottom:0.5rem;">Êtes-vous sûr de quitter ?</h3>
      <p style="font-size:0.88rem; color:#64748b; line-height:1.55; margin-bottom:1.5rem;">
        Si vous quittez maintenant, la création de votre live ou mastermind sera annulée et toutes les informations saisies seront effacées.
      </p>
      <div style="display:flex; justify-content:center; gap:0.75rem;">
        <button type="button" class="btn btn-secondary" id="btnStayOnCreatePage" style="padding:0.6rem 1.15rem; font-weight:700;">
          Rester sur la page
        </button>
        <button type="button" class="btn btn-danger" id="btnConfirmQuitToDashboard" style="padding:0.6rem 1.15rem; font-weight:700; background:#dc2626; color:#ffffff; border:none; border-radius:10px; cursor:pointer;">
          Oui, quitter
        </button>
      </div>
    </div>
  </div>

  <!-- CONTENU PRINCIPAL : CRÉATION DU LIVE & APERÇU EN DIRECT -->
  <main class="create-live-main section">
    <div class="container">

      <?php if (!$isCreator): ?>
        <!-- ÉCRAN DE BLOCAGE POUR LES MEMBRES FORMULE 9€/MOIS (UPGRADE 29€/MOIS CRÉATEUR) -->
        <div class="creator-plan-lock-card" style="max-width:760px; margin:2rem auto; background:#ffffff; border:2px solid #e2e8f0; border-radius:24px; padding:2.85rem 2rem; text-align:center; box-shadow:0 15px 40px rgba(0,0,0,0.06);">
          <div style="width:72px; height:72px; border-radius:50%; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; margin:0 auto 1.35rem; font-size:2.2rem;">
            🎙️
          </div>
          <span style="display:inline-block; font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; background:#eff6ff; color:#2563eb; padding:4px 12px; border-radius:99px; margin-bottom:0.75rem;">
            Formule Créateur requise (29 € / mois)
          </span>
          <h2 style="font-size:1.75rem; font-weight:800; color:#0f172a; margin-bottom:0.85rem;">
            Animez vos propres Lives & Masterminds
          </h2>
          <p style="font-size:0.95rem; color:#64748b; line-height:1.6; max-width:580px; margin:0 auto 1.65rem;">
            Votre formule actuelle <strong>Membre Illimité (9€/mois)</strong> vous permet d'assister à tous les lives, masterminds, salons et replays en tant que participant. Pour programmer vos propres ateliers, animer des sessions et accroître votre visibilité auprès des <strong>+1 200 entrepreneurs</strong> de la communauté, passez à la formule <strong>Créateur (29€/mois)</strong> !
          </p>

          <div style="display:flex; justify-content:center; gap:0.85rem; flex-wrap:wrap; margin-bottom:1.75rem;">
            <a href="choisir-abonnement.php?plan=animateur" class="btn btn-primary btn-lg" style="text-decoration:none;">
              <span>Passer à la Formule Créateur (29€/mois)</span>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </a>
            <a href="dashboard.php" class="btn btn-secondary btn-lg" style="text-decoration:none;">
              <span>Retour à mon espace</span>
            </a>
          </div>

          <div style="font-size:0.82rem; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:1.25rem;">
            💡 <em>Mode Démonstration & Test local :</em> <a href="creer-live.php?test_plan=creator" style="color:#2563eb; font-weight:700; text-decoration:underline;">Activer le rôle Créateur pour mon compte</a>
          </div>
        </div>
      <?php else: ?>

      <!-- En-tête de la page -->
      <div class="create-live-intro">
        <div class="badge badge-accent mb-2">
          <span class="pulse-status-dot"></span>
          <span>Espace Collaboratif Ouvert à Tous les Membres</span>
        </div>
        <h1 class="create-live-title">Créer un Live ou un Mastermind</h1>
        <p class="create-live-subtitle">
          Vous souhaitez partager un retour d'expérience concret, animer un atelier thématique ou organiser un mastermind d'échange ? Renseignez votre sujet ci-dessous : votre session sera immédiatement enregistrée dans la base de données et ajoutée au calendrier de la communauté.
        </p>
      </div>

      <?php if (!empty($error)): ?>
        <div style="max-width:800px; margin: 0 auto 1.5rem; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:1rem 1.25rem; border-radius:12px; font-size:0.95rem; display:flex; align-items:center; gap:0.75rem;">
          <span>⚠️</span>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- Écran de confirmation de publication (affiché dès que $success est vrai) -->
      <div class="create-live-success-screen" id="createLiveSuccessScreen" style="<?= $success ? 'display:block;' : 'display:none;' ?>">
        <div class="success-card">
          <div class="success-icon-badge">🎉</div>
          <h2 class="success-title">Votre session est enregistrée !</h2>
          <p class="success-desc">
            Félicitations <strong><?= htmlspecialchars($createdLive['author'] ?? $currentUser['full_name']) ?></strong> ! Votre intervention est maintenant programmée et visible dans le calendrier officiel des membres.
          </p>

          <div class="success-recap-box" id="successRecapBox">
            <?php if ($createdLive): ?>
              <div class="recap-row">
                <span class="recap-label">Titre de la session :</span>
                <span class="recap-value">« <?= htmlspecialchars($createdLive['title']) ?> »</span>
              </div>
              <div class="recap-row">
                <span class="recap-label">Date & Heure :</span>
                <span class="recap-value"><?= htmlspecialchars($createdLive['date']) ?> à <?= htmlspecialchars($createdLive['time']) ?> (<?= htmlspecialchars($createdLive['duration']) ?>)</span>
              </div>
              <div class="recap-row">
                <span class="recap-label">Format :</span>
                <span class="recap-value"><?= htmlspecialchars($createdLive['format']) ?></span>
              </div>
              <div class="recap-row">
                <span class="recap-label">Animateur :</span>
                <span class="recap-value"><?= htmlspecialchars($createdLive['author']) ?> (<?= htmlspecialchars($createdLive['role']) ?>)</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="success-actions">
            <a href="dashboard.php" class="btn btn-primary btn-lg" id="btnGoToCalendar">
              <span>Voir dans mon Dashboard</span>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </a>
            <a href="creer-live.php" class="btn btn-secondary">
              <span>Programmer une autre session</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Grille à 2 colonnes : Formulaire de création (gauche) + Aperçu dynamique (droite) -->
      <div class="create-live-grid" id="createLiveGrid" style="<?= $success ? 'display:none;' : 'display:grid;' ?>">

        <!-- Colonne Gauche : Formulaire de programmation -->
        <div class="create-live-form-col">
          <form id="createLiveForm" method="POST" action="creer-live.php" class="create-live-form">
            <?= csrf_field() ?>

            <!-- Étape 1 : Format de l'intervention -->
            <div class="form-card">
              <div class="form-card-header">
                <span class="form-step-number">1</span>
                <div>
                  <h2 class="form-card-title">Format de la session</h2>
                  <p class="form-card-desc">Choisissez la dynamique qui correspond le mieux à votre intervention.</p>
                </div>
              </div>

              <div class="format-options-grid">
                <label class="format-option-card">
                  <input type="radio" name="liveFormat" value="Live Thématique" checked>
                  <div class="format-card-content">
                    <span class="format-icon">🎙️</span>
                    <strong class="format-name">Live Thématique</strong>
                    <span class="format-desc">Présentation de conseils, méthodes actionnables et questions/réponses.</span>
                  </div>
                </label>

                <label class="format-option-card">
                  <input type="radio" name="liveFormat" value="Mastermind Échange">
                  <div class="format-card-content">
                    <span class="format-icon">🧠</span>
                    <strong class="format-name">Mastermind Échange</strong>
                    <span class="format-desc">Brainstorming collectif, déblocage de freins et retours des pairs.</span>
                  </div>
                </label>

                <label class="format-option-card">
                  <input type="radio" name="liveFormat" value="Retour d'Expérience">
                  <div class="format-card-content">
                    <span class="format-icon">🚀</span>
                    <strong class="format-name">Retour d'Expérience</strong>
                    <span class="format-desc">Partage d'un cas réel, chiffre, succès ou leçon tirée d'un échec.</span>
                  </div>
                </label>

                <label class="format-option-card">
                  <input type="radio" name="liveFormat" value="Atelier Co-Working">
                  <div class="format-card-content">
                    <span class="format-icon">💻</span>
                    <strong class="format-name">Atelier Co-Working</strong>
                    <span class="format-desc">Travail collaboratif guidé, feedback en direct sur vos projets.</span>
                  </div>
                </label>
              </div>
            </div>

            <!-- Étape 2 : Sujet & Contenu du Live -->
            <div class="form-card">
              <div class="form-card-header">
                <span class="form-step-number">2</span>
                <div>
                  <h2 class="form-card-title">Thème & Sujet de l'intervention</h2>
                  <p class="form-card-desc">Rendez votre session irrésistible et claire pour les membres.</p>
                </div>
              </div>

              <!-- Titre du Live -->
              <div class="form-group mb-4">
                <div class="form-label-row">
                  <label for="liveTitle" class="form-label">Titre ou Thème principal *</label>
                  <button type="button" class="btn-preset-example" id="btnFillExample" title="Remplir avec une idée inspirante">
                    💡 Idée d'exemple
                  </button>
                </div>
                <input 
                  type="text" 
                  id="liveTitle" 
                  name="liveTitle"
                  class="form-input" 
                  placeholder="Ex : Comment j'ai signé 3 clients à 2 500€ en 30 jours sans prospection froide" 
                  maxlength="110" 
                  required
                >
                <span class="form-hint">Un titre direct mettant en avant le résultat concret ou le bénéfice pour les membres.</span>
              </div>

              <!-- Thématique / Domaine -->
              <div class="form-group mb-4">
                <label for="liveCategoryTag" class="form-label">Domaine d'expertise *</label>
                <select id="liveCategoryTag" name="liveCategoryTag" class="form-input form-select">
                  <option value="Retour d'Expérience">🚀 Retour d'Expérience & Cas Réel</option>
                  <option value="Acquisition & Vente">💼 Vente, Négociation & Closing</option>
                  <option value="Copywriting & Offres">✍️ Copywriting & Pages de Vente</option>
                  <option value="IA & Productivité">🤖 Intelligence Artificielle & No-Code</option>
                  <option value="Mastermind Collectif">🧠 Mastermind & Brainstorming</option>
                  <option value="Mindset & Organisation">⚡ Organisation & Clarté Mentale</option>
                  <option value="Atelier Pratique">🛠️ Atelier Pratique & Co-working</option>
                </select>
              </div>

              <!-- Description -->
              <div class="form-group mb-4">
                <label for="liveDesc" class="form-label">Description & Ce que les membres vont retirer *</label>
                <textarea 
                  id="liveDesc" 
                  name="liveDesc"
                  class="form-input form-textarea" 
                  rows="4" 
                  placeholder="Expliquez en 2 ou 3 phrases le déroulement : vos apprentissages clés, vos étapes vécues et le temps réservé aux questions des membres..."
                  required
                ></textarea>
                <span class="form-hint">Soyez direct et authentique. Les membres apprécient la transparence et les cas pratiques.</span>
              </div>

              <!-- Ressource / Document offert -->
              <div class="form-group">
                <label for="liveResources" class="form-label">Ressources fournies aux participants (optionnel)</label>
                <input 
                  type="text" 
                  id="liveResources" 
                  name="liveResources"
                  class="form-input" 
                  placeholder="Ex : Template Notion offert, Fiche PDF récapitulative, Grille Excel..."
                >
              </div>
            </div>

            <!-- Étape 3 : Date & Créneaux disponibles (Calendrier 30 jours façon Calendly) -->
            <div class="form-card">
              <div class="form-card-header">
                <span class="form-step-number">3</span>
                <div>
                  <h2 class="form-card-title">Date & Créneau horaire (Disponibilités en temps réel façon Calendly)</h2>
                  <p class="form-card-desc">Sélectionnez une date parmi les 30 prochains jours. Les créneaux déjà réservés par d'autres animateurs sont automatiquement verrouillés pour garantir l'absence de conflit.</p>
                </div>
              </div>

              <!-- Légende des disponibilités -->
              <div class="calendly-legend" style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;padding:0.75rem 1rem;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:1.25rem;font-size:0.84rem;">
                <div style="display:flex;align-items:center;gap:0.4rem;font-weight:600;color:#16a34a;">
                  <span style="width:10px;height:10px;border-radius:50%;background:#16a34a;display:inline-block;"></span>
                  <span>Créneau disponible</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;font-weight:600;color:#dc2626;">
                  <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;"></span>
                  <span>Déjà réservé (Verrouillé)</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;font-weight:600;color:#2563eb;">
                  <span style="width:10px;height:10px;border-radius:50%;background:#2563eb;display:inline-block;"></span>
                  <span>Votre sélection</span>
                </div>
              </div>

              <!-- Sélecteur de date (30 jours glissants) -->
              <div class="calendly-days-container" style="margin-bottom:1.5rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.65rem;">
                  <span style="font-weight:700;font-size:0.92rem;color:#0f172a;">1. Choisissez une date parmi les 30 prochains jours :</span>
                  <div style="display:flex;gap:0.35rem;">
                    <button type="button" id="btnCalScrollLeft" class="btn-cal-nav" style="padding:4px 9px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;cursor:pointer;font-size:0.85rem;" title="Défiler à gauche">◀</button>
                    <button type="button" id="btnCalScrollRight" class="btn-cal-nav" style="padding:4px 9px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;cursor:pointer;font-size:0.85rem;" title="Défiler à droite">▶</button>
                  </div>
                </div>

                <div id="calendlyDaysScroll" style="display:flex;gap:0.65rem;overflow-x:auto;padding-bottom:0.75rem;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;">
                  <?php foreach ($calendarDays as $idx => $cDay): ?>
                    <button 
                      type="button" 
                      class="cal-day-card <?= $cDay['is_today'] ? 'is-today' : '' ?> <?= $cDay['is_full'] ? 'is-full' : '' ?>"
                      data-date="<?= $cDay['date'] ?>"
                      data-full-label="<?= htmlspecialchars($cDay['full_label']) ?>"
                      data-free-count="<?= $cDay['free_count'] ?>"
                      data-occupied-count="<?= $cDay['occupied_count'] ?>"
                      style="flex:0 0 92px;min-width:92px;padding:0.75rem 0.5rem;text-align:center;border-radius:12px;border:2px solid #e2e8f0;background:#ffffff;cursor:pointer;transition:all 0.2s ease;"
                    >
                      <div class="cal-day-name" style="font-size:0.75rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:2px;"><?= $cDay['day_short'] ?></div>
                      <div class="cal-day-num" style="font-size:1.35rem;font-weight:800;color:#0f172a;line-height:1.1;margin-bottom:2px;"><?= $cDay['day_num'] ?></div>
                      <div class="cal-day-month" style="font-size:0.72rem;color:#94a3b8;margin-bottom:6px;"><?= $cDay['month_short'] ?></div>
                      <?php if ($cDay['is_full']): ?>
                        <span class="cal-day-badge badge-full" style="font-size:0.65rem;font-weight:700;color:#dc2626;background:#fee2e2;padding:2px 5px;border-radius:99px;display:block;">Complet</span>
                      <?php elseif ($cDay['occupied_count'] > 0): ?>
                        <span class="cal-day-badge badge-partial" style="font-size:0.65rem;font-weight:700;color:#b45309;background:#fef3c7;padding:2px 5px;border-radius:99px;display:block;"><?= $cDay['free_count'] ?> libre<?= $cDay['free_count'] > 1 ? 's' : '' ?></span>
                      <?php else: ?>
                        <span class="cal-day-badge badge-free" style="font-size:0.65rem;font-weight:700;color:#15803d;background:#dcfce7;padding:2px 5px;border-radius:99px;display:block;"><?= $cDay['free_count'] ?> libres</span>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Grille des créneaux horaires pour le jour sélectionné -->
              <div class="calendly-slots-wrapper" style="border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;background:#ffffff;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
                  <div>
                    <span style="font-size:0.8rem;text-transform:uppercase;font-weight:700;color:#64748b;letter-spacing:0.5px;">2. Créneaux disponibles pour :</span>
                    <h3 id="calSelectedDayLabel" style="font-size:1.1rem;font-weight:800;color:#0f172a;margin:0.2rem 0 0 0;">Chargement...</h3>
                  </div>
                  <span id="calDayAvailabilityStatus" style="font-size:0.8rem;font-weight:700;padding:4px 10px;border-radius:99px;background:#eff6ff;color:#2563eb;">Disponibilités en direct</span>
                </div>

                <!-- Grille dynamique des slots alimentée en JS -->
                <div id="calSlotsGrid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(210px, 1fr));gap:0.75rem;margin-bottom:1.15rem;">
                  <!-- Rempli dynamiquement -->
                </div>

                <!-- Récapitulatif du créneau actif sélectionné -->
                <div id="calChosenRecapBanner" style="display:flex;align-items:center;gap:0.75rem;padding:0.85rem 1.15rem;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:0.92rem;">
                  <span style="font-size:1.3rem;">✅</span>
                  <div>
                    <strong>Créneau retenu pour votre live :</strong><br>
                    <span id="calChosenSummaryText">Vendredi 25 Octobre 2026 à 19h00</span>
                  </div>
                </div>
              </div>

              <!-- Champs masqués synchronisés pour l'enregistrement BDD -->
              <input type="hidden" id="liveDate" name="liveDate" value="<?= htmlspecialchars($calendarDays[0]['date'] ?? date('Y-m-d')) ?>" required>
              <input type="hidden" id="liveTime" name="liveTime" value="19h00" required>

              <div class="form-row-2 mt-4">
                <div class="form-group">
                  <label for="liveDuration" class="form-label">Durée estimée *</label>
                  <select id="liveDuration" name="liveDuration" class="form-input form-select">
                    <option value="45 min">45 minutes</option>
                    <option value="1h00" selected>1 heure</option>
                    <option value="1h30">1 heure 30 minutes</option>
                    <option value="2h00">2 heures</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="liveTabSection" class="form-label">Période d'affichage *</label>
                  <select id="liveTabSection" name="liveTabSection" class="form-input form-select">
                    <option value="current" selected>Cette semaine (Programme immédiat)</option>
                    <option value="upcoming">Semaine prochaine (À venir)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Étape 4 : Profil de l'Animateur -->
            <div class="form-card">
              <div class="form-card-header">
                <span class="form-step-number">4</span>
                <div>
                  <h2 class="form-card-title">Votre profil d'intervenant</h2>
                  <p class="form-card-desc">Indiquez votre nom et votre spécialité pour que les membres vous identifient.</p>
                </div>
              </div>

              <div class="form-row-2">
                <div class="form-group">
                  <label for="liveAuthor" class="form-label">Votre Nom & Prénom *</label>
                  <input type="text" id="liveAuthor" name="liveAuthor" class="form-input" value="<?= htmlspecialchars($currentUser['full_name'] ?? 'Katahana Désiré') ?>" required>
                </div>

                <div class="form-group">
                  <label for="liveRole" class="form-label">Votre rôle ou domaine d'activité *</label>
                  <input type="text" id="liveRole" name="liveRole" class="form-input" value="<?= htmlspecialchars($currentUser['job_title'] ?? 'Stratège Digital & Membre One Vision') ?>" required>
                </div>
              </div>

              <div class="form-group mt-3">
                <label class="form-label">Photo ou avatar de présentation</label>
                <div class="avatar-selection-grid">
                  <label class="avatar-option">
                    <input type="radio" name="liveAvatar" value="./img/avatar-maxime.jpg" <?= ($currentUser['avatar'] ?? '') === './img/avatar-maxime.jpg' || empty($currentUser['avatar']) ? 'checked' : '' ?>>
                    <img src="./img/avatar-maxime.jpg" alt="Avatar 1" class="avatar-choice-img">
                  </label>
                  <label class="avatar-option">
                    <input type="radio" name="liveAvatar" value="./img/avatar-cyril.jpg" <?= ($currentUser['avatar'] ?? '') === './img/avatar-cyril.jpg' ? 'checked' : '' ?>>
                    <img src="./img/avatar-cyril.jpg" alt="Avatar Cyril" class="avatar-choice-img">
                  </label>
                  <label class="avatar-option">
                    <input type="radio" name="liveAvatar" value="./img/avatar-sophie.jpg" <?= ($currentUser['avatar'] ?? '') === './img/avatar-sophie.jpg' ? 'checked' : '' ?>>
                    <img src="./img/avatar-sophie.jpg" alt="Avatar Sophie" class="avatar-choice-img">
                  </label>
                  <label class="avatar-option">
                    <input type="radio" name="liveAvatar" value="./img/avatar-thomas.jpg" <?= ($currentUser['avatar'] ?? '') === './img/avatar-thomas.jpg' ? 'checked' : '' ?>>
                    <img src="./img/avatar-thomas.jpg" alt="Avatar Thomas" class="avatar-choice-img">
                  </label>
                  <label class="avatar-option">
                    <input type="radio" name="liveAvatar" value="./img/avatar-aurore.jpg">
                    <img src="./img/avatar-aurore.jpg" alt="Avatar Aurore" class="avatar-choice-img">
                  </label>
                  <label class="avatar-option">
                    <input type="radio" name="liveAvatar" value="./img/avatar-florian.jpg">
                    <img src="./img/avatar-florian.jpg" alt="Avatar Florian" class="avatar-choice-img">
                  </label>
                </div>
              </div>
            </div>

            <!-- Bouton de soumission -->
            <div class="form-actions-bar">
              <button type="submit" class="btn btn-primary btn-lg btn-block" id="submitLiveBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                <span>Publier et Enregistrer dans le Calendrier BDD</span>
              </button>
              <p class="form-legal-note">
                🔒 Votre session sera instantanément enregistrée dans la base de données et affichée dans le calendrier officiel des membres.
              </p>
            </div>

          </form>
        </div>

        <!-- Colonne Droite : Prévisualisation en direct -->
        <div class="create-live-preview-col">
          <div class="sticky-preview-wrapper">
            
            <div class="preview-box-header">
              <div class="preview-badge-status">
                <span class="pulse-status-dot"></span>
                <span>Aperçu en direct dans le calendrier</span>
              </div>
              <span class="preview-pill-tip">Mise à jour instantanée</span>
            </div>

            <div class="live-preview-container">
              <article class="live-card preview-live-card" id="previewLiveCard">
                <div>
                  <div class="live-card-top">
                    <span class="live-tag" id="previewTag">🚀 Retour d'Expérience</span>
                    <span class="live-date" id="previewDateBadge">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                      </svg>
                      <span id="previewDateText">Prochainement • 19h00 (Session Membre)</span>
                    </span>
                  </div>
                  
                  <div class="live-member-pill-indicator">
                    <span>🤝 Session animée par un membre</span>
                  </div>

                  <h4 class="live-card-title" id="previewTitle">
                    Comment j'ai signé 3 clients à 2 500€ en 30 jours sans prospection froide
                  </h4>
                  <p class="live-card-desc" id="previewDesc">
                    Partage d'un cas réel sans langue de bois : mon offre d'appel, la trame de cadrage utilisée et les erreurs évitées. Séance ouverte de questions-réponses avec les membres.
                  </p>
                </div>

                <div class="live-card-footer">
                  <div class="speaker-info">
                    <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="Intervenant" class="speaker-avatar" id="previewAvatarImg">
                    <div>
                      <span class="speaker-name" id="previewAuthor"><?= htmlspecialchars($currentUser['full_name'] ?? 'Katahana Désiré') ?></span>
                      <span class="speaker-role" id="previewRole"><?= htmlspecialchars($currentUser['job_title'] ?? 'Stratège Digital') ?></span>
                    </div>
                  </div>
                  <div class="live-meta-info">
                    <span class="live-duration" id="previewDurationText">⏱️ 1h00</span>
                    <span class="live-badge-access">Inclus à 9€</span>
                  </div>
                </div>
              </article>
            </div>

            <div class="preview-tip-card">
              <div class="tip-card-header">
                <span class="tip-icon">💡</span>
                <strong>Conseils pour une session percutante</strong>
              </div>
              <ul class="tip-checklist">
                <li>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <span><strong>Soyez spécifique :</strong> Privilégiez un cas pratique précis plutôt qu'un concept abstrait.</span>
                </li>
                <li>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <span><strong>Prévoyez 20 min de Q&A :</strong> Les échanges directs créent le plus de valeur.</span>
                </li>
                <li>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <span><strong>Offrez un template :</strong> Une ressource offerte maximise l'engagement.</span>
                </li>
              </ul>
            </div>

          </div>
        </div>

      </div>

      <?php endif; ?>

    </div>
  </main>

  <style>
    .cal-day-card {
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      outline: none;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .cal-day-card:hover:not(.is-full) {
      transform: translateY(-2px);
      border-color: #3b82f6 !important;
      box-shadow: 0 4px 12px rgba(59,130,246,0.15);
    }
    .cal-day-card.selected {
      border-color: #2563eb !important;
      background: #eff6ff !important;
      box-shadow: 0 0 0 3px rgba(37,99,235,0.2) !important;
    }
    .cal-day-card.selected .cal-day-num {
      color: #2563eb !important;
    }
    .cal-day-card.is-full {
      opacity: 0.6;
      cursor: not-allowed !important;
      background: #f8fafc !important;
    }
    .btn-cal-slot {
      width: 100%;
      padding: 0.85rem 1rem;
      border-radius: 12px;
      text-align: left;
      transition: all 0.2s ease;
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
      position: relative;
    }
    .btn-cal-slot.is-free {
      background: #ffffff;
      border: 2px solid #e2e8f0;
      cursor: pointer;
    }
    .btn-cal-slot.is-free:hover {
      border-color: #3b82f6;
      background: #f8fafc;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(37,99,235,0.1);
    }
    .btn-cal-slot.is-free.selected {
      border-color: #2563eb !important;
      background: #eff6ff !important;
      box-shadow: 0 0 0 3px rgba(37,99,235,0.25) !important;
    }
    .btn-cal-slot.is-occupied {
      background: #fff1f2;
      border: 1px solid #fecdd3;
      color: #9f1239;
      cursor: not-allowed;
      opacity: 0.88;
    }
  </style>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const daysData = <?= json_encode($calendarDays, JSON_UNESCAPED_UNICODE) ?>;
    const dayCards = document.querySelectorAll('.cal-day-card');
    const slotsGrid = document.getElementById('calSlotsGrid');
    const dayLabelEl = document.getElementById('calSelectedDayLabel');
    const availabilityBadge = document.getElementById('calDayAvailabilityStatus');
    const hiddenDateInput = document.getElementById('liveDate');
    const hiddenTimeInput = document.getElementById('liveTime');
    const chosenSummaryText = document.getElementById('calChosenSummaryText');
    const previewDateText = document.getElementById('previewDateText');

    const scrollContainer = document.getElementById('calendlyDaysScroll');
    const btnScrollLeft = document.getElementById('btnCalScrollLeft');
    const btnScrollRight = document.getElementById('btnCalScrollRight');

    if (btnScrollLeft && scrollContainer) {
      btnScrollLeft.addEventListener('click', () => {
        scrollContainer.scrollBy({ left: -270, behavior: 'smooth' });
      });
    }
    if (btnScrollRight && scrollContainer) {
      btnScrollRight.addEventListener('click', () => {
        scrollContainer.scrollBy({ left: 270, behavior: 'smooth' });
      });
    }

    let currentSelectedDate = '';
    let currentSelectedTime = '19h00';

    function renderDaySlots(dateStr) {
      const day = daysData.find(d => d.date === dateStr);
      if (!day || !slotsGrid) return;

      currentSelectedDate = day.date;
      if (hiddenDateInput) hiddenDateInput.value = currentSelectedDate;
      if (dayLabelEl) dayLabelEl.textContent = day.full_label;

      if (availabilityBadge) {
        if (day.is_full) {
          availabilityBadge.textContent = '🔒 Tous les créneaux sont réservés';
          availabilityBadge.style.background = '#fee2e2';
          availabilityBadge.style.color = '#dc2626';
        } else if (day.occupied_count > 0) {
          availabilityBadge.textContent = `⚡ ${day.free_count} créneau${day.free_count > 1 ? 'x' : ''} disponible${day.free_count > 1 ? 's' : ''} (${day.occupied_count} déjà réservé)`;
          availabilityBadge.style.background = '#fef3c7';
          availabilityBadge.style.color = '#b45309';
        } else {
          availabilityBadge.textContent = `🟢 ${day.free_count} créneaux disponibles`;
          availabilityBadge.style.background = '#dcfce7';
          availabilityBadge.style.color = '#15803d';
        }
      }

      slotsGrid.innerHTML = '';

      // Vérifier si le créneau actuellement sélectionné est libre sur ce jour
      const currentSlotIsFree = day.slots.some(s => s.time === currentSelectedTime && !s.occupied);
      if (!currentSlotIsFree) {
        const firstFree = day.slots.find(s => !s.occupied);
        if (firstFree) {
          currentSelectedTime = firstFree.time;
          if (hiddenTimeInput) hiddenTimeInput.value = currentSelectedTime;
        }
      }

      day.slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';

        if (slot.occupied) {
          btn.className = 'btn-cal-slot is-occupied';
          btn.disabled = true;
          const liveAuthor = slot.live?.author_name || 'Autre Animateur';
          const liveTitle = slot.live?.title || 'Session réservée';
          btn.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;width:100%;">
              <strong style="font-size:1.02rem;text-decoration:line-through;color:#9f1239;">${slot.time}</strong>
              <span style="font-size:0.7rem;font-weight:700;background:#fee2e2;color:#b91c1c;padding:3px 7px;border-radius:99px;border:1px solid #fecdd3;">🔒 Déjà Réservé</span>
            </div>
            <div style="font-size:0.78rem;color:#881337;line-height:1.35;margin-top:2px;">
              Animé par <strong>${escapeHtml(liveAuthor)}</strong><br>
              <em style="color:#9f1239;">« ${escapeHtml(liveTitle.length > 36 ? liveTitle.substring(0, 36) + '...' : liveTitle)} »</em>
            </div>
          `;
        } else {
          const isSelected = (slot.time === currentSelectedTime);
          btn.className = `btn-cal-slot is-free ${isSelected ? 'selected' : ''}`;
          btn.setAttribute('data-time', slot.time);
          btn.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;width:100%;">
              <strong style="font-size:1.02rem;color:${isSelected ? '#2563eb' : '#0f172a'};">${slot.time}</strong>
              <span style="font-size:0.7rem;font-weight:700;background:#dcfce7;color:#15803d;padding:3px 7px;border-radius:99px;">🟢 Libre</span>
            </div>
            <div style="font-size:0.78rem;color:#64748b;">
              ${slot.label.split('(')[1]?.replace(')', '') || 'Créneau standard'}
            </div>
          `;

          btn.addEventListener('click', () => {
            currentSelectedTime = slot.time;
            if (hiddenTimeInput) hiddenTimeInput.value = currentSelectedTime;
            document.querySelectorAll('.btn-cal-slot.is-free').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            updateRecapAndPreview(day);
          });
        }

        slotsGrid.appendChild(btn);
      });

      updateRecapAndPreview(day);
    }

    function updateRecapAndPreview(day) {
      if (chosenSummaryText && day) {
        chosenSummaryText.textContent = `${day.full_label} à ${currentSelectedTime}`;
      }
      if (previewDateText && day) {
        previewDateText.textContent = `${day.day_full} ${day.day_num} ${day.month_short} • ${currentSelectedTime} (Session Membre)`;
      }
    }

    // Clic sur une carte de jour
    dayCards.forEach(card => {
      card.addEventListener('click', () => {
        dayCards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        const dateVal = card.getAttribute('data-date');
        renderDaySlots(dateVal);
      });
    });

    // Sélection initiale : priorité à une date avec des lives réservés (ex: 2026-10-25)
    let initialCard = document.querySelector('.cal-day-card[data-date="2026-10-25"]') || dayCards[0];
    if (initialCard) {
      initialCard.classList.add('selected');
      renderDaySlots(initialCard.getAttribute('data-date'));
      setTimeout(() => {
        initialCard.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      }, 300);
    }
  });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
