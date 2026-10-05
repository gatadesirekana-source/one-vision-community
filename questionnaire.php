<?php
/**
 * ONE VISION COMMUNITY — QUESTIONNAIRE D'ACCUEIL INTERACTIF (TYPEFORM-LIKE)
 * 
 * Parcours moderne sans aucun pop-up, enregistrement progressif,
 * reprise à la dernière question, gestion des mineurs et transition vers l'abonnement.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/subscriptions.php';
require_once __DIR__ . '/includes/questionnaire_data.php';
require_once __DIR__ . '/includes/onboarding.php';

// 1. CONTRÔLE D'ACCÈS STRICT CÔTÉ SERVEUR
require_auth('login.php');

$currentUser = current_user();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

$userId = (int)$currentUser['id'];

// Les administrateurs ne sont jamais soumis à ce parcours
if (is_admin_user($currentUser)) {
    header('Location: dashboard.php');
    exit;
}

// Si la personne a déjà un abonnement actif -> Dashboard direct
$subCheck = check_user_subscription($userId);
if (!empty($subCheck['is_active'])) {
    header('Location: dashboard.php');
    exit;
}

$definition = get_questionnaire_definition();
$totalQuestions = get_total_questions_count();

// Assurer l'existence du profil
$profile = ensure_user_onboarding_profile($userId);

// Si la personne a déjà terminé le questionnaire -> Page choix d'abonnement direct (jamais redemandé)
if ($profile['statut'] === 'termine' && empty($_SESSION['show_completion_screen'])) {
    header('Location: choisir-abonnement.php');
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// 2. TRAITEMENT DES REQUÊTES POST (ENREGISTREMENT PROGRESSIF & FINALISATION)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Session expirée. Veuillez rafraîchir la page."]);
            exit;
        }
        set_flash('error', "Session expirée. Veuillez réessayer.");
        header("Location: questionnaire.php");
        exit;
    }

    $action = $_POST['action'] ?? 'save_question';

    // A. ENREGISTREMENT D'UNE QUESTION
    if ($action === 'save_question') {
        $qId = trim($_POST['question_id'] ?? '');
        $answers = $_POST['answers'] ?? [];

        $saveResult = save_user_question_response($userId, $qId, $answers);

        if (!$saveResult['success']) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode($saveResult);
                exit;
            }
            set_flash('error', $saveResult['error']);
            $currentIdx = isset($_POST['current_index']) ? (int)$_POST['current_index'] : 1;
            header("Location: questionnaire.php?q={$currentIdx}");
            exit;
        }

        // Cas mineur
        if (!empty($saveResult['is_minor'])) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'is_minor' => true, 'redirect' => 'questionnaire.php?view=minor']);
                exit;
            }
            header("Location: questionnaire.php?view=minor");
            exit;
        }

        // Si dernière question soumise -> marquer terminé et afficher écran de remerciement
        $question = get_question_by_id($qId);
        if ($question && (int)$question['index'] === $totalQuestions) {
            $consent = !empty($_POST['marketing_consent']);
            finish_user_questionnaire($userId, $consent);
            $_SESSION['show_completion_screen'] = true;

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'is_completed' => true, 'redirect' => 'questionnaire.php?view=merci']);
                exit;
            }
            header("Location: questionnaire.php?view=merci");
            exit;
        }

        $nextIndex = (int)($saveResult['next_index'] ?? 2);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'next_index' => $nextIndex, 'redirect' => "questionnaire.php?q={$nextIndex}"]);
            exit;
        }

        header("Location: questionnaire.php?q={$nextIndex}");
        exit;
    }

    // B. FINALISATION DIRECTE (SI NÉCESSAIRE)
    if ($action === 'finish_questionnaire') {
        $consent = !empty($_POST['marketing_consent']);
        finish_user_questionnaire($userId, $consent);
        $_SESSION['show_completion_screen'] = true;

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'redirect' => 'questionnaire.php?view=merci']);
            exit;
        }
        header("Location: questionnaire.php?view=merci");
        exit;
    }
}

// 3. DÉTERMINATION DE LA VUE À AFFICHER
$view = $_GET['view'] ?? '';

// Écran mineur
if ($view === 'minor' || is_user_minor($userId)) {
    $currentView = 'minor';
}
// Écran de fin / remerciement ("Merci, votre place vous attend")
elseif ($view === 'merci' || !empty($_SESSION['show_completion_screen'])) {
    $currentView = 'completion';
}
// Écran d'accueil du questionnaire
elseif (isset($_GET['welcome'])) {
    $currentView = 'welcome';
}
// Question par question
else {
    // Vérifier si l'utilisateur a déjà répondu à des questions
    $userResponses = get_user_questionnaire_responses($userId);
    
    // Si aucune réponse et pas de paramètre q -> écran d'accueil avec bouton "Commencer"
    if (empty($userResponses) && !isset($_GET['q'])) {
        $currentView = 'welcome';
    } else {
        $currentView = 'question';

        // Index demandé ou reprise automatique à la dernière question répondue
        if (isset($_GET['q'])) {
            $requestedIndex = max(1, min($totalQuestions, (int)$_GET['q']));
            // On permet d'aller jusqu'au current_question_index enregistré
            $currentIndex = min($requestedIndex, (int)$profile['current_question_index']);
        } else {
            // Reprendre à la dernière question non terminée
            $currentIndex = max(1, min($totalQuestions, (int)$profile['current_question_index']));
        }

        $currentQuestion = get_question_by_index($currentIndex);
        if (!$currentQuestion) {
            $currentIndex = 1;
            $currentQuestion = get_question_by_index(1);
        }

        $stepInfo = $definition['steps'][$currentQuestion['step']] ?? [
            'number' => 1,
            'total' => 5,
            'title' => "Faisons connaissance"
        ];

        // Réponses déjà cochées par l'utilisateur pour cette question
        $currentAnswers = $userResponses[$currentQuestion['id']] ?? [];
        $progressPct = round(($currentIndex / $totalQuestions) * 100);
    }
}

// Photo d'accueil personnalisée pour l'écran de bienvenue
$welcomePhoto = !empty($definition['welcome']['photo']) ? $definition['welcome']['photo'] : 'img/welcome-photo.jpg';
if (!file_exists(__DIR__ . '/' . $welcomePhoto)) {
    $ownerAvatar = $db->query("SELECT avatar FROM users WHERE role = 'proprietaire' LIMIT 1")->fetchColumn();
    $welcomePhoto = !empty($ownerAvatar) ? ltrim($ownerAvatar, './') : 'img/avatar-cyril.jpg';
}

$pageTitle = "Questionnaire d'accueil — One Vision Community";
$pageDescription = "Répondez à quelques questions pour personnaliser votre expérience dans la communauté.";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">
  
  <!-- Polices Google -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Feuilles de style -->
  <link rel="stylesheet" href="./css/style.css?v=7">
</head>
<body class="onboarding-page-body">

  <!-- EN-TÊTE ÉPURÉ DE LA MARQUE -->
  <header style="padding: 0.6rem 1rem; border-bottom: 1px solid rgba(15, 23, 42, 0.06); background: #ffffff;">
    <div class="container" style="max-width: 900px; margin: 0 auto; display:flex; justify-content:center; align-items:center;">
      <a href="index.php" class="logo" aria-label="One Vision Community" style="text-decoration:none;">
        <div class="logo-text" style="transform: scale(0.9); transform-origin: center;">
          <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
          <span class="logo-sub">Community</span>
        </div>
      </a>
    </div>
  </header>

  <!-- CONTENU PRINCIPAL -->
  <main class="onboarding-main-wrapper">

    <!-- ====================================================================
         CAS 1 : ÉCRAN D'ACCUEIL DU QUESTIONNAIRE (Un seul bouton Commencer)
         ==================================================================== -->
    <?php if ($currentView === 'welcome'): ?>
      <div class="onboarding-card">
        <div class="onboarding-splash-box">
          <div class="onboarding-welcome-avatar-wrapper">
            <img src="<?= htmlspecialchars($welcomePhoto) ?>" alt="One Vision Community" class="onboarding-welcome-avatar">
          </div>
          <h1 class="onboarding-splash-title">
            <?= htmlspecialchars($definition['welcome']['title']) ?>
          </h1>
          <p class="onboarding-splash-desc">
            <?= htmlspecialchars($definition['welcome']['subtitle']) ?>
          </p>
          <a href="questionnaire.php?q=1" class="onboarding-splash-btn" id="btnStartQuestionnaire">
            <span><?= htmlspecialchars($definition['welcome']['button_text']) ?></span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="5" y1="12" x2="19" y2="12"></line>
              <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </a>
        </div>
      </div>

    <!-- ====================================================================
         CAS 2 : ÉCRAN MINEUR BIENVEILLANT (Réservé aux adultes)
         ==================================================================== -->
    <?php elseif ($currentView === 'minor'): ?>
      <div class="onboarding-card">
        <div class="onboarding-splash-box">
          <div class="onboarding-welcome-avatar-wrapper">
            <img src="<?= htmlspecialchars($welcomePhoto) ?>" alt="One Vision Community" class="onboarding-welcome-avatar">
          </div>
          <h1 class="onboarding-splash-title" style="color:#0f172a;">
            <?= htmlspecialchars($definition['minor_screen']['title']) ?>
          </h1>
          <p class="onboarding-splash-desc">
            <?= htmlspecialchars($definition['minor_screen']['message']) ?>
          </p>
          <div style="display:flex; flex-direction:column; gap:0.75rem; align-items:center;">
            <a href="logout.php?redirect=index.php&quiet=1" class="onboarding-splash-btn" style="background:#0f172a;">
              <span><?= htmlspecialchars($definition['minor_screen']['button_text']) ?></span>
            </a>
          </div>
        </div>
      </div>

    <!-- ====================================================================
         CAS 3 : ÉCRAN DE REMERCIEMENT FINAL ("Merci, votre place vous attend")
         ==================================================================== -->
    <?php elseif ($currentView === 'completion'): ?>
      <?php unset($_SESSION['show_completion_screen']); ?>
      <div class="onboarding-card" style="box-shadow: 0 25px 60px -15px rgba(37, 99, 235, 0.15);">
        <div class="onboarding-splash-box">
          <div class="onboarding-welcome-avatar-wrapper">
            <img src="<?= htmlspecialchars($welcomePhoto) ?>" alt="One Vision Community" class="onboarding-welcome-avatar">
          </div>
          <h1 class="onboarding-splash-title">
            <?= htmlspecialchars($definition['completion_screen']['title']) ?>
          </h1>
          <p class="onboarding-splash-desc">
            <?= htmlspecialchars($definition['completion_screen']['message']) ?>
          </p>
          <a href="choisir-abonnement.php" class="onboarding-splash-btn" id="btnGoToAbonnement" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
            <span><?= htmlspecialchars($definition['completion_screen']['button_text']) ?></span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="5" y1="12" x2="19" y2="12"></line>
              <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </a>
        </div>
      </div>

    <!-- ====================================================================
         CAS 4 : QUESTIONNAIRE ACTIF (UNE QUESTION PAR ÉCRAN)
         ==================================================================== -->
    <?php else: ?>
      <div class="onboarding-card" id="questionCard">
        
        <!-- En-tête : Progression et Étape courante -->
        <div class="onboarding-header">
          <div class="onboarding-step-badge">
            <span>Étape <?= $stepInfo['number'] ?> sur <?= $stepInfo['total'] ?> : <?= htmlspecialchars($stepInfo['title']) ?></span>
          </div>

          <!-- Barre de progression -->
          <div class="onboarding-progress-track">
            <div class="onboarding-progress-fill" style="width: <?= $progressPct ?>%;"></div>
          </div>

          <div class="onboarding-meta-row">
            <span>Question <?= $currentIndex ?> sur <?= $totalQuestions ?></span>
            <span><?= $progressPct ?>% complété</span>
          </div>
        </div>

        <!-- Formulaire de la question courante -->
        <form method="POST" action="questionnaire.php" id="onboardingForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_question">
          <input type="hidden" name="question_id" id="questionIdInput" value="<?= htmlspecialchars($currentQuestion['id']) ?>">
          <input type="hidden" name="current_index" value="<?= $currentIndex ?>">
          <input type="hidden" name="question_type" id="questionTypeInput" value="<?= htmlspecialchars($currentQuestion['type']) ?>">

          <h2 class="onboarding-question-title">
            <?= htmlspecialchars($currentQuestion['question']) ?>
          </h2>

          <div class="onboarding-question-helper">
            <?php if ($currentQuestion['type'] === 'unique'): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle></svg>
              <span>Une seule réponse possible</span>
            <?php else: ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span><strong>Plusieurs réponses possibles</strong></span>
            <?php endif; ?>
          </div>

          <!-- Liste des cartes de réponses interactives -->
          <div class="onboarding-options-list" id="optionsList">
            <?php foreach ($currentQuestion['options'] as $idx => $opt): ?>
              <?php $isSelected = in_array($opt, $currentAnswers, true); ?>
              <button 
                type="button" 
                class="onboarding-option-card <?= $isSelected ? 'selected' : '' ?>" 
                data-value="<?= htmlspecialchars($opt) ?>"
                role="<?= ($currentQuestion['type'] === 'unique') ? 'radio' : 'checkbox' ?>"
                aria-checked="<?= $isSelected ? 'true' : 'false' ?>"
              >
                <?php if ($currentQuestion['type'] === 'unique'): ?>
                  <span class="onboarding-indicator-circle"></span>
                <?php else: ?>
                  <span class="onboarding-indicator-square">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                  </span>
                <?php endif; ?>
                <span class="onboarding-option-text"><?= htmlspecialchars($opt) ?></span>
                
                <!-- Inputs masqués pour compatibilité sans JS -->
                <input 
                  type="<?= ($currentQuestion['type'] === 'unique') ? 'radio' : 'checkbox' ?>" 
                  name="answers[]" 
                  value="<?= htmlspecialchars($opt) ?>" 
                  class="hidden-answer-input"
                  <?= $isSelected ? 'checked' : '' ?>
                  style="display:none;"
                >
              </button>
            <?php endforeach; ?>
          </div>

          <!-- Section Spécifique Q13 : Consentement & Mentions RGPD -->
          <?php if ($currentQuestion['id'] === 'q13' && !empty($currentQuestion['consent_section'])): ?>
            <div class="onboarding-consent-card">
              <input 
                type="checkbox" 
                name="marketing_consent" 
                id="marketingConsent" 
                class="onboarding-consent-checkbox"
                value="1"
                <?= !empty($profile['consentement_marketing']) ? 'checked' : '' ?>
              >
              <label for="marketingConsent" class="onboarding-consent-label">
                <strong><?= htmlspecialchars($currentQuestion['consent_section']['label']) ?></strong>
                <span class="onboarding-consent-notice">
                  <?= htmlspecialchars($currentQuestion['consent_section']['notice']) ?>
                </span>
              </label>
            </div>
          <?php endif; ?>

          <!-- Message d'erreur dynamique -->
          <div id="onboardingError" style="display:none; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.75rem 1rem; border-radius:12px; margin-bottom:1.5rem; font-size:0.88rem; align-items:center; gap:0.5rem;">
            <span>⚠️</span>
            <span class="err-text">Veuillez sélectionner au moins une réponse pour continuer.</span>
          </div>

          <!-- Barre de navigation inférieure -->
          <div class="onboarding-nav-bar">
            <?php if ($currentIndex > 1): ?>
              <a href="questionnaire.php?q=<?= $currentIndex - 1 ?>" class="onboarding-nav-btn onboarding-btn-back" id="btnBack">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                <span>Retour</span>
              </a>
            <?php else: ?>
              <div></div> <!-- Espace réservé pour maintenir Suivant à droite -->
            <?php endif; ?>

            <button 
              type="submit" 
              class="onboarding-nav-btn onboarding-btn-next" 
              id="btnNext"
              <?= empty($currentAnswers) ? 'disabled' : '' ?>
            >
              <span><?= ($currentIndex === $totalQuestions) ? 'Terminer' : 'Suivant' ?></span>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>
        </form>

      </div>
    <?php endif; ?>

  </main>

  <!-- JAVASCRIPT DU QUESTIONNAIRE TYPEFORM -->
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('onboardingForm');
    if (!form) return;

    const optionsList = document.getElementById('optionsList');
    const optionCards = document.querySelectorAll('.onboarding-option-card');
    const btnNext = document.getElementById('btnNext');
    const errorBox = document.getElementById('onboardingError');
    const questionType = document.getElementById('questionTypeInput')?.value || 'unique';
    const isLastQuestion = <?= ($currentIndex ?? 0) === $totalQuestions ? 'true' : 'false' ?>;

    let isSubmitting = false;

    // Mise à jour de l'état activé/désactivé du bouton Suivant
    function updateNextButtonState() {
      const selectedCount = form.querySelectorAll('.hidden-answer-input:checked').length;
      if (btnNext) {
        btnNext.disabled = (selectedCount === 0);
      }
      if (selectedCount > 0 && errorBox) {
        errorBox.style.display = 'none';
      }
    }

    // Gestion du clic sur une carte de réponse
    optionCards.forEach(card => {
      card.addEventListener('click', (e) => {
        if (isSubmitting) return;

        const val = card.getAttribute('data-value');
        const hiddenInput = card.querySelector('.hidden-answer-input');

        if (questionType === 'unique') {
          // Désélectionner toutes les autres cartes
          optionCards.forEach(c => {
            c.classList.remove('selected');
            c.setAttribute('aria-checked', 'false');
            const inp = c.querySelector('.hidden-answer-input');
            if (inp) inp.checked = false;
          });

          // Sélectionner la carte courante
          card.classList.add('selected');
          card.setAttribute('aria-checked', 'true');
          if (hiddenInput) hiddenInput.checked = true;
          updateNextButtonState();

          // Avancement automatique pour les questions intermédiaires à choix unique.
          // Sur la dernière question ou si un consentement est présent, JAMAIS d'envoi automatique :
          // L'utilisateur doit pouvoir cocher la case de consentement et cliquer lui-même sur "Terminer".
          if (!isLastQuestion && !document.getElementById('marketingConsent')) {
            setTimeout(() => {
              if (!isSubmitting) {
                submitQuestionForm();
              }
            }, 400);
          }

        } else {
          // Question à choix multiple : bascule active/inactive
          const willBeSelected = !card.classList.contains('selected');
          if (willBeSelected) {
            card.classList.add('selected');
            card.setAttribute('aria-checked', 'true');
            if (hiddenInput) hiddenInput.checked = true;
          } else {
            card.classList.remove('selected');
            card.setAttribute('aria-checked', 'false');
            if (hiddenInput) hiddenInput.checked = false;
          }
          updateNextButtonState();
        }
      });
    });

    // Envoi du formulaire (par AJAX progressif avec fallback)
    function submitQuestionForm() {
      const selectedInputs = form.querySelectorAll('.hidden-answer-input:checked');
      if (selectedInputs.length === 0) {
        if (errorBox) {
          errorBox.querySelector('.err-text').textContent = "Veuillez sélectionner au moins une réponse pour continuer.";
          errorBox.style.display = 'flex';
        }
        return;
      }

      isSubmitting = true;
      if (btnNext) {
        btnNext.disabled = true;
        btnNext.innerHTML = `
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin-icon" style="animation: spin 1s linear infinite;">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor"></path>
          </svg>
          <span>Enregistrement...</span>
        `;
      }

      const formData = new FormData(form);

      fetch('questionnaire.php', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(res => res.json().then(data => ({ status: res.status, data })))
      .then(({ status, data }) => {
        if (data && data.success) {
          // Redirection vers l'écran ou la question suivante
          window.location.href = data.redirect || `questionnaire.php?q=${data.next_index}`;
        } else {
          isSubmitting = false;
          updateNextButtonState();
          if (btnNext) {
            btnNext.innerHTML = `
              <span>${isLastQuestion ? 'Terminer' : 'Suivant'}</span>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            `;
          }
          if (errorBox) {
            errorBox.querySelector('.err-text').textContent = (data && data.error) ? data.error : "Une erreur est survenue lors de l'enregistrement.";
            errorBox.style.display = 'flex';
          }
        }
      })
      .catch(err => {
        // En cas de coupure réseau, soumettre de façon classique en fallback
        form.submit();
      });
    }

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!isSubmitting) {
        submitQuestionForm();
      }
    });

    // Initialiser l'état du bouton Suivant au chargement
    updateNextButtonState();
  });
  </script>

</body>
</html>
