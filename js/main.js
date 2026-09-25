/**
 * ONE VISION COMMUNITY — LOGIQUE INTERACTIVE JAVASCRIPT
 * Gestion du menu mobile, accordéon FAQ, simulation de Live,
 * bascule de thème sombre/clair, onglets des sessions et modale 9€/mois.
 */

function isPhpEnvironment() {
  if (typeof window === 'undefined') return false;
  // Ne jamais traiter comme environnement PHP sur GitHub Pages ou en ouverture directe de fichier local
  if (window.location.hostname.includes('github.io') || window.location.protocol === 'file:') {
    return false;
  }
  // En environnement PHP réel (Apache, XAMPP, serveur PHP intégré), la page se termine explicitement par .php
  return window.location.pathname.endsWith('.php');
}

function getAppUrl(path) {
  const isPhp = isPhpEnvironment();
  return isPhp ? path.replace(/\.html/g, '.php') : path.replace(/\.php/g, '.html');
}

document.addEventListener('DOMContentLoaded', () => {
  initThemeToggle();
  initHeroTitleRotator();
  initVslPlayer();
  initHeaderScroll();
  initMobileMenu();
  initLiveScheduleTabs();
  initFaqAccordion();
  initLiveHubSimulation();
  initCheckoutModal();
  initTestimonialsCarousel();
  initSmoothScroll();
  initCheckoutPage();
  initDashboard();
  initCreateLivePage();
});

/* ==========================================================================
   1. BASCULE DU THÈME (SOMBRE / CLAIR)
   ========================================================================== */
function initThemeToggle() {
  // Verrouillage permanent du thème fond blanc lumineux & moderne
  document.documentElement.setAttribute('data-theme', 'light');
  localStorage.setItem('ov-community-theme', 'light');
}

/* ==========================================================================
   ROTATION DU TITRE PRINCIPAL HERO (ANIMATION FLUIDE H1)
   ========================================================================== */
function initHeroTitleRotator() {
  const rotator = document.getElementById('titleRotator');
  if (!rotator) return;

  const items = rotator.querySelectorAll('.title-item');
  if (items.length < 2) return;

  let currentIndex = 0;
  const intervalTime = 3800; // Alternance fluide toutes les 3.8s

  setInterval(() => {
    const currentItem = items[currentIndex];
    currentItem.classList.remove('active');
    currentItem.classList.add('exit');

    setTimeout(() => {
      currentItem.classList.remove('exit');
    }, 600);

    currentIndex = (currentIndex + 1) % items.length;
    items[currentIndex].classList.add('active');
  }, intervalTime);
}

/* ==========================================================================
   LECTEUR VIDÉO VSL (PLAY / HOOK INTÉRACTIF)
   ========================================================================== */
function initVslPlayer() {
  const playBtn = document.getElementById('vslPlayBtn');
  const poster = document.querySelector('.vsl-poster');
  const video = document.getElementById('vslVideo');

  if (!playBtn || !video) return;

  const handlePlay = () => {
    const sourceEl = video.querySelector('source');
    const hasSource = sourceEl && sourceEl.getAttribute('src') && sourceEl.getAttribute('src').trim() !== '';

    if (hasSource) {
      if (poster) poster.style.display = 'none';
      video.style.display = 'block';
      video.play().catch(err => console.log('Autoplay prevented', err));
    } else {
      showToast('🎬 Emplacement VSL prêt — Ajoutez votre lien vidéo ou fichier MP4 !');
    }
  };

  playBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    handlePlay();
  });

  if (poster) {
    poster.addEventListener('click', handlePlay);
  }
}

/* ==========================================================================
   2. EN-TÊTE FIXE AU DÉFILEMENT
   ========================================================================== */
function initHeaderScroll() {
  const header = document.querySelector('.header');
  if (!header) return;

  const handleScroll = () => {
    if (window.scrollY > 20) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  };

  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();
}

/* ==========================================================================
   3. MENU BURGER MOBILE
   ========================================================================== */
function initMobileMenu() {
  const burgerBtn = document.getElementById('burgerBtn');
  const navLinks = document.getElementById('navLinks');
  if (!burgerBtn || !navLinks) return;

  const toggleMenu = () => {
    const isOpen = navLinks.classList.contains('open');
    burgerBtn.classList.toggle('active');
    navLinks.classList.toggle('open');
    burgerBtn.setAttribute('aria-expanded', !isOpen);
    document.body.style.overflow = isOpen ? '' : 'hidden';
  };

  burgerBtn.addEventListener('click', toggleMenu);

  // Fermer le menu lors du clic sur un lien
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      if (navLinks.classList.contains('open')) {
        toggleMenu();
      }
    });
  });
}

/* ==========================================================================
   4. PROGRAMME DES LIVES (FILTRAGE DES ONGLETS)
   ========================================================================== */
const LIVES_DATA = {
  current: [
    {
      tag: "Masterclass Business",
      date: "Jeudi • 18h30 (En direct)",
      title: "Passer de 0 à 10 clients réguliers sans publicité payante",
      desc: "Étude concrète du tunnel d'acquisition organique de Julien, membre de la Community. Questions & réponses ouvertes.",
      author: "Julien B.",
      role: "Fondateur SaaS & Coach B2B",
      initials: "JB"
    },
    {
      tag: "Atelier Collaboratif",
      date: "Vendredi • 14h00",
      title: "Co-working & Feedback en direct sur vos pages de vente",
      desc: "Session de review bienveillante : 4 volontaires présentent leur projet et reçoivent des retours critiques immédiats.",
      author: "Sophie M.",
      role: "Copywriter & Stratège",
      initials: "SM"
    },
    {
      tag: "Live Q&A",
      date: "Dimanche • 20h00",
      title: "Mastermind informel du dimanche : bilan et objectifs de la semaine",
      desc: "Le rendez-vous hebdomadaire sans filtre pour partager ses blocages, célébrer ses victoires et planifier sa semaine.",
      author: "Thomas R.",
      role: "Coach en leadership",
      initials: "TR"
    }
  ],
  upcoming: [
    {
      tag: "Conférence Invitée",
      date: "Mardi prochain • 19h00",
      title: "Automatiser son back-office de coaching avec l'IA et No-Code",
      desc: "Gagnez 10h par semaine grâce à des workflows simples et reproductibles sans aucune compétence technique préalable.",
      author: "Alexandre L.",
      role: "Expert No-Code",
      initials: "AL"
    },
    {
      tag: "Pitch & Networking",
      date: "Jeudi prochain • 18h30",
      title: "Speed-Networking : Trouver son partenaire de projet",
      desc: "3 minutes par salle tournante pour présenter son projet, échanger ses compétences et déceler des synergies immédiates.",
      author: "Clara D.",
      role: "Community Manager",
      initials: "CD"
    },
    {
      tag: "Masterclass Vente",
      date: "Samedi prochain • 11h00",
      title: "Closer ses offres à 4 chiffres avec éthique et sérénité",
      desc: "Démonstrations en direct d'appels de découverte et psychologie de conversion pour les coachs et indépendants.",
      author: "Marc V.",
      role: "Mentor Vente Haute Valeur",
      initials: "MV"
    }
  ],
  replays: [
    {
      tag: "Replay Star ⭐️",
      date: "Replay HD disponible",
      title: "Le guide complet pour tarifer ses prestations de coaching",
      desc: "Plus de 450 vues en rediffusion. Fiche modèle de contrat et simulateur de rentabilité téléchargeable inclus.",
      author: "Élodie P.",
      role: "Coach Business",
      initials: "EP"
    },
    {
      tag: "Masterclass Replay",
      date: "Replay HD disponible",
      title: "Comment créer une communauté engagée autour de sa marque",
      desc: "Les 5 piliers psychologiques pour fidéliser ses premiers membres et générer du bouche-à-oreille naturel.",
      author: "Karim T.",
      role: "Créateur de communauté",
      initials: "KT"
    },
    {
      tag: "Atelier Replay",
      date: "Replay HD disponible",
      title: "Lancer son podcast professionnel sans matériel coûteux",
      desc: "Workflow d'enregistrement, outils gratuits et stratégie d'invitation pour attirer des profils inspirants.",
      author: "Sarah N.",
      role: "Podcasteuse & Consultante",
      initials: "SN"
    }
  ]
};

function initLiveScheduleTabs() {
  const tabs = document.querySelectorAll('.tab-btn');
  const container = document.getElementById('livesGrid');
  if (!tabs.length || !container) return;

  const getCustomLives = () => {
    try {
      const stored = localStorage.getItem('ov_community_custom_lives');
      return stored ? JSON.parse(stored) : [];
    } catch (e) {
      return [];
    }
  };

  const getParam = (param) => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get(param)) return urlParams.get(param);
    if (window.location.hash.includes('?')) {
      const hashQuery = window.location.hash.split('?')[1];
      const hashParams = new URLSearchParams(hashQuery);
      return hashParams.get(param);
    }
    return null;
  };

  const newLiveId = getParam('new_live');

  const renderLives = (category) => {
    const customLives = getCustomLives().filter(item => (item.category || 'current') === category);
    const standardItems = LIVES_DATA[category] || LIVES_DATA.current;
    const allItems = [...customLives, ...standardItems];

    container.innerHTML = allItems.map(item => {
      const isNew = newLiveId && item.id === newLiveId;
      const highlightClass = isNew ? 'highlight-new-live' : '';
      const communityPill = item.isCustom 
        ? `<div class="live-community-badge">🤝 Proposé par un membre</div>` 
        : '';
      const avatarHtml = item.avatar 
        ? `<div class="host-avatar-img"><img src="${item.avatar}" alt="${item.author}"></div>`
        : `<div class="host-avatar">${item.initials}</div>`;

      return `
        <article class="live-card ${highlightClass}" id="${item.id || ''}">
          <div>
            <div class="live-card-top">
              <span class="live-tag">${item.tag}</span>
              <span class="live-date">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                ${item.date}
              </span>
            </div>
            ${communityPill}
            <h4 class="live-card-title">${item.title}</h4>
            <p class="live-card-desc">${item.desc}</p>
          </div>
          <div class="live-host">
            ${avatarHtml}
            <div>
              <div class="host-name">${item.author}</div>
              <div class="host-role">${item.role}</div>
            </div>
          </div>
        </article>
      `;
    }).join('');

    // Si une session vient d'être créée, défilement fluide vers la carte avec focus
    if (newLiveId) {
      setTimeout(() => {
        const targetCard = document.getElementById(newLiveId);
        if (targetCard) {
          targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 400);
    }
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const category = tab.getAttribute('data-tab');
      renderLives(category);
    });
  });

  // Si on arrive avec un new_live, déterminer son onglet
  let initialCategory = 'current';
  if (newLiveId) {
    const customList = getCustomLives();
    const found = customList.find(c => c.id === newLiveId);
    if (found && found.category) {
      initialCategory = found.category;
      tabs.forEach(t => {
        t.classList.toggle('active', t.getAttribute('data-tab') === initialCategory);
      });
    }
  }

  // Rendu initial
  renderLives(initialCategory);
}

/* ==========================================================================
   5. FOIRE AUX QUESTIONS (ACCORDÉON ACCESSIBLE)
   ========================================================================== */
function initFaqAccordion() {
  const faqItems = document.querySelectorAll('.faq-item');
  if (!faqItems.length) return;

  faqItems.forEach(item => {
    const trigger = item.querySelector('.faq-trigger');
    const content = item.querySelector('.faq-content');

    trigger.addEventListener('click', () => {
      const isOpen = item.classList.contains('active');

      // Fermer tous les autres éléments (optionnel pour clarté)
      faqItems.forEach(otherItem => {
        if (otherItem !== item) {
          otherItem.classList.remove('active');
          const otherTrigger = otherItem.querySelector('.faq-trigger');
          const otherContent = otherItem.querySelector('.faq-content');
          if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
          if (otherContent) otherContent.style.maxHeight = null;
        }
      });

      if (isOpen) {
        item.classList.remove('active');
        trigger.setAttribute('aria-expanded', 'false');
        content.style.maxHeight = null;
      } else {
        item.classList.add('active');
        trigger.setAttribute('aria-expanded', 'true');
        content.style.maxHeight = content.scrollHeight + 'px';
      }
    });
  });
}

/* ==========================================================================
   6. SIMULATION INTERACTIVE DU HUB COMMUNAUTAIRE
   ========================================================================== */
function initLiveHubSimulation() {
  const chatContainer = document.getElementById('chatMessages');
  const viewerCounter = document.getElementById('viewerCount');
  if (!chatContainer) return;

  const messagesPool = [
    { name: "Maxime Coach", role: "Coach Sportif", text: "Ce conseil sur l'offre d'appel change tout !" },
    { name: "Camille R.", role: "Freelance Notion", text: "Je teste ça dès demain matin avec mes 2 prospects." },
    { name: "Damien V.", role: "Consultant B2B", text: "Est-ce qu'on aura la trame en replay ?" },
    { name: "Éléonore", role: "Coach Carrière", text: "Oui la rediffusion est postée dans le salon #replays !" },
    { name: "Sébastien P.", role: "Fondateur E-com", text: "Qui est partant pour un co-working virtuel vendredi 14h ?" },
    { name: "Inès B.", role: "Copywriter", text: "Partante Sébastien ! Je te MP dans #co-working." },
    { name: "Romain K.", role: "Coach Mental", text: "Le mastermind de dimanche m'a débloqué un contrat à 2k€. Merci One Vision 🙌" }
  ];

  let index = 0;
  setInterval(() => {
    const msgData = messagesPool[index % messagesPool.length];
    index++;

    const msgElement = document.createElement('div');
    msgElement.className = 'chat-msg';
    msgElement.innerHTML = `
      <div class="chat-sender">
        <span class="sender-name">${msgData.name}</span>
        <span class="sender-role">• ${msgData.role}</span>
      </div>
      <div class="chat-text">${msgData.text}</div>
    `;

    chatContainer.appendChild(msgElement);

    // Garder seulement les 6 derniers messages pour la propreté
    if (chatContainer.children.length > 6) {
      chatContainer.removeChild(chatContainer.children[0]);
    }

    chatContainer.scrollTop = chatContainer.scrollHeight;
  }, 4500);

  // Fluctuations légères du nombre de spectateurs en live
  if (viewerCounter) {
    let currentViewers = 42;
    setInterval(() => {
      const delta = (Math.floor(Math.random() * 3) - 1); // -1, 0, ou +1
      currentViewers = Math.max(38, Math.min(52, currentViewers + delta));
      viewerCounter.textContent = `${currentViewers} en direct`;
    }, 6000);
  }
}

/* ==========================================================================
   7. MODALE D'ADHÉSION & CONNEXION (GESTION NOUVEAU VS MEMBRE AYANT PAYÉ)
   ========================================================================== */
function initCheckoutModal() {
  const modal = document.getElementById('checkoutModal');
  if (!modal) return;

  const closeBtn = document.getElementById('modalCloseBtn');
  const checkoutForm = document.getElementById('checkoutForm');
  const loginForm = document.getElementById('loginForm');

  const viewJoin = document.getElementById('viewJoin');
  const viewLogin = document.getElementById('viewLogin');
  const viewSuccess = document.getElementById('viewSuccess');
  const successActionBtn = document.getElementById('successActionBtn');
  const successTitle = document.getElementById('successTitle');
  const successDesc = document.getElementById('successDesc');

  function setMode(mode) {
    if (viewSuccess) viewSuccess.style.display = 'none';

    if (mode === 'login') {
      if (viewJoin) viewJoin.style.display = 'none';
      if (viewLogin) viewLogin.style.display = 'block';
      const firstInput = viewLogin ? viewLogin.querySelector('input') : null;
      if (firstInput) setTimeout(() => firstInput.focus(), 80);
    } else {
      if (viewLogin) viewLogin.style.display = 'none';
      if (viewJoin) viewJoin.style.display = 'block';
      const firstInput = viewJoin ? viewJoin.querySelector('input') : null;
      if (firstInput) setTimeout(() => firstInput.focus(), 80);
    }
  }

  const openModal = (mode = 'join') => {
    if (mode === 'join') {
      window.location.href = getAppUrl('checkout.html');
      return;
    }
    setMode(mode);
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  const closeModal = () => {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };

  // Synchronisation dynamique de l'interface : Nouveau visiteur vs Membre ayant déjà payé
  function updateMemberUI() {
    // En environnement PHP, l'état de session est géré directement par le serveur
    if (isPhpEnvironment()) {
      return;
    }

    const hasPaid = localStorage.getItem('ov_has_paid') === 'true';

    // 1. Bouton En-tête (Header)
    const headerBtn = document.querySelector('.header .nav-actions .open-checkout-btn, .header .nav-actions .open-login-btn, .header .nav-actions .open-dashboard-link');
    if (headerBtn) {
      if (hasPaid) {
        headerBtn.className = 'btn btn-secondary open-dashboard-link';
        headerBtn.innerHTML = `
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          <span>Mon Dashboard</span>
        `;
      } else {
        headerBtn.className = 'btn btn-primary open-checkout-btn';
        headerBtn.innerHTML = `
          <span>Rejoindre pour 9€/mois</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        `;
      }
    }

    // 2. Bouton Hero VSL
    const heroBtn = document.querySelector('.hero-actions button, .hero-actions a');
    if (heroBtn) {
      if (hasPaid) {
        heroBtn.className = 'btn btn-primary btn-lg btn-pulse open-dashboard-link';
        heroBtn.innerHTML = `
          <span>Accéder à mon Dashboard</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        `;
      } else {
        heroBtn.className = 'btn btn-primary btn-lg btn-pulse open-checkout-btn';
        heroBtn.innerHTML = `
          <span>Rejoindre One Vision Community — 9€/mois</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        `;
      }
    }

    // 3. Bouton Formule Tarif Unique (Section Comparatif)
    const pricingBtn = document.querySelector('.comparison-card button, .comparison-card a');
    if (pricingBtn) {
      if (hasPaid) {
        pricingBtn.className = 'btn btn-primary open-dashboard-link';
        pricingBtn.style.width = '100%';
        pricingBtn.innerHTML = `
          <span style="display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Accéder à mon Dashboard</span>
          </span>
        `;
      } else {
        pricingBtn.className = 'btn btn-primary open-checkout-btn';
        pricingBtn.style.width = '100%';
        pricingBtn.innerHTML = `Rejoindre pour 9€/mois`;
      }
    }

    // 4. Espace de connexion : masquer "Nouveau ici ? Rejoindre pour 9€" pour ceux qui ont déjà payé
    const loginSwitch = document.getElementById('loginSwitchMode');
    if (loginSwitch) {
      if (hasPaid) {
        loginSwitch.innerHTML = `Espace Membre Actif • <button type="button" id="resetMemberBtn" style="color:#0f172a;text-decoration:underline;cursor:pointer;background:none;border:none;font-weight:700;">Changer de compte</button>`;
        const resetBtn = document.getElementById('resetMemberBtn');
        if (resetBtn) {
          resetBtn.addEventListener('click', () => {
            localStorage.removeItem('ov_has_paid');
            localStorage.removeItem('ov_member_name');
            localStorage.removeItem('ov_member_email');
            updateMemberUI();
            setMode('join');
            showToast("Session fermée. Vous êtes de retour en mode nouveau visiteur.");
          });
        }
      } else {
        loginSwitch.innerHTML = `Nouveau ici ? <button type="button" id="switchToJoinBtn" style="color:#0f172a;text-decoration:underline;cursor:pointer;background:none;border:none;font-weight:700;">Rejoindre pour 9€/mois</button>`;
        const switchToJoinBtn = document.getElementById('switchToJoinBtn');
        if (switchToJoinBtn) {
          switchToJoinBtn.addEventListener('click', () => {
            window.location.href = getAppUrl('checkout.html');
          });
        }
      }
    }
  }

  // Initialisation de l'affichage selon le statut du visiteur
  updateMemberUI();

  // Écouteur global pour l'ouverture des pages & modales
  document.addEventListener('click', (e) => {
    const targetDashboard = e.target.closest('.open-dashboard-link');
    const targetJoin = e.target.closest('.open-checkout-btn');
    const targetLogin = e.target.closest('.open-login-btn');

    if (targetDashboard) {
      e.preventDefault();
      window.location.href = getAppUrl('dashboard.html');
      return;
    }

    if (targetJoin) {
      e.preventDefault();
      // Redirection inconditionnelle vers la page de paiement
      window.location.href = getAppUrl('checkout.html');
      return;
    }

    if (targetLogin) {
      e.preventDefault();
      if (isPhpEnvironment()) {
        window.location.href = 'login.php';
        return;
      }
      const hasPaid = localStorage.getItem('ov_has_paid') === 'true';
      if (hasPaid) {
        window.location.href = getAppUrl('dashboard.html');
      } else {
        openModal('login');
      }
      return;
    }
  });

  // Bascule depuis le formulaire d'adhésion vers la connexion
  const switchToLoginBtn = document.getElementById('switchToLoginBtn');
  if (switchToLoginBtn) {
    switchToLoginBtn.addEventListener('click', () => setMode('login'));
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeModal);
  }

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('open')) {
      closeModal();
    }
  });

  // Soumission Adhésion 9€/mois (Redirection vers checkout dédié)
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const nameInput = document.getElementById('memberName');
      const memberName = nameInput && nameInput.value.trim() ? nameInput.value.trim() : '';
      const emailInput = document.getElementById('memberEmail');
      const memberEmail = emailInput && emailInput.value.trim() ? emailInput.value.trim() : '';

      if (memberName) localStorage.setItem('ov_member_name', memberName);
      if (memberEmail) localStorage.setItem('ov_member_email', memberEmail);

      window.location.href = getAppUrl('checkout.html');
    });
  }

  // Soumission Connexion Membre -> Redirection directe vers dashboard
  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const emailInput = document.getElementById('loginEmail');
      const passInput = document.getElementById('loginPassword');
      const submitBtn = loginForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;

      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin-icon" style="animation: spin 1s linear infinite;">
          <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
          <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor"></path>
        </svg>
        Connexion en cours...
      `;

      if (isPhpEnvironment()) {
        const formData = new FormData();
        formData.append('email', emailInput ? emailInput.value : '');
        formData.append('password', passInput ? passInput.value : '');

        fetch('login.php', {
          method: 'POST',
          body: formData
        }).then(() => {
          showToast("👋 Connexion réussie ! Redirection vers votre Dashboard...");
          setTimeout(() => {
            window.location.href = 'dashboard.php';
          }, 500);
        }).catch(() => {
          window.location.href = 'login.php';
        });
        return;
      }

      setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;

        // Assure que l'état payé reste mémorisé
        localStorage.setItem('ov_has_paid', 'true');
        updateMemberUI();

        showToast("👋 Connexion réussie ! Redirection vers votre Dashboard...");
        setTimeout(() => {
          window.location.href = getAppUrl('dashboard.html');
        }, 600);
        loginForm.reset();
      }, 700);
    });
  }

  if (successActionBtn) {
    successActionBtn.addEventListener('click', () => {
      closeModal();
      window.location.href = getAppUrl('dashboard.html');
    });
  }
}

/* ==========================================================================
   MOTEUR DE FACTURATION OFFICIELLE & COMPTABILISATION SÉQUENTIELLE ONE VISION
   ========================================================================== */

function downloadBlobFile(filename, content, mimeType) {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  if (typeof showToast === 'function') {
    showToast(`📥 Téléchargement lancé : ${filename}`);
  }
}

function formatFrenchDate(dateObj = new Date()) {
  const months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
  const day = dateObj.getDate();
  const month = months[dateObj.getMonth()];
  const year = dateObj.getFullYear();
  return `${day} ${month} ${year}`;
}

const INVOICE_REGISTRY_KEY = 'ov_invoices_registry';
const INVOICE_COUNTER_KEY = 'ov_invoices_counter';

function getInvoicesRegistry() {
  try {
    const list = JSON.parse(localStorage.getItem(INVOICE_REGISTRY_KEY) || '[]');
    if (Array.isArray(list) && list.length > 0) return list;
  } catch(e) {}

  const initial = [
    {
      invCode: 'OV-2026-01',
      sequence: 1,
      clientName: localStorage.getItem('ov_member_name') || 'Désiré Gk',
      clientEmail: localStorage.getItem('ov_member_email') || 'membre@onevision.community',
      dateLabel: '24 Septembre 2026',
      paymentMethod: localStorage.getItem('ov_payment_method') || 'Carte Bancaire (•••• 4242)',
      amountTTC: '9,00 € TTC',
      amountHT: '7,50 €',
      tvaRate: '20 %',
      tvaAmount: '1,50 €',
      serviceTitle: 'Abonnement One Vision Community — Formule Illimitée',
      serviceDesc: "Accès illimité aux Sessions Live Mastermind, salons d'entraide, replays HD et outils business."
    }
  ];
  localStorage.setItem(INVOICE_REGISTRY_KEY, JSON.stringify(initial));
  localStorage.setItem(INVOICE_COUNTER_KEY, '1');
  return initial;
}

function generateNewInvoice({ clientName, clientEmail, paymentMethod }) {
  const currentInvoices = getInvoicesRegistry();
  let counter = parseInt(localStorage.getItem(INVOICE_COUNTER_KEY) || '1', 10);
  counter += 1;
  localStorage.setItem(INVOICE_COUNTER_KEY, String(counter));

  const padNum = String(counter).padStart(2, '0');
  const invCode = `OV-2026-${padNum}`;
  const dateLabel = formatFrenchDate(new Date());

  const newInvoice = {
    invCode: invCode,
    sequence: counter,
    clientName: (clientName && clientName.trim()) ? clientName.trim() : (localStorage.getItem('ov_member_name') || 'Désiré Gk'),
    clientEmail: (clientEmail && clientEmail.trim()) ? clientEmail.trim() : (localStorage.getItem('ov_member_email') || 'membre@onevision.community'),
    dateLabel: dateLabel,
    paymentMethod: paymentMethod || localStorage.getItem('ov_payment_method') || 'Carte Bancaire (•••• 4242)',
    amountTTC: '9,00 € TTC',
    amountHT: '7,50 €',
    tvaRate: '20 %',
    tvaAmount: '1,50 €',
    serviceTitle: 'Abonnement One Vision Community — Formule Illimitée',
    serviceDesc: "Accès illimité aux Sessions Live Mastermind, salons d'entraide, replays HD et outils business."
  };

  currentInvoices.unshift(newInvoice);
  localStorage.setItem(INVOICE_REGISTRY_KEY, JSON.stringify(currentInvoices));
  localStorage.setItem('ov_last_generated_invoice', JSON.stringify(newInvoice));
  return newInvoice;
}

function getInvoiceTemplateHtml(invoiceDataOrName, invCodeParam, dateLabelParam, paymentMethodParam, clientEmailParam) {
  let inv = {};
  if (typeof invoiceDataOrName === 'object' && invoiceDataOrName !== null) {
    inv = invoiceDataOrName;
  } else {
    inv = {
      clientName: invoiceDataOrName,
      invCode: invCodeParam,
      dateLabel: dateLabelParam,
      paymentMethod: paymentMethodParam,
      clientEmail: clientEmailParam
    };
  }

  const clientName = inv.clientName || localStorage.getItem('ov_member_name') || 'Désiré Gk';
  const clientEmail = inv.clientEmail || localStorage.getItem('ov_member_email') || 'membre@onevision.community';
  const invCode = inv.invCode || 'OV-2026-01';
  const dateLabel = inv.dateLabel || formatFrenchDate();
  const paymentMethod = inv.paymentMethod || localStorage.getItem('ov_payment_method') || 'Carte Bancaire (•••• 4242)';

  const isMobileMoney = paymentMethod.toLowerCase().includes('mobile money') || paymentMethod.toLowerCase().includes('orange') || paymentMethod.toLowerCase().includes('wave') || paymentMethod.toLowerCase().includes('mtn');
  const paymentGateway = isMobileMoney ? 'Paiement Mobile Sécurisé (Mobile Money)' : 'Stripe Payments Europe';

  const itemTitle = inv.serviceTitle || 'Abonnement One Vision Community — Formule Illimitée';
  const itemDesc = inv.serviceDesc || "Accès illimité aux Sessions Live Mastermind, salons d'entraide, replays HD et outils business.";
  const qty = '1';
  const unitPriceHT = inv.amountHT || '7,50 €';
  const tvaRate = inv.tvaRate || '20 %';
  const totalHT = inv.amountHT || '7,50 €';
  const totalTVA = inv.tvaAmount || '1,50 €';
  const totalTTC = inv.amountTTC || '9,00 € TTC';

  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facture Officielle ${invCode} - One Vision Community</title>
  
  <!-- Google Fonts : Great Vibes & Plus Jakarta Sans (Identique au Header du site) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background: #f1f5f9;
      color: #0f172a;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      padding: 40px 20px;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    .print-bar {
      max-width: 820px;
      margin: 0 auto 20px auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 13.5px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      border: none;
    }

    .btn-print {
      background: #0284c7;
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    }
    .btn-print:hover {
      background: #0369a1;
      transform: translateY(-1px);
    }

    .btn-back {
      background: #ffffff;
      color: #475569;
      border: 1px solid #cbd5e1;
    }
    .btn-back:hover {
      background: #f8fafc;
      color: #0f172a;
    }

    .invoice-wrapper {
      max-width: 820px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 48px;
      box-shadow: 0 12px 35px rgba(15, 23, 42, 0.06);
    }

    /* 1. EN-TÊTE : LOGO OFFICIEL DU SITE + INFOS LÉGALES & MÉTA */
    .invoice-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 28px;
      border-bottom: 2px solid #f1f5f9;
      gap: 24px;
    }

    .logo-brand-header {
      display: flex;
      align-items: center;
      gap: 14px;
      text-decoration: none;
    }

    .logo-icon-box {
      width: 46px;
      height: 46px;
      border-radius: 10px;
      background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      font-weight: 900;
      font-size: 17px;
      letter-spacing: -0.04em;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
      flex-shrink: 0;
    }

    .logo-text-box {
      display: flex;
      flex-direction: column;
      line-height: 1.15;
    }

    .logo-brand-name {
      font-weight: 800;
      font-size: 24px;
      letter-spacing: -0.025em;
      color: #0f172a;
      display: inline-flex;
      align-items: baseline;
    }

    .logo-cursive-one {
      font-family: 'Great Vibes', 'Alex Brush', cursive;
      font-weight: 400;
      font-size: 34px;
      line-height: 0.8;
      display: inline-block;
      color: #0284c7;
      margin-right: 6px;
      vertical-align: -2px;
      letter-spacing: 0.02em;
    }

    .logo-sub-name {
      font-weight: 700;
      font-size: 11px;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: #64748b;
      margin-top: 2px;
    }

    .brand-legal {
      font-size: 11.5px;
      color: #64748b;
      margin-top: 10px;
      line-height: 1.65;
    }

    .brand-legal strong {
      color: #1e293b;
      font-weight: 700;
    }

    .invoice-meta-right {
      text-align: right;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #dcfce7;
      color: #15803d;
      border: 1px solid #bbf7d0;
      font-size: 12px;
      font-weight: 800;
      padding: 5px 14px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 10px;
    }

    .badge-dot {
      font-size: 10px;
      color: #16a34a;
    }

    .invoice-code {
      font-size: 20px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.01em;
      line-height: 1.2;
    }

    .invoice-dates {
      font-size: 12.5px;
      color: #64748b;
      margin-top: 6px;
      line-height: 1.6;
    }

    .invoice-dates strong {
      color: #334155;
    }

    /* 2. PARTIES PRENANTES */
    .parties-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      padding: 26px 0;
      border-bottom: 1px solid #f1f5f9;
    }

    .party-card {
      background: #fafafa;
      border: 1px solid #f1f5f9;
      border-radius: 10px;
      padding: 18px 20px;
    }

    .party-label {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #0284c7;
      margin-bottom: 8px;
    }

    .party-name {
      font-size: 15.5px;
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 4px;
    }

    .party-info {
      font-size: 12.5px;
      color: #475569;
      line-height: 1.65;
    }

    /* 3. TABLEAU DES PRESTATIONS (ALIGNEMENT STRICT SUR UNE SEULE LIGNE) */
    .table-container {
      margin: 28px 0 24px 0;
      overflow-x: auto;
    }

    .invoice-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    .invoice-table th {
      background: #f8fafc;
      padding: 13px 14px;
      font-size: 11px;
      font-weight: 800;
      color: #475569;
      border-top: 1px solid #e2e8f0;
      border-bottom: 1px solid #e2e8f0;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      white-space: nowrap;
    }

    .invoice-table td {
      padding: 16px 14px;
      font-size: 13px;
      color: #334155;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }

    .col-desc { text-align: left; }
    .col-qty { text-align: center; }
    .col-pu { text-align: right; }
    .col-tva { text-align: right; }
    .col-total { text-align: right; }

    .nowrap {
      white-space: nowrap;
    }

    .item-title {
      font-weight: 700;
      color: #0f172a;
      font-size: 13.5px;
      line-height: 1.4;
    }

    .item-desc {
      font-size: 12px;
      color: #64748b;
      margin-top: 4px;
      line-height: 1.45;
    }

    .qty-pill {
      display: inline-block;
      background: #f1f5f9;
      color: #0f172a;
      font-weight: 700;
      padding: 2px 10px;
      border-radius: 6px;
      font-size: 12px;
    }

    /* 4. ZONE DES TOTAUX */
    .totals-area {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 24px;
    }

    .totals-box {
      width: 320px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 16px 20px;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 6px 0;
      font-size: 13px;
      color: #64748b;
    }

    .total-row strong {
      color: #1e293b;
    }

    .total-row.final {
      border-top: 2px solid #0f172a;
      padding-top: 10px;
      margin-top: 8px;
      font-size: 16.5px;
      font-weight: 800;
      color: #0f172a;
    }

    .total-row.final .total-amount-highlight {
      color: #0284c7;
    }

    /* 5. ENCADRÉ DE RÈGLEMENT ACQUITTÉ */
    .receipt-box {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 10px;
      padding: 14px 20px;
      margin-bottom: 26px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }

    .receipt-text {
      font-size: 12.5px;
      font-weight: 700;
      color: #166534;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .receipt-amount {
      font-size: 13px;
      color: #15803d;
      font-weight: 800;
      background: #ffffff;
      padding: 4px 12px;
      border-radius: 6px;
      border: 1px solid #bbf7d0;
    }

    /* 6. PIED DE PAGE LÉGAL */
    .legal-footer {
      border-top: 1px solid #f1f5f9;
      padding-top: 20px;
      font-size: 11.5px;
      color: #94a3b8;
      text-align: center;
      line-height: 1.7;
    }

    /* OPTIMISATION IMPRESSION / PDF */
    @media print {
      body {
        background: #ffffff;
        padding: 0;
      }
      .print-bar {
        display: none !important;
      }
      .invoice-wrapper {
        border: none;
        box-shadow: none;
        padding: 0;
        max-width: 100%;
      }
      @page {
        margin: 12mm 15mm;
        size: A4 portrait;
      }
    }

    @media (max-width: 640px) {
      .invoice-wrapper { padding: 24px 18px; }
      .invoice-top { flex-direction: column; align-items: flex-start; }
      .invoice-meta-right { text-align: left; align-items: flex-start; }
      .parties-grid { grid-template-columns: 1fr; gap: 16px; }
      .totals-box { width: 100%; }
      .receipt-box { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>

  <div class="print-bar">
    <a href="dashboard.html" class="btn-action btn-back">← Retour au Dashboard</a>
    <button class="btn-action btn-print" onclick="window.print()">
      🖨️ Imprimer / Enregistrer en PDF
    </button>
  </div>

  <div class="invoice-wrapper">
    <div class="invoice-top">
      <div>
        <div class="logo-brand-header">
          <div class="logo-icon-box">OV</div>
          <div class="logo-text-box">
            <div class="logo-brand-name"><span class="logo-cursive-one">One</span> Vision</div>
            <div class="logo-sub-name">Community</div>
          </div>
        </div>
        <div class="brand-legal">
          <strong>ONE VISION COMMUNITY SAS</strong> • Capital de 10 000 €<br>
          SIRET : 912 345 678 00014 • RCS Paris B 912 345 678<br>
          N° TVA Intracommunautaire : FR 32 912345678 • contact@onevision.community
        </div>
      </div>

      <div class="invoice-meta-right">
        <div class="status-badge">
          <span class="badge-dot">●</span> Facture Acquittée
        </div>
        <div class="invoice-code">FACTURE N° ${invCode}</div>
        <div class="invoice-dates">
          <div><strong>Date :</strong> ${dateLabel}</div>
          <div><strong>Moyen de règlement :</strong> ${escapeHtml(paymentMethod)}</div>
        </div>
      </div>
    </div>

    <div class="parties-grid">
      <div class="party-card">
        <div class="party-label">Émetteur</div>
        <div class="party-name">ONE VISION COMMUNITY SAS</div>
        <div class="party-info">
          128 Boulevard Saint-Germain<br>
          75006 Paris, France<br>
          Email : contact@onevision.community
        </div>
      </div>

      <div class="party-card">
        <div class="party-label">Facturé à (Client)</div>
        <div class="party-name">${escapeHtml(clientName)}</div>
        <div class="party-info">
          Membre One Vision Community<br>
          Email : ${escapeHtml(clientEmail)}
        </div>
      </div>
    </div>

    <div class="table-container">
      <table class="invoice-table">
        <colgroup>
          <col style="width: 48%;">
          <col style="width: 10%;">
          <col style="width: 14%;">
          <col style="width: 12%;">
          <col style="width: 16%;">
        </colgroup>
        <thead>
          <tr>
            <th class="col-desc">Désignation de la prestation</th>
            <th class="col-qty">Qté</th>
            <th class="col-pu">P.U. HT</th>
            <th class="col-tva">TVA</th>
            <th class="col-total">Total HT</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="col-desc">
              <div class="item-title">${escapeHtml(itemTitle)}</div>
              <div class="item-desc">${escapeHtml(itemDesc)}</div>
            </td>
            <td class="col-qty nowrap"><span class="qty-pill">${qty}</span></td>
            <td class="col-pu nowrap">${unitPriceHT}</td>
            <td class="col-tva nowrap">${tvaRate}</td>
            <td class="col-total nowrap"><strong>${totalHT}</strong></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="totals-area">
      <div class="totals-box">
        <div class="total-row">
          <span>Total HT :</span>
          <strong class="nowrap">${totalHT}</strong>
        </div>
        <div class="total-row">
          <span>TVA (20 %) :</span>
          <strong class="nowrap">${totalTVA}</strong>
        </div>
        <div class="total-row final">
          <span>Total TTC Réglé :</span>
          <span class="total-amount-highlight nowrap">${totalTTC}</span>
        </div>
      </div>
    </div>

    <div class="receipt-box">
      <div class="receipt-text">
        ✓ Règlement validé et sécurisé via ${escapeHtml(paymentGateway)}
      </div>
      <div class="receipt-amount nowrap">
        Montant acquitté : ${totalTTC}
      </div>
    </div>

    <div class="legal-footer">
      Facture acquittée le ${dateLabel}. Aucun escompte accordé pour paiement comptant.<br>
      TVA acquittée d'après les débits. Merci pour votre confiance et bienvenue dans l'aventure One Vision !
    </div>
  </div>
</body>
</html>`;
}

function triggerInvoiceDownload(invoiceOrCode, optionalDate) {
  let invObj = null;
  if (typeof invoiceOrCode === 'object' && invoiceOrCode !== null) {
    invObj = invoiceOrCode;
  } else {
    const registry = getInvoicesRegistry();
    invObj = registry.find(item => item.invCode === invoiceOrCode);
    if (!invObj) {
      invObj = {
        invCode: invoiceOrCode || 'OV-2026-01',
        dateLabel: optionalDate || formatFrenchDate(),
        clientName: localStorage.getItem('ov_member_name') || 'Désiré Gk',
        clientEmail: localStorage.getItem('ov_member_email') || 'membre@onevision.community',
        paymentMethod: localStorage.getItem('ov_payment_method') || 'Carte Bancaire (•••• 4242)',
        amountTTC: '9,00 € TTC',
        amountHT: '7,50 €',
        tvaRate: '20 %',
        tvaAmount: '1,50 €',
        serviceTitle: 'Abonnement One Vision Community — Formule Illimitée',
        serviceDesc: "Accès illimité aux Sessions Live Mastermind, salons d'entraide, replays HD et outils business."
      };
    }
  }

  const htmlContent = getInvoiceTemplateHtml(invObj);
  downloadBlobFile(`Facture_${invObj.invCode}.html`, htmlContent, 'text/html;charset=utf-8;');
  if (typeof showToast === 'function') {
    showToast(`📥 Facture #${invObj.invCode} téléchargée avec succès.`);
  }

  const printWin = window.open('', '_blank');
  if (printWin) {
    printWin.document.write(htmlContent);
    printWin.document.close();
  }
}

/* ==========================================================================
   PAGE CHECKOUT DÉDIÉE (CHECKOUT.HTML)
   ========================================================================== */
function initCheckoutPage() {
  const checkoutForm = document.getElementById('checkoutPaymentForm');
  if (!checkoutForm) return;

  // Si une commande était en cours ou vient d'être payée, rediriger vers le succès
  const checkPendingOrder = new URLSearchParams(window.location.search).get('order') || sessionStorage.getItem('ov_current_order');
  if (checkPendingOrder && isPhpEnvironment()) {
    fetch(`api/check-payment-status.php?order=${encodeURIComponent(checkPendingOrder)}`)
      .then(res => res.json())
      .then(statusData => {
        if (statusData && statusData.status === 'paid') {
          sessionStorage.removeItem('ov_current_order');
          window.location.href = statusData.redirect_url || `checkout-success.php?order=${encodeURIComponent(checkPendingOrder)}`;
        }
      })
      .catch(() => {});
  }

  const nameInput = document.getElementById('checkoutName');
  const emailInput = document.getElementById('checkoutEmail');
  const passwordInput = document.getElementById('checkoutPassword');

  // Sélecteur de méthode de paiement
  const methodCard = document.getElementById('methodCard');
  const methodMobileMoney = document.getElementById('methodMobileMoney');
  const paymentMethodHidden = document.getElementById('paymentMethodHidden');
  const cardDetailsBox = document.getElementById('cardDetailsBox');
  const mobileMoneyDetailsBox = document.getElementById('mobileMoneyDetailsBox');

  // Champs Carte Bancaire
  const cardInput = document.getElementById('cardNumber');
  const expInput = document.getElementById('cardExp');
  const cvcInput = document.getElementById('cardCvc');
  const cardHolderInput = document.getElementById('cardHolder');

  // Widget SasPay Mobile Money
  const saspayCountrySelect = document.getElementById('saspayCountrySelect');
  const saspayMethodsGrid = document.getElementById('saspayMethodsGrid');
  const saspayPhonePrefix = document.getElementById('saspayPhonePrefix');
  const saspayPhoneInput = document.getElementById('saspayPhoneInput');
  const saspaySelectedOperator = document.getElementById('saspaySelectedOperator');
  const saspayHeaderAmount = document.getElementById('saspayHeaderAmount');
  const saspayBreakdownAmount = document.getElementById('saspayBreakdownAmount');
  const saspayBreakdownFee = document.getElementById('saspayBreakdownFee');
  const saspayBreakdownTotal = document.getElementById('saspayBreakdownTotal');
  const momoAmountHidden = document.getElementById('momoAmountHidden');
  const momoCurrencyHidden = document.getElementById('momoCurrencyHidden');

  // Éléments du formulaire multi-étapes
  const checkoutStep1 = document.getElementById('checkoutStep1');
  const checkoutStep2 = document.getElementById('checkoutStep2');
  const stepPill1 = document.getElementById('stepPill1');
  const stepPill2 = document.getElementById('stepPill2');
  const stepPillNum1 = document.getElementById('stepPillNum1');
  const stepPillNum2 = document.getElementById('stepPillNum2');
  const goToStep2Btn = document.getElementById('goToStep2Btn');
  const backToStep1Btn = document.getElementById('backToStep1Btn');
  const step2SummaryName = document.getElementById('step2SummaryName');
  const step2SummaryEmail = document.getElementById('step2SummaryEmail');

  // Modale de traitement
  const processingModal = document.getElementById('paymentProcessingModal');
  const processingTitle = document.getElementById('processingTitle');
  const processingDesc = document.getElementById('processingDesc');
  const processingDeviceAlert = document.getElementById('processingDeviceAlert');
  const processingAlertTitle = document.getElementById('processingAlertTitle');
  const processingAlertMsg = document.getElementById('processingAlertMsg');
  const processingProgressBar = document.getElementById('processingProgressBar');
  const processingSpinnerIcon = document.getElementById('processingSpinnerIcon');
  const processingTimerBadge = document.getElementById('processingTimerBadge');
  const processingTimerText = document.getElementById('processingTimerText');
  const processingActions = document.getElementById('processingActions');
  const processingRetryBtn = document.getElementById('processingRetryBtn');
  const processingCancelBtn = document.getElementById('processingCancelBtn');
  const processingQrCard = document.getElementById('processingQrCard');
  const processingQrImg = document.getElementById('processingQrImg');
  const processingQrDirectBtn = document.getElementById('processingQrDirectBtn');

  // Zone d'attente et QR code embarquée directement sur la page (sans redirection)
  const checkoutPaymentForm = document.getElementById('checkoutPaymentForm');
  const checkoutWaitingArea = document.getElementById('checkoutWaitingArea');
  const inpageStatePending = document.getElementById('inpageStatePending');
  const inpageStateSuccess = document.getElementById('inpageStateSuccess');
  const inpageWaitingTitle = document.getElementById('inpageWaitingTitle');
  const inpageWaitingDesc = document.getElementById('inpageWaitingDesc');
  const inpageQrSection = document.getElementById('inpageQrSection');
  const inpageQrCanvas = document.getElementById('inpageQrCanvas');
  const inpageDirectLinkBtn = document.getElementById('inpageDirectLinkBtn');
  const inpageTimerText = document.getElementById('inpageTimerText');
  const inpageProgressBar = document.getElementById('inpageProgressBar');
  const inpageCancelBtn = document.getElementById('inpageCancelBtn');
  const inpageSuccessOrder = document.getElementById('inpageSuccessOrder');
  const inpageSuccessAmount = document.getElementById('inpageSuccessAmount');

  // Soumission
  const submitBtn = document.getElementById('submitPaymentBtn');
  const submitText = document.getElementById('submitPaymentText');

  let currentPaymentMethod = 'card'; // 'card' ou 'mobile_money'

  // Configuration officielle des pays et opérateurs SasPay
  const saspayCountries = {
    "Cameroun": {
      currency: "XAF",
      amount: "5 904",
      amountRaw: 5904,
      fee: "0 XAF",
      feeRaw: 0,
      total: "5 904 XAF",
      prefix: "+237",
      phonePlaceholder: "67 12 34 56 7",
      operators: [
        { id: "MTN MoMo", name: "MTN MoMo Cameroun", fee: "0 XAF de frais", icon: "🟡" },
        { id: "Orange Money", name: "Orange Money Cameroun", fee: "0 XAF de frais", icon: "🟠" },
        { id: "Crypto", name: "Crypto / Stablecoin", fee: "0 USD de frais", icon: "🟣" }
      ]
    },
    "Côte d'Ivoire": {
      currency: "XOF",
      amount: "5 900",
      amountRaw: 5900,
      fee: "0 XOF",
      feeRaw: 0,
      total: "5 900 XOF",
      prefix: "+225",
      phonePlaceholder: "07 77 95 73 37",
      operators: [
        { id: "Wave", name: "Wave CI", fee: "0 XOF de frais", icon: "🔵" },
        { id: "MTN MoMo", name: "MTN MoMo CI", fee: "0 XOF de frais", icon: "🟡" },
        { id: "Orange Money", name: "Orange Money CI", fee: "0 XOF de frais", icon: "🟠" },
        { id: "Moov Money", name: "Moov Money CI", fee: "0 XOF de frais", icon: "🟢" }
      ]
    },
    "Sénégal": {
      currency: "XOF",
      amount: "5 900",
      amountRaw: 5900,
      fee: "0 XOF",
      feeRaw: 0,
      total: "5 900 XOF",
      prefix: "+221",
      phonePlaceholder: "77 123 45 67",
      operators: [
        { id: "Wave", name: "Wave Sénégal", fee: "0 XOF de frais", icon: "🔵" },
        { id: "Orange Money", name: "Orange Money SN", fee: "0 XOF de frais", icon: "🟠" },
        { id: "Free Money", name: "Free Money SN", fee: "0 XOF de frais", icon: "🔴" }
      ]
    },
    "Bénin": {
      currency: "XOF",
      amount: "5 900",
      amountRaw: 5900,
      fee: "0 XOF",
      feeRaw: 0,
      total: "5 900 XOF",
      prefix: "+229",
      phonePlaceholder: "97 12 34 56",
      operators: [
        { id: "MTN MoMo", name: "MTN MoMo Bénin", fee: "0 XOF de frais", icon: "🟡" },
        { id: "Moov Money", name: "Moov Money Bénin", fee: "0 XOF de frais", icon: "🟢" }
      ]
    },
    "Burkina Faso": {
      currency: "XOF",
      amount: "5 900",
      amountRaw: 5900,
      fee: "0 XOF",
      feeRaw: 0,
      total: "5 900 XOF",
      prefix: "+226",
      phonePlaceholder: "70 12 34 56",
      operators: [
        { id: "Orange Money", name: "Orange Money BF", fee: "0 XOF de frais", icon: "🟠" },
        { id: "Moov Money", name: "Moov Money BF", fee: "0 XOF de frais", icon: "🟢" }
      ]
    },
    "Mali": {
      currency: "XOF",
      amount: "5 900",
      amountRaw: 5900,
      fee: "0 XOF",
      feeRaw: 0,
      total: "5 900 XOF",
      prefix: "+223",
      phonePlaceholder: "70 12 34 56",
      operators: [
        { id: "Orange Money", name: "Orange Money Mali", fee: "0 XOF de frais", icon: "🟠" },
        { id: "Moov Money", name: "Moov Money Mali", fee: "0 XOF de frais", icon: "🟢" }
      ]
    },
    "Togo": {
      currency: "XOF",
      amount: "5 900",
      amountRaw: 5900,
      fee: "0 XOF",
      feeRaw: 0,
      total: "5 900 XOF",
      prefix: "+228",
      phonePlaceholder: "90 12 34 56",
      operators: [
        { id: "T-Money", name: "T-Money Togo", fee: "0 XOF de frais", icon: "🟡" },
        { id: "Moov Money", name: "Moov Money Togo", fee: "0 XOF de frais", icon: "🟢" }
      ]
    },
    "Guinée": {
      currency: "GNF",
      amount: "84 000",
      amountRaw: 84000,
      fee: "0 GNF",
      feeRaw: 0,
      total: "84 000 GNF",
      prefix: "+224",
      phonePlaceholder: "620 12 34 56",
      operators: [
        { id: "Orange Money", name: "Orange Money GN", fee: "0 GNF de frais", icon: "🟠" },
        { id: "MTN MoMo", name: "MTN MoMo GN", fee: "0 GNF de frais", icon: "🟡" }
      ]
    },
    "RDC": {
      currency: "USD",
      amount: "9.80",
      amountRaw: 9.80,
      fee: "0.00 USD",
      feeRaw: 0.00,
      total: "9.80 USD",
      prefix: "+243",
      phonePlaceholder: "81 234 5678",
      operators: [
        { id: "Vodacom M-Pesa", name: "Vodacom M-Pesa", fee: "0 USD de frais", icon: "🔴" },
        { id: "Airtel Money", name: "Airtel Money RDC", fee: "0 USD de frais", icon: "🔴" },
        { id: "Orange Money", name: "Orange Money RDC", fee: "0 USD de frais", icon: "🟠" }
      ]
    },
    "Congo": {
      currency: "XAF",
      amount: "5 904",
      amountRaw: 5904,
      fee: "0 XAF",
      feeRaw: 0,
      total: "5 904 XAF",
      prefix: "+242",
      phonePlaceholder: "06 123 4567",
      operators: [
        { id: "MTN MoMo", name: "MTN MoMo Congo", fee: "0 XAF de frais", icon: "🟡" },
        { id: "Airtel Money", name: "Airtel Money Congo", fee: "0 XAF de frais", icon: "🔴" }
      ]
    },
    "Gabon": {
      currency: "XAF",
      amount: "5 904",
      amountRaw: 5904,
      fee: "0 XAF",
      feeRaw: 0,
      total: "5 904 XAF",
      prefix: "+241",
      phonePlaceholder: "074 12 34 56",
      operators: [
        { id: "Airtel Money", name: "Airtel Money Gabon", fee: "0 XAF de frais", icon: "🔴" },
        { id: "Moov Money", name: "Moov Money Gabon", fee: "0 XAF de frais", icon: "🟢" }
      ]
    },
    "France": {
      currency: "EUR",
      amount: "9.00",
      amountRaw: 9.00,
      fee: "0.00 EUR",
      feeRaw: 0.00,
      total: "9.00 EUR",
      prefix: "+33",
      phonePlaceholder: "06 12 34 56 78",
      operators: [
        { id: "Orange Money", name: "Orange Money Europe", fee: "0,00 € de frais", icon: "🟠" },
        { id: "Crypto", name: "Crypto / Stablecoin", fee: "0,00 € de frais", icon: "🟣" }
      ]
    }
  };

  // 1. Rendu dynamique du widget SasPay en fonction du pays sélectionné
  function updateSaspayWidget(countryKey) {
    const data = saspayCountries[countryKey] || saspayCountries["Cameroun"];

    // Prise en compte du montant de test surchargé (ex: 100 XOF)
    const isTestOverride = window.SASPAY_CONFIG && window.SASPAY_CONFIG.testOverrideActive;
    const activeAmount = isTestOverride ? String(window.SASPAY_CONFIG.testOverrideAmount) : data.amount;
    const activeCurrency = isTestOverride ? (window.SASPAY_CONFIG.testOverrideCurrency || 'XOF') : data.currency;
    const activeTotal = `${activeAmount} ${activeCurrency}`;
    const activeAmountRaw = isTestOverride ? Number(window.SASPAY_CONFIG.testOverrideAmount) : data.amountRaw;

    if (saspayHeaderAmount) saspayHeaderAmount.textContent = activeTotal;
    if (saspayPhonePrefix) saspayPhonePrefix.textContent = `📱 ${data.prefix}`;
    if (saspayPhoneInput) saspayPhoneInput.placeholder = data.phonePlaceholder;

    if (saspayBreakdownAmount) saspayBreakdownAmount.textContent = activeTotal;
    if (saspayBreakdownFee) saspayBreakdownFee.textContent = isTestOverride ? `0 ${activeCurrency}` : data.fee;
    if (saspayBreakdownTotal) saspayBreakdownTotal.textContent = activeTotal;

    if (momoAmountHidden) momoAmountHidden.value = activeAmountRaw;
    if (momoCurrencyHidden) momoCurrencyHidden.value = activeCurrency;

    function updateOperatorTip(opId) {
      const tipText = document.getElementById('saspayOperatorTipText');
      if (!tipText) return;
      const isWave = (opId || '').toLowerCase().includes('wave');
      if (isWave) {
        tipText.innerHTML = `<strong>📲 Wave :</strong> Sur smartphone, l'application Wave s'ouvre automatiquement. Sur ordinateur, Wave affiche un QR code sécurisé directement sur la page à scanner avec votre application Wave. <em>(Pour un push direct sur votre écran sans scan, choisissez MTN MoMo ou Moov).</em>`;
      } else {
        tipText.innerHTML = `<strong>⚡ Push direct (${escapeHtml(opId || 'Mobile Money')}) :</strong> Une invite USSD s'affichera directement sur l'écran de votre téléphone pour saisir votre code PIN secret (sans quitter la page).`;
      }
    }

    // Rendu des boutons opérateurs
    if (saspayMethodsGrid) {
      saspayMethodsGrid.innerHTML = '';
      data.operators.forEach((op, index) => {
        const isWave = op.id.toLowerCase().includes('wave');
        const badgeColor = isWave ? '#0284c7' : '#16a34a';
        const badgeLabel = isWave ? '📲 App / QR' : '⚡ Push direct';

        const card = document.createElement('div');
        card.className = `saspay-method-card ${index === 0 ? 'active' : ''}`;
        card.dataset.operator = op.id;
        card.innerHTML = `
          <div class="saspay-method-icon">${op.icon}</div>
          <div class="saspay-method-info">
            <span class="saspay-method-name">${escapeHtml(op.name)}</span>
            <span class="saspay-method-fee">${escapeHtml(op.fee)}</span>
            <span style="font-size:0.7rem; color:${badgeColor}; font-weight:700; display:inline-block; margin-top:2px;">${badgeLabel}</span>
          </div>
        `;

        card.addEventListener('click', () => {
          document.querySelectorAll('.saspay-method-card').forEach(c => c.classList.remove('active'));
          card.classList.add('active');
          if (saspaySelectedOperator) saspaySelectedOperator.value = op.id;
          updateOperatorTip(op.id);
        });

        saspayMethodsGrid.appendChild(card);
      });

      if (saspaySelectedOperator && data.operators.length > 0) {
        saspaySelectedOperator.value = data.operators[0].id;
        updateOperatorTip(data.operators[0].id);
      }
    }

    if (currentPaymentMethod === 'mobile_money' && submitText) {
      submitText.textContent = `Payer ${activeTotal} via Mobile Money →`;
    }
  }

  if (saspayCountrySelect) {
    saspayCountrySelect.addEventListener('change', (e) => {
      updateSaspayWidget(e.target.value);
    });
    // Initialisation
    updateSaspayWidget(saspayCountrySelect.value || "Cameroun");
  }

  // 2. Bascule entre Carte Bancaire et Mobile Money
  function selectPaymentMethod(method) {
    currentPaymentMethod = method;
    if (paymentMethodHidden) paymentMethodHidden.value = method;

    const isTestOverride = window.SASPAY_CONFIG && window.SASPAY_CONFIG.testOverrideActive;
    const testAmountStr = isTestOverride ? `${window.SASPAY_CONFIG.testOverrideAmount} ${window.SASPAY_CONFIG.testOverrideCurrency || 'XOF'}` : null;

    if (method === 'card') {
      if (methodCard) methodCard.classList.add('selected');
      if (methodMobileMoney) methodMobileMoney.classList.remove('selected');
      if (cardDetailsBox) cardDetailsBox.style.display = 'block';
      if (mobileMoneyDetailsBox) mobileMoneyDetailsBox.style.display = 'none';
      if (submitText) {
        submitText.textContent = testAmountStr ? `Payer ${testAmountStr} par Carte Bancaire →` : "Payer 9,00 € par Carte Bancaire →";
      }
    } else {
      if (methodMobileMoney) methodMobileMoney.classList.add('selected');
      if (methodCard) methodCard.classList.remove('selected');
      if (cardDetailsBox) cardDetailsBox.style.display = 'none';
      if (mobileMoneyDetailsBox) mobileMoneyDetailsBox.style.display = 'block';

      const selCountry = saspayCountrySelect ? saspayCountrySelect.value : "Cameroun";
      const data = saspayCountries[selCountry] || saspayCountries["Cameroun"];
      const activeTotal = testAmountStr || data.total;
      if (submitText) submitText.textContent = `Payer ${activeTotal} via Mobile Money →`;
    }
  }

  if (methodCard) {
    methodCard.addEventListener('click', () => selectPaymentMethod('card'));
    methodCard.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        selectPaymentMethod('card');
      }
    });
  }

  if (methodMobileMoney) {
    methodMobileMoney.addEventListener('click', () => selectPaymentMethod('mobile_money'));
    methodMobileMoney.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        selectPaymentMethod('mobile_money');
      }
    });
  }

  // 3. Formatage de Carte Bancaire & Mobile Money
  if (cardInput) {
    cardInput.addEventListener('input', (e) => {
      let val = e.target.value.replace(/\D/g, '');
      if (val.length > 16) val = val.substring(0, 16);
      const parts = val.match(/.{1,4}/g);
      e.target.value = parts ? parts.join(' ') : '';
      hideError('cardError', cardInput);
    });
  }

  if (expInput) {
    expInput.addEventListener('input', (e) => {
      let val = e.target.value.replace(/\D/g, '');
      if (val.length > 4) val = val.substring(0, 4);
      if (val.length >= 3) {
        e.target.value = val.substring(0, 2) + '/' + val.substring(2);
      } else {
        e.target.value = val;
      }
      hideError('expError', expInput);
    });
  }

  if (cvcInput) {
    cvcInput.addEventListener('input', (e) => {
      let val = e.target.value.replace(/\D/g, '');
      if (val.length > 4) val = val.substring(0, 4);
      e.target.value = val;
      hideError('cvcError', cvcInput);
    });
  }

  if (saspayPhoneInput) {
    saspayPhoneInput.addEventListener('input', (e) => {
      hideError('momoPhoneError', saspayPhoneInput);
    });
  }

  function showError(errorId, inputEl) {
    const errorEl = document.getElementById(errorId);
    if (errorEl) errorEl.classList.add('visible');
    if (inputEl) inputEl.classList.add('input-error');
  }

  function hideError(errorId, inputEl) {
    const errorEl = document.getElementById(errorId);
    if (errorEl) errorEl.classList.remove('visible');
    if (inputEl) inputEl.classList.remove('input-error');
  }

  // 3. Navigation multi-étapes (Étape 1 : Identifiants -> Étape 2 : Paiement sécurisé)
  function validateStep1() {
    let isValid = true;
    const nameVal = nameInput ? nameInput.value.trim() : '';
    const emailVal = emailInput ? emailInput.value.trim() : '';
    const passVal = passwordInput ? passwordInput.value : '';

    if (!nameVal || nameVal.length < 2) {
      showError('nameError', nameInput);
      isValid = false;
    } else {
      hideError('nameError', nameInput);
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailVal || !emailRegex.test(emailVal)) {
      showError('emailError', emailInput);
      isValid = false;
    } else {
      hideError('emailError', emailInput);
    }

    if (passwordInput && (!passVal || passVal.length < 6)) {
      showError('passwordError', passwordInput);
      isValid = false;
    } else if (passwordInput) {
      hideError('passwordError', passwordInput);
    }

    if (!isValid) {
      const firstError = checkoutStep1 ? checkoutStep1.querySelector('.form-input.input-error') : null;
      if (firstError) firstError.focus();
    }
    return isValid;
  }

  function goToStep2() {
    if (!validateStep1()) return;

    const nameVal = nameInput ? nameInput.value.trim() : 'Membre';
    const emailVal = emailInput ? emailInput.value.trim() : '';

    if (step2SummaryName) step2SummaryName.textContent = nameVal;
    if (step2SummaryEmail) step2SummaryEmail.textContent = emailVal;

    if (checkoutStep1) checkoutStep1.style.display = 'none';
    if (checkoutStep2) checkoutStep2.style.display = 'block';

    if (stepPill1) {
      stepPill1.classList.remove('active');
      stepPill1.classList.add('completed');
    }
    if (stepPillNum1) stepPillNum1.textContent = '✓';

    if (stepPill2) {
      stepPill2.classList.add('active');
    }

    const card = document.querySelector('.checkout-card');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function goToStep1() {
    if (checkoutStep2) checkoutStep2.style.display = 'none';
    if (checkoutStep1) checkoutStep1.style.display = 'block';

    if (stepPill2) {
      stepPill2.classList.remove('active');
    }
    if (stepPill1) {
      stepPill1.classList.remove('completed');
      stepPill1.classList.add('active');
    }
    if (stepPillNum1) stepPillNum1.textContent = '1';

    const card = document.querySelector('.checkout-card');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  if (goToStep2Btn) {
    goToStep2Btn.addEventListener('click', goToStep2);
  }

  if (backToStep1Btn) {
    backToStep1Btn.addEventListener('click', goToStep1);
  }

  if (stepPill1) {
    stepPill1.addEventListener('click', () => {
      if (checkoutStep2 && checkoutStep2.style.display !== 'none') {
        goToStep1();
      }
    });
  }

  // Appuyer sur Entrée dans les champs de l'étape 1 mène à l'étape 2
  [nameInput, emailInput, passwordInput].forEach(inp => {
    if (inp) {
      inp.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          goToStep2();
        }
      });
    }
  });

  // 4. Soumission et traitement du paiement SANS REDIRECTION PRÉMATURÉE
  checkoutForm.addEventListener('submit', (e) => {
    e.preventDefault();

    // S'assurer que l'étape 1 est valide
    if (!validateStep1()) {
      goToStep1();
      return;
    }

    let hasError = false;

    const nameVal = nameInput ? nameInput.value.trim() : '';
    const emailVal = emailInput ? emailInput.value.trim() : '';
    const passVal = passwordInput ? passwordInput.value : '';

    if (currentPaymentMethod === 'card') {
      const cardVal = cardInput ? cardInput.value.replace(/\s/g, '') : '';
      const expVal = expInput ? expInput.value.trim() : '';
      const cvcVal = cvcInput ? cvcInput.value.trim() : '';

      if (!cardVal || cardVal.length < 15) {
        showError('cardError', cardInput);
        hasError = true;
      } else {
        hideError('cardError', cardInput);
      }

      if (!expVal || !expVal.includes('/') || expVal.length < 5) {
        showError('expError', expInput);
        hasError = true;
      } else {
        hideError('expError', expInput);
      }

      if (!cvcVal || cvcVal.length < 3) {
        showError('cvcError', cvcInput);
        hasError = true;
      } else {
        hideError('cvcError', cvcInput);
      }
    } else {
      // Validation Mobile Money
      const momoVal = saspayPhoneInput ? saspayPhoneInput.value.replace(/\s/g, '') : '';
      if (!momoVal || momoVal.length < 6) {
        showError('momoPhoneError', saspayPhoneInput);
        hasError = true;
      } else {
        hideError('momoPhoneError', saspayPhoneInput);
      }
    }

    if (hasError) {
      const firstError = checkoutForm.querySelector('.form-input.input-error');
      if (firstError) firstError.focus();
      return;
    }

    // Données relatives au pays et à l'opérateur
    const selCountry = saspayCountrySelect ? saspayCountrySelect.value : "Cameroun";
    const countryData = saspayCountries[selCountry] || saspayCountries["Cameroun"];
    const opVal = saspaySelectedOperator ? saspaySelectedOperator.value : "MTN MoMo";
    const phoneVal = saspayPhoneInput ? saspayPhoneInput.value.trim() : "";

    // Variables de contrôle des timers et du polling
    let activePollInterval = null;
    let activeCountdownInterval = null;
    let secondsRemaining = 180; // 3 minutes timeout

    function cleanupTimers() {
      if (activePollInterval) { clearInterval(activePollInterval); activePollInterval = null; }
      if (activeCountdownInterval) { clearInterval(activeCountdownInterval); activeCountdownInterval = null; }
      if (processingQrCard) processingQrCard.style.display = 'none';
    }

    function formatTime(s) {
      const m = Math.floor(s / 60);
      const sec = s % 60;
      return `${m.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
    }

    function resetCheckoutFormDisplay() {
      cleanupTimers();
      if (checkoutWaitingArea) checkoutWaitingArea.style.display = 'none';
      if (checkoutPaymentForm) checkoutPaymentForm.style.display = 'block';
      if (checkoutStep1) checkoutStep1.style.display = 'none';
      if (checkoutStep2) checkoutStep2.style.display = 'block';
      if (processingModal) {
        processingModal.classList.remove('visible');
        processingModal.setAttribute('aria-hidden', 'true');
      }
      if (submitBtn) submitBtn.disabled = false;
      selectPaymentMethod(currentPaymentMethod);
    }

    if (processingCancelBtn) processingCancelBtn.onclick = resetCheckoutFormDisplay;
    if (processingRetryBtn) processingRetryBtn.onclick = resetCheckoutFormDisplay;
    if (inpageCancelBtn) inpageCancelBtn.onclick = resetCheckoutFormDisplay;

    // AFFICHER L'ÉTAT D'ATTENTE DIRECTEMENT SUR LA PAGE (OU EN MODALE EN CAS DE FALLBACK)
    if (checkoutWaitingArea) {
      // UX In-Page : on masque le formulaire et on affiche directement la zone d'attente / QR code
      if (checkoutPaymentForm) checkoutPaymentForm.style.display = 'none';
      checkoutWaitingArea.style.display = 'block';
      if (inpageStatePending) inpageStatePending.style.display = 'block';
      if (inpageStateSuccess) inpageStateSuccess.style.display = 'none';
      if (inpageQrSection) inpageQrSection.style.display = 'none';
      if (inpageQrCanvas) inpageQrCanvas.innerHTML = '';

      if (currentPaymentMethod === 'mobile_money') {
        if (inpageWaitingTitle) inpageWaitingTitle.textContent = "⏳ En attente de confirmation sur votre téléphone...";
        if (inpageWaitingDesc) inpageWaitingDesc.textContent = `Transmission de la demande à ${opVal} (${selCountry}) sur le ${countryData.prefix} ${phoneVal}...`;
      } else {
        if (inpageWaitingTitle) inpageWaitingTitle.textContent = "⏳ En attente d'authentification bancaire (3D Secure)...";
        if (inpageWaitingDesc) inpageWaitingDesc.textContent = "Initialisation de la session bancaire sécurisée auprès de SasPay...";
      }

      if (inpageTimerText) inpageTimerText.textContent = `Temps restant pour valider : ${formatTime(secondsRemaining)}`;
      if (inpageProgressBar) inpageProgressBar.style.width = '25%';

      const cardContainer = document.querySelector('.checkout-card');
      if (cardContainer) {
        cardContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    } else if (processingModal) {
      // Fallback si checkoutWaitingArea n'est pas présent
      processingModal.classList.add('visible');
      processingModal.setAttribute('aria-hidden', 'false');
      if (processingQrCard) processingQrCard.style.display = 'none';
      if (processingTitle) processingTitle.textContent = "⏳ En attente de confirmation...";
      if (processingTimerBadge) processingTimerBadge.style.display = 'inline-flex';
      if (processingProgressBar) processingProgressBar.style.width = '30%';
    }

    if (submitBtn) submitBtn.disabled = true;

    // Gestion du compte à rebours d'expiration
    activeCountdownInterval = setInterval(() => {
      secondsRemaining--;
      const timeStr = `Temps restant pour valider : ${formatTime(Math.max(0, secondsRemaining))}`;
      if (processingTimerText) processingTimerText.textContent = timeStr;
      if (inpageTimerText) inpageTimerText.textContent = timeStr;

      if (secondsRemaining <= 0) {
        cleanupTimers();
        handlePaymentTimeout();
      }
    }, 1000);

    function handlePaymentSuccessInPage(orderNum) {
      cleanupTimers();
      // LE STATUT PAYÉ EST ACTIVÉ UNIQUEMENT LORSQUE CETTE CONFIRMATION RÉELLE EST REÇUE
      localStorage.setItem('ov_has_paid', 'true');

      if (checkoutWaitingArea) {
        if (inpageStatePending) inpageStatePending.style.display = 'none';
        if (inpageStateSuccess) inpageStateSuccess.style.display = 'block';
        if (inpageSuccessOrder) inpageSuccessOrder.textContent = orderNum;

        const isTestOverride = window.SASPAY_CONFIG && window.SASPAY_CONFIG.testOverrideActive;
        const finalAmount = isTestOverride 
          ? `${window.SASPAY_CONFIG.testOverrideAmount} ${window.SASPAY_CONFIG.testOverrideCurrency || 'XOF'}`
          : (currentPaymentMethod === 'mobile_money' ? countryData.total : '9,00 €');
        if (inpageSuccessAmount) inpageSuccessAmount.textContent = finalAmount;

        updateMemberUI();
        return;
      }

      // Fallback modale classique si checkoutWaitingArea absent
      handlePaymentSuccess(`checkout-success.php?order=${encodeURIComponent(orderNum)}`);
    }

    function handlePaymentSuccess(targetUrl) {
      cleanupTimers();
      localStorage.setItem('ov_has_paid', 'true');

      if (processingQrCard) processingQrCard.style.display = 'none';
      if (processingProgressBar) processingProgressBar.style.width = '100%';
      if (processingTitle) processingTitle.textContent = "✅ Paiement validé avec succès !";
      if (processingDesc) processingDesc.textContent = "Votre transaction a été confirmée. Votre adhésion est active.";
      if (processingTimerBadge) processingTimerBadge.style.display = 'none';
      if (processingDeviceAlert) processingDeviceAlert.style.display = 'none';
      if (processingActions) processingActions.style.display = 'none';
      if (processingSpinnerIcon) {
        processingSpinnerIcon.innerHTML = `<polyline points="20 6 9 17 4 12" stroke="#16a34a" stroke-width="3"></polyline>`;
      }

      setTimeout(() => {
        window.location.href = targetUrl;
      }, 1500);
    }

    function handlePaymentFailed(msg) {
      cleanupTimers();
      const failMsg = msg || "La transaction a été refusée ou annulée depuis votre téléphone. Votre compte n'a pas été débité.";
      
      if (inpageWaitingTitle) inpageWaitingTitle.textContent = "❌ Échec du paiement";
      if (inpageWaitingDesc) inpageWaitingDesc.textContent = failMsg;
      if (inpageCancelBtn) inpageCancelBtn.textContent = "← Recommencer avec un autre moyen";

      if (processingModal) {
        if (processingQrCard) processingQrCard.style.display = 'none';
        if (processingProgressBar) processingProgressBar.style.width = '100%';
        if (processingTitle) processingTitle.textContent = "❌ Échec du paiement";
        if (processingDesc) processingDesc.textContent = failMsg;
        if (processingTimerBadge) processingTimerBadge.style.display = 'none';
        if (processingDeviceAlert) processingDeviceAlert.style.display = 'none';
        if (processingSpinnerIcon) {
          processingSpinnerIcon.innerHTML = `<line x1="18" y1="6" x2="6" y2="18" stroke="#dc2626" stroke-width="3"></line><line x1="6" y1="6" x2="18" y2="18" stroke="#dc2626" stroke-width="3"></line>`;
        }
        if (processingActions) {
          processingActions.style.display = 'flex';
          if (processingRetryBtn) processingRetryBtn.style.display = 'inline-block';
          if (processingCancelBtn) processingCancelBtn.textContent = 'Fermer';
        }
      }
    }

    function handlePaymentTimeout() {
      cleanupTimers();
      const timeoutMsg = "Aucune validation n'a été reçue dans les délais impartis. La transaction a expiré sans aucun prélèvement.";

      if (inpageWaitingTitle) inpageWaitingTitle.textContent = "⏱️ Délai de confirmation dépassé";
      if (inpageWaitingDesc) inpageWaitingDesc.textContent = timeoutMsg;
      if (inpageCancelBtn) inpageCancelBtn.textContent = "← Recommencer";

      if (processingModal) {
        if (processingQrCard) processingQrCard.style.display = 'none';
        if (processingTitle) processingTitle.textContent = "⏱️ Délai de confirmation dépassé";
        if (processingDesc) processingDesc.textContent = timeoutMsg;
        if (processingTimerBadge) processingTimerBadge.style.display = 'none';
        if (processingDeviceAlert) processingDeviceAlert.style.display = 'none';
        if (processingSpinnerIcon) {
          processingSpinnerIcon.innerHTML = `<circle cx="12" cy="12" r="10" stroke="#f59e0b" stroke-width="2.5"></circle><polyline points="12 6 12 12 16 14" stroke="#f59e0b" stroke-width="2.5"></polyline>`;
        }
        if (processingActions) {
          processingActions.style.display = 'flex';
          if (processingRetryBtn) processingRetryBtn.style.display = 'inline-block';
          if (processingCancelBtn) processingCancelBtn.textContent = 'Fermer';
        }
      }
    }

    // Préparation des données du formulaire
    const formData = new FormData(checkoutForm);
    formData.set('paymentMethod', currentPaymentMethod);
    formData.set('checkoutName', nameVal);
    formData.set('checkoutEmail', emailVal);
    if (passVal) formData.set('checkoutPassword', passVal);

    if (currentPaymentMethod === 'mobile_money') {
      const isTestOverride = window.SASPAY_CONFIG && window.SASPAY_CONFIG.testOverrideActive;
      formData.set('momoCountry', selCountry);
      formData.set('momoOperator', opVal);
      formData.set('momoPhone', `${countryData.prefix} ${phoneVal}`);
      formData.set('momoAmount', isTestOverride ? window.SASPAY_CONFIG.testOverrideAmount : countryData.amountRaw);
      formData.set('momoCurrency', isTestOverride ? (window.SASPAY_CONFIG.testOverrideCurrency || 'XOF') : countryData.currency);
    }

    // Mémoriser le nom et l'email pour le reçu (mais PAS ov_has_paid)
    localStorage.setItem('ov_member_name', nameVal);
    localStorage.setItem('ov_member_email', emailVal);

    // Détermination de l'endpoint d'initiation réel (PHP ou Vercel Serverless Function)
    const initiateEndpoint = isPhpEnvironment() ? 'checkout.php' : '/api/initiate-payment';

    let fetchOptions;
    if (isPhpEnvironment()) {
      fetchOptions = {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      };
    } else {
      const payloadObj = {};
      formData.forEach((val, key) => { payloadObj[key] = val; });
      fetchOptions = {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payloadObj)
      };
    }

    // 1. Déclencher l'appel d'initiation réel auprès de SasPay
    fetch(initiateEndpoint, fetchOptions)
    .then(r => {
      if (!r.ok) {
        return r.json().catch(() => ({})).then(errData => {
          throw new Error(errData.error || ('Erreur HTTP ' + r.status));
        });
      }
      return r.json();
    })
    .then(initData => {
      if (initData && initData.success && initData.order_number) {
        const orderNum = initData.order_number;
        const paymentId = initData.payment_id || '';
        sessionStorage.setItem('ov_current_order', orderNum);

        // Si SasPay renvoie un checkout_url (Wave, Mobile ou Carte Bancaire 3DS) :
        if (initData.checkout_url) {
          const isWave = opVal.toLowerCase().includes('wave') || initData.checkout_url.toLowerCase().includes('wave');

          // AFFICHER LE QR CODE DIRECTEMENT SUR LA PAGE DE CHECKOUT (SANS REDIRECTION)
          if (inpageQrCanvas) {
            inpageQrCanvas.innerHTML = '';
            if (typeof QRCode !== 'undefined') {
              try {
                new QRCode(inpageQrCanvas, {
                  text: initData.checkout_url,
                  width: 220,
                  height: 220,
                  colorDark: "#0f172a",
                  colorLight: "#ffffff",
                  correctLevel: QRCode.CorrectLevel.M
                });
              } catch (qrErr) {
                console.warn("Erreur QRCode canvas:", qrErr);
                inpageQrCanvas.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=${encodeURIComponent(initData.checkout_url)}" alt="QR Code" style="width:220px;height:220px;display:block;border-radius:10px;" />`;
              }
            } else {
              inpageQrCanvas.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=${encodeURIComponent(initData.checkout_url)}" alt="QR Code" style="width:220px;height:220px;display:block;border-radius:10px;" />`;
            }
          }

          if (inpageDirectLinkBtn) {
            inpageDirectLinkBtn.href = initData.checkout_url;
          }

          if (inpageQrSection) {
            inpageQrSection.style.display = 'block';
          }

          if (isWave) {
            if (inpageWaitingTitle) inpageWaitingTitle.textContent = "📸 Scannez le QR Code Wave avec votre téléphone";
            if (inpageWaitingDesc) inpageWaitingDesc.innerHTML = "Ouvrez votre application <strong>Wave</strong> sur votre téléphone, appuyez sur <strong>Scanner</strong> et validez avec votre code PIN secret.<br>Votre statut se mettra à jour automatiquement dès confirmation.";
          } else if (currentPaymentMethod === 'card') {
            if (inpageWaitingTitle) inpageWaitingTitle.textContent = "⏳ Authentification 3D Secure";
            if (inpageWaitingDesc) inpageWaitingDesc.innerHTML = "Scannez le QR code ou confirmez sur votre application bancaire.<br>Votre statut se mettra à jour en direct dès validation.";
          }

          // Support miroir dans la modale si fallback
          if (processingQrCard) processingQrCard.style.display = 'block';
          if (processingQrImg) processingQrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' + encodeURIComponent(initData.checkout_url);
          if (processingQrDirectBtn) processingQrDirectBtn.href = initData.checkout_url;

        } else {
          // Aucun checkout_url : Push USSD direct sur le téléphone
          if (inpageQrSection) inpageQrSection.style.display = 'none';
          if (inpageWaitingTitle) inpageWaitingTitle.textContent = "⏳ Invite envoyée sur votre mobile...";
          if (inpageWaitingDesc) inpageWaitingDesc.textContent = `Consultez l'écran de votre téléphone (${phoneVal}) et saisissez votre code PIN secret pour approuver le règlement.`;
        }

        // 2. Lancer IMMÉDIATEMENT le polling toutes les 2.5 secondes du statut réel
        const pollEndpoint = isPhpEnvironment() 
          ? `api/check-payment-status.php?order=${encodeURIComponent(orderNum)}&payment_id=${encodeURIComponent(paymentId)}`
          : `/api/check-payment-status?order=${encodeURIComponent(orderNum)}&payment_id=${encodeURIComponent(paymentId)}`;

        activePollInterval = setInterval(() => {
          fetch(pollEndpoint)
            .then(res => res.json())
            .then(statusData => {
              if (statusData && statusData.status === 'paid') {
                // Confirmation réelle reçue : AFFICHAGE EN DIRECT DU SUCCÈS SANS REDIRECTION NI RECHARGEMENT
                handlePaymentSuccessInPage(orderNum);
              } else if (statusData && statusData.status === 'failed') {
                handlePaymentFailed(statusData.message || "La transaction a été refusée ou rejetée.");
              } else if (statusData && statusData.status === 'expired') {
                handlePaymentTimeout();
              } else {
                // Toujours en attente : mise à jour visuelle fluide
                const progPct = Math.min(85, 25 + (180 - secondsRemaining) * 0.35) + '%';
                if (processingProgressBar) processingProgressBar.style.width = progPct;
                if (inpageProgressBar) inpageProgressBar.style.width = progPct;
              }
            })
            .catch(pollErr => {
              console.log("Polling en attente...", pollErr);
            });
        }, 2500);

      } else {
        handlePaymentFailed(initData.error || "Impossible d'initier la demande de paiement SasPay.");
      }
    })
    .catch(err => {
      console.warn("Échec d'initiation:", err);
      handlePaymentFailed(err.message || "Erreur lors de la communication avec le serveur de paiement.");
    });
  });
}


/* ==========================================================================
   PAGE DASHBOARD MEMBRE (DASHBOARD.HTML)
   ========================================================================== */
function initDashboard() {
  const dashBody = document.querySelector('.dashboard-body');
  if (!dashBody) return;

  // 1. Données dynamiques de l'utilisateur (Katahana Désiré, Téléphone, Photo)
  const storedName = localStorage.getItem('ov_member_name');
  let memberName = storedName && storedName.trim() ? storedName.trim() : 'Katahana Désiré';
  const userNameEl = document.getElementById('dashUserName');
  const dropdownUserTitle = document.getElementById('dropdownUserTitle');
  if (userNameEl) userNameEl.textContent = memberName;
  if (dropdownUserTitle) dropdownUserTitle.textContent = memberName;

  const storedPhone = localStorage.getItem('ov_member_phone');
  let memberPhone = storedPhone && storedPhone.trim() ? storedPhone.trim() : '+33 6 84 92 10 33';

  const storedAvatar = localStorage.getItem('ov_member_avatar');
  let memberAvatar = storedAvatar || './img/avatar-maxime.jpg';
  const dashAvatarEl = document.getElementById('dashAvatar');
  if (dashAvatarEl) dashAvatarEl.src = memberAvatar;

  const storedEmail = localStorage.getItem('ov_member_email');
  const dropdownUserEmail = document.getElementById('dropdownUserEmail');
  if (storedEmail && dropdownUserEmail) dropdownUserEmail.textContent = storedEmail;

  // 1b. Intégration des lives créés par les membres dans le dashboard (Lives & Calendrier)
  const dashLivesGrid = document.getElementById('dashUpcomingLivesGrid');
  const calEventsList = document.querySelector('#tab-calendrier .calendar-events-list');

  try {
    const storedLives = localStorage.getItem('ov_community_custom_lives');
    const customLives = storedLives ? JSON.parse(storedLives) : [];

    if (dashLivesGrid && !dashLivesGrid.querySelector('.custom-dash-event-card')) {
      customLives.forEach(session => {
        const card = document.createElement('div');
        card.className = 'dash-event-card custom-dash-event-card';
        card.id = `dash-live-${session.id}`;
        card.innerHTML = `
          <div class="event-card-date" style="background:#0f172a; color:#ffffff;">
            <span class="event-day">LIVE</span>
            <span class="event-num">🤝</span>
          </div>
          <div class="event-card-content">
            <span class="event-tag" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;">${escapeHtml(session.tag)} • Session Membre</span>
            <h3 class="event-title">${escapeHtml(session.title)}</h3>
            <p class="event-desc">${escapeHtml(session.desc)}</p>
            <div class="event-footer">
              <span class="event-host">Animé par ${escapeHtml(session.author)} (${escapeHtml(session.role)})</span>
              <span class="badge" style="background:#eff6ff; color:#1d4ed8; padding:3px 8px; border-radius:999px; font-size:0.75rem; font-weight:700;">${escapeHtml(session.date)}</span>
            </div>
          </div>
        `;
        dashLivesGrid.insertBefore(card, dashLivesGrid.firstChild);
      });
    }

    if (calEventsList && !calEventsList.querySelector('.custom-cal-card')) {
      customLives.forEach(session => {
        const card = document.createElement('div');
        card.className = 'calendar-card custom-cal-card';
        card.id = `cal-card-${session.id}`;
        card.innerHTML = `
          <div class="calendar-date-badge" style="background:#047857; color:#ffffff;">
            <span class="calendar-date-month">LIVE</span>
            <span class="calendar-date-day">🤝</span>
          </div>
          <div class="calendar-card-body">
            <div class="calendar-card-top">
              <span class="badge-plan-active" style="background:#047857;">🤝 ${escapeHtml(session.tag)}</span>
              <span class="calendar-time-tag" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-weight:700;">${escapeHtml(session.date)} • ${escapeHtml(session.duration)}</span>
            </div>
            <h3 class="calendar-card-title">${escapeHtml(session.title)}</h3>
            <p class="calendar-card-desc">${escapeHtml(session.desc)}</p>
            <div class="calendar-card-actions" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-top:1rem;">
              <span style="font-size:0.85rem; color:#475569; font-weight:600;">Animé par ${escapeHtml(session.author)} (${escapeHtml(session.role)})</span>
              <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn btn-primary btn-sm btn-join-live-direct">
                  Rejoindre la salle Live
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showToast('📅 Session membre ajoutée à votre agenda !')">
                  📅 Rappel agenda
                </button>
              </div>
            </div>
          </div>
        `;
        calEventsList.insertBefore(card, calEventsList.firstChild);
      });
    }
  } catch (err) {}

  // 2. Panneau Déroulant Notifications
  const notifBellBtn = document.getElementById('notifBellBtn');
  const notifDropdownPanel = document.getElementById('notifDropdownPanel');
  const markAllReadBtn = document.getElementById('markAllReadBtn');
  const notifBadge = document.querySelector('.badge-dot-alert');

  if (notifBellBtn && notifDropdownPanel) {
    notifBellBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isVisible = notifDropdownPanel.style.display === 'block';
      closeAllDashDropdowns();
      if (!isVisible) {
        notifDropdownPanel.style.display = 'block';
        notifBellBtn.setAttribute('aria-expanded', 'true');
      }
    });
  }

  if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const unreadItems = document.querySelectorAll('.dropdown-item.unread');
      unreadItems.forEach(item => {
        item.classList.remove('unread');
        const dot = item.querySelector('.dropdown-dot');
        if (dot) dot.style.display = 'none';
      });
      const dots = document.querySelectorAll('.dropdown-dot');
      dots.forEach(d => d.style.display = 'none');
      if (notifBadge) notifBadge.style.display = 'none';
      localStorage.setItem('ov_notifs_read', 'true');
      showToast("✅ Toutes les notifications sont marquées comme lues !");
    });

    if (localStorage.getItem('ov_notifs_read') === 'true') {
      const unreadItems = document.querySelectorAll('.dropdown-item.unread');
      unreadItems.forEach(item => {
        item.classList.remove('unread');
        const dot = item.querySelector('.dropdown-dot');
        if (dot) dot.style.display = 'none';
      });
      const dots = document.querySelectorAll('.dropdown-dot');
      dots.forEach(d => d.style.display = 'none');
      if (notifBadge) notifBadge.style.display = 'none';
    }
  }

  // 3. Menu Déroulant Profil Utilisateur (au bout à droite)
  const userDropdownTrigger = document.getElementById('dashUserDropdownTrigger');
  const userDropdownMenu = document.getElementById('dashUserDropdownMenu');
  const menuItemDashboard = document.getElementById('menuItemDashboard');
  const menuItemAbonnement = document.getElementById('menuItemAbonnement');
  const menuItemParams = document.getElementById('menuItemParams');
  const dashLayout = document.querySelector('.dash-layout');

  let currentCollaborativeTab = 'tab-lives';

  function enterDashboardMode(targetTabId) {
    if (dashLayout) dashLayout.classList.remove('account-mode');

    if (menuItemDashboard) menuItemDashboard.classList.add('active-dropdown-item');
    if (menuItemAbonnement) menuItemAbonnement.classList.remove('active-dropdown-item');
    if (menuItemParams) menuItemParams.classList.remove('active-dropdown-item');

    const tabToOpen = targetTabId || currentCollaborativeTab || 'tab-lives';
    switchToTab(tabToOpen);
    showToast("📊 Retour à votre Dashboard collaboratif.");
  }

  function enterAccountMode(accountTabId) {
    if (dashLayout) dashLayout.classList.add('account-mode');

    if (menuItemDashboard) menuItemDashboard.classList.remove('active-dropdown-item');

    if (accountTabId === 'tab-compte') {
      if (menuItemAbonnement) menuItemAbonnement.classList.add('active-dropdown-item');
      if (menuItemParams) menuItemParams.classList.remove('active-dropdown-item');
      switchToTab('tab-compte');
    } else {
      if (menuItemAbonnement) menuItemAbonnement.classList.remove('active-dropdown-item');
      if (menuItemParams) menuItemParams.classList.add('active-dropdown-item');
      switchToTab('tab-parametres');
    }
  }

  if (userDropdownTrigger && userDropdownMenu) {
    userDropdownTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isVisible = userDropdownMenu.style.display === 'block';
      closeAllDashDropdowns();
      if (!isVisible) {
        userDropdownMenu.style.display = 'block';
        userDropdownTrigger.setAttribute('aria-expanded', 'true');
      }
    });
  }

  function closeAllDashDropdowns() {
    if (notifDropdownPanel) notifDropdownPanel.style.display = 'none';
    if (notifBellBtn) notifBellBtn.setAttribute('aria-expanded', 'false');
    if (userDropdownMenu) userDropdownMenu.style.display = 'none';
    if (userDropdownTrigger) userDropdownTrigger.setAttribute('aria-expanded', 'false');
  }

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.dash-notif-wrapper') && !e.target.closest('.dash-user-dropdown-wrapper')) {
      closeAllDashDropdowns();
    }
  });

  // Action menu déroulant : Mon Dashboard
  if (menuItemDashboard) {
    menuItemDashboard.addEventListener('click', () => {
      closeAllDashDropdowns();
      enterDashboardMode();
    });
  }

  // Redirection menu déroulant : Mon Abonnement (vue isolée en pleine largeur)
  if (menuItemAbonnement) {
    menuItemAbonnement.addEventListener('click', () => {
      closeAllDashDropdowns();
      enterAccountMode('tab-compte');
    });
  }

  // Action menu déroulant : Paramètres du compte (vue isolée en pleine largeur)
  if (menuItemParams) {
    menuItemParams.addEventListener('click', () => {
      closeAllDashDropdowns();
      enterAccountMode('tab-parametres');
    });
  }

  // Boutons de retour vers le Dashboard collaboratif depuis les pages de compte
  const btnBackButtons = document.querySelectorAll('.btnBackToDashboardAction, .btn-back-to-dashboard');
  btnBackButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      enterDashboardMode();
    });
  });

  // 4. Gestion des onglets de navigation du Dashboard
  const navTabs = document.querySelectorAll('.dash-nav-item[data-dash-tab]');
  const tabContents = document.querySelectorAll('.dash-tab-content');

  function switchToTab(targetId) {
    const isCollaborative = ['tab-lives', 'tab-calendrier', 'tab-salons', 'tab-ressources', 'tab-reseau'].includes(targetId);
    if (isCollaborative) {
      currentCollaborativeTab = targetId;
      if (dashLayout && dashLayout.classList.contains('account-mode')) {
        dashLayout.classList.remove('account-mode');
        if (accountNavBar) accountNavBar.style.display = 'none';
      }
    }

    navTabs.forEach(btn => {
      btn.classList.toggle('active', btn.getAttribute('data-dash-tab') === targetId);
    });

    const allTabs = document.querySelectorAll('.dash-tab-content');
    allTabs.forEach(content => {
      content.style.display = '';
      content.classList.toggle('active', content.id === targetId);
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  navTabs.forEach(tabBtn => {
    tabBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = tabBtn.getAttribute('data-dash-tab');
      if (targetId) switchToTab(targetId);
    });
  });

  // Liens d'accès direct et notifications vers les onglets (ex: Nouveau live en direct)
  document.addEventListener('click', (e) => {
    const targetTabEl = e.target.closest('[data-target-tab]');
    if (targetTabEl) {
      e.preventDefault();
      const targetTabId = targetTabEl.getAttribute('data-target-tab');
      if (targetTabId) {
        closeAllDashDropdowns();
        switchToTab(targetTabId);
        if (targetTabId === 'tab-lives') {
          showToast("🔴 Redirection directe vers la session Mastermind en cours !");
          const playerEl = document.getElementById('liveScreenBox');
          if (playerEl) playerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
      return;
    }

    if (e.target.closest('.btn-join-live-direct')) {
      e.preventDefault();
      switchToTab('tab-lives');
      showToast("🔴 Connexion à la salle Live Mastermind en cours...");
      const playerEl = document.getElementById('liveScreenBox');
      if (playerEl) playerEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });

  // 4b. Prise en charge des paramètres d'URL (onglet & highlight d'un nouveau live créé)
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const targetTab = urlParams.get('tab');
    const newLiveId = urlParams.get('new_live');
    if (targetTab && document.getElementById(targetTab)) {
      switchToTab(targetTab);
    }
    if (newLiveId) {
      setTimeout(() => {
        const targetCard = document.getElementById(`cal-card-${newLiveId}`) || document.getElementById(`dash-live-${newLiveId}`);
        if (targetCard) {
          targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
          targetCard.classList.add('highlight-new-live');
          showToast("✨ Votre live a été publié avec succès dans le calendrier !");
        }
      }, 400);
    }
  } catch (err) {}

  // 5. Animation du Live Player, Caméra & Prise de Parole
  const livePlayBtn = document.getElementById('livePlayBtn');
  const liveScreenBox = document.getElementById('liveScreenBox');
  const liveStatusBadge = document.getElementById('liveStatusBadge');
  const cyrilSoundWave = document.getElementById('cyrilSoundWave');
  let isLivePlaying = false;

  const toggleLivePlay = () => {
    isLivePlaying = !isLivePlaying;
    if (isLivePlaying) {
      if (livePlayBtn) livePlayBtn.style.display = 'none';
      if (liveStatusBadge) liveStatusBadge.textContent = 'DIRECT HD • EN COURS';
      if (cyrilSoundWave) cyrilSoundWave.style.display = 'flex';
      showToast("🟢 Flux Live Mastermind connecté en direct ! Audio et vidéo de Cyril D. synchronisés.");
    } else {
      if (livePlayBtn) livePlayBtn.style.display = 'flex';
      if (liveStatusBadge) liveStatusBadge.textContent = 'PAUSE';
      if (cyrilSoundWave) cyrilSoundWave.style.display = 'none';
      showToast("Flux mis en pause.");
    }
  };

  if (livePlayBtn) livePlayBtn.addEventListener('click', toggleLivePlay);

  // Fluctuations réalistes du nombre de spectateurs en direct
  const liveChatCount = document.getElementById('liveChatCount');
  const topbarLiveBadge = document.getElementById('topbarLiveBadge');
  const participantsTotalCount = document.getElementById('participantsTotalCount');
  const modalLivePillCount = document.getElementById('modalLivePillCount');
  const modalOtherCountLabel = document.getElementById('modalOtherCountLabel');
  let viewerCount = 142;

  setInterval(() => {
    const delta = Math.floor(Math.random() * 5) - 2; // -2 à +2
    viewerCount = Math.max(135, Math.min(160, viewerCount + delta));
    if (liveChatCount) liveChatCount.textContent = `● ${viewerCount} actifs`;
    if (topbarLiveBadge) {
      const sub = topbarLiveBadge.querySelector('.live-text-sub');
      if (sub) sub.textContent = `• Mastermind Q&A (${viewerCount} connectés)`;
    }
    if (participantsTotalCount) {
      participantsTotalCount.textContent = `${viewerCount} connectés`;
    }
    if (modalLivePillCount) {
      modalLivePillCount.textContent = `● ${viewerCount} connectés en direct`;
    }
    if (modalOtherCountLabel) {
      modalOtherCountLabel.textContent = `Membres connectés (${viewerCount - 2})`;
    }
  }, 4500);

  // Synchronisation avatar utilisateur dans les participants et la caméra
  const participantSelfAvatar = document.getElementById('participantSelfAvatar');
  if (participantSelfAvatar && memberAvatar) participantSelfAvatar.src = memberAvatar;
  const modalSelfAvatar = document.getElementById('modalSelfAvatar');
  if (modalSelfAvatar && memberAvatar) modalSelfAvatar.src = memberAvatar;
  const modalSelfName = document.getElementById('modalSelfName');
  if (modalSelfName && memberName) modalSelfName.innerHTML = `${escapeHtml(memberName)} <span class="badge-role-admin">👑 Admin</span>`;

  const userCamAvatarImg = document.getElementById('userCamAvatarImg');
  if (userCamAvatarImg && memberAvatar) userCamAvatarImg.src = memberAvatar;
  const userCamLabel = document.getElementById('userCamLabel');
  if (userCamLabel && memberName) userCamLabel.textContent = `${memberName} (Vous)`;

  // --- Gestion de la Caméra en Direct (getUserMedia avec fallback virtuel) ---
  const camToggleBtn = document.getElementById('camToggleBtn');
  const liveUserCamBox = document.getElementById('liveUserCamBox');
  const userCamVideo = document.getElementById('userCamVideo');
  const userCamFallback = document.getElementById('userCamFallback');
  const userCamCloseBtn = document.getElementById('userCamCloseBtn');
  let userCameraStream = null;
  let isCameraActive = false;

  function stopUserCamera() {
    if (userCameraStream) {
      userCameraStream.getTracks().forEach(track => track.stop());
      userCameraStream = null;
    }
    if (userCamVideo) {
      userCamVideo.srcObject = null;
      userCamVideo.style.display = 'none';
    }
    if (userCamFallback) userCamFallback.style.display = 'none';
    if (liveUserCamBox) liveUserCamBox.style.display = 'none';
    isCameraActive = false;
    if (camToggleBtn) {
      camToggleBtn.classList.remove('btn-ctrl-active');
      camToggleBtn.innerHTML = '📹 Activer ma caméra';
    }
  }

  function startUserCamera() {
    if (liveUserCamBox) liveUserCamBox.style.display = 'block';
    if (camToggleBtn) {
      camToggleBtn.classList.add('btn-ctrl-active');
      camToggleBtn.innerHTML = '📹 Couper la caméra';
    }

    if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
      navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 640 }, height: { ideal: 480 } }, audio: false })
        .then(stream => {
          userCameraStream = stream;
          if (userCamVideo) {
            userCamVideo.srcObject = stream;
            userCamVideo.style.display = 'block';
          }
          if (userCamFallback) userCamFallback.style.display = 'none';
          isCameraActive = true;
          showToast("🟢 Caméra active ! Vous apparaissez en direct dans le Mastermind.");
        })
        .catch(err => {
          console.warn("Accès caméra indisponible ou refusé, bascule en mode flux virtuel :", err);
          if (userCamVideo) userCamVideo.style.display = 'none';
          if (userCamFallback) userCamFallback.style.display = 'flex';
          isCameraActive = true;
          showToast("📹 Flux vidéo activé (Mode caméra virtuelle haute résolution).");
        });
    } else {
      if (userCamVideo) userCamVideo.style.display = 'none';
      if (userCamFallback) userCamFallback.style.display = 'flex';
      isCameraActive = true;
      showToast("📹 Caméra virtuelle active.");
    }
  }

  if (camToggleBtn) {
    camToggleBtn.addEventListener('click', () => {
      if (isCameraActive) {
        stopUserCamera();
        showToast("Caméra désactivée.");
      } else {
        startUserCamera();
      }
    });
  }

  if (userCamCloseBtn) {
    userCamCloseBtn.addEventListener('click', () => {
      stopUserCamera();
      showToast("Caméra fermée.");
    });
  }

  // --- Prise de parole en direct, Main Levée & Microphone ---
  const handRaiseBtn = document.getElementById('handRaiseBtn');
  const askQuestionBtn = document.getElementById('askQuestionBtn');
  const micToggleBtn = document.getElementById('micToggleBtn');
  const liveSpeakingBanner = document.getElementById('liveSpeakingBanner');
  const liveHandRaisedOverlayPill = document.getElementById('liveHandRaisedOverlayPill');
  const btnMuteActiveSpeech = document.getElementById('btnMuteActiveSpeech');
  const speakingAuthorName = document.getElementById('speakingAuthorName');
  const participantSelfStatusDot = document.getElementById('participantSelfStatusDot');
  const participantSelfAudioIcon = document.getElementById('participantSelfAudioIcon');
  const modalSelfStatusBadge = document.getElementById('modalSelfStatusBadge');
  const chatMessages = document.getElementById('liveChatMessages');

  let isSpeakingLive = false;
  let isHandRaised = false;

  function grantSpeechFloor() {
    isSpeakingLive = true;
    if (speakingAuthorName) speakingAuthorName.textContent = memberName;
    if (liveSpeakingBanner) liveSpeakingBanner.style.display = 'flex';
    if (liveHandRaisedOverlayPill) liveHandRaisedOverlayPill.style.display = 'none';

    if (micToggleBtn) {
      micToggleBtn.classList.add('ctrl-live-speaking');
      micToggleBtn.innerHTML = '🎙️ Micro Ouvert (Vous parlez)';
    }
    if (askQuestionBtn) {
      askQuestionBtn.classList.remove('btn-requesting-mic');
      askQuestionBtn.classList.add('btn-ctrl-active');
      askQuestionBtn.innerHTML = '🎙️ En direct avec Cyril';
    }
    if (handRaiseBtn) {
      handRaiseBtn.classList.remove('btn-hand-raised', 'btn-ctrl-active');
      handRaiseBtn.innerHTML = '✋ Lever la main';
      isHandRaised = false;
    }

    if (participantSelfStatusDot) participantSelfStatusDot.classList.add('is-speaking');
    if (participantSelfAudioIcon) participantSelfAudioIcon.textContent = '🎙️';
    if (modalSelfStatusBadge) {
      modalSelfStatusBadge.className = 'badge-speaking-tag';
      modalSelfStatusBadge.innerHTML = '🎙️ Au micro';
    }

    showToast(`🎉 Cyril D. vous a donné la parole en direct ! On vous écoute ${memberName}.`);

    // Poster automatiquement la prise de parole de l'utilisateur dans le Live Chat
    if (chatMessages) {
      const now = new Date();
      const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
      
      setTimeout(() => {
        const spokenMsg = document.createElement('div');
        spokenMsg.className = 'chat-msg msg-self msg-admin';
        spokenMsg.innerHTML = `
          <img src="${escapeHtml(memberAvatar)}" alt="${escapeHtml(memberName)}" class="msg-avatar">
          <div class="msg-body">
            <div class="msg-author">
              <span class="badge-chat-admin">👑 Administrateur • En direct</span>
              ${escapeHtml(memberName)} 
              <span class="msg-time">${timeStr}</span>
            </div>
            <div class="msg-text"><em>🎙️ [En direct au micro]</em> Bonjour Cyril ! Sur la phase de découverte client, comment désamorcer l'objection du budget avant même la fin de l'appel ?</div>
          </div>
        `;
        chatMessages.appendChild(spokenMsg);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Réponse vocale / chat de l'animateur Cyril D.
        setTimeout(() => {
          const cyrilReply = document.createElement('div');
          cyrilReply.className = 'chat-msg msg-highlight';
          cyrilReply.innerHTML = `
            <img src="./img/avatar-cyril.jpg" alt="Cyril D." class="msg-avatar">
            <div class="msg-body">
              <div class="msg-author">Cyril D. (Host) <span class="msg-time">${timeStr}</span></div>
              <div class="msg-text">@${escapeHtml(memberName)} Excellente question Katahana ! Il faut ancrer le coût de l'inaction dès l'Étape 2 de la fiche pratique avant même de citer un montant. Regarde la question n°2 dans la fiche de l'atelier !</div>
            </div>
          `;
          chatMessages.appendChild(cyrilReply);
          chatMessages.scrollTop = chatMessages.scrollHeight;
          showToast("🎙️ Cyril D. vous répond au micro et partage son écran !");
        }, 2600);
      }, 800);
    }
  }

  function releaseSpeechFloor() {
    isSpeakingLive = false;
    if (liveSpeakingBanner) liveSpeakingBanner.style.display = 'none';
    if (liveHandRaisedOverlayPill) liveHandRaisedOverlayPill.style.display = 'none';

    if (micToggleBtn) {
      micToggleBtn.classList.remove('ctrl-live-speaking');
      micToggleBtn.innerHTML = '🎙️ Micro : Coupé';
    }
    if (askQuestionBtn) {
      askQuestionBtn.classList.remove('btn-ctrl-active', 'btn-requesting-mic');
      askQuestionBtn.innerHTML = '🎤 Demander le micro';
    }
    if (handRaiseBtn) {
      handRaiseBtn.classList.remove('btn-hand-raised', 'btn-ctrl-active');
      handRaiseBtn.innerHTML = '✋ Lever la main';
      isHandRaised = false;
    }
    if (participantSelfStatusDot) participantSelfStatusDot.classList.remove('is-speaking');
    if (participantSelfAudioIcon) participantSelfAudioIcon.textContent = '🔇';
    if (modalSelfStatusBadge) {
      modalSelfStatusBadge.className = 'badge-listen-tag';
      modalSelfStatusBadge.innerHTML = '🎧 Écoute';
    }

    showToast("Micro rendu. Vous êtes de nouveau en écoute passive.");
  }

  if (askQuestionBtn) {
    askQuestionBtn.addEventListener('click', () => {
      if (isSpeakingLive) {
        releaseSpeechFloor();
      } else {
        askQuestionBtn.classList.add('btn-requesting-mic');
        askQuestionBtn.innerHTML = '⏳ Demande micro envoyée...';
        showToast("📡 Demande de prise de parole envoyée à Cyril D. (Host)...");
        setTimeout(() => {
          grantSpeechFloor();
        }, 1500);
      }
    });
  }

  if (micToggleBtn) {
    micToggleBtn.addEventListener('click', () => {
      if (isSpeakingLive) {
        releaseSpeechFloor();
      } else {
        grantSpeechFloor();
      }
    });
  }

  if (btnMuteActiveSpeech) {
    btnMuteActiveSpeech.addEventListener('click', () => {
      releaseSpeechFloor();
    });
  }

  if (handRaiseBtn) {
    handRaiseBtn.addEventListener('click', () => {
      if (isSpeakingLive) {
        releaseSpeechFloor();
        return;
      }
      isHandRaised = !isHandRaised;
      if (isHandRaised) {
        handRaiseBtn.classList.add('btn-hand-raised');
        handRaiseBtn.innerHTML = '✋ Main levée (Rang #1 en attente)';
        if (liveHandRaisedOverlayPill) liveHandRaisedOverlayPill.style.display = 'inline-flex';
        if (modalSelfStatusBadge) {
          modalSelfStatusBadge.className = 'badge-hand-tag';
          modalSelfStatusBadge.innerHTML = '✋ Main levée';
        }
        showToast("✋ Vous avez levé la main ! Cyril D. a vu votre signal et va vous passer la parole.");
        setTimeout(() => {
          if (isHandRaised) grantSpeechFloor();
        }, 1800);
      } else {
        handRaiseBtn.classList.remove('btn-hand-raised');
        handRaiseBtn.innerHTML = '✋ Lever la main';
        if (liveHandRaisedOverlayPill) liveHandRaisedOverlayPill.style.display = 'none';
        if (modalSelfStatusBadge) {
          modalSelfStatusBadge.className = 'badge-listen-tag';
          modalSelfStatusBadge.innerHTML = '🎧 Écoute';
        }
        showToast("Main baissée.");
      }
    });
  }

  // --- Gestion du Pop-up Modal des Participants au Mastermind ---
  const btnOpenParticipantsList = document.getElementById('btnOpenParticipantsList');
  const liveParticipantsModal = document.getElementById('liveParticipantsModal');
  const closeLiveParticipantsModalBtn = document.getElementById('closeLiveParticipantsModalBtn');
  const closeLiveParticipantsFooterBtn = document.getElementById('closeLiveParticipantsFooterBtn');
  const backdropLiveParticipants = document.getElementById('backdropLiveParticipants');
  const searchParticipantInput = document.getElementById('searchParticipantInput');
  const modalOtherParticipantsList = document.getElementById('modalOtherParticipantsList');

  const PARTICIPANTS_DIRECTORY = [
    { name: "Sarah B.", role: "Copywriter & Stratège Freelance", avatar: "./img/avatar-sarah.jpg" },
    { name: "Florian L.", role: "Fondateur E-commerce & Scaling", avatar: "./img/avatar-florian.jpg" },
    { name: "Maxime T.", role: "Consultant IA & Automatisation", avatar: "./img/avatar-maxime.jpg" },
    { name: "Aurore M.", role: "Coach Sport & Bien-être Premium", avatar: "./img/avatar-aurore.jpg" },
    { name: "Marc V.", role: "Closer & Négociateur Haute Valeur", avatar: "./img/avatar-marc.jpg" },
    { name: "Élodie P.", role: "Formatrice & Créatrice de Contenu", avatar: "./img/avatar-elodie.jpg" },
    { name: "Julien B.", role: "SaaS Builder & Dev Fullstack", avatar: "./img/avatar-julien.jpg" },
    { name: "Clara D.", role: "Growth Marketer & Acquisition", avatar: "./img/avatar-clara.jpg" },
    { name: "Thomas R.", role: "Consultant Finance & Trésorerie", avatar: "./img/avatar-thomas.jpg" },
    { name: "Karim T.", role: "Community Builder & Podcasteur", avatar: "./img/avatar-karim.jpg" },
    { name: "Sophie M.", role: "Architecte d'Offres & Funnels B2B", avatar: "./img/avatar-sophie.jpg" },
    { name: "Alexandre L.", role: "Expert No-Code & Webflow", avatar: "./img/avatar-alexandre.jpg" }
  ];

  function renderModalParticipants(filter = "") {
    if (!modalOtherParticipantsList) return;
    const cleanFilter = filter.trim().toLowerCase();
    const filtered = PARTICIPANTS_DIRECTORY.filter(p => 
      p.name.toLowerCase().includes(cleanFilter) || p.role.toLowerCase().includes(cleanFilter)
    );

    if (filtered.length === 0) {
      modalOtherParticipantsList.innerHTML = `
        <div style="padding: 1.5rem; text-align: center; color: #64748b; font-size: 0.85rem;">
          Aucun participant ne correspond à « ${escapeHtml(filter)} ».
        </div>
      `;
      return;
    }

    modalOtherParticipantsList.innerHTML = filtered.map(p => `
      <div class="participant-row-card">
        <img src="${p.avatar}" alt="${p.name}" class="row-avatar" onerror="this.src='./img/avatar-maxime.jpg'">
        <div class="row-info">
          <div class="row-name">${p.name}</div>
          <div class="row-desc">${p.role}</div>
        </div>
        <div class="row-status">
          <span class="badge-listen-tag">🎧 Écoute</span>
        </div>
      </div>
    `).join('');
  }

  function openParticipantsModal() {
    if (liveParticipantsModal) {
      if (searchParticipantInput) searchParticipantInput.value = '';
      renderModalParticipants('');
      liveParticipantsModal.style.display = 'flex';
    }
  }

  function closeParticipantsModal() {
    if (liveParticipantsModal) {
      liveParticipantsModal.style.display = 'none';
    }
  }

  if (btnOpenParticipantsList) btnOpenParticipantsList.addEventListener('click', openParticipantsModal);
  if (closeLiveParticipantsModalBtn) closeLiveParticipantsModalBtn.addEventListener('click', closeParticipantsModal);
  if (closeLiveParticipantsFooterBtn) closeLiveParticipantsFooterBtn.addEventListener('click', closeParticipantsModal);
  if (backdropLiveParticipants) backdropLiveParticipants.addEventListener('click', closeParticipantsModal);

  if (searchParticipantInput) {
    searchParticipantInput.addEventListener('input', (e) => {
      renderModalParticipants(e.target.value);
    });
  }

  // --- Fiche de l'Atelier (Consultation en ligne & Téléchargement PDF réel) ---
  const btnViewNotesModal = document.getElementById('btnViewNotesModal');
  const btnDownloadNotes = document.getElementById('btnDownloadNotes');
  const atelierWorksheetModal = document.getElementById('atelierWorksheetModal');
  const closeAtelierWorksheetModalBtn = document.getElementById('closeAtelierWorksheetModalBtn');
  const closeAtelierWorksheetModalFooterBtn = document.getElementById('closeAtelierWorksheetModalFooterBtn');
  const backdropAtelierWorksheet = document.getElementById('backdropAtelierWorksheet');
  const btnPrintWorksheetBtn = document.getElementById('btnPrintWorksheetBtn');
  const btnDownloadWorksheetFromModal = document.getElementById('btnDownloadWorksheetFromModal');

  function openAtelierWorksheet() {
    if (atelierWorksheetModal) {
      atelierWorksheetModal.style.display = 'flex';
      showToast("📋 Fiche pratique de l'atelier ouverte.");
    }
  }

  function closeAtelierWorksheet() {
    if (atelierWorksheetModal) atelierWorksheetModal.style.display = 'none';
  }

  if (btnViewNotesModal) btnViewNotesModal.addEventListener('click', openAtelierWorksheet);
  if (closeAtelierWorksheetModalBtn) closeAtelierWorksheetModalBtn.addEventListener('click', closeAtelierWorksheet);
  if (closeAtelierWorksheetModalFooterBtn) closeAtelierWorksheetModalFooterBtn.addEventListener('click', closeAtelierWorksheet);
  if (backdropAtelierWorksheet) backdropAtelierWorksheet.addEventListener('click', closeAtelierWorksheet);

  function generateAndDownloadWorkshopDoc() {
    const docContent = `ONE VISION COMMUNITY - FICHE PRATIQUE ATELIER MASTERMIND #14
================================================================================
SESSION : Lever ses blocages et closer ses premières offres High-Ticket
ANIMATEUR : Cyril D. (Coach Business & Négociation)
PARTICIPANT : ${memberName} (Statut : Membre & Administrateur)
DATE : Mercredi 19h00 • One Vision Mastermind Series
================================================================================

FRAMEWORK EN 6 ÉTAPES : DE LA DÉCOUVERTE AU CLOSING FERME

ÉTAPE 1 : DIAGNOSTIC & QUESTIONNEMENT STRATÉGIQUE (80% d'écoute)
--------------------------------------------------------------------------------
• Question Clé 1 : « Quelle est votre priorité n°1 pour votre entreprise sur les 90 prochains jours ? »
• Question Clé 2 : « Qu'avez-vous déjà testé pour résoudre ce problème, et qu'est-ce qui a bloqué ? »
• Règle : Reformulez systématiquement avec les mots précis de votre prospect.

ÉTAPE 2 : ANCRAGE DE LA DOULEUR & COÛT DE L'INACTION
--------------------------------------------------------------------------------
• Question Clé 1 : « Si rien ne change d'ici 6 mois, quel est l'impact financier direct sur votre chiffre d'affaires ? »
• Question Clé 2 : « Combien d'heures et d'énergie perdez-vous chaque semaine sur ce problème ? »
• Objectif : Rendre le coût de l'inaction 5 à 10 fois supérieur au prix de votre offre.

ÉTAPE 3 : PRÉSENTATION SUR-MESURE DE L'OFFRE (3 PILIERS)
--------------------------------------------------------------------------------
• Ne présentez pas une liste de fonctionnalités, mais une solution directe à ses 3 problèmes majeurs.
• Validez chaque jalon : « Est-ce que cette approche correspond exactement à votre attente ? »

ÉTAPE 4 : ANNONCE DU TARIF & RÈGLE DU SILENCE
--------------------------------------------------------------------------------
• Formule : « L'investissement pour ce dispositif complet est de 2 900€ HT. »
• RÈGLE D'OR : Silence absolu après le chiffre. Le premier qui parle cède du terrain.

ÉTAPE 5 : DÉSAMORÇAGE DES 4 OBJECTIONS MAJEURES
--------------------------------------------------------------------------------
1. « C'est trop cher » :
   -> « Par rapport à quoi ? Ou est-ce un problème de trésorerie disponible immédiatement ? »
2. « Je dois réfléchir » :
   -> « Bien sûr. En général, c'est soit que le plan ne vous semble pas réaliste, soit que vous doutez du ROI. Lequel est-ce ? »
3. « Je dois en parler à mon associé/conjoint » :
   -> « Quelle sera sa principale question à votre avis ? Préparons la réponse ensemble maintenant. »
4. « Je n'ai pas le temps en ce moment » :
   -> « Justement, notre méthode est conçue pour vous faire gagner 8h/semaine dès le premier mois. »

ÉTAPE 6 : ACCORD D'ENGAGEMENT ET LANCEMENT IMMÉDIAT
--------------------------------------------------------------------------------
• Verrouillez la date du point de lancement directement au téléphone.
• Envoyez le récapitulatif avec lien d'acompte sous 2 heures maximum.

CHECKLIST D'ACTION DE LA SEMAINE :
[X] 1. Rédiger mes 3 questions de qualification adaptées à mon secteur
[X] 2. Formaliser mon offre avec engagement de 90 jours
[ ] 3. Réaliser 5 appels de diagnostic avec mon réseau
[ ] 4. Partager mes retours et résultats dans le salon #ventes-closing de One Vision

Document généré pour ${memberName} • One Vision Community © Tous droits réservés.`;

    const blob = new Blob([docContent], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Fiche_Atelier_Mastermind_Closing_HighTicket.txt';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    showToast("📥 Téléchargement de la fiche d'atelier terminé avec succès !");
  }

  if (btnDownloadNotes) btnDownloadNotes.addEventListener('click', generateAndDownloadWorkshopDoc);
  if (btnDownloadWorksheetFromModal) btnDownloadWorksheetFromModal.addEventListener('click', generateAndDownloadWorkshopDoc);

  if (btnPrintWorksheetBtn) {
    btnPrintWorksheetBtn.addEventListener('click', () => {
      const printContent = document.getElementById('worksheetModalBody');
      if (!printContent) return;
      const printWindow = window.open('', '_blank', 'width=800,height=900');
      printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="fr">
        <head>
          <meta charset="UTF-8">
          <title>Fiche Atelier Mastermind #14 - One Vision</title>
          <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 2rem; color: #0f172a; line-height: 1.5; }
            h4, h5 { color: #0f172a; }
            .worksheet-header-banner { background: #0f172a; color: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
            .worksheet-tag { color: #38bdf8; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; }
            .worksheet-step-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
            .step-num-badge { display: inline-block; background: #0284c7; color: white; border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 24px; font-weight: bold; }
            .worksheet-checklist-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; }
            @media print { body { padding: 0; } }
          </style>
        </head>
        <body>
          ${printContent.innerHTML}
        </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.focus();
      setTimeout(() => {
        printWindow.print();
        printWindow.close();
      }, 300);
    });
  }

  // 6. Chat en Direct avec Badge "Administrateur"
  const chatForm = document.getElementById('liveChatForm');
  const chatInput = document.getElementById('liveChatInput');

  if (chatForm && chatInput && chatMessages) {
    chatForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = chatInput.value.trim();
      if (!text) return;

      const now = new Date();
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const timeStr = `${hours}:${minutes}`;

      const msgDiv = document.createElement('div');
      msgDiv.className = 'chat-msg msg-self msg-admin';
      msgDiv.innerHTML = `
        <img src="${escapeHtml(memberAvatar)}" alt="${escapeHtml(memberName)}" class="msg-avatar">
        <div class="msg-body">
          <div class="msg-author">
            <span class="badge-chat-admin">👑 Administrateur</span>
            ${escapeHtml(memberName)} 
            <span class="msg-time">${timeStr}</span>
          </div>
          <div class="msg-text">${escapeHtml(text)}</div>
        </div>
      `;

      chatMessages.appendChild(msgDiv);
      chatMessages.scrollTop = chatMessages.scrollHeight;
      chatInput.value = '';

      // Simulation d'une réaction immédiate des participants
      setTimeout(() => {
        const replyDiv = document.createElement('div');
        replyDiv.className = 'chat-msg';
        const firstName = memberName.split(' ')[0];
        replyDiv.innerHTML = `
          <img src="./img/avatar-sarah.jpg" alt="Sarah B." class="msg-avatar">
          <div class="msg-body">
            <div class="msg-author">Sarah B. <span class="msg-time">${timeStr}</span></div>
            <div class="msg-text">@${escapeHtml(firstName)} Merci pour cette précision ! C'est exactement le retour d'expérience que j'attendais.</div>
          </div>
        `;
        chatMessages.appendChild(replyDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
      }, 2000);
    });
  }

  // 7. Salons d'Échange : Fil Interactif, Réponses Imbriquées, Compositeur & Création de Salons
  const channelsListEl = document.getElementById('salonsChannelsList');
  const salonMainTitle = document.getElementById('salonMainTitle');
  const salonFeedContainer = document.getElementById('salonFeedContainer');

  let activeChannelKey = 'general';

  const channelPostsData = {
    general: [
      {
        id: "gen-1",
        name: "Aurore M.",
        role: "Studio Créatif",
        tag: "# général",
        avatar: "./img/avatar-aurore.jpg",
        time: "Il y a 35 minutes",
        text: "Bienvenue à tous les nouveaux membres arrivés cette semaine ! N'hésitez pas à vous présenter et à partager ce sur quoi vous travaillez en ce moment. 🙌",
        likes: 18,
        userLiked: false,
        replies: [
          {
            name: "Cyril D.",
            role: "Host / Mentor",
            avatar: "./img/avatar-cyril.jpg",
            time: "Il y a 22 minutes",
            text: "Merci pour l'accueil Aurore ! On a hâte de découvrir les projets des nouveaux arrivants lors du live de ce jeudi soir."
          },
          {
            name: "Florian L.",
            role: "Consultant B2B",
            avatar: "./img/avatar-florian.jpg",
            time: "Il y a 14 minutes",
            text: "Hello tout le monde ! Très heureux de rejoindre la dynamique One Vision. Mon objectif ce mois-ci : doubler mes prises de contact."
          }
        ]
      },
      {
        id: "gen-2",
        name: "Florian L.",
        role: "Consultant B2B",
        tag: "# général",
        avatar: "./img/avatar-florian.jpg",
        time: "Il y a 3 heures",
        text: "La régularité dans les lives du jeudi commence à payer : on a noué 2 partenariats stratégiques solides en un mois au sein du réseau.",
        likes: 12,
        userLiked: false,
        replies: [
          {
            name: "Sarah B.",
            role: "Copywriter & Formatrice",
            avatar: "./img/avatar-sarah.jpg",
            time: "Il y a 2 heures",
            text: "C'est exactement la force de cette communauté : l'effet réseau démultiplie tout !"
          }
        ]
      }
    ],
    entraide: [
      {
        id: "ent-1",
        name: "Maxime T.",
        role: "Développeur Freelance",
        tag: "# entraide-et-solutions",
        avatar: "./img/avatar-maxime.jpg",
        time: "Il y a 1 heure",
        text: "Besoin d'un retour d'expérience : qui utilise Stripe Billing vs Chargebee pour gérer ses abonnements à 9€ sans friction ?",
        likes: 14,
        userLiked: false,
        replies: [
          {
            name: "Alexandre L.",
            role: "Expert No-Code",
            avatar: "./img/avatar-marc.jpg",
            time: "Il y a 45 minutes",
            text: "Stripe Billing est de loin le plus fluide à ce tarif. Moins de frais fixes, intégration native avec Stripe Checkout et portail client intégré sans coder."
          },
          {
            name: "Maxime T.",
            role: "Développeur Freelance",
            avatar: "./img/avatar-maxime.jpg",
            time: "Il y a 30 minutes",
            text: "Super merci Alexandre, je valide donc Stripe Customer Portal direct !"
          }
        ]
      }
    ],
    feedback: [
      {
        id: "feed-1",
        name: "Aurore M.",
        role: "Studio Créatif",
        tag: "# feedback-projets",
        avatar: "./img/avatar-aurore.jpg",
        time: "Il y a 40 minutes",
        text: "Hello tout le monde ! J'ai refait la page de présentation de mes prestations de refonte de marque. Si certains ont 2 minutes pour me donner un avis sans filtre sur la clarté de l'offre, je prends tous vos retours ! 🙏",
        likes: 18,
        userLiked: false,
        replies: [
          {
            name: "Sarah B.",
            role: "Copywriter",
            avatar: "./img/avatar-sarah.jpg",
            time: "Il y a 25 minutes",
            text: "Ton accroche est percutante ! Je te suggère juste d'ajouter les 3 livrables concrets dès le premier écran pour rassurer le prospect."
          },
          {
            name: "Thomas R.",
            role: "Mentor",
            avatar: "./img/avatar-cyril.jpg",
            time: "Il y a 15 minutes",
            text: "Très propre visuellement. On pourra même en faire une revue rapide en direct lors de la session de co-working de vendredi si tu veux !"
          }
        ]
      }
    ],
    partenariats: [
      {
        id: "part-1",
        name: "Sarah B.",
        role: "Copywriter & Formatrice",
        tag: "# partenariats-offres",
        avatar: "./img/avatar-sarah.jpg",
        time: "Il y a 2 heures",
        text: "Je recherche un binôme expert en automatisation No-Code (Make/Zapier) pour co-créer une offre groupée destinée aux coachs. Me contacter en privé !",
        likes: 21,
        userLiked: false,
        replies: [
          {
            name: "Alexandre L.",
            role: "Expert No-Code",
            avatar: "./img/avatar-marc.jpg",
            time: "Il y a 1 heure",
            text: "Je t'ai envoyé un message privé Sarah, le projet correspond exactement à ma stack actuelle !"
          }
        ]
      }
    ],
    coworking: [
      {
        id: "cow-1",
        name: "Thomas R.",
        role: "Coach en Leadership",
        tag: "# co-working-virtuel",
        avatar: "./img/avatar-cyril.jpg",
        time: "Il y a 15 minutes",
        text: "Salle de co-working silencieuse ouverte jusqu'à 17h ! Session Deep Work de 90 minutes. Qui est présent aujourd'hui ?",
        likes: 9,
        userLiked: false,
        replies: [
          {
            name: "Florian L.",
            role: "Consultant B2B",
            avatar: "./img/avatar-florian.jpg",
            time: "Il y a 10 minutes",
            text: "Présent ! Micro coupé et focus total sur la finalisation de mes propositions commerciales."
          }
        ]
      }
    ],
    victoires: [
      {
        id: "vic-1",
        name: "Florian L.",
        role: "Consultant B2B",
        tag: "# victoires-et-bilans",
        avatar: "./img/avatar-florian.jpg",
        time: "Il y a 2 heures",
        text: "Victoire du jour : premier contrat à 4 chiffres signé ce matin grâce aux conseils du live sur la posture en closing. Merci à toute la communauté pour le boost !",
        likes: 35,
        userLiked: false,
        replies: [
          {
            name: "Cyril D.",
            role: "Host / Mentor",
            avatar: "./img/avatar-cyril.jpg",
            time: "Il y a 1 heure",
            text: "Félicitations Florian ! La persévérance et le respect de la méthode paient toujours. Bravo !"
          },
          {
            name: "Aurore M.",
            role: "Studio Créatif",
            avatar: "./img/avatar-aurore.jpg",
            time: "Il y a 45 minutes",
            text: "Bravo Florian ! Immense victoire, ça motive tout le monde !"
          }
        ]
      }
    ]
  };

  const renderSalonPosts = (channelKey, fetchFromDb = true) => {
    if (!salonFeedContainer) return;
    activeChannelKey = channelKey;

    if (fetchFromDb && isPhpEnvironment()) {
      fetch(`api/chat.php?channel=${encodeURIComponent(channelKey)}`)
        .then(res => res.json())
        .then(data => {
          if (data && data.success && Array.isArray(data.messages)) {
            if (!channelPostsData[channelKey]) channelPostsData[channelKey] = [];
            data.messages.forEach(m => {
              const postId = `msg-${m.id}`;
              const exists = channelPostsData[channelKey].some(p => p.id === postId || (p.text === m.content && p.name === m.user_name));
              if (!exists) {
                channelPostsData[channelKey].unshift({
                  id: postId,
                  name: m.user_name,
                  role: m.user_role || "Membre",
                  tag: `# ${channelKey}`,
                  avatar: m.user_avatar || "./img/avatar-maxime.jpg",
                  time: m.time_display || "Récemment",
                  text: m.content,
                  image: null,
                  likes: 1,
                  userLiked: false,
                  replies: []
                });
              }
            });
            renderSalonPosts(channelKey, false);
          }
        })
        .catch(err => console.log('Error fetching chat messages:', err));
    }

    const posts = channelPostsData[channelKey] || [];

    if (posts.length === 0) {
      salonFeedContainer.innerHTML = `
        <div style="text-align:center;padding:3rem 1rem;background:#ffffff;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b;">
          <div style="font-size:2rem;margin-bottom:0.5rem;">💬</div>
          <h4 style="color:#0f172a;margin-bottom:0.25rem;">Bienvenue dans ce nouveau salon !</h4>
          <p style="font-size:0.85rem;">Soyez le premier à lancer une discussion ou à poser une question ci-dessous.</p>
        </div>
      `;
      return;
    }

    salonFeedContainer.innerHTML = posts.map(p => `
      <div class="salon-post" data-post-id="${p.id}" data-channel="${channelKey}">
        <div class="post-header">
          <img src="${p.avatar}" alt="${p.name}" class="post-avatar">
          <div class="post-meta">
            <strong>${escapeHtml(p.name)}</strong> 
            <span>${p.time} • ${escapeHtml(p.role)} • ${escapeHtml(p.tag)}</span>
          </div>
        </div>
        <div class="post-content">
          <p>${escapeHtml(p.text)}</p>
          ${p.image ? `
            <div class="salon-post-image-attachment">
              <img src="${p.image}" alt="Image jointe" class="salon-post-img" title="Cliquer pour agrandir" onclick="window.open(this.src, '_blank')">
            </div>
          ` : ''}
        </div>
        
        <!-- Actions interactives : Réponses, Like, Répondre, Message privé -->
        <div class="post-footer-actions">
          <button type="button" class="post-action-pill btn-toggle-replies" data-post-id="${p.id}" title="Afficher ou masquer les réponses">
            💬 <span class="replies-count">${p.replies.length}</span> ${p.replies.length > 1 ? 'réponses' : 'réponse'}
          </button>
          
          <button type="button" class="post-action-pill btn-encourage-post ${p.userLiked ? 'is-active' : ''}" data-post-id="${p.id}" title="Encourager cette publication">
            ❤️ <span class="likes-count">${p.likes}</span> encouragements
          </button>

          <button type="button" class="post-action-pill btn-open-reply-inline" data-post-id="${p.id}" title="Rédiger une réponse publique">
            ✍️ Répondre
          </button>

          <button type="button" class="post-action-pill btn-dm-author" data-author-name="${escapeHtml(p.name)}" data-author-role="${escapeHtml(p.role)}" data-author-avatar="${p.avatar}" title="Envoyer un message privé à ${escapeHtml(p.name)}">
            ✉️ Envoyer un message
          </button>
        </div>

        <!-- Fil des commentaires et réponses imbriquées -->
        <div class="post-threaded-comments" id="thread-${p.id}">
          ${p.replies.map(r => `
            <div class="comment-reply-card">
              <div class="comment-reply-header">
                <img src="${r.avatar}" alt="${r.name}" class="comment-reply-avatar">
                <span class="comment-reply-author">${escapeHtml(r.name)}</span>
                <span class="comment-reply-role">• ${escapeHtml(r.role)}</span>
                <span class="comment-reply-time">${r.time}</span>
              </div>
              <p class="comment-reply-text">${escapeHtml(r.text)}</p>
            </div>
          `).join('')}

          <!-- Champ de réponse inline -->
          <div class="inline-reply-input-box" id="replyBox-${p.id}">
            <input type="text" class="inline-reply-input" placeholder="Répondre à ${escapeHtml(p.name.split(' ')[0])}..." data-post-id="${p.id}">
            <button type="button" class="btn btn-primary btn-sm btn-submit-inline-reply" data-post-id="${p.id}">
              Répondre
            </button>
          </div>
        </div>
      </div>
    `).join('');
  };

  // Gestion des clics sur les salons (canaux)
  if (channelsListEl) {
    channelsListEl.addEventListener('click', (e) => {
      const ch = e.target.closest('.channel-item');
      if (ch) {
        document.querySelectorAll('.salons-channels-list .channel-item').forEach(c => c.classList.remove('active'));
        ch.classList.add('active');
        const channelKey = ch.getAttribute('data-channel') || 'general';
        if (salonMainTitle) salonMainTitle.textContent = `Salon ${ch.textContent.trim()}`;
        renderSalonPosts(channelKey);
        showToast(`Salon basculé sur ${ch.textContent.trim()}`);
      }
    });
  }

  // Interactions dans le fil des salons : Likes, Toggles réponses, Réponses rapides & MP direct
  if (salonFeedContainer) {
    salonFeedContainer.addEventListener('click', (e) => {
      // 1. Bouton Like / Encourager
      const likeBtn = e.target.closest('.btn-encourage-post');
      if (likeBtn) {
        const postId = likeBtn.getAttribute('data-post-id');
        const post = findPostById(postId);
        if (post) {
          post.userLiked = !post.userLiked;
          post.likes += post.userLiked ? 1 : -1;
          likeBtn.classList.toggle('is-active', post.userLiked);
          const countEl = likeBtn.querySelector('.likes-count');
          if (countEl) countEl.textContent = post.likes;
          showToast(post.userLiked ? "❤️ Encouragement envoyé !" : "Encouragement retiré");
        }
        return;
      }

      // 2. Bascule d'affichage du fil de commentaires
      const toggleRepliesBtn = e.target.closest('.btn-toggle-replies');
      if (toggleRepliesBtn) {
        const postId = toggleRepliesBtn.getAttribute('data-post-id');
        const thread = document.getElementById(`thread-${postId}`);
        if (thread) {
          const isHidden = thread.style.display === 'none';
          thread.style.display = isHidden ? 'flex' : 'none';
        }
        return;
      }

      // 3. Bouton "Répondre" : focus sur le champ de réponse inline
      const openReplyBtn = e.target.closest('.btn-open-reply-inline');
      if (openReplyBtn) {
        const postId = openReplyBtn.getAttribute('data-post-id');
        const thread = document.getElementById(`thread-${postId}`);
        if (thread) thread.style.display = 'flex';
        const input = document.querySelector(`.inline-reply-input[data-post-id="${postId}"]`);
        if (input) {
          input.focus();
          input.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        return;
      }

      // 4. Soumission d'une réponse inline
      const submitReplyBtn = e.target.closest('.btn-submit-inline-reply');
      if (submitReplyBtn) {
        const postId = submitReplyBtn.getAttribute('data-post-id');
        submitInlineReply(postId);
        return;
      }

      // 5. Bouton "Envoyer un message" à l'auteur du post
      const dmAuthorBtn = e.target.closest('.btn-dm-author');
      if (dmAuthorBtn) {
        const authorName = dmAuthorBtn.getAttribute('data-author-name') || 'Membre';
        const authorRole = dmAuthorBtn.getAttribute('data-author-role') || 'Membre actif';
        const authorAvatar = dmAuthorBtn.getAttribute('data-author-avatar') || './img/avatar-aurore.jpg';
        openDirectMessageWithMember(authorName, authorRole, authorAvatar);
        return;
      }
    });

    // Support de la touche "Entrée" dans les réponses inline
    salonFeedContainer.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.classList.contains('inline-reply-input')) {
        e.preventDefault();
        const postId = e.target.getAttribute('data-post-id');
        submitInlineReply(postId);
      }
    });
  }

  function findPostById(id) {
    for (const ch in channelPostsData) {
      const found = channelPostsData[ch].find(p => p.id === id);
      if (found) return found;
    }
    return null;
  }

  function submitInlineReply(postId) {
    const input = document.querySelector(`.inline-reply-input[data-post-id="${postId}"]`);
    if (!input || !input.value.trim()) return;

    const replyText = input.value.trim();
    const post = findPostById(postId);
    if (!post) return;

    const newReply = {
      name: memberName,
      role: "Membre One Vision (Vous)",
      avatar: memberAvatar,
      time: "À l'instant",
      text: replyText
    };

    post.replies.push(newReply);
    renderSalonPosts(activeChannelKey);
    showToast("💬 Votre réponse a été publiée avec succès !");
  }

  // Compositeur de message intégré en bas du salon actif
  const inlineSalonPostForm = document.getElementById('inlineSalonPostForm');
  const inlineSalonPostInput = document.getElementById('inlineSalonPostInput');
  const salonComposerAvatar = document.getElementById('salonComposerAvatar');
  const btnToggleSalonEmoji = document.getElementById('btnToggleSalonEmoji');
  const salonEmojiPicker = document.getElementById('salonEmojiPicker');
  const salonImageFileInput = document.getElementById('salonImageFileInput');
  const salonImageAttachmentPreview = document.getElementById('salonImageAttachmentPreview');
  const attachedImageElement = document.getElementById('attachedImageElement');
  const btnRemoveAttachedImage = document.getElementById('btnRemoveAttachedImage');

  let attachedSalonImageDataUrl = null;

  if (salonComposerAvatar) salonComposerAvatar.src = memberAvatar;

  // Toggle du sélecteur d'émojis
  if (btnToggleSalonEmoji && salonEmojiPicker) {
    btnToggleSalonEmoji.addEventListener('click', (e) => {
      e.stopPropagation();
      const isVisible = salonEmojiPicker.style.display === 'flex';
      salonEmojiPicker.style.display = isVisible ? 'none' : 'flex';
    });

    salonEmojiPicker.addEventListener('click', (e) => {
      const emojiBtn = e.target.closest('.emoji-btn');
      if (emojiBtn && inlineSalonPostInput) {
        const emoji = emojiBtn.getAttribute('data-emoji') || emojiBtn.textContent;
        const start = inlineSalonPostInput.selectionStart || inlineSalonPostInput.value.length;
        const end = inlineSalonPostInput.selectionEnd || inlineSalonPostInput.value.length;
        const text = inlineSalonPostInput.value;
        inlineSalonPostInput.value = text.substring(0, start) + emoji + text.substring(end);
        inlineSalonPostInput.focus();
        inlineSalonPostInput.selectionStart = inlineSalonPostInput.selectionEnd = start + emoji.length;
      }
    });
  }

  // Sélection et prévisualisation d'une image jointe
  if (salonImageFileInput) {
    salonImageFileInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (evt) => {
          attachedSalonImageDataUrl = evt.target.result;
          if (attachedImageElement) attachedImageElement.src = attachedSalonImageDataUrl;
          if (salonImageAttachmentPreview) salonImageAttachmentPreview.style.display = 'flex';
          showToast("🖼️ Image ajoutée à votre message !");
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Suppression de l'image jointe
  if (btnRemoveAttachedImage) {
    btnRemoveAttachedImage.addEventListener('click', () => {
      attachedSalonImageDataUrl = null;
      if (salonImageFileInput) salonImageFileInput.value = '';
      if (salonImageAttachmentPreview) salonImageAttachmentPreview.style.display = 'none';
      showToast("Image retirée du compositeur.");
    });
  }

  // Fermeture du sélecteur d'émojis au clic extérieur
  document.addEventListener('click', (e) => {
    if (salonEmojiPicker && !e.target.closest('#salonEmojiPicker') && !e.target.closest('#btnToggleSalonEmoji')) {
      salonEmojiPicker.style.display = 'none';
    }
  });

  if (inlineSalonPostForm && inlineSalonPostInput) {
    inlineSalonPostForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = inlineSalonPostInput.value.trim();
      if (!text && !attachedSalonImageDataUrl) return;

      const newPost = {
        id: `post-${Date.now()}`,
        name: memberName,
        role: "Administrateur One Vision",
        tag: `# ${activeChannelKey}`,
        avatar: memberAvatar,
        time: "À l'instant",
        text: text || "A partagé une image",
        image: attachedSalonImageDataUrl || null,
        likes: 1,
        userLiked: true,
        replies: []
      };

      if (!channelPostsData[activeChannelKey]) {
        channelPostsData[activeChannelKey] = [];
      }
      channelPostsData[activeChannelKey].unshift(newPost);

      // Synchronisation BDD SQLite via API chat
      if (isPhpEnvironment()) {
        fetch('api/chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            channel: activeChannelKey,
            content: text || "A partagé une image"
          })
        }).catch(err => console.log('Chat backend sync:', err));
      }

      renderSalonPosts(activeChannelKey);
      inlineSalonPostInput.value = '';
      attachedSalonImageDataUrl = null;
      if (salonImageFileInput) salonImageFileInput.value = '';
      if (salonImageAttachmentPreview) salonImageAttachmentPreview.style.display = 'none';
      if (salonEmojiPicker) salonEmojiPicker.style.display = 'none';
      showToast(`🎉 Message publié dans le salon #${activeChannelKey} !`);
    });
  }

  // Modale Admin : Création d'un Nouveau Salon Thématique
  const btnOpenNewSalonModal = document.getElementById('btnOpenNewSalonModal');
  const createSalonModal = document.getElementById('createSalonModal');
  const closeCreateSalonModalBtn = document.getElementById('closeCreateSalonModalBtn');
  const cancelCreateSalonBtn = document.getElementById('cancelCreateSalonBtn');
  const backdropCreateSalon = document.getElementById('backdropCreateSalon');
  const createSalonForm = document.getElementById('createSalonForm');

  const openCreateSalonModal = () => {
    if (createSalonModal) createSalonModal.style.display = 'flex';
  };

  const closeCreateSalonModal = () => {
    if (createSalonModal) createSalonModal.style.display = 'none';
  };

  if (btnOpenNewSalonModal) btnOpenNewSalonModal.addEventListener('click', openCreateSalonModal);
  if (closeCreateSalonModalBtn) closeCreateSalonModalBtn.addEventListener('click', closeCreateSalonModal);
  if (cancelCreateSalonBtn) cancelCreateSalonBtn.addEventListener('click', closeCreateSalonModal);
  if (backdropCreateSalon) backdropCreateSalon.addEventListener('click', closeCreateSalonModal);

  if (createSalonForm) {
    createSalonForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const emojiInput = document.getElementById('newSalonEmoji');
      const nameInput = document.getElementById('newSalonNameInput');
      const descInput = document.getElementById('newSalonDescInput');

      const emoji = emojiInput ? emojiInput.value.trim() : '🚀';
      let rawName = nameInput ? nameInput.value.trim().toLowerCase() : '';
      const slug = rawName.replace(/[^a-z0-9-_]/g, '-').replace(/-+/g, '-');
      const desc = descInput ? descInput.value.trim() : '';

      if (!slug) {
        showToast("Veuillez saisir un identifiant valide pour le salon.");
        return;
      }

      // Initialiser le nouveau canal dans les données
      channelPostsData[slug] = [
        {
          id: `init-${slug}`,
          name: memberName,
          role: "Fondateur & Administrateur",
          tag: `# ${slug}`,
          avatar: memberAvatar,
          time: "À l'instant",
          text: `Bienvenue dans le salon #${slug} ! ${desc || 'Partagez vos réflexions, vos questions et collaborez avec les membres.'}`,
          likes: 2,
          userLiked: true,
          replies: []
        }
      ];

      // Ajouter le nouveau canal dans la barre latérale des canaux
      if (channelsListEl) {
        const newChanDiv = document.createElement('div');
        newChanDiv.className = 'channel-item active';
        newChanDiv.setAttribute('data-channel', slug);
        newChanDiv.textContent = `# ${emoji} ${slug}`;

        document.querySelectorAll('.salons-channels-list .channel-item').forEach(c => c.classList.remove('active'));
        channelsListEl.appendChild(newChanDiv);
      }

      if (salonMainTitle) salonMainTitle.textContent = `Salon # ${emoji} ${slug}`;
      renderSalonPosts(slug);
      closeCreateSalonModal();
      createSalonForm.reset();
      if (emojiInput) emojiInput.value = '🚀';

      showToast(`✨ Le salon #${slug} a été créé et ouvert avec succès !`);
    });
  }

  // Rendu initial du salon général
  renderSalonPosts('general');

  // 8. Modèles & Outils : Téléchargements de Documents & Boîte de Dialogue des Formats
  const resourceCatalog = {
    contrat: {
      title: "Modèle de Contrat de Prestation Freelance",
      subtitle: "Document juridique validé pour freelances, consultants & coachs",
      icon: "📄",
      description: "Ce modèle certifié protège vos prestations et sécurise vos acomptes. Vous pouvez télécharger la version PDF prête à être signée ou la version Word modifiable pour adapter les clauses, délais et tarifs à votre activité.",
      options: [
        {
          ext: "pdf",
          badge: "Prêt à signer",
          badgeClass: "badge-pdf",
          icon: "📥",
          title: "Version PDF (.pdf)",
          meta: "Format sécurisé • 240 Ko • Idéal pour impression ou signature électronique",
          btnLabel: "Télécharger en .PDF"
        },
        {
          ext: "docx",
          badge: "Éditable",
          badgeClass: "badge-word",
          icon: "📝",
          title: "Version Word (.docx)",
          meta: "Document modifiable • Personnalisez vos mentions, clauses et tarifs",
          btnLabel: "Télécharger en .DOCX"
        }
      ]
    },
    trame: {
      title: "Trame d'Appel Découverte & Closing Éthique",
      subtitle: "Script étape par étape en 6 phases (Taux de conversion moyen : 42%)",
      icon: "🎯",
      description: "La structure complète pour mener un entretien de qualification fluide et closer sans pression commerciale. Téléchargez la fiche de synthèse rapide en PDF ou la version Word pour personnaliser les questions de cadrage.",
      options: [
        {
          ext: "pdf",
          badge: "Fiche Mémo",
          badgeClass: "badge-pdf",
          icon: "📥",
          title: "Version PDF (.pdf)",
          meta: "Guide visuel 6 étapes • 180 Ko • Prêt à garder sous les yeux lors de vos appels",
          btnLabel: "Télécharger en .PDF"
        },
        {
          ext: "docx",
          badge: "Modifiable",
          badgeClass: "badge-word",
          icon: "📝",
          title: "Version Word (.docx)",
          meta: "Trame personnalisable • Intégrez vos packages d'accompagnement et objections types",
          btnLabel: "Télécharger en .DOCX"
        }
      ]
    },
    simulateur: {
      title: "Simulateur de Rentabilité & Tarifs Horaires",
      subtitle: "Calculateur automatisé de TJM & seuil de viabilité financière",
      icon: "📊",
      description: "Déterminez le prix exact de vos offres en tenant compte de vos charges URSSAF, de vos congés et de votre temps facturable. Téléchargez le guide explicatif complet ou la matrice Excel avec formules automatiques.",
      options: [
        {
          ext: "pdf",
          badge: "Guide Pratique",
          badgeClass: "badge-pdf",
          icon: "📥",
          title: "Guide Méthodologique (.pdf)",
          meta: "Méthodologie de calcul du TJM et stratégie de packaging de valeur",
          btnLabel: "Télécharger le Guide (.PDF)"
        },
        {
          ext: "csv",
          badge: "Tableur",
          badgeClass: "badge-excel",
          icon: "📊",
          title: "Matrice Excel / .CSV",
          meta: "Fichier tableur dynamique avec calculs et simulateur de rentabilité",
          btnLabel: "Télécharger le Tableur (.CSV)"
        }
      ]
    }
  };

  const resourceDownloadModal = document.getElementById('resourceDownloadModal');
  const closeResourceDownloadModalBtn = document.getElementById('closeResourceDownloadModalBtn');
  const backdropResourceDownload = document.getElementById('backdropResourceDownload');
  const closeResourceDownloadModalFooterBtn = document.getElementById('closeResourceDownloadModalFooterBtn');
  const resModalIcon = document.getElementById('resModalIcon');
  const resModalTitle = document.getElementById('resModalTitle');
  const resModalSubtitle = document.getElementById('resModalSubtitle');
  const resModalDescription = document.getElementById('resModalDescription');
  const resModalVersionsContainer = document.getElementById('resModalVersionsContainer');

  function openResourceDownloadModal(resKey) {
    const data = resourceCatalog[resKey];
    if (!data) return;

    if (resModalIcon) resModalIcon.textContent = data.icon;
    if (resModalTitle) resModalTitle.textContent = data.title;
    if (resModalSubtitle) resModalSubtitle.textContent = data.subtitle;
    if (resModalDescription) resModalDescription.textContent = data.description;

    if (resModalVersionsContainer) {
      resModalVersionsContainer.innerHTML = data.options.map(opt => `
        <div class="resource-version-card">
          <div class="resource-version-left">
            <div class="resource-version-icon-box">${opt.icon}</div>
            <div class="resource-version-details">
              <div class="resource-version-header-line">
                <strong class="resource-version-name">${opt.title}</strong>
                <span class="resource-version-badge ${opt.badgeClass}">${opt.badge}</span>
              </div>
              <p class="resource-version-meta">${opt.meta}</p>
            </div>
          </div>
          <button type="button" class="btn btn-primary btn-sm btn-trigger-version-dl" data-res="${resKey}" data-ext="${opt.ext}">
            ${opt.btnLabel}
          </button>
        </div>
      `).join('');
    }

    if (resourceDownloadModal) {
      resourceDownloadModal.style.display = 'flex';
    }
  }

  function closeResourceDownloadModal() {
    if (resourceDownloadModal) {
      resourceDownloadModal.style.display = 'none';
    }
  }

  if (closeResourceDownloadModalBtn) closeResourceDownloadModalBtn.addEventListener('click', closeResourceDownloadModal);
  if (backdropResourceDownload) backdropResourceDownload.addEventListener('click', closeResourceDownloadModal);
  if (closeResourceDownloadModalFooterBtn) closeResourceDownloadModalFooterBtn.addEventListener('click', closeResourceDownloadModal);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (resourceDownloadModal && resourceDownloadModal.style.display === 'flex') {
        closeResourceDownloadModal();
      }
    }
  });

  function downloadBlobFile(filename, content, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast(`📥 Téléchargement lancé : ${filename}`);
  }

  document.addEventListener('click', (e) => {
    // 1. Bouton flèche déroulante (.btn-split-toggle) pour choisir le format (.PDF ou .DOCX / .CSV)
    const splitToggleBtn = e.target.closest('.btn-split-toggle');
    if (splitToggleBtn) {
      e.stopPropagation();
      const group = splitToggleBtn.closest('.split-download-group');
      const menu = group ? group.querySelector('.split-download-menu') : null;
      const isOpen = menu && (menu.style.display === 'block' || group.classList.contains('open'));

      // Fermer tous les menus de téléchargement ouverts
      document.querySelectorAll('.split-download-menu').forEach(m => m.style.display = 'none');
      document.querySelectorAll('.split-download-group').forEach(g => g.classList.remove('open'));
      document.querySelectorAll('.btn-split-toggle').forEach(t => t.setAttribute('aria-expanded', 'false'));

      if (menu && !isOpen) {
        menu.style.display = 'block';
        group.classList.add('open');
        splitToggleBtn.setAttribute('aria-expanded', 'true');
      }
      return;
    }

    // Fermer les menus déroulants si on clique en dehors
    if (!e.target.closest('.split-download-group')) {
      document.querySelectorAll('.split-download-menu').forEach(m => m.style.display = 'none');
      document.querySelectorAll('.split-download-group').forEach(g => g.classList.remove('open'));
      document.querySelectorAll('.btn-split-toggle').forEach(t => t.setAttribute('aria-expanded', 'false'));
    }

    // 2. Clic sur un bouton de téléchargement (.btn-download-res) dans le bouton split ou son sous-menu
    const dlBtn = e.target.closest('.btn-download-res');
    if (dlBtn) {
      const res = dlBtn.getAttribute('data-res');
      const ext = dlBtn.getAttribute('data-ext') || 'pdf';

      // Fermer le menu déroulant
      document.querySelectorAll('.split-download-menu').forEach(m => m.style.display = 'none');
      document.querySelectorAll('.split-download-group').forEach(g => g.classList.remove('open'));
      document.querySelectorAll('.btn-split-toggle').forEach(t => t.setAttribute('aria-expanded', 'false'));

      handleResourceDownload(res, ext);
      return;
    }

    // Fallback boutons historique
    const versionDlBtn = e.target.closest('.btn-trigger-version-dl');
    if (versionDlBtn) {
      const res = versionDlBtn.getAttribute('data-res');
      const ext = versionDlBtn.getAttribute('data-ext') || 'pdf';
      handleResourceDownload(res, ext);
      return;
    }
  });

  function handleResourceDownload(res, ext) {
    if (res === 'contrat') {
      if (ext === 'pdf') {
        const contratPdf = `%PDF-1.4
% CONTRAT DE PRESTATION DE SERVICES FREELANCE & COACHING
==============================================================
Édité par : One Vision Community (Espace Membre)
Validé par juriste d'affaires spécialisé Web, Conseil & Formation

ENTRE LES SOUSSIGNÉS :
Le Prestataire : [Nom de votre entreprise / Freelance]
Email : [Votre email professionnel]
SIRET : [Votre numéro SIRET]

ET :
Le Client : [Nom du client / Entreprise]
Email : [Email du client]

ARTICLE 1 - OBJET DE LA PRESTATION
Le Prestataire s'engage à réaliser pour le compte du Client les prestations définies comme suit :
- Analyse stratégique, cadrage et livrables d'accompagnement
- Séances de suivi hebdomadaire et support par messagerie dédiée

ARTICLE 2 - TARIFS ET MODALITÉS DE RÈGLEMENT
Le montant forfaitaire de la prestation est fixé à [Montant] € Hors Taxes.
Acompte de 30% à la signature du contrat, solde à la livraison finale.

ARTICLE 3 - PROPRIÉTÉ INTELLECTUELLE
Les livrables développés deviennent la propriété exclusive du Client après paiement intégral de la facture.

Fait à ................................., le ....................
Signature du Prestataire :                   Signature du Client :
`;
        downloadBlobFile('Contrat_Prestation_Freelance_OneVision.pdf', contratPdf, 'application/pdf');
      } else {
        const contratDocx = `CONTRAT DE PRESTATION DE SERVICES FREELANCE & COACHING (FORMAT WORD EDITABLE)
One Vision Community - Document Officiel
... (Document complet prêt à l'emploi avec clauses de garantie et paiement) ...`;
        downloadBlobFile('Contrat_Prestation_Freelance_OneVision.docx', contratDocx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
      }
    } else if (res === 'trame') {
      if (ext === 'pdf') {
        const tramePdf = `%PDF-1.4
% TRAME D'APPEL DÉCOUVERTE & CLOSING ÉTHIQUE EN 6 ÉTAPES
=========================================================
Méthode One Vision Community • Taux de conversion moyen : 42%

PHASE 1 : LE BRIS DE GLACE & CADRAGE (3 minutes)
- "Bonjour [Prénom], ravi d'échanger avec vous aujourd'hui. L'objectif des 30 prochaines minutes est de faire un état des lieux de votre activité, de comprendre vos blocages actuels et de voir en toute franchise si je suis la bonne personne pour vous aider. Ça vous convient ?"

PHASE 2 : LA SITUATION ACTUELLE (5 minutes)
- "Où en est votre activité aujourd'hui ?"
- "Quel est votre chiffre d'affaires moyen mensuel ?"

PHASE 3 : LA SITUATION DÉSIRÉE (5 minutes)
- "Où aimeriez-vous être dans 6 mois ?"
- "Qu'est-ce que cela changerait concrètement pour vous et votre quotidien ?"

PHASE 4 : LES BLOCAGES CLÉS (7 minutes)
- "Qu'est-ce qui vous empêche d'atteindre cet objectif seul aujourd'hui ?"
- "Qu'avez-vous déjà essayé qui n'a pas fonctionné ?"

PHASE 5 : LA PRÉSENTATION DE L'OFFRE ADAPTÉE (7 minutes)
- "Au vu de ce que vous venez de me décrire, voici le plan d'action en 3 piliers que nous mettrions en place..."

PHASE 6 : LE CLOSING & TRAITEMENT DES QUESTIONS (5 minutes)
- "Sur une échelle de 1 à 10, où vous situez-vous par rapport à ce projet ?"
- "Avez-vous des questions sur les modalités de démarrage ?"
`;
        downloadBlobFile('Trame_Appel_Decouverte_Closing.pdf', tramePdf, 'application/pdf');
      } else {
        const trameDocx = `TRAME D'APPEL DÉCOUVERTE & CLOSING ÉTHIQUE (FORMAT WORD MODIFIABLE)
... (Script complet mot-à-mot avec réponses aux objections) ...`;
        downloadBlobFile('Trame_Appel_Decouverte_Closing.docx', trameDocx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
      }
    } else if (res === 'simulateur') {
      if (ext === 'pdf') {
        const simuPdf = `%PDF-1.4
% GUIDE PRATIQUE DU SIMULATEUR DE RENTABILITÉ & TAUX JOURNALIER
===============================================================
One Vision Community - Guide méthodologique de calcul du TJM

1. Fixer son revenu net cible mensuel (ex: 3 500 € net)
2. Intégrer les charges sociales URSSAF (22% à 24% en micro-entreprise)
3. Intégrer les frais d'outils et abonnements logiciels (150 € / mois)
4. Déduire les jours non facturables (prospection, gestion, congés : 6 à 8 jours / mois)
5. Formule : TJM = (Charges totales + Objectif Net) / Jours Facturables

Exemple : Pour 5 000 € HT facturés sur 12 jours = TJM minimum conseillé de 416,67 € HT.
`;
        downloadBlobFile('Guide_Simulateur_Rentabilite_TJM.pdf', simuPdf, 'application/pdf');
      } else {
        const csvContent = `Catégorie,Indicateur,Valeur mensuelle (€),Commentaires
Revenus,Objectif de chiffre d'affaires,5000,Objectif net avant impôts
Charges,Cotisations sociales URSSAF (22%),1100,Régime micro-entreprise
Charges,Outils logiciels et abonnements,150,SaaS & outils métiers
Charges,Assurance et compte pro,60,Frais fixes indispensables
Temps,Jours travaillés par mois,18,Moyenne sur l'année
Temps,Jours facturés aux clients,12,6 jours dédiés à l'administratif & prospection
Résultat,Taux Journalier Moyen (TJM) Conseillé,416.67,Prix minimal à facturer par jour
`;
        downloadBlobFile('Simulateur_Rentabilite_TJM_OneVision.csv', csvContent, 'text/csv;charset=utf-8;');
      }
    }
  }

  // Intégration Directe Google Calendar (Rappel & Agenda)
  function addToGoogleCalendar(title, dates, details, location) {
    const gcalUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${encodeURIComponent(dates)}&details=${encodeURIComponent(details)}&location=${encodeURIComponent(location)}`;
    window.open(gcalUrl, '_blank', 'noopener,noreferrer');
    showToast(`📅 Événement ouvert dans votre Google Agenda : ${title}`);
  }

  document.addEventListener('click', (e) => {
    const gcalBtn = e.target.closest('.btn-add-google-cal');
    if (gcalBtn) {
      const title = gcalBtn.getAttribute('data-cal-title') || 'Mastermind One Vision';
      const dates = gcalBtn.getAttribute('data-cal-date') || '20260924T183000Z/20260924T200000Z';
      const desc = gcalBtn.getAttribute('data-cal-desc') || 'Session Live One Vision Community';
      const loc = gcalBtn.getAttribute('data-cal-loc') || 'Espace Live One Vision (dashboard.html)';
      addToGoogleCalendar(title, dates, desc, loc);
    }
  });

  // 9. Messagerie Privée avec Historique d'Échanges & Réponse Automatique Réaliste
  const directMessageModal = document.getElementById('directMessageModal');
  const closeDirectMessageModalBtn = document.getElementById('closeDirectMessageModalBtn');
  const backdropDirectMessage = document.getElementById('backdropDirectMessage');
  const directMessageForm = document.getElementById('directMessageForm');
  const dmRecipientName = document.getElementById('dmRecipientName');
  const dmRecipientRole = document.getElementById('dmRecipientRole');
  const dmRecipientAvatar = document.getElementById('dmRecipientAvatar');
  const dmConversationBox = document.getElementById('dmConversationBox');
  const dmMessageInput = document.getElementById('dmMessageInput');

  let currentDmRecipient = "Aurore M.";

  const memberConversations = {
    "Aurore M.": [
      { sender: 'them', text: "Hello Katahana ! Ravi d'échanger avec toi. En quoi puis-je t'aider sur ton projet ou ton branding aujourd'hui ?", time: "18:42" }
    ],
    "Cyril D.": [
      { sender: 'them', text: "Salut Katahana ! Bienvenue dans la communauté. Si tu as des questions sur le positionnement de tes forfaits de coaching, dis-moi tout !", time: "17:15" }
    ],
    "Florian L.": [
      { sender: 'them', text: "Hello ! J'ai vu tes interactions dans le salon général. Toujours disponible si tu veux échanger sur la prospection B2B !", time: "Hier 14:10" }
    ],
    "Sarah B.": [
      { sender: 'them', text: "Bonjour Katahana ! Ravie de te connecter ici. As-tu pu mettre en pratique les retours du dernier atelier de co-working ?", time: "Hier 16:30" }
    ],
    "Maxime T.": [
      { sender: 'them', text: "Hello ! Dispo si tu as besoin d'un coup de main sur l'automatisation de ton back-office ou Stripe.", time: "12:05" }
    ],
    "Thomas R.": [
      { sender: 'them', text: "Salut ! On se retrouve ce dimanche pour le bilan de la semaine. Prépare tes victoires et tes questions blocages !", time: "09:30" }
    ]
  };

  function renderDmConversation(recipientName) {
    if (!dmConversationBox) return;
    const history = memberConversations[recipientName] || [
      { sender: 'them', text: `Bonjour ! Ravi de faire ta connaissance sur One Vision. N'hésite pas à me poser tes questions.`, time: "À l'instant" }
    ];
    memberConversations[recipientName] = history;

    dmConversationBox.innerHTML = history.map(msg => `
      <div class="dm-bubble ${msg.sender === 'me' ? 'dm-bubble-out' : 'dm-bubble-in'}">
        <div class="dm-bubble-text">${escapeHtml(msg.text)}</div>
        <span class="dm-bubble-time">${msg.time}</span>
      </div>
    `).join('');

    dmConversationBox.scrollTop = dmConversationBox.scrollHeight;
  }

  function openDirectMessageWithMember(name, role, avatar) {
    currentDmRecipient = name;
    if (dmRecipientName) dmRecipientName.textContent = `Message privé avec ${name}`;
    if (dmRecipientRole) dmRecipientRole.textContent = `${role} • En ligne`;
    if (dmRecipientAvatar) {
      dmRecipientAvatar.src = avatar;
      dmRecipientAvatar.alt = name;
    }

    renderDmConversation(name);
    if (directMessageModal) directMessageModal.style.display = 'flex';
    if (dmMessageInput) {
      dmMessageInput.value = '';
      dmMessageInput.focus();
    }
  }

  const closeDirectMessageModal = () => {
    if (directMessageModal) directMessageModal.style.display = 'none';
  };

  if (closeDirectMessageModalBtn) closeDirectMessageModalBtn.addEventListener('click', closeDirectMessageModal);
  if (backdropDirectMessage) backdropDirectMessage.addEventListener('click', closeDirectMessageModal);

  // Ouverture depuis l'annuaire des membres
  document.addEventListener('click', (e) => {
    const dmBtn = e.target.closest('.btn-send-dm');
    if (dmBtn) {
      const name = dmBtn.getAttribute('data-member-name') || 'Membre';
      const role = dmBtn.getAttribute('data-member-role') || 'Membre vérifié';
      const avatar = dmBtn.getAttribute('data-member-avatar') || './img/avatar-aurore.jpg';
      openDirectMessageWithMember(name, role, avatar);
    }
  });

  // Envoi du message privé avec indicateur de frappe et réponse simulée
  if (directMessageForm && dmMessageInput) {
    directMessageForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const messageText = dmMessageInput.value.trim();
      if (!messageText) return;

      const now = new Date();
      const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

      // 1. Ajouter le message de l'utilisateur
      if (!memberConversations[currentDmRecipient]) {
        memberConversations[currentDmRecipient] = [];
      }
      memberConversations[currentDmRecipient].push({
        sender: 'me',
        text: messageText,
        time: timeStr
      });

      renderDmConversation(currentDmRecipient);
      dmMessageInput.value = '';
      showToast(`📨 Message envoyé à ${currentDmRecipient} !`);

      // 2. Afficher l'indicateur de frappe
      setTimeout(() => {
        if (dmConversationBox && directMessageModal.style.display !== 'none') {
          const typingEl = document.createElement('div');
          typingEl.className = 'dm-typing-indicator';
          typingEl.id = 'dmTypingIndicator';
          typingEl.textContent = `${currentDmRecipient} est en train d'écrire...`;
          dmConversationBox.appendChild(typingEl);
          dmConversationBox.scrollTop = dmConversationBox.scrollHeight;
        }
      }, 700);

      // 3. Réponse réaliste de l'interlocuteur après ~2 secondes
      setTimeout(() => {
        const typingEl = document.getElementById('dmTypingIndicator');
        if (typingEl) typingEl.remove();

        const firstName = currentDmRecipient.split(' ')[0];
        const repliesPool = [
          `Merci pour ton message Katahana ! J'ai bien noté tes éléments. Je regarde ça en détail et je te réponds posément d'ici la fin de journée. On reste en contact ! 🙌`,
          `Super initiative Katahana ! Ton approche est très pertinente. On en reparle aussi lors du prochain live de co-working !`,
          `Bien reçu ! Merci pour ce retour. C'est exactement le type d'entraide concrète qu'on adore partager dans l'Academy. À très vite !`
        ];
        const chosenReply = repliesPool[Math.floor(Math.random() * repliesPool.length)];

        memberConversations[currentDmRecipient].push({
          sender: 'them',
          text: chosenReply,
          time: timeStr
        });

        renderDmConversation(currentDmRecipient);
        showToast(`📩 Nouveau message reçu de ${currentDmRecipient} !`);
      }, 2200);
    });
  }

  // 9b. Lecteur Vidéo Replay HD Interactif des Masterclasses
  const replayPlayerModal = document.getElementById('replayPlayerModal');
  const closeReplayPlayerModalBtn = document.getElementById('closeReplayPlayerModalBtn');
  const closeReplayPlayerModalFooterBtn = document.getElementById('closeReplayPlayerModalFooterBtn');
  const backdropReplayPlayer = document.getElementById('backdropReplayPlayer');
  const replayModalTitle = document.getElementById('replayModalTitle');
  const replayModalCategory = document.getElementById('replayModalCategory');
  const replayModalHostName = document.getElementById('replayModalHostName');
  const replayModalHostAvatar = document.getElementById('replayModalHostAvatar');
  const replayModalMeta = document.getElementById('replayModalMeta');
  const replayModalDescription = document.getElementById('replayModalDescription');
  const replayVideoPoster = document.getElementById('replayVideoPoster');
  const replayHudDuration = document.getElementById('replayHudDuration');
  const replayTimestamp = document.getElementById('replayTimestamp');
  const replayTimelineProgress = document.getElementById('replayTimelineProgress');
  const replayTimelineBar = document.getElementById('replayTimelineBar');
  const replayPlayPauseToggle = document.getElementById('replayPlayPauseToggle');
  const replayBigPlayBtn = document.getElementById('replayBigPlayBtn');
  const replaySpeedBtn = document.getElementById('replaySpeedBtn');
  const replayFullscreenBtn = document.getElementById('replayFullscreenBtn');
  const btnReplayActionDownload = document.getElementById('btnReplayActionDownload');
  const btnReplayOpenWorksheet = document.getElementById('btnReplayOpenWorksheet');

  let isReplayPlaying = true;
  let replayProgressPercent = 28;
  const speedOptions = ['1.0x', '1.25x', '1.5x', '2.0x'];
  let currentSpeedIdx = 1;

  function openReplayModal(card) {
    if (!replayPlayerModal) return;
    const title = card.getAttribute('data-replay-title') || 'Masterclass Replay HD';
    const cat = card.getAttribute('data-replay-cat') || 'Formation Replay';
    const host = card.getAttribute('data-replay-host') || 'Mentor One Vision';
    const views = card.getAttribute('data-replay-views') || '450';
    const duration = card.getAttribute('data-replay-duration') || '1h 15min';
    const thumb = card.getAttribute('data-replay-thumb') || './img/feature-replays.jpg';
    const desc = card.getAttribute('data-replay-desc') || 'Accédez à l\'enregistrement intégral de cette session intensive.';

    if (replayModalTitle) replayModalTitle.textContent = title;
    if (replayModalCategory) replayModalCategory.textContent = cat;
    if (replayModalHostName) replayModalHostName.textContent = host;
    if (replayModalMeta) replayModalMeta.textContent = `Animé par ${host} • ${views} vues • Durée : ${duration}`;
    if (replayModalDescription) replayModalDescription.textContent = desc;
    if (replayVideoPoster) replayVideoPoster.src = thumb;
    if (replayHudDuration) replayHudDuration.textContent = duration;
    if (replayTimestamp) replayTimestamp.textContent = `18:40 / ${duration}`;
    if (replayTimelineProgress) replayTimelineProgress.style.width = '24%';

    if (host.includes('Cyril') && replayModalHostAvatar) replayModalHostAvatar.src = './img/avatar-cyril.jpg';
    else if (host.includes('Sarah') && replayModalHostAvatar) replayModalHostAvatar.src = './img/avatar-sarah.jpg';
    else if (host.includes('Maxime') && replayModalHostAvatar) replayModalHostAvatar.src = './img/avatar-maxime.jpg';

    isReplayPlaying = true;
    if (replayPlayPauseToggle) replayPlayPauseToggle.textContent = '⏸️ Pause';
    if (replayBigPlayBtn) replayBigPlayBtn.style.display = 'none';

    replayPlayerModal.style.display = 'flex';
    showToast(`🎬 Lecture du Replay : ${title}`);
  }

  function closeReplayModal() {
    if (replayPlayerModal) replayPlayerModal.style.display = 'none';
  }

  document.addEventListener('click', (e) => {
    const replayCard = e.target.closest('.replay-card');
    if (replayCard) {
      openReplayModal(replayCard);
    }
  });

  if (closeReplayPlayerModalBtn) closeReplayPlayerModalBtn.addEventListener('click', closeReplayModal);
  if (closeReplayPlayerModalFooterBtn) closeReplayPlayerModalFooterBtn.addEventListener('click', closeReplayModal);
  if (backdropReplayPlayer) backdropReplayPlayer.addEventListener('click', closeReplayModal);

  if (replayPlayPauseToggle && replayBigPlayBtn) {
    const toggleReplayPlay = () => {
      isReplayPlaying = !isReplayPlaying;
      if (isReplayPlaying) {
        replayPlayPauseToggle.textContent = '⏸️ Pause';
        replayBigPlayBtn.style.display = 'none';
        showToast('▶️ Replay HD en cours de lecture');
      } else {
        replayPlayPauseToggle.textContent = '▶️ Lecture';
        replayBigPlayBtn.style.display = 'flex';
        showToast('⏸️ Replay en pause');
      }
    };
    replayPlayPauseToggle.addEventListener('click', toggleReplayPlay);
    replayBigPlayBtn.addEventListener('click', toggleReplayPlay);
  }

  if (replaySpeedBtn) {
    replaySpeedBtn.addEventListener('click', () => {
      currentSpeedIdx = (currentSpeedIdx + 1) % speedOptions.length;
      replaySpeedBtn.textContent = `⚡ ${speedOptions[currentSpeedIdx]}`;
      showToast(`Vitesse de lecture ajustée à ${speedOptions[currentSpeedIdx]}`);
    });
  }

  if (replayTimelineBar && replayTimelineProgress) {
    replayTimelineBar.addEventListener('click', (e) => {
      const rect = replayTimelineBar.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      const pct = Math.max(0, Math.min(100, (clickX / rect.width) * 100));
      replayTimelineProgress.style.width = `${pct}%`;
      showToast(`Progression calée à ${Math.round(pct)}%`);
    });
  }

  if (replayFullscreenBtn) {
    replayFullscreenBtn.addEventListener('click', () => {
      const videoScreen = document.getElementById('replayVideoScreen');
      if (videoScreen) {
        if (!document.fullscreenElement) {
          videoScreen.requestFullscreen().catch(() => {});
        } else {
          document.exitFullscreen().catch(() => {});
        }
      }
    });
  }

  if (btnReplayActionDownload) {
    btnReplayActionDownload.addEventListener('click', () => {
      handleResourceDownload('contrat', 'pdf');
    });
  }

  if (btnReplayOpenWorksheet) {
    btnReplayOpenWorksheet.addEventListener('click', () => {
      closeReplayModal();
      const atelierModal = document.getElementById('atelierWorksheetModal');
      if (atelierModal) atelierModal.style.display = 'flex';
    });
  }

  // 9c. Profil Détaillé des Membres & Accomplissements Professionnels
  const memberProfileModal = document.getElementById('memberProfileModal');
  const closeMemberProfileModalBtn = document.getElementById('closeMemberProfileModalBtn');
  const backdropMemberProfile = document.getElementById('backdropMemberProfile');
  const profileModalAvatar = document.getElementById('profileModalAvatar');
  const profileModalName = document.getElementById('profileModalName');
  const profileModalBadge = document.getElementById('profileModalBadge');
  const profileModalRole = document.getElementById('profileModalRole');
  const profileModalLocation = document.getElementById('profileModalLocation');
  const profileStatMissions = document.getElementById('profileStatMissions');
  const profileStatRating = document.getElementById('profileStatRating');
  const profileStatMasterminds = document.getElementById('profileStatMasterminds');
  const profileModalAbout = document.getElementById('profileModalAbout');
  const profileModalSkills = document.getElementById('profileModalSkills');
  const profileLinkWebsite = document.getElementById('profileLinkWebsite');
  const profileLinkWebsiteUrl = document.getElementById('profileLinkWebsiteUrl');
  const profileLinkLinkedin = document.getElementById('profileLinkLinkedin');
  const profileLinkLinkedinUrl = document.getElementById('profileLinkLinkedinUrl');
  const profileLinkCommunity = document.getElementById('profileLinkCommunity');
  const profileLinkCommunityUrl = document.getElementById('profileLinkCommunityUrl');
  const btnCopyProfileLink = document.getElementById('btnCopyProfileLink');
  const btnProfileSendDm = document.getElementById('btnProfileSendDm');

  let activeProfileMember = null;

  const membersDirectoryData = {
    "aurore": {
      id: "aurore",
      name: "Aurore M.",
      role: "Fondatrice Studio Créatif & Directrice Artistique",
      subRole: "Direction Artistique, Design de Marques & Webflow",
      badge: "🎨 Experte Branding & DA",
      avatar: "./img/avatar-aurore.jpg",
      location: "Paris, France",
      tenure: "8 mois",
      stats: {
        missions: "85+",
        rating: "4.9/5",
        lives: "14",
        partners: "28"
      },
      about: "J'accompagne les consultants, coachs et créateurs indépendants à bâtir une identité visuelle et un univers de marque haut de gamme. Mon métier est de transformer votre expertise en une présence magnétique et percutante qui vous permet de justifier naturellement des tarifs 2 à 3 fois plus élevés sans la moindre négociation.",
      skills: ["Identité Visuelle Premium", "Direction Artistique Figma", "Design de Pages de Vente", "Webflow", "Refonte Graphique d'Offres"],
      socials: [
        { type: "linkedin", name: "LinkedIn Pro", handle: "linkedin.com/in/aurore-design", url: "https://www.linkedin.com/in/aurore-design", icon: "💼" },
        { type: "website", name: "Site Web & Portfolio", handle: "aurore-studio.design", url: "https://aurore-studio.design", icon: "🌐" },
        { type: "community", name: "Canal Créatif & Veille", handle: "t.me/creatif_brand_hub", url: "https://t.me/creatif_brand_hub", icon: "💬" }
      ],
      links: {
        website: "https://aurore-studio.design",
        websiteDisplay: "aurore-studio.design (Portfolio)",
        linkedin: "https://linkedin.com/in/aurore-design",
        linkedinDisplay: "linkedin.com/in/aurore-design",
        community: "https://t.me/creatif_brand_hub",
        communityDisplay: "t.me/creatif_brand_hub (Canal Design)"
      }
    },
    "cyril": {
      id: "cyril",
      name: "Cyril D.",
      role: "Coach Business & Mentor High-Ticket",
      subRole: "Fondateur Mentorat One Vision & Stratège Commercial",
      badge: "👑 Mentor & Host Officiel",
      avatar: "./img/avatar-cyril.jpg",
      location: "Lyon, France",
      tenure: "1 an et 4 mois",
      stats: {
        missions: "120+",
        rating: "5.0/5",
        lives: "36",
        partners: "45"
      },
      about: "Ancien dirigeant d'agence et consultant en stratégie commerciale, j'aide les freelances et prestataires de services à sortir de la précarité du tarif horaire pour packager des accompagnements complets à plus de 2 000€ et structurer un pipeline commercial prévisible et serein.",
      skills: ["Packaging d'Offres 2000€+", "Closing & Négociation Éthique", "Stratégie Prix & TJM", "Prospection Grand Compte", "Scaling Solo"],
      socials: [
        { type: "linkedin", name: "LinkedIn Pro", handle: "linkedin.com/in/cyril-mentor", url: "https://www.linkedin.com/in/cyril-mentor", icon: "💼" },
        { type: "website", name: "Cabinet de Conseil", handle: "cyrild-conseil.fr", url: "https://cyrild-conseil.fr", icon: "🌐" },
        { type: "community", name: "Discord VIP One Vision", handle: "discord.gg/onevision-mentors", url: "https://discord.gg/onevision-mentors", icon: "💬" }
      ],
      links: {
        website: "https://cyrild-conseil.fr",
        websiteDisplay: "cyrild-conseil.fr (Cabinet Conseil)",
        linkedin: "https://linkedin.com/in/cyril-mentor",
        linkedinDisplay: "linkedin.com/in/cyril-mentor",
        community: "https://discord.gg/onevision-mentors",
        communityDisplay: "Discord VIP One Vision (Salon Mentors)"
      }
    },
    "florian": {
      id: "florian",
      name: "Florian L.",
      role: "Consultant Stratégie B2B & Prospection LinkedIn",
      subRole: "Growth Engineer & Spécialiste Acquisition Outbound",
      badge: "💼 Expert Prospection B2B",
      avatar: "./img/avatar-florian.jpg",
      location: "Bruxelles, Belgique",
      tenure: "6 mois",
      stats: {
        missions: "42",
        rating: "4.8/5",
        lives: "8",
        partners: "19"
      },
      about: "Spécialiste de l'acquisition de leads B2B sans publicité payante. Je crée des routines de prospection multicanale (LinkedIn, cold emailing ultra-personnalisé, réseaux d'affaires) pour remplir les agendas des prestataires et consultants avec des prospects qualifiés et prêts à acheter.",
      skills: ["Prospection LinkedIn Outbound", "Cold Emailing Ciblé", "Social Selling B2B", "Tunnels de Prise de RDV", "Scripts de Prospection"],
      socials: [
        { type: "linkedin", name: "LinkedIn Pro (15k abonnés)", handle: "linkedin.com/in/florian-b2b", url: "https://www.linkedin.com/in/florian-b2b", icon: "💼" },
        { type: "website", name: "Agence Growth B2B", handle: "florian-b2b-growth.com", url: "https://florian-b2b-growth.com", icon: "🌐" },
        { type: "community", name: "Canal Outbound Telegram", handle: "t.me/b2b_prospection_pro", url: "https://t.me/b2b_prospection_pro", icon: "💬" }
      ],
      links: {
        website: "https://florian-b2b-growth.com",
        websiteDisplay: "florian-b2b-growth.com (Agence B2B)",
        linkedin: "https://linkedin.com/in/florian-b2b",
        linkedinDisplay: "linkedin.com/in/florian-b2b (15k abonnés)",
        community: "https://t.me/b2b_prospection_pro",
        communityDisplay: "t.me/b2b_prospection_pro (Veille Outbound)"
      }
    },
    "sarah": {
      id: "sarah",
      name: "Sarah B.",
      role: "Copywriter & Formatrice en Vente Écrite",
      subRole: "Conception de Pages de Vente & Séquences Emailing",
      badge: "✒️ Copywriter Certifiée",
      avatar: "./img/avatar-sarah.jpg",
      location: "Bordeaux, France",
      tenure: "10 mois",
      stats: {
        missions: "64",
        rating: "4.9/5",
        lives: "11",
        partners: "31"
      },
      about: "J'aide les solopreneurs et infopreneurs à convertir des lecteurs passifs en acheteurs enthousiastes grâce à la précision des mots. Spécialisée en conception de pages de vente persuasives, séquences emails de lancement et newsletters à fort taux d'engagement.",
      skills: ["Pages de Vente Persuasives", "Emails de Lancement", "Newsletters Hebdomadaires", "Psychologie de Conversion", "Storytelling"],
      socials: [
        { type: "linkedin", name: "LinkedIn Pro", handle: "linkedin.com/in/sarah-copywriter", url: "https://www.linkedin.com/in/sarah-copywriter", icon: "💼" },
        { type: "website", name: "Études de Cas & Portfolio", handle: "sarahb-copywriting.com", url: "https://sarahb-copywriting.com", icon: "🌐" },
        { type: "community", name: "Newsletter Substack", handle: "sarahb.substack.com", url: "https://sarahb.substack.com", icon: "💬" }
      ],
      links: {
        website: "https://sarahb-copywriting.com",
        websiteDisplay: "sarahb-copywriting.com (Études de Cas)",
        linkedin: "https://linkedin.com/in/sarah-copywriter",
        linkedinDisplay: "linkedin.com/in/sarah-copywriter",
        community: "https://sarahb.substack.com",
        communityDisplay: "sarahb.substack.com (Newsletter Hebdo)"
      }
    },
    "alexandre": {
      id: "alexandre",
      name: "Alexandre L.",
      role: "Architecte No-Code & Automatisations IA",
      subRole: "Expert Make, Zapier & Intégrations Stripe / Notion",
      badge: "⚡ Expert No-Code & Workflows",
      avatar: "./img/avatar-marc.jpg",
      location: "Nantes, France",
      tenure: "5 mois",
      stats: {
        missions: "50+",
        rating: "4.9/5",
        lives: "6",
        partners: "24"
      },
      about: "J'élimine les tâches répétitives et chronophages des indépendants en concevant des architectures No-Code robustes reliant Stripe, Notion, Airtable, Make et l'IA. Libérez 10 à 15 heures par semaine sur votre gestion administrative et facturation.",
      skills: ["Make (Integromat)", "Zapier Avancé", "Intégrations IA & ChatGPT", "Stripe Customer Portal", "CRM Notion & Airtable"],
      socials: [
        { type: "linkedin", name: "LinkedIn Pro", handle: "linkedin.com/in/alexandre-nocode", url: "https://www.linkedin.com/in/alexandre-nocode", icon: "💼" },
        { type: "website", name: "Plateforme & Démos No-Code", handle: "alexandre-nocode.io", url: "https://alexandre-nocode.io", icon: "🌐" },
        { type: "community", name: "Club No-Code & IA", handle: "t.me/nocode_ia_france", url: "https://t.me/nocode_ia_france", icon: "💬" }
      ],
      links: {
        website: "https://alexandre-nocode.io",
        websiteDisplay: "alexandre-nocode.io (Démos en direct)",
        linkedin: "https://linkedin.com/in/alexandre-nocode",
        linkedinDisplay: "linkedin.com/in/alexandre-nocode",
        community: "https://t.me/nocode_ia_france",
        communityDisplay: "t.me/nocode_ia_france (Club No-Code)"
      }
    },
    "thomas": {
      id: "thomas",
      name: "Thomas R.",
      role: "Mentor Leadership & Posture Dirigeant",
      subRole: "Psychologie de la Haute Performance & Déblocage Mental",
      badge: "🧠 Coach Leadership & Mindset",
      avatar: "./img/avatar-cyril.jpg",
      location: "Genève, Suisse",
      tenure: "1 an et 1 mois",
      stats: {
        missions: "90+",
        rating: "5.0/5",
        lives: "28",
        partners: "40"
      },
      about: "J'accompagne les entrepreneurs ambitieux à briser leur plafond de verre psychologique, éliminer le syndrome de l'imposteur face aux clients d'envergure et maintenir une clarté stratégique sans s'épuiser. Animateur fidèle du rendez-vous du dimanche soir.",
      skills: ["Psychologie de la Haute Performance", "Posture Dirigeant", "Gestion du Doute & Résilience", "Vision Stratégique", "Déblocage Mental"],
      socials: [
        { type: "linkedin", name: "LinkedIn Pro", handle: "linkedin.com/in/thomas-leadership", url: "https://www.linkedin.com/in/thomas-leadership", icon: "💼" },
        { type: "website", name: "Cabinet Suisse Leadership", handle: "thomasr-leadership.ch", url: "https://thomasr-leadership.ch", icon: "🌐" },
        { type: "community", name: "Cercle Privé Mastermind", handle: "t.me/mastermind_mindset_pro", url: "https://t.me/mastermind_mindset_pro", icon: "💬" }
      ],
      links: {
        website: "https://thomasr-leadership.ch",
        websiteDisplay: "thomasr-leadership.ch (Cabinet Suisse)",
        linkedin: "https://linkedin.com/in/thomas-leadership",
        linkedinDisplay: "linkedin.com/in/thomas-leadership",
        community: "https://t.me/mastermind_mindset_pro",
        communityDisplay: "t.me/mastermind_mindset_pro (Cercle Privé)"
      }
    }
  };

  // Éléments de la page dédiée Profil Membre (#tab-profil-membre)
  const tabProfilMembre = document.getElementById('tab-profil-membre');
  const btnBackFromMemberProfile = document.getElementById('btnBackFromMemberProfile');
  const memberPageAvatar = document.getElementById('memberPageAvatar');
  const memberPageName = document.getElementById('memberPageName');
  const memberPageBadgeRole = document.getElementById('memberPageBadgeRole');
  const memberPageBadgeVerified = document.getElementById('memberPageBadgeVerified');
  const memberPageRoleSub = document.getElementById('memberPageRoleSub');
  const memberPageLocation = document.getElementById('memberPageLocation');
  const memberPageTenure = document.getElementById('memberPageTenure');
  const memberPageStatProjects = document.getElementById('memberPageStatProjects');
  const memberPageStatRating = document.getElementById('memberPageStatRating');
  const memberPageStatLives = document.getElementById('memberPageStatLives');
  const memberPageStatPartners = document.getElementById('memberPageStatPartners');
  const memberPageAbout = document.getElementById('memberPageAbout');
  const memberPageSkills = document.getElementById('memberPageSkills');
  const memberPageSocialsList = document.getElementById('memberPageSocialsList');
  const btnMemberPageSendDm = document.getElementById('btnMemberPageSendDm');
  const btnMemberPageCopyLink = document.getElementById('btnMemberPageCopyLink');
  const btnMemberPageQuickConnect = document.getElementById('btnMemberPageQuickConnect');

  // Navigation vers la page dédiée complète du membre (remplace l'ancienne pop-up)
  function openMemberProfilePage(memberId) {
    const member = membersDirectoryData[memberId] || membersDirectoryData["aurore"];
    activeProfileMember = member;

    // Fermer l'ancienne modale si elle était ouverte
    if (memberProfileModal) memberProfileModal.style.display = 'none';

    // Remplissage des informations du membre
    if (memberPageAvatar) {
      memberPageAvatar.src = member.avatar;
      memberPageAvatar.alt = member.name;
    }
    if (memberPageName) memberPageName.textContent = member.name;
    if (memberPageBadgeRole) memberPageBadgeRole.textContent = member.badge;
    if (memberPageRoleSub) memberPageRoleSub.textContent = member.subRole || member.role;
    if (memberPageLocation) memberPageLocation.textContent = `📍 ${member.location.split('•')[0].trim()}`;
    if (memberPageTenure) memberPageTenure.textContent = `🗓️ Membre actif depuis ${member.tenure || '6 mois'} (One Vision)`;

    // Remplissage des statistiques
    if (memberPageStatProjects) memberPageStatProjects.textContent = member.stats.missions;
    if (memberPageStatRating) memberPageStatRating.textContent = member.stats.rating;
    if (memberPageStatLives) memberPageStatLives.textContent = member.stats.lives;
    if (memberPageStatPartners) memberPageStatPartners.textContent = member.stats.partners || '28';

    // Ce que la personne fait dans la vie & Bio
    if (memberPageAbout) memberPageAbout.textContent = member.about;

    // Compétences clés
    if (memberPageSkills) {
      memberPageSkills.innerHTML = member.skills.map(s => `
        <span class="member-page-skill-badge">✓ ${escapeHtml(s)}</span>
      `).join('');
    }

    // Réseaux sociaux & liens directs cliquables (LinkedIn, Site, etc.)
    if (memberPageSocialsList) {
      const socList = member.socials || [
        { type: "linkedin", name: "LinkedIn Pro", handle: member.links.linkedinDisplay, url: member.links.linkedin, icon: "💼" },
        { type: "website", name: "Site Web & Portfolio", handle: member.links.websiteDisplay, url: member.links.website, icon: "🌐" },
        { type: "community", name: "Canal / Veille", handle: member.links.communityDisplay, url: member.links.community, icon: "💬" }
      ];

      memberPageSocialsList.innerHTML = socList.map(soc => `
        <a href="${soc.url}" target="_blank" rel="noopener noreferrer" class="member-social-link-btn" title="Ouvrir ${escapeHtml(soc.name)} (${escapeHtml(soc.url)})">
          <span class="social-btn-icon">${soc.icon}</span>
          <div class="social-btn-text">
            <strong>${escapeHtml(soc.name)}</strong>
            <span>${escapeHtml(soc.handle)}</span>
          </div>
          <span class="social-btn-arrow">↗</span>
        </a>
      `).join('');
    }

    // Activer le mode pleine page (identique à Mon Compte / Mon Abonnement)
    if (dashLayout) dashLayout.classList.add('account-mode');

    // Masquer les autres onglets et afficher la page du profil
    switchToTab('tab-profil-membre');
    showToast(`👤 Consultation du profil complet de ${member.name}`);
  }

  // Redirection de l'ancienne fonction modale vers la page complète
  function openMemberProfileModal(memberId) {
    openMemberProfilePage(memberId);
  }

  function closeMemberProfileModal() {
    if (memberProfileModal) memberProfileModal.style.display = 'none';
  }

  if (closeMemberProfileModalBtn) closeMemberProfileModalBtn.addEventListener('click', closeMemberProfileModal);
  if (backdropMemberProfile) backdropMemberProfile.addEventListener('click', closeMemberProfileModal);

  // Bouton Retour de la page membre -> renvoie directement à l'annuaire du réseau
  if (btnBackFromMemberProfile) {
    btnBackFromMemberProfile.addEventListener('click', () => {
      if (dashLayout) dashLayout.classList.remove('account-mode');
      switchToTab('tab-reseau');
      showToast("👥 Retour à l'annuaire des membres.");
    });
  }

  // Action Envoyer un message depuis la page profil
  if (btnMemberPageSendDm) {
    btnMemberPageSendDm.addEventListener('click', () => {
      if (!activeProfileMember) return;
      if (dashLayout) dashLayout.classList.remove('account-mode');
      switchToTab('tab-salons');
      openDirectMessageWithMember(activeProfileMember.name, activeProfileMember.role, activeProfileMember.avatar);
    });
  }

  // Action Proposer une collaboration depuis la page profil
  if (btnMemberPageQuickConnect) {
    btnMemberPageQuickConnect.addEventListener('click', () => {
      if (!activeProfileMember) return;
      if (dashLayout) dashLayout.classList.remove('account-mode');
      switchToTab('tab-salons');
      openDirectMessageWithMember(activeProfileMember.name, activeProfileMember.role, activeProfileMember.avatar);
    });
  }

  // Action Copier le profil depuis la page profil
  if (btnMemberPageCopyLink) {
    btnMemberPageCopyLink.addEventListener('click', () => {
      const name = activeProfileMember ? activeProfileMember.name : "Membre";
      const dummyUrl = `https://onevision.community/membres/${activeProfileMember ? activeProfileMember.id : 'profil'}`;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(dummyUrl).catch(() => {});
      }
      showToast(`🔗 Lien du profil de ${name} copié dans le presse-papier !`);
    });
  }

  // Gestion des clics pour ouvrir la page du profil dans l'annuaire
  document.addEventListener('click', (e) => {
    // 1. Clic sur le bouton direct "Voir le profil"
    const viewBtn = e.target.closest('.btn-view-profile');
    if (viewBtn) {
      const memberId = viewBtn.getAttribute('data-member-id');
      if (memberId) openMemberProfilePage(memberId);
      return;
    }

    // 2. Clic sur la carte membre elle-même (hors boutons de contact direct)
    const memberCard = e.target.closest('.member-profile-card');
    if (memberCard && !e.target.closest('button') && !e.target.closest('a')) {
      const memberId = memberCard.getAttribute('data-member-id');
      if (memberId) openMemberProfilePage(memberId);
      return;
    }
  });


  // Copier le lien du profil
  if (btnCopyProfileLink) {
    btnCopyProfileLink.addEventListener('click', () => {
      const name = activeProfileMember ? activeProfileMember.name : "Membre";
      const dummyUrl = `https://onevision.community/membres/${activeProfileMember ? activeProfileMember.id : 'profil'}`;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(dummyUrl).catch(() => {});
      }
      showToast(`🔗 Lien du profil de ${name} copié dans le presse-papier !`);
    });
  }

  // Envoyer un message privé depuis la modale de profil
  if (btnProfileSendDm) {
    btnProfileSendDm.addEventListener('click', () => {
      if (!activeProfileMember) return;
      closeMemberProfileModal();
      openDirectMessageWithMember(activeProfileMember.name, activeProfileMember.role, activeProfileMember.avatar);
    });
  }

  // 10. Gestion des Paramètres du Compte (Photo, Nom, Téléphone, Mot de passe)
  const accountSettingsForm = document.getElementById('accountSettingsForm');
  const settingsFullName = document.getElementById('settingsFullName');
  const settingsPhone = document.getElementById('settingsPhone');
  const settingsRole = document.getElementById('settingsRole');
  const settingsAvatarPreview = document.getElementById('settingsAvatarPreview');
  const settingsAvatarInput = document.getElementById('settingsAvatarInput');
  const btnUploadAvatar = document.getElementById('btnUploadAvatar');
  const btnResetAvatar = document.getElementById('btnResetAvatar');
  const quickAvatarItems = document.querySelectorAll('.quick-avatar-item');
  const btnCancelAccountSettings = document.getElementById('btnCancelAccountSettings');

  if (settingsFullName) settingsFullName.value = memberName;
  if (settingsPhone) settingsPhone.value = memberPhone;
  if (settingsAvatarPreview) settingsAvatarPreview.src = memberAvatar;

  // Sélection rapide parmi les avatars existants
  quickAvatarItems.forEach(item => {
    const src = item.getAttribute('data-src');
    if (src === memberAvatar) item.classList.add('active');
    item.addEventListener('click', () => {
      quickAvatarItems.forEach(i => i.classList.remove('active'));
      item.classList.add('active');
      if (settingsAvatarPreview) settingsAvatarPreview.src = src;
      if (dashAvatarEl) dashAvatarEl.src = src;
      const partSelfAv = document.getElementById('participantSelfAvatar');
      if (partSelfAv) partSelfAv.src = src;
      const uCamAv = document.getElementById('userCamAvatarImg');
      if (uCamAv) uCamAv.src = src;
      memberAvatar = src;
      localStorage.setItem('ov_member_avatar', src);
      showToast("✅ Modèle de photo appliqué avec succès.");
    });
  });

  // Lecture du fichier image local (Changer ma photo)
  if (settingsAvatarInput) {
    settingsAvatarInput.addEventListener('change', (e) => {
      const file = e.target.files && e.target.files[0];
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          showToast("La taille de l'image ne doit pas dépasser 5 Mo.");
          return;
        }
        const reader = new FileReader();
        reader.onload = (event) => {
          const newImgSrc = event.target.result;
          if (settingsAvatarPreview) settingsAvatarPreview.src = newImgSrc;
          if (dashAvatarEl) dashAvatarEl.src = newImgSrc;
          const partSelfAv = document.getElementById('participantSelfAvatar');
          if (partSelfAv) partSelfAv.src = newImgSrc;
          const uCamAv = document.getElementById('userCamAvatarImg');
          if (uCamAv) uCamAv.src = newImgSrc;
          memberAvatar = newImgSrc;
          localStorage.setItem('ov_member_avatar', newImgSrc);
          quickAvatarItems.forEach(i => i.classList.remove('active'));
          showToast("✅ Nouvelle photo de profil appliquée avec succès !");
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Réinitialisation photo
  if (btnResetAvatar) {
    btnResetAvatar.addEventListener('click', () => {
      const defaultAvatar = './img/avatar-maxime.jpg';
      if (settingsAvatarPreview) settingsAvatarPreview.src = defaultAvatar;
      if (dashAvatarEl) dashAvatarEl.src = defaultAvatar;
      const partSelfAv = document.getElementById('participantSelfAvatar');
      if (partSelfAv) partSelfAv.src = defaultAvatar;
      const uCamAv = document.getElementById('userCamAvatarImg');
      if (uCamAv) uCamAv.src = defaultAvatar;
      memberAvatar = defaultAvatar;
      localStorage.setItem('ov_member_avatar', defaultAvatar);
      quickAvatarItems.forEach(i => i.classList.toggle('active', i.getAttribute('data-src') === defaultAvatar));
      showToast("Photo de profil réinitialisée.");
    });
  }

  // Enregistrement des modifications du compte
  if (accountSettingsForm) {
    accountSettingsForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const newName = settingsFullName ? settingsFullName.value.trim() : '';
      const newPhone = settingsPhone ? settingsPhone.value.trim() : '';
      const newRole = settingsRole ? settingsRole.value.trim() : '';
      const newAvatar = settingsAvatarPreview ? settingsAvatarPreview.src : memberAvatar;
      const oldPwd = document.getElementById('settingsOldPassword')?.value;
      const newPwd = document.getElementById('settingsNewPassword')?.value;
      const confirmPwd = document.getElementById('settingsConfirmPassword')?.value;

      if (!newName) {
        showToast("Veuillez renseigner votre nom complet.");
        return;
      }

      if (newPwd) {
        if (newPwd.length < 8) {
          showToast("Le mot de passe doit comporter au moins 8 caractères.");
          return;
        }
        if (newPwd !== confirmPwd) {
          showToast("Les deux nouveaux mots de passe ne correspondent pas.");
          return;
        }
      }

      // Sauvegarde dans le localStorage
      localStorage.setItem('ov_member_name', newName);
      if (newPhone) localStorage.setItem('ov_member_phone', newPhone);
      if (newRole) localStorage.setItem('ov_member_role', newRole);
      localStorage.setItem('ov_member_avatar', newAvatar);

      memberName = newName;
      memberPhone = newPhone;
      memberAvatar = newAvatar;

      if (userNameEl) userNameEl.textContent = newName;
      if (dropdownUserTitle) dropdownUserTitle.textContent = newName;
      if (dashAvatarEl) dashAvatarEl.src = newAvatar;

      // Réinitialisation des champs mot de passe
      const oldPwdInput = document.getElementById('settingsOldPassword');
      const newPwdInput = document.getElementById('settingsNewPassword');
      const confirmPwdInput = document.getElementById('settingsConfirmPassword');
      if (oldPwdInput) oldPwdInput.value = '';
      if (newPwdInput) newPwdInput.value = '';
      if (confirmPwdInput) confirmPwdInput.value = '';

      showToast("✅ Vos paramètres de compte et profil ont été enregistrés avec succès !");
    });
  }

  if (btnCancelAccountSettings) {
    btnCancelAccountSettings.addEventListener('click', () => {
      enterDashboardMode();
    });
  }

  // 11. Modèle de Facture Officielle & Historique Dynamique
  function renderDashboardInvoices() {
    const listEl = document.getElementById('dashboardInvoicesList');
    const paymentValEl = document.getElementById('dashBillingPaymentMethod');
    const storedPaymentMethod = localStorage.getItem('ov_payment_method');
    if (paymentValEl && storedPaymentMethod) {
      paymentValEl.textContent = storedPaymentMethod;
    }

    if (!listEl) return;
    const invoices = getInvoicesRegistry();
    listEl.innerHTML = '';

    invoices.forEach(inv => {
      const item = document.createElement('div');
      item.className = 'invoice-item';
      item.innerHTML = `
        <div class="invoice-meta">
          <span class="invoice-icon">🧾</span>
          <div>
            <strong>Facture #${inv.invCode}</strong>
            <span class="invoice-date">${inv.dateLabel} • ${inv.amountTTC || '9,00 € TTC'}</span>
          </div>
        </div>
        <span class="badge-invoice-paid">Payée</span>
        <button type="button" class="btn-invoice-dl btnDownloadInvoiceItem" data-inv="${inv.invCode}">Télécharger</button>
      `;

      const dlBtn = item.querySelector('.btnDownloadInvoiceItem');
      if (dlBtn) {
        dlBtn.addEventListener('click', () => {
          triggerInvoiceDownload(inv);
        });
      }

      listEl.appendChild(item);
    });
  }

  // Rendu initial des factures dans le dashboard
  renderDashboardInvoices();

  // Téléchargement dernière facture
  const btnDownloadInvoice = document.getElementById('btnDownloadInvoice');
  if (btnDownloadInvoice) {
    btnDownloadInvoice.addEventListener('click', () => {
      const invoices = getInvoicesRegistry();
      const latest = invoices.length > 0 ? invoices[0] : null;
      if (latest) {
        triggerInvoiceDownload(latest);
      } else {
        triggerInvoiceDownload('OV-2026-01', '24 Septembre 2026');
      }
    });
  }

  // 12. Gestion Abonnement - Suspendre ou résilier mon abonnement
  const cancelBtn = document.getElementById('btnCancelSubscription');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      const isConfirmed = confirm("Êtes-vous certain de vouloir suspendre ou résilier votre abonnement de 9€/mois ? Vos accès à l'espace membre seront immédiatement désactivés.");
      if (isConfirmed) {
        localStorage.removeItem('ov_has_paid');
        showToast("Votre abonnement a été résilié avec succès. Redirection vers la page d'accueil...");
        setTimeout(() => {
          window.location.href = 'index.html';
        }, 1200);
      }
    });
  }

  // 11. Déconnexion
  const logoutBtn = document.getElementById('dashLogoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      localStorage.removeItem('ov_has_paid');
      localStorage.removeItem('ov_member_name');
      showToast("Déconnexion réussie. À très bientôt sur One Vision Community !");
      setTimeout(() => {
        window.location.href = 'index.html';
      }, 600);
    });
  }
}

// Fonction utilitaire de protection XSS
function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>'"]/g, 
    tag => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[tag] || tag)
  );
}

/* ==========================================================================
   8. NOTIFICATION TOAST
   ========================================================================== */
function showToast(message) {
  let toast = document.getElementById('communityToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'communityToast';
    toast.className = 'toast';
    document.body.appendChild(toast);
  }

  toast.innerHTML = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
      <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>
    <span>${message}</span>
  `;

  toast.classList.add('show');
  setTimeout(() => {
    toast.classList.remove('show');
  }, 5000);
}

/* ==========================================================================
   9. DÉFILEMENT FLUIDE & SUIVI DE L'ANCRE ACTIVE
   ========================================================================== */
function initSmoothScroll() {
  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach(link => {
    link.addEventListener('click', (e) => {
      const targetId = link.getAttribute('href');
      if (targetId === '#') return;

      const targetEl = document.querySelector(targetId);
      if (targetEl) {
        e.preventDefault();
        const headerOffset = 80;
        const elementPosition = targetEl.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    });
  });

  // IntersectionObserver pour les liens de navigation
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-link');

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.getAttribute('id');
        navLinks.forEach(link => {
          link.classList.toggle('active', link.getAttribute('href') === `#${id}`);
        });
      }
    });
  }, { threshold: 0.35 });

  sections.forEach(section => observer.observe(section));
}

/* ==========================================================================
   10. CARROUSEL DYNAMIQUE DES TÉMOIGNAGES (AUTO-ANIMATION & CONTRÔLES)
   ========================================================================== */
function initTestimonialsCarousel() {
  const carousel = document.getElementById('testimonialsCarousel');
  const track = document.getElementById('testimonialTrack');
  const prevBtn = document.getElementById('testimonialPrevBtn');
  const nextBtn = document.getElementById('testimonialNextBtn');
  const dotsContainer = document.getElementById('carouselDots');

  if (!carousel || !track || !prevBtn || !nextBtn || !dotsContainer) return;

  const cards = track.querySelectorAll('.testimonial-card');
  if (cards.length === 0) return;

  let currentIndex = 0;
  let autoplayTimer = null;
  const autoplayDelay = 4500;

  function getCardsPerView() {
    if (window.innerWidth <= 768) return 1;
    if (window.innerWidth <= 1024) return 2;
    return 3;
  }

  function getMaxIndex() {
    const perView = getCardsPerView();
    return Math.max(0, cards.length - perView);
  }

  function renderDots() {
    dotsContainer.innerHTML = '';
    const maxIndex = getMaxIndex();
    const count = maxIndex + 1;

    for (let i = 0; i < count; i++) {
      const dot = document.createElement('button');
      dot.className = `carousel-dot ${i === currentIndex ? 'active' : ''}`;
      dot.setAttribute('aria-label', `Aller au témoignage ${i + 1}`);
      dot.addEventListener('click', () => {
        currentIndex = i;
        updateCarousel();
        resetAutoplay();
      });
      dotsContainer.appendChild(dot);
    }
  }

  function updateCarousel() {
    const maxIndex = getMaxIndex();
    if (currentIndex > maxIndex) currentIndex = maxIndex;
    if (currentIndex < 0) currentIndex = 0;

    const firstCard = cards[0];
    const cardRect = firstCard.getBoundingClientRect();
    const gap = 24; // 1.5rem = 24px
    const step = cardRect.width + gap;

    track.style.transform = `translateX(-${currentIndex * step}px)`;

    // Update dots
    const dots = dotsContainer.querySelectorAll('.carousel-dot');
    dots.forEach((dot, index) => {
      dot.classList.toggle('active', index === currentIndex);
    });
  }

  function nextSlide() {
    const maxIndex = getMaxIndex();
    if (currentIndex >= maxIndex) {
      currentIndex = 0;
    } else {
      currentIndex++;
    }
    updateCarousel();
  }

  function prevSlide() {
    const maxIndex = getMaxIndex();
    if (currentIndex <= 0) {
      currentIndex = maxIndex;
    } else {
      currentIndex--;
    }
    updateCarousel();
  }

  function startAutoplay() {
    stopAutoplay();
    autoplayTimer = setInterval(nextSlide, autoplayDelay);
  }

  function stopAutoplay() {
    if (autoplayTimer) {
      clearInterval(autoplayTimer);
      autoplayTimer = null;
    }
  }

  function resetAutoplay() {
    stopAutoplay();
    startAutoplay();
  }

  prevBtn.addEventListener('click', (e) => {
    e.preventDefault();
    prevSlide();
    resetAutoplay();
  });

  nextBtn.addEventListener('click', (e) => {
    e.preventDefault();
    nextSlide();
    resetAutoplay();
  });

  // Pause on mouse hover or touch interaction
  carousel.addEventListener('mouseenter', stopAutoplay);
  carousel.addEventListener('mouseleave', startAutoplay);
  carousel.addEventListener('touchstart', stopAutoplay, { passive: true });
  carousel.addEventListener('touchend', startAutoplay, { passive: true });

  // Handle resize
  let resizeTimeout;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      renderDots();
      updateCarousel();
    }, 150);
  });

  // Initial setup
  renderDots();
  updateCarousel();
  startAutoplay();
}

/* ==========================================================================
   11. MODULE DE CRÉATION DE LIVE & MASTERMIND (CREER-LIVE.HTML)
   ========================================================================== */
function initCreateLivePage() {
  const form = document.getElementById('createLiveForm');
  if (!form) return;

  const liveTitleInput = document.getElementById('liveTitle');
  const liveCategorySelect = document.getElementById('liveCategoryTag');
  const liveDescTextarea = document.getElementById('liveDesc');
  const liveDateInput = document.getElementById('liveDate');
  const liveTimeSelect = document.getElementById('liveTime');
  const liveDurationSelect = document.getElementById('liveDuration');
  const liveTabSelect = document.getElementById('liveTabSection');
  const liveAuthorInput = document.getElementById('liveAuthor');
  const liveRoleInput = document.getElementById('liveRole');
  const liveResourcesInput = document.getElementById('liveResources');

  // Preview elements
  const previewTag = document.getElementById('previewTag');
  const previewDateText = document.getElementById('previewDateText');
  const previewTitle = document.getElementById('previewTitle');
  const previewDesc = document.getElementById('previewDesc');
  const previewAuthor = document.getElementById('previewAuthor');
  const previewRole = document.getElementById('previewRole');
  const previewAvatarImg = document.getElementById('previewAvatarImg');

  // Set default date to today + 2 days
  const today = new Date();
  today.setDate(today.getDate() + 2);
  const defaultDateStr = today.toISOString().split('T')[0];
  if (liveDateInput && !liveDateInput.value) {
    liveDateInput.value = defaultDateStr;
    liveDateInput.min = new Date().toISOString().split('T')[0];
  }

  function getSelectedRadioValue(name) {
    const radio = document.querySelector(`input[name="${name}"]:checked`);
    return radio ? radio.value : '';
  }

  function formatDisplayDate(dateVal, timeVal) {
    if (!dateVal) return `Prochainement • ${timeVal || '19h00'} (Session Membre)`;
    try {
      const parts = dateVal.split('-');
      const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
      const days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
      const months = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
      const dayName = days[d.getDay()];
      const dayNum = d.getDate();
      const monthName = months[d.getMonth()];
      return `${dayName} ${dayNum} ${monthName} • ${timeVal || '19h00'} (Session Membre)`;
    } catch (e) {
      return `${dateVal} • ${timeVal || '19h00'} (Session Membre)`;
    }
  }

  function updatePreview() {
    const selectedFormat = getSelectedRadioValue('liveFormat');
    const selectedCategory = liveCategorySelect ? liveCategorySelect.value : 'Retour d\'Expérience';
    const titleVal = (liveTitleInput && liveTitleInput.value.trim()) || 'Comment j\'ai signé 3 clients à 2 500€ en 30 jours sans prospection froide';
    const descVal = (liveDescTextarea && liveDescTextarea.value.trim()) || 'Partage d\'un cas réel sans langue de bois : mon offre d\'appel, la trame de cadrage utilisée et les erreurs évitées. Séance ouverte de questions-réponses avec les membres.';
    const authorVal = (liveAuthorInput && liveAuthorInput.value.trim()) || 'Katahana Désiré';
    const roleVal = (liveRoleInput && liveRoleInput.value.trim()) || 'Stratège Digital & Membre One Vision';
    const avatarVal = getSelectedRadioValue('liveAvatar') || './img/avatar-maxime.jpg';
    const dateVal = liveDateInput ? liveDateInput.value : '';
    const timeVal = liveTimeSelect ? liveTimeSelect.value : '19h00';

    if (previewTag) previewTag.textContent = selectedCategory || selectedFormat;
    if (previewTitle) previewTitle.textContent = titleVal;
    if (previewDesc) previewDesc.textContent = descVal;
    if (previewAuthor) previewAuthor.textContent = authorVal;
    if (previewRole) previewRole.textContent = roleVal;
    if (previewAvatarImg) previewAvatarImg.src = avatarVal;
    if (previewDateText) previewDateText.textContent = formatDisplayDate(dateVal, timeVal);
  }

  // Écouteurs pour la mise à jour instantanée de l'aperçu
  const inputs = [liveTitleInput, liveCategorySelect, liveDescTextarea, liveDateInput, liveTimeSelect, liveDurationSelect, liveAuthorInput, liveRoleInput];
  inputs.forEach(input => {
    if (input) {
      input.addEventListener('input', updatePreview);
      input.addEventListener('change', updatePreview);
    }
  });

  document.querySelectorAll('input[name="liveFormat"]').forEach(radio => {
    radio.addEventListener('change', updatePreview);
  });

  document.querySelectorAll('input[name="liveAvatar"]').forEach(radio => {
    radio.addEventListener('change', updatePreview);
  });

  // Bouton pour charger un exemple inspirant
  const btnExample = document.getElementById('btnFillExample');
  if (btnExample) {
    btnExample.addEventListener('click', () => {
      if (liveTitleInput) liveTitleInput.value = "Comment j'ai signé 3 clients à 2 500€ en 30 jours sans prospection froide";
      if (liveCategorySelect) liveCategorySelect.value = "Acquisition & Vente";
      if (liveDescTextarea) liveDescTextarea.value = "Dans ce retour d'expérience sans filtre, je vous partage ma transition d'offres vendues à l'heure vers un accompagnement packagé à forte valeur. Analyse complète de ma première page de capture, de ma trame d'appel et séance ouverte de Q&A.";
      if (liveTimeSelect) liveTimeSelect.value = "19h00";
      if (liveDurationSelect) liveDurationSelect.value = "1h00";
      if (liveResourcesInput) liveResourcesInput.value = "Trame de cadrage offerte (.PDF) + Grille d'appel";
      
      const formatRadio = document.querySelector('input[name="liveFormat"][value="Retour d\'Expérience"]');
      if (formatRadio) formatRadio.checked = true;

      updatePreview();
    });
  }

  // Soumission et enregistrement du Live / Mastermind
  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const title = liveTitleInput ? liveTitleInput.value.trim() : '';
    const desc = liveDescTextarea ? liveDescTextarea.value.trim() : '';
    const author = liveAuthorInput ? liveAuthorInput.value.trim() : '';
    const role = liveRoleInput ? liveRoleInput.value.trim() : '';
    const date = liveDateInput ? liveDateInput.value : '';
    const time = liveTimeSelect ? liveTimeSelect.value : '19h00';
    const duration = liveDurationSelect ? liveDurationSelect.value : '1h00';
    const category = liveTabSelect ? liveTabSelect.value : 'current';
    const tag = liveCategorySelect ? liveCategorySelect.value : 'Retour d\'Expérience';
    const format = getSelectedRadioValue('liveFormat') || 'Live Thématique';
    const avatar = getSelectedRadioValue('liveAvatar') || './img/avatar-maxime.jpg';
    const resources = liveResourcesInput ? liveResourcesInput.value.trim() : '';

    if (!title || !desc || !author) {
      alert('Veuillez remplir tous les champs obligatoires (Titre, Description, Nom).');
      return;
    }

    const sessionId = 'custom-live-' + Date.now();
    const initials = author.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'KD';
    const displayDate = formatDisplayDate(date, time);

    const newSession = {
      id: sessionId,
      format: format,
      tag: tag,
      category: category,
      date: displayDate,
      dateRaw: date,
      timeRaw: time,
      duration: duration,
      title: title,
      desc: desc,
      author: author,
      role: role,
      initials: initials,
      avatar: avatar,
      resources: resources,
      isCustom: true,
      createdAt: new Date().toISOString()
    };

    // Sauvegarde persistante dans localStorage & BDD SQLite
    try {
      const stored = localStorage.getItem('ov_community_custom_lives');
      const list = stored ? JSON.parse(stored) : [];
      list.unshift(newSession); // Placer en tête de liste
      localStorage.setItem('ov_community_custom_lives', JSON.stringify(list));

      if (isPhpEnvironment()) {
        const formData = new FormData(form);
        fetch('creer-live.php', {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(err => console.log('Live backend sync note:', err));
      }
    } catch (err) {
      console.error('Erreur sauvegarde live:', err);
    }

    // Affichage de l'écran de succès
    const gridEl = document.getElementById('createLiveGrid');
    const successScreenEl = document.getElementById('createLiveSuccessScreen');
    const recapBoxEl = document.getElementById('successRecapBox');
    const btnGoToCalendar = document.getElementById('btnGoToCalendar');

    if (recapBoxEl) {
      recapBoxEl.innerHTML = `
        <div class="recap-row">
          <span class="recap-label">Titre de la session :</span>
          <span class="recap-value">« ${newSession.title} »</span>
        </div>
        <div class="recap-row">
          <span class="recap-label">Date & Heure :</span>
          <span class="recap-value">${newSession.date} (${newSession.duration})</span>
        </div>
        <div class="recap-row">
          <span class="recap-label">Format :</span>
          <span class="recap-value">${newSession.format}</span>
        </div>
        <div class="recap-row">
          <span class="recap-label">Animateur :</span>
          <span class="recap-value">${newSession.author} (${newSession.role})</span>
        </div>
        <div class="recap-row">
          <span class="recap-label">Section du calendrier :</span>
          <span class="recap-value">${category === 'current' ? 'Cette semaine' : 'Semaine prochaine'}</span>
        </div>
      `;
    }

    if (btnGoToCalendar) {
      btnGoToCalendar.href = getAppUrl('dashboard.html') + `?tab=tab-calendrier&new_live=${newSession.id}`;
    }

    if (gridEl) gridEl.style.display = 'none';
    if (successScreenEl) {
      successScreenEl.style.display = 'block';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });

  // Bouton pour créer une autre session
  const btnCreateAnother = document.getElementById('btnCreateAnother');
  if (btnCreateAnother) {
    btnCreateAnother.addEventListener('click', () => {
      form.reset();
      updatePreview();
      const gridEl = document.getElementById('createLiveGrid');
      const successScreenEl = document.getElementById('createLiveSuccessScreen');
      if (gridEl) gridEl.style.display = 'grid';
      if (successScreenEl) successScreenEl.style.display = 'none';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Rendu initial de l'aperçu
  updatePreview();
}




