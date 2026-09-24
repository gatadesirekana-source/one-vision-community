<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Cookies & Préférences — One Vision Community</title>
  <meta name="description" content="Gérez vos préférences relatives aux cookies et traceurs sur One Vision Community. Respect total de votre vie privée et de vos choix.">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./css/style.css">
</head>
<body>

  <!-- EN-TÊTE -->
  <header class="header">
    <div class="container nav-wrapper">
      <a href="index.php" class="logo" aria-label="Accueil One Vision Community">
        <div class="logo-icon">OV</div>
        <div class="logo-text">
          <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
          <span class="logo-sub">Community</span>
        </div>
      </a>

      <div class="nav-actions">
        <a href="index.php" class="btn btn-secondary" style="font-size:0.9rem;padding:0.55rem 1.1rem;">
          ← Retour à l'accueil
        </a>
        <button class="btn btn-primary open-checkout-btn">
          <span>Rejoindre pour 9€/mois</span>
        </button>
      </div>
    </div>
  </header>

  <!-- HERO DE PAGE -->
  <div class="page-hero">
    <div class="container">
      <span class="page-hero-badge">Vos Préférences</span>
      <h1 class="page-hero-title">Gestion des Cookies</h1>
      <p class="page-hero-subtitle">
        Contrôlez simplement et en temps réel les traceurs et données d'usage enregistrés sur votre appareil lors de votre navigation.
      </p>
    </div>
  </div>

  <!-- CONTENU DU DOCUMENT -->
  <main class="legal-layout">
    <div class="container">
      <div class="legal-document">
        <a href="index.php" class="back-home-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
          Retour à l'accueil
        </a>

        <div class="legal-alert">
          <strong>Transparence One Vision Community :</strong> Nous refusons tout cookie publicitaire intrusif. Nous utilisons exclusivement des traceurs techniques nécessaires au bon fonctionnement de votre espace membre et des mesures d’audience agrégées et anonymes.
        </div>

        <section class="legal-article">
          <h3>Centre de Contrôle de Vos Préférences</h3>
          <p>
            Vous pouvez activer ou désactiver chaque catégorie de cookies à tout moment ci-dessous. Vos choix sont automatiquement sauvegardés pour une durée maximale de 13 mois.
          </p>

          <!-- Cookie 1 : Essentiels -->
          <div class="cookie-card">
            <div class="cookie-info">
              <h4>1. Cookies strictement nécessaires (Techniques)</h4>
              <p>
                Indispensables pour naviguer sur la plateforme, sécuriser les sessions membres, gérer l’authentification et assurer le paiement mensuel sécurisé de 9€. Ils ne peuvent pas être désactivés.
              </p>
            </div>
            <div class="cookie-toggle">
              <span class="cookie-status-badge badge-required">Toujours actif</span>
            </div>
          </div>

          <!-- Cookie 2 : Mesure d'audience -->
          <div class="cookie-card">
            <div class="cookie-info">
              <h4>2. Cookies de mesure d'audience & performance</h4>
              <p>
                Permettent de mesurer anonymement la fréquentation des salons et des replays afin d'améliorer la stabilité des lives et la réactivité de la plateforme.
              </p>
            </div>
            <div class="cookie-toggle">
              <label class="switch" aria-label="Activer ou désactiver les cookies d'audience">
                <input type="checkbox" id="cookieAnalytics" checked>
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <!-- Cookie 3 : Préférences utilisateur -->
          <div class="cookie-card">
            <div class="cookie-info">
              <h4>3. Cookies de personnalisation & confort</h4>
              <p>
                Mémorisent vos réglages d'affichage (thème d'interface, position de lecture des replays vidéo) pour une navigation fluide d'une visite à l'autre.
              </p>
            </div>
            <div class="cookie-toggle">
              <label class="switch" aria-label="Activer ou désactiver les cookies de confort">
                <input type="checkbox" id="cookiePreferences" checked>
                <span class="slider"></span>
              </label>
            </div>
          </div>

          <!-- Actions de validation -->
          <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:2rem;">
            <button id="saveCookiesBtn" class="btn btn-primary" style="flex:1;min-width:200px;">
              <span>Enregistrer mes choix</span>
            </button>
            <button id="acceptAllCookiesBtn" class="btn btn-secondary" style="flex:1;min-width:200px;">
              <span>Tout accepter</span>
            </button>
          </div>
        </section>

        <section class="legal-article" style="margin-top:3rem;">
          <h3>Comment désactiver les cookies depuis votre navigateur ?</h3>
          <p>
            Vous pouvez également configurer votre navigateur pour qu’il bloque systématiquement tous les cookies ou vous alerte lorsqu’un cookie est déposé :
          </p>
          <ul>
            <li><strong>Google Chrome :</strong> Paramètres &gt; Confidentialité et sécurité &gt; Cookies tiers.</li>
            <li><strong>Mozilla Firefox :</strong> Paramètres &gt; Vie privée et sécurité &gt; Protection renforcée contre le pistage.</li>
            <li><strong>Apple Safari :</strong> Préférences &gt; Confidentialité &gt; Bloquer tous les cookies.</li>
            <li><strong>Microsoft Edge :</strong> Paramètres &gt; Cookies et autorisations de site &gt; Gérer et supprimer les cookies.</li>
          </ul>
        </section>
      </div>
    </div>
  </main>

  <!-- PIED DE PAGE -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="index.php" class="logo" aria-label="One Vision Community">
            <div class="logo-icon">OV</div>
            <div class="logo-text">
              <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
              <span class="logo-sub">Community</span>
            </div>
          </a>
          <p>
            La communauté en ligne des entrepreneurs et coachs qui s'entraident, collaborent sur leurs projets et participent à des lives chaque semaine pour 9€/mois.
          </p>
        </div>

        <div class="footer-col">
          <h5>Navigation</h5>
          <ul class="footer-links">
            <li><a href="index.html#fonctionnalites">Fonctionnalités</a></li>
            <li><a href="index.html#lives">Programme des lives</a></li>
            <li><a href="index.html#comparatif">Tarif unique (9€)</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h5>Ressources</h5>
          <ul class="footer-links">
            <li><a href="index.html#temoignages">Témoignages membres</a></li>
            <li><a href="index.html#faq">Questions fréquentes</a></li>
            <li><a href="javascript:void(0)" class="open-login-btn">Espace de connexion</a></li>
            <li><a href="support.php">Support & Service Client</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h5>Légal</h5>
          <ul class="footer-links">
            <li><a href="conditions-generales.php">Conditions Générales</a></li>
            <li><a href="politique-confidentialite.php">Politique de Confidentialité</a></li>
            <li><a href="mentions-legales.php">Mentions Légales</a></li>
            <li><a href="gestion-cookies.php" style="font-weight:700;color:var(--color-white);">Gestion des Cookies</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>© <?= date('Y') ?> One Vision Community. Tous droits réservés.</div>
      </div>
    </div>
  </footer>

  <!-- MODALE CHECKOUT & CONNEXION -->
  <div id="checkoutModal" class="modal-backdrop" aria-hidden="true" role="dialog" aria-labelledby="modalTitle">
    <div class="modal-card">
      <button id="modalCloseBtn" class="modal-close-btn" aria-label="Fermer la modale">✕</button>
      
      <!-- VUE 1 : FORMULAIRE D'ADHÉSION 9€ (Mode Nouveau Visiteur) -->
      <div id="viewJoin">
        <div class="modal-header">
          <div class="modal-header-top">
            <h3 id="modalTitle" class="modal-title">Rejoindre la communauté</h3>
            <span class="modal-price-pill">9€ <span>/mois</span></span>
          </div>
          <p class="modal-subtitle">Accès complet et immédiat • Sans engagement • Annulable en 1 clic</p>
        </div>

        <form id="checkoutForm">
          <div class="form-group">
            <label for="memberName" class="form-label">Nom complet</label>
            <input type="text" id="memberName" class="form-input" placeholder="ex. Alexandre Martin" required autocomplete="name">
          </div>

          <div class="form-group">
            <label for="memberEmail" class="form-label">Adresse email</label>
            <input type="email" id="memberEmail" class="form-input" placeholder="ex. alexandre@monprojet.fr" required autocomplete="email">
          </div>

          <div class="form-group">
            <label for="memberPassword" class="form-label">Mot de passe</label>
            <input type="password" id="memberPassword" class="form-input" placeholder="Au moins 6 caractères" minlength="6" required autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-primary modal-submit-btn">
            <span>Valider mon accès pour 9€</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="5" y1="12" x2="19" y2="12"></line>
              <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </button>

          <div class="modal-footer-notes">
            🔒 Paiement chiffré • Reçu fiscal par email • Résiliable en 1 clic
          </div>

          <div class="modal-switch-mode">
            Vous avez déjà un compte ? <button type="button" id="switchToLoginBtn">Se connecter</button>
          </div>
        </form>
      </div>

      <!-- VUE 2 : ESPACE DE CONNEXION POUR MEMBRES EXISTANTS -->
      <div id="viewLogin" style="display:none;">
        <div class="modal-header">
          <div class="modal-header-top">
            <h3 class="modal-title">Espace Connexion</h3>
            <span class="modal-badge-login">Membres</span>
          </div>
          <p class="modal-subtitle">Accédez à vos salons d'échanges, masterminds et replays HD.</p>
        </div>

        <form id="loginForm">
          <div class="form-group">
            <label for="loginEmail" class="form-label">Adresse email</label>
            <input type="email" id="loginEmail" class="form-input" placeholder="ex. alexandre@monprojet.fr" required autocomplete="email">
          </div>

          <div class="form-group">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <label for="loginPassword" class="form-label">Mot de passe</label>
              <a href="support.php" style="font-size:0.75rem;color:#64748b;text-decoration:underline;">Oublié ?</a>
            </div>
            <input type="password" id="loginPassword" class="form-input" placeholder="Votre mot de passe" required autocomplete="current-password">
          </div>

          <button type="submit" class="btn btn-primary modal-submit-btn">
            <span>Me connecter à mon espace</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>

          <div class="modal-footer-notes">
            ⚡ Connexion rapide et chiffrée • Accès immédiat aux salons 24/7
          </div>

          <div class="modal-switch-mode" id="loginSwitchMode">
            Nouveau ici ? <button type="button" id="switchToJoinBtn">Rejoindre pour 9€/mois</button>
          </div>
        </form>
      </div>

      <!-- VUE 3 : ÉTAT DE SUCCÈS (ANIMÉ) -->
      <div id="viewSuccess" style="display:none;">
        <div class="modal-success-box">
          <div class="modal-success-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <h3 id="successTitle" style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:0.4rem;">
            Bienvenue dans One Vision Community !
          </h3>
          <p id="successDesc" style="font-size:0.875rem;color:#475569;line-height:1.55;margin-bottom:1.4rem;">
            Votre accès à 9€/mois est validé. Vous pouvez dès maintenant rejoindre les salons et explorer les replays.
          </p>
          <button id="successActionBtn" class="btn btn-primary" style="width:100%;padding:0.85rem;">
            <span>Entrer dans l'espace membre →</span>
          </button>
        </div>
      </div>

        <form id="checkoutForm">
          <div class="form-group">
            <label for="memberName" class="form-label">Nom complet</label>
            <input type="text" id="memberName" class="form-input" placeholder="ex. Alexandre Martin" required autocomplete="name">
          </div>

          <div class="form-group">
            <label for="memberEmail" class="form-label">Adresse email</label>
            <input type="email" id="memberEmail" class="form-input" placeholder="ex. alexandre@monprojet.fr" required autocomplete="email">
          </div>

          <div class="form-group">
            <label for="memberPassword" class="form-label">Mot de passe</label>
            <input type="password" id="memberPassword" class="form-input" placeholder="Au moins 6 caractères" minlength="6" required autocomplete="new-password">
          </div>

          <div style="margin-top:1.15rem;">
            <button type="submit" class="btn btn-primary" style="width:100%;padding:0.85rem;">
              <span>Valider mon accès pour 9€</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>

          <div class="modal-footer-notes">
            🔒 Données chiffrées • Reçu fiscal envoyé par email • Résiliable en 1 clic
          </div>

          <div class="modal-switch-mode">
            Vous avez déjà un compte ? <button type="button" id="switchToLoginBtn">Se connecter</button>
          </div>
        </form>
      </div>

      <!-- VUE 2 : ESPACE DE CONNEXION POUR MEMBRES EXISTANTS -->
      <div id="viewLogin" style="display:none;">
        <div class="modal-header">
          <h3 class="modal-title">Connexion à votre espace membre</h3>
          <p class="modal-subtitle">Accédez à vos salons d'échanges, masterminds et replays HD.</p>
        </div>

        <form id="loginForm">
          <div class="form-group">
            <label for="loginEmail" class="form-label">Adresse email</label>
            <input type="email" id="loginEmail" class="form-input" placeholder="ex. alexandre@monprojet.fr" required autocomplete="email">
          </div>

          <div class="form-group">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <label for="loginPassword" class="form-label">Mot de passe</label>
              <a href="support.php" style="font-size:0.75rem;color:#64748b;text-decoration:underline;">Oublié ?</a>
            </div>
            <input type="password" id="loginPassword" class="form-input" placeholder="Votre mot de passe" required autocomplete="current-password">
          </div>

          <div style="margin-top:1.15rem;">
            <button type="submit" class="btn btn-primary" style="width:100%;padding:0.85rem;">
              <span>Me connecter à mon espace</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </button>
          </div>

          <div class="modal-footer-notes">
            ⚡ Connexion rapide et chiffrée • Accès immédiat aux salons 24/7
          </div>

          <div class="modal-switch-mode">
            Nouveau ici ? <button type="button" id="switchToJoinBtn">Rejoindre pour 9€/mois</button>
          </div>
        </form>
      </div>

      <!-- VUE 3 : ÉTAT DE SUCCÈS (ANIMÉ) -->
      <div id="viewSuccess" style="display:none;">
        <div class="modal-success-box">
          <div class="modal-success-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <h3 id="successTitle" style="font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:0.4rem;">
            Bienvenue dans One Vision Community !
          </h3>
          <p id="successDesc" style="font-size:0.875rem;color:#475569;line-height:1.55;margin-bottom:1.4rem;">
            Votre accès à 9€/mois est validé. Vous pouvez dès maintenant rejoindre les salons et explorer les replays.
          </p>
          <button id="successActionBtn" class="btn btn-primary" style="width:100%;padding:0.85rem;">
            <span>Entrer dans l'espace membre →</span>
          </button>
        </div>
      </div>

    </div>
  </div>

  <script src="./js/main.js"></script>
  <script>
    // Logique interactive de sauvegarde des cookies
    document.addEventListener('DOMContentLoaded', () => {
      const saveBtn = document.getElementById('saveCookiesBtn');
      const acceptAllBtn = document.getElementById('acceptAllCookiesBtn');
      const analyticsInput = document.getElementById('cookieAnalytics');
      const prefInput = document.getElementById('cookiePreferences');

      // Charger l'état sauvegardé
      if (localStorage.getItem('ov-cookies-analytics') !== null) {
        analyticsInput.checked = localStorage.getItem('ov-cookies-analytics') === 'true';
      }
      if (localStorage.getItem('ov-cookies-pref') !== null) {
        prefInput.checked = localStorage.getItem('ov-cookies-pref') === 'true';
      }

      if (saveBtn) {
        saveBtn.addEventListener('click', () => {
          localStorage.setItem('ov-cookies-analytics', analyticsInput.checked);
          localStorage.setItem('ov-cookies-pref', prefInput.checked);
          localStorage.setItem('ov-cookies-consent', 'custom');
          showToast('✅ Vos préférences de cookies ont bien été enregistrées.');
        });
      }

      if (acceptAllBtn) {
        acceptAllBtn.addEventListener('click', () => {
          analyticsInput.checked = true;
          prefInput.checked = true;
          localStorage.setItem('ov-cookies-analytics', 'true');
          localStorage.setItem('ov-cookies-pref', 'true');
          localStorage.setItem('ov-cookies-consent', 'all');
          showToast('✅ Tous les cookies recommandés ont été acceptés.');
        });
      }
    });
  </script>
</body>
</html>
