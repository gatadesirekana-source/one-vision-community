<?php
/**
 * ONE VISION COMMUNITY — SUPPORT & SERVICE CLIENT (PHP & SQLITE)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

$db = get_db();
$currentUser = current_user();

$ticketSent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['contactName'] ?? '');
    $email = trim($_POST['contactEmail'] ?? '');
    $subject = trim($_POST['contactSubject'] ?? '');
    $message = trim($_POST['contactMessage'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "Veuillez renseigner tous les champs obligatoires du formulaire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO support_tickets (user_id, name, email, subject, message, status)
                VALUES (?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $currentUser['id'] ?? null,
                $name,
                $email,
                $subject,
                $message
            ]);

            $ticketSent = true;
            set_flash('success', "Votre demande a été prise en compte avec succès ! Un conseiller vous répondra sous 24h ouvrées.");

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }

        } catch (Exception $e) {
            $error = "Une erreur est survenue lors de l'enregistrement de votre message : " . $e->getMessage();
        }
    }
}

$pageTitle = "Support & Service Client — One Vision Community";
$pageDescription = "Centre d'aide et support officiel de One Vision Community. Une équipe dédiée à vos côtés pour répondre sous 24h ouvrées.";

require_once __DIR__ . '/includes/header.php';
?>

  <!-- HERO DE PAGE -->
  <div class="page-hero">
    <div class="container">
      <span class="page-hero-badge">Centre d'Assistance</span>
      <h1 class="page-hero-title">Support & Service Client</h1>
      <p class="page-hero-subtitle">
        Une question sur votre adhésion à 9€/mois, un souci technique ou une suggestion pour la communauté ? Nous vous répondons sous 24 heures ouvrées.
      </p>
    </div>
  </div>

  <!-- CONTENU PRINCIPAL SUPPORT -->
  <main class="legal-layout">
    <div class="container">
      <!-- Lien retour -->
      <div style="max-width:860px;margin:0 auto 2rem;">
        <a href="index.php" class="back-home-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
          Retour à l'accueil
        </a>
      </div>

      <!-- Grille d'accès rapide -->
      <div class="support-quick-grid" style="max-width:960px;margin:0 auto 3.5rem;">
        <div class="support-quick-card">
          <div class="support-quick-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
          </div>
          <h4>Contact Direct par Email</h4>
          <p>Pour toute question générale ou demande administrative prioritaire.</p>
          <a href="mailto:support@onevisioncommunity.fr" class="support-link">support@onevisioncommunity.fr</a>
        </div>

        <div class="support-quick-card">
          <div class="support-quick-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="4" width="20" height="16" rx="2"></rect>
              <path d="M7 15h0M2 9.5h20"></path>
            </svg>
          </div>
          <h4>Abonnement & Facturation</h4>
          <p>Gérez votre prélèvement de 9€, téléchargez vos reçus ou résiliez en 1 clic.</p>
          <a href="<?= is_logged_in() ? 'dashboard.php' : 'login.php' ?>" class="support-link">Accéder à mon compte</a>
        </div>

        <div class="support-quick-card">
          <div class="support-quick-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="23 7 16 12 23 17 23 7"></polygon>
              <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
            </svg>
          </div>
          <h4>Accès aux Lives & Replays</h4>
          <p>Un problème de son, de caméra ou d'accès aux archives vidéo ?</p>
          <a href="#supportFormSection" class="support-link">Ouvrir un ticket technique</a>
        </div>
      </div>

      <!-- Formulaire de Contact Détaillé -->
      <div class="support-form-card" id="supportFormSection">
        <h3 style="font-size:1.45rem;font-weight:800;color:#0f172a;margin-bottom:0.5rem;text-align:center;">
          Envoyer un message à l'équipe support
        </h3>
        <p style="font-size:0.95rem;color:var(--color-gray-300);text-align:center;margin-bottom:2rem;">
          Remplissez les champs ci-dessous. Votre message sera enregistré dans notre système et un membre de l'équipe vous répondra rapidement.
        </p>

        <?php if (!empty($error)): ?>
          <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.92rem; display:flex; align-items:center; gap:0.5rem;">
            <span>⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($ticketSent): ?>
          <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:1.25rem; border-radius:12px; margin-bottom:1.5rem; text-align:center;">
            <div style="font-size:2rem; margin-bottom:0.5rem;">✅</div>
            <strong style="font-size:1.1rem; display:block; margin-bottom:0.25rem;">Message envoyé avec succès !</strong>
            <p style="font-size:0.92rem; margin:0;">Votre demande a bien été enregistrée dans notre base de données. Un conseiller vous répondra par email dans les plus brefs délais.</p>
          </div>
        <?php endif; ?>

        <form id="supportContactForm" method="POST" action="support.php">
          <?= csrf_field() ?>

          <div class="form-group">
            <label for="contactName" class="form-label">Votre nom complet</label>
            <input 
              type="text" 
              id="contactName" 
              name="contactName"
              class="form-input" 
              placeholder="ex. Thomas Robert" 
              value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>"
              required
            >
          </div>

          <div class="form-group">
            <label for="contactEmail" class="form-label">Votre adresse email</label>
            <input 
              type="email" 
              id="contactEmail" 
              name="contactEmail"
              class="form-input" 
              placeholder="ex. thomas@monentreprise.com" 
              value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>"
              required
            >
          </div>

          <div class="form-group">
            <label for="contactSubject" class="form-label">Objet de votre demande</label>
            <select id="contactSubject" name="contactSubject" class="form-select" required>
              <option value="" disabled selected>Sélectionnez une thématique...</option>
              <option value="Abonnement & Facturation (9€/mois, résiliation, reçu)">Gestion de mon abonnement (9€/mois, résiliation, reçu)</option>
              <option value="Problème technique (Live, visio, Replays HD)">Problème technique (Live, visio, Replays HD)</option>
              <option value="Question avant adhésion">Question avant de rejoindre One Vision Community</option>
              <option value="Proposer un Live / Mastermind">Proposer une intervention ou un atelier live</option>
              <option value="Autre demande">Autre demande</option>
            </select>
          </div>

          <div class="form-group">
            <label for="contactMessage" class="form-label">Votre message détaillé</label>
            <textarea 
              id="contactMessage" 
              name="contactMessage"
              class="form-textarea" 
              rows="5"
              placeholder="Expliquez-nous précisément votre besoin..." 
              required
            ></textarea>
          </div>

          <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary" style="width:100%;padding:0.95rem;">
              <span>Envoyer mon message au support</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
              </svg>
            </button>
          </div>

          <div class="modal-footer-notes" style="margin-top:1.25rem;">
            ⚡ Délai de réponse garanti sous 24h ouvrées • Support disponible du lundi au samedi
          </div>
        </form>
      </div>

    </div>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
