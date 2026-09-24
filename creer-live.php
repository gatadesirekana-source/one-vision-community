<?php
/**
 * ONE VISION COMMUNITY — CRÉER UN LIVE OU UN MASTERMIND (PHP & BDD SQLITE)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

// Protection : seuls les membres connectés peuvent planifier un live officiel
require_auth('login.php');

$currentUser = current_user();
$db = get_db();

$success = false;
$createdLive = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si token CSRF fourni, le vérifier
    if (isset($_POST['csrf_token']) && !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Session expirée. Veuillez actualiser la page et réessayer.";
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
                $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Créer un Live ou un Mastermind — One Vision Community";
$pageDescription = "Proposez une session live ou un retour d'expérience à la communauté. Enregistrement direct dans le calendrier officiel des membres.";
$bodyClass = "create-live-body";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- CONTENU PRINCIPAL : CRÉATION DU LIVE & APERÇU EN DIRECT -->
  <main class="create-live-main section">
    <div class="container">

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

            <!-- Étape 3 : Date, Heure & Durée -->
            <div class="form-card">
              <div class="form-card-header">
                <span class="form-step-number">3</span>
                <div>
                  <h2 class="form-card-title">Date & Planification</h2>
                  <p class="form-card-desc">Choisissez quand aura lieu votre intervention.</p>
                </div>
              </div>

              <div class="form-row-2">
                <div class="form-group">
                  <label for="liveDate" class="form-label">Date de la session *</label>
                  <input type="date" id="liveDate" name="liveDate" class="form-input" required>
                </div>

                <div class="form-group">
                  <label for="liveTime" class="form-label">Heure de début *</label>
                  <select id="liveTime" name="liveTime" class="form-input form-select">
                    <option value="11h00">11h00 (Matinée)</option>
                    <option value="12h30">12h30 (Pause déjeuner)</option>
                    <option value="14h00">14h00 (Début d'après-midi)</option>
                    <option value="18h00">18h00 (Fin de journée)</option>
                    <option value="18h30">18h30 (Afterwork)</option>
                    <option value="19h00" selected>19h00 (Créneau privilégié)</option>
                    <option value="20h00">20h00 (Soirée)</option>
                    <option value="20h30">20h30 (Session nocturne)</option>
                  </select>
                </div>
              </div>

              <div class="form-row-2 mt-3">
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

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
