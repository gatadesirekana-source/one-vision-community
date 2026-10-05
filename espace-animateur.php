<?php
/**
 * ONE VISION COMMUNITY — ESPACE ANIMATEUR DÉDIÉ (PHP & SQLITE)
 * 
 * Espace réservé aux abonnés de la Formule Animateur (et administrateurs).
 * Contrôle strict côté serveur : tout Membre tentant d'accéder directement est redirigé vers abonnements.php.
 * Permet de :
 * - Programmer, modifier, annuler et suivre ses propres lives & masterminds
 * - Consulter ses statistiques d'audience (inscrits, présents)
 * - Mettre en valeur sa page et son profil d'expert certifié One Vision
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/subscriptions.php';

// Vérification stricte d'accès côté serveur (Section 3) :
// Si le visiteur est un Membre, redirection immédiate vers abonnements.php
require_animateur_access('abonnements.php');

$db = get_db();
$currentUser = current_user();
$userId = (int)$currentUser['id'];
$isOwner = is_owner($currentUser);

// Traitement des actions (annulation, republication de live, mise à jour du profil expert)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', "Session expirée. Veuillez actualiser et réessayer.");
        header('Location: espace-animateur.php');
        exit;
    }

    $action = trim($_POST['action'] ?? '');

    if ($action === 'annuler_live') {
        $liveId = (int)($_POST['live_id'] ?? 0);
        
        // Vérification stricte que l'animateur ne modifie que son propre live (sauf propriétaire)
        $stmtCheck = $db->prepare("SELECT * FROM lives WHERE id = ?");
        $stmtCheck->execute([$liveId]);
        $targetLive = $stmtCheck->fetch();

        if (!$targetLive) {
            set_flash('error', "Session live introuvable.");
        } elseif (!$isOwner && (int)$targetLive['user_id'] !== $userId) {
            set_flash('error', "Accès refusé : vous ne pouvez modifier que vos propres lives.");
        } else {
            $stmtCancel = $db->prepare("UPDATE lives SET status = 'annule' WHERE id = ?");
            $stmtCancel->execute([$liveId]);
            set_flash('success', "La session « " . htmlspecialchars($targetLive['title']) . " » a été annulée.");
        }
        header('Location: espace-animateur.php');
        exit;
    } elseif ($action === 'publier_live') {
        $liveId = (int)($_POST['live_id'] ?? 0);
        $stmtCheck = $db->prepare("SELECT * FROM lives WHERE id = ?");
        $stmtCheck->execute([$liveId]);
        $targetLive = $stmtCheck->fetch();

        if ($targetLive && ($isOwner || (int)$targetLive['user_id'] === $userId)) {
            $stmtPub = $db->prepare("UPDATE lives SET status = 'publie' WHERE id = ?");
            $stmtPub->execute([$liveId]);
            set_flash('success', "La session « " . htmlspecialchars($targetLive['title']) . " » est désormais publiée et visible dans le calendrier.");
        }
        header('Location: espace-animateur.php');
        exit;
    } elseif ($action === 'update_expert_profile') {
        $jobTitle = trim($_POST['job_title'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $skills = trim($_POST['skills'] ?? '');

        $stmtUp = $db->prepare("
            UPDATE users 
            SET job_title = ?, company = ?, bio = ?, skills = ?
            WHERE id = ?
        ");
        $stmtUp->execute([$jobTitle, $company, $bio, $skills, $userId]);
        set_flash('success', "Votre profil expert a été mis à jour avec succès.");
        header('Location: espace-animateur.php');
        exit;
    }
}

// Récupération des lives de l'animateur (ou tous les lives si propriétaire)
if ($isOwner) {
    $stmtLives = $db->prepare("SELECT * FROM lives ORDER BY scheduled_date DESC, scheduled_time DESC");
    $stmtLives->execute();
} else {
    $stmtLives = $db->prepare("SELECT * FROM lives WHERE user_id = ? ORDER BY scheduled_date DESC, scheduled_time DESC");
    $stmtLives->execute([$userId]);
}
$myLives = $stmtLives->fetchAll();

// Statistiques sur les lives de l'animateur
$totalLives = count($myLives);
$totalAttendees = 0;
$publishedCount = 0;
$draftCount = 0;

foreach ($myLives as $l) {
    $totalAttendees += (int)($l['attendees_count'] ?? 0);
    $st = $l['status'] ?? 'publie';
    if ($st === 'publie') $publishedCount++;
    if ($st === 'brouillon') $draftCount++;
}

$pageTitle = "Espace Animateur — One Vision Community";
$pageDescription = "Votre espace dédié pour créer, gérer et suivre vos sessions Live et Masterminds.";
require_once __DIR__ . '/includes/header.php';
?>

<main class="section host-section" style="min-height: calc(100vh - 260px); padding: 3rem 1rem; background: #f8fafc;">
  <div class="container" style="max-width: 1040px; margin: 0 auto;">

    <!-- En-tête Espace Animateur avec badge officiel -->
    <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #ffffff; border-radius: 20px; padding: 2.25rem 2rem; margin-bottom: 2rem; box-shadow: 0 15px 35px rgba(15,23,42,0.15); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
      <div style="display:flex; align-items:center; gap:1.25rem;">
        <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="Avatar" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid #f97316;">
        <div>
          <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap; margin-bottom:0.35rem;">
            <h1 style="font-size: 1.65rem; font-weight: 800; color: #ffffff; margin: 0;">
              Espace Animateur
            </h1>
            <span style="background:linear-gradient(135deg, #f97316, #ea580c); color:#ffffff; font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; padding:0.25rem 0.75rem; border-radius:20px; display:inline-flex; align-items:center; gap:0.35rem;">
              <span>🏅</span> Animateur certifié One Vision
            </span>
          </div>
          <p style="color: #94a3b8; font-size: 0.95rem; margin: 0;">
            Bonjour <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>. Programmez vos interventions et partagez votre savoir-faire auprès de la communauté.
          </p>
        </div>
      </div>

      <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <a href="creer-live.php" class="btn btn-primary" style="background:linear-gradient(135deg, #f97316, #ea580c); font-weight:700; padding:0.75rem 1.4rem; border-radius:12px; display:inline-flex; align-items:center; gap:0.5rem; box-shadow:0 4px 15px rgba(249,115,22,0.35);">
          <span>🎙️ Programmer un Live</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </a>
      </div>
    </div>

    <!-- 1. STATISTIQUES DES LIVES (Section 2) -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:1.25rem; margin-bottom:2.25rem;">
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:0.82rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Sessions totales</div>
        <div style="font-size:1.85rem; font-weight:900; color:#0f172a; margin-top:0.35rem;"><?= $totalLives ?></div>
        <span style="font-size:0.78rem; color:#10b981; font-weight:600;"><?= $publishedCount ?> session<?= ($publishedCount > 1) ? 's' : '' ?> au calendrier</span>
      </div>

      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:0.82rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Audience cumulée</div>
        <div style="font-size:1.85rem; font-weight:900; color:#f97316; margin-top:0.35rem;"><?= number_format($totalAttendees, 0, ',', ' ') ?></div>
        <span style="font-size:0.78rem; color:#64748b;">Participants inscrits & connectés</span>
      </div>

      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:0.82rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Statut Certification</div>
        <div style="font-size:1.45rem; font-weight:800; color:#10b981; margin-top:0.5rem; display:flex; align-items:center; gap:0.4rem;">
          <span>✓</span> Validée
        </div>
        <span style="font-size:0.78rem; color:#64748b;">Badge affiché sur votre profil</span>
      </div>

      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:0.82rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Brouillons / En attente</div>
        <div style="font-size:1.85rem; font-weight:900; color:#64748b; margin-top:0.35rem;"><?= $draftCount ?></div>
        <span style="font-size:0.78rem; color:#64748b;">Sessions modifiables</span>
      </div>
    </div>

    <!-- 2. MES SESSIONS LIVE & MASTERMINDS -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; margin-bottom:2.5rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; border-bottom:1px solid #f1f5f9; padding-bottom:1rem;">
        <div>
          <h2 style="font-size:1.35rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem 0;">
            Mes sessions programmées
          </h2>
          <p style="color:#64748b; font-size:0.88rem; margin:0;">
            Gérez vos lives, modifiez vos dates et suivez le statut de publication.
          </p>
        </div>
        <a href="creer-live.php" class="btn btn-secondary btn-sm" style="font-weight:700;">
          + Nouveau live
        </a>
      </div>

      <?php if (!empty($myLives)): ?>
        <div style="display:flex; flex-direction:column; gap:1rem;">
          <?php foreach ($myLives as $live): ?>
            <?php 
              $st = $live['status'] ?? 'publie';
              $isPast = (strtotime($live['scheduled_date']) < strtotime(date('Y-m-d')));
            ?>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
              <div style="flex:1; min-width:260px;">
                <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap; margin-bottom:0.4rem;">
                  <span style="font-size:0.75rem; font-weight:800; background:#e0f2fe; color:#0369a1; padding:0.2rem 0.55rem; border-radius:6px; text-transform:uppercase;">
                    <?= htmlspecialchars($live['format'] ?? 'Live') ?>
                  </span>

                  <?php if ($st === 'publie'): ?>
                    <span style="font-size:0.75rem; font-weight:700; background:#dcfce7; color:#15803d; padding:0.2rem 0.55rem; border-radius:6px;">
                      ● En ligne
                    </span>
                  <?php elseif ($st === 'brouillon'): ?>
                    <span style="font-size:0.75rem; font-weight:700; background:#fef3c7; color:#b45309; padding:0.2rem 0.55rem; border-radius:6px;">
                      ✏️ Brouillon
                    </span>
                  <?php else: ?>
                    <span style="font-size:0.75rem; font-weight:700; background:#fee2e2; color:#991b1b; padding:0.2rem 0.55rem; border-radius:6px;">
                      ✕ Annulé
                    </span>
                  <?php endif; ?>

                  <span style="font-size:0.82rem; color:#64748b;">
                    📅 <?= date('d/m/Y', strtotime($live['scheduled_date'])) ?> à <?= htmlspecialchars($live['scheduled_time']) ?> (<?= htmlspecialchars($live['duration']) ?>)
                  </span>
                </div>

                <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
                  <?= htmlspecialchars($live['title']) ?>
                </h3>
                <p style="font-size:0.88rem; color:#475569; margin:0; line-height:1.4;">
                  <?= htmlspecialchars(mb_strimwidth($live['description'], 0, 140, '...')) ?>
                </p>
                <div style="font-size:0.8rem; color:#64748b; margin-top:0.4rem;">
                  👥 <strong><?= (int)$live['attendees_count'] ?></strong> inscrits / participants
                </div>
              </div>

              <!-- Actions Animateur sur son live -->
              <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                <?php if ($st === 'brouillon'): ?>
                  <form method="POST" action="espace-animateur.php" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="publier_live">
                    <input type="hidden" name="live_id" value="<?= $live['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" style="background:#10b981; color:#fff; border:none; font-weight:700;">
                      Publier
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($st !== 'annule'): ?>
                  <form method="POST" action="espace-animateur.php" style="display:inline;" onsubmit="return confirm('Confirmez-vous l\'annulation de cette session ?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="annuler_live">
                    <input type="hidden" name="live_id" value="<?= $live['id'] ?>">
                    <button type="submit" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#fca5a5;">
                      Annuler la session
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align:center; padding:2.5rem 1rem; background:#f8fafc; border-radius:14px; border:1px dashed #cbd5e1;">
          <span style="font-size:2.2rem; display:block; margin-bottom:0.5rem;">🎙️</span>
          <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0;">
            Vous n'avez pas encore programmé de session Live
          </h3>
          <p style="color:#64748b; font-size:0.9rem; margin:0 0 1.25rem 0;">
            Créez votre premier atelier, live thématique ou mastermind dès aujourd'hui.
          </p>
          <a href="creer-live.php" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #f97316, #ea580c); font-weight:700;">
            Programmer mon premier Live →
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- 3. MA PAGE D'EXPERT ET CERTIFICATIONS (Section 2) -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
      <div style="margin-bottom:1.5rem; border-bottom:1px solid #f1f5f9; padding-bottom:1rem;">
        <h2 style="font-size:1.35rem; font-weight:800; color:#0f172a; margin:0 0 0.25rem 0;">
          Ma page d'expert dans l'annuaire communautaire
        </h2>
        <p style="color:#64748b; font-size:0.88rem; margin:0;">
          Exposez et valorisez vos expertises, offres et parcours auprès de l'ensemble des membres One Vision.
        </p>
      </div>

      <form method="POST" action="espace-animateur.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_expert_profile">

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
          <div>
            <label class="form-label" style="font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:0.35rem; display:block;">
              Titre professionnel / Expertise principale
            </label>
            <input type="text" name="job_title" class="form-input" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:8px;" value="<?= htmlspecialchars($currentUser['job_title'] ?? '') ?>" placeholder="ex. Consultant Acquisition B2B & High-Ticket">
          </div>

          <div>
            <label class="form-label" style="font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:0.35rem; display:block;">
              Entreprise / Cabinet
            </label>
            <input type="text" name="company" class="form-input" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:8px;" value="<?= htmlspecialchars($currentUser['company'] ?? '') ?>" placeholder="ex. Pulse Consulting">
          </div>
        </div>

        <div style="margin-bottom:1.25rem;">
          <label class="form-label" style="font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:0.35rem; display:block;">
            Compétences clés (séparées par des virgules)
          </label>
          <input type="text" name="skills" class="form-input" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:8px;" value="<?= htmlspecialchars($currentUser['skills'] ?? '') ?>" placeholder="ex. Vente B2B, Copywriting, Automatisation NoCode, Scaling">
        </div>

        <div style="margin-bottom:1.5rem;">
          <label class="form-label" style="font-size:0.85rem; font-weight:700; color:#334155; margin-bottom:0.35rem; display:block;">
            Bio & Présentation d'expert
          </label>
          <textarea name="bio" class="form-input" rows="4" style="width:100%; padding:0.65rem 0.9rem; border:1px solid #cbd5e1; border-radius:8px;" placeholder="Partagez votre histoire, vos compétences et sur quels sujets les membres peuvent vous solliciter..."><?= htmlspecialchars($currentUser['bio'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-secondary" style="font-weight:700; padding:0.7rem 1.4rem; border-radius:10px;">
          Enregistrer ma page d'expert
        </button>
      </form>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
