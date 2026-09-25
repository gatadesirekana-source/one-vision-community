<?php
/**
 * ONE VISION COMMUNITY — FOOTER GLOBAL PHP
 */
?>
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
            <li><a href="index.php#fonctionnalites">Fonctionnalités</a></li>
            <li><a href="index.php#lives">Programme des lives</a></li>
            <li><a href="index.php#comparatif">Tarif unique (9€)</a></li>
            <li><a href="creer-live.php">Créer un Live / Mastermind</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h5>Espace Membre</h5>
          <ul class="footer-links">
            <li><a href="index.php#temoignages">Témoignages membres</a></li>
            <li><a href="index.php#faq">Questions fréquentes</a></li>
            <?php if (is_logged_in()): ?>
              <li><a href="dashboard.php" style="font-weight:600; color:var(--color-primary);">Accéder au Dashboard</a></li>
              <li><a href="logout.php">Déconnexion</a></li>
            <?php else: ?>
              <li><a href="login.php">Espace de connexion</a></li>
              <li><a href="checkout.php">Rejoindre pour 200 FCFA (Test)</a></li>
            <?php endif; ?>
            <li><a href="support.php" style="font-weight:600; color:var(--color-white);">Support & Service Client</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h5>Légal</h5>
          <ul class="footer-links">
            <li><a href="conditions-generales.php">Conditions Générales</a></li>
            <li><a href="politique-confidentialite.php">Politique de Confidentialité</a></li>
            <li><a href="mentions-legales.php">Mentions Légales</a></li>
            <li><a href="gestion-cookies.php">Gestion des Cookies</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>© <?= date('Y') ?> One Vision Community. Tous droits réservés.</div>
      </div>
    </div>
  </footer>

  <!-- Scripts JavaScript -->
  <script src="./js/main.js?v=5"></script>
</body>
</html>
