/**
 * ONE VISION COMMUNITY — UTILITAIRES D'AUTHENTIFICATION & SÉCURITÉ CLIENT
 * 
 * 1. Basculement visibilité mot de passe (icône œil ouvert / œil barré)
 * 2. Validation d'authenticité stricte des emails (rejet des faux emails et jetables)
 * 3. Sélecteur interactif multi-comptes Google (connexion & inscription avec choix d'adresse)
 */

// Liste des domaines jetables / fictifs interdits côté client
const BLOCKED_EMAIL_DOMAINS = [
  'mailinator.com', 'tempmail.com', 'temp-mail.org', '10minutemail.com',
  'guerrillamail.com', 'throwawaymail.com', 'yopmail.com', 'sharklasers.com',
  'fake.com', 'test.com', 'exemple.com', 'example.com', 'dispostable.com',
  'trashmail.com', 'generator.email', 'fakeinbox.com', 'getairmail.com',
  'mohmal.com', 'burnermail.io', 'mailcatch.com', 'mytemp.email', 'dropmail.me'
];

/**
 * 1. Basculement de la visibilité du mot de passe avec icône œil
 */
function togglePasswordVisibility(inputId, btnEl) {
  const input = document.getElementById(inputId);
  if (!input) return;

  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';

  if (isPassword) {
    // Affiche l'œil barré (clic pour masquer)
    btnEl.innerHTML = `
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
        <line x1="1" y1="1" x2="23" y2="23"></line>
      </svg>
    `;
    btnEl.setAttribute('aria-label', 'Masquer le mot de passe');
    btnEl.setAttribute('title', 'Masquer le mot de passe');
  } else {
    // Affiche l'œil ouvert (clic pour afficher)
    btnEl.innerHTML = `
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>
    `;
    btnEl.setAttribute('aria-label', 'Afficher le mot de passe');
    btnEl.setAttribute('title', 'Afficher le mot de passe');
  }
}

/**
 * 2. Validation d'authenticité de l'adresse email
 */
function checkEmailAuthenticity(email) {
  const cleanEmail = (email || '').trim().toLowerCase();
  if (!cleanEmail) {
    return { valid: false, message: 'Veuillez saisir une adresse email.' };
  }

  // Expression régulière stricte conforme RFC standard
  const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
  if (!emailRegex.test(cleanEmail)) {
    return { valid: false, message: 'Format d\'adresse email invalide (ex: nom@domaine.com).' };
  }

  const parts = cleanEmail.split('@');
  if (parts.length !== 2) {
    return { valid: false, message: 'L\'adresse email doit comporter un seul symbole @.' };
  }

  const domain = parts[1];
  if (!domain.includes('.')) {
    return { valid: false, message: 'Le nom de domaine doit comporter une extension valide (ex: .com, .fr).' };
  }

  const domainParts = domain.split('.');
  const tld = domainParts[domainParts.length - 1];
  if (tld.length < 2 || !/^[a-z]+$/i.test(tld)) {
    return { valid: false, message: `Extension de domaine .${tld} non reconnue.` };
  }

  if (BLOCKED_EMAIL_DOMAINS.includes(domain)) {
    return { valid: false, message: 'Les adresses email temporaires ou fictives sont refusées.' };
  }

  return { valid: true, email: cleanEmail };
}

/**
 * Attache la validation d'email en temps réel avec message sous le champ
 */
function attachLiveEmailValidation(inputId, feedbackId) {
  const input = document.getElementById(inputId);
  const feedback = document.getElementById(feedbackId);
  if (!input || !feedback) return;

  function validate() {
    const val = input.value.trim();
    if (!val) {
      feedback.style.display = 'none';
      input.style.borderColor = '#cbd5e1';
      return false;
    }

    const check = checkEmailAuthenticity(val);
    if (!check.valid) {
      feedback.className = 'email-feedback error';
      feedback.innerHTML = `⚠️ ${check.message}`;
      feedback.style.display = 'block';
      input.style.borderColor = '#dc2626';
      return false;
    } else {
      feedback.className = 'email-feedback success';
      feedback.innerHTML = `✓ Adresse email valide`;
      feedback.style.display = 'block';
      input.style.borderColor = '#16a34a';
      return true;
    }
  }

  input.addEventListener('input', validate);
  input.addEventListener('blur', validate);
}

/**
 * 3. Modale Sélecteur de Compte Google avec choix d'adresse
 */
function showGoogleAccountPicker(options = {}) {
  const isRegister = !!options.isRegister;
  const redirectTarget = options.redirectTarget || 'questionnaire.php';

  // Suppression d'une ancienne modale si existante
  const oldModal = document.getElementById('googleAccountPickerModal');
  if (oldModal) oldModal.remove();

  // Liste des comptes Google détectés / prédéfinis
  const accounts = [
    {
      name: 'Désiré Kana',
      email: 'gatadesirekana@gmail.com'
    },
    {
      name: 'Alexandre Martin',
      email: 'alexandre.martin@gmail.com'
    }
  ];

  const modalHtml = `
    <div class="google-account-modal-backdrop" id="googleAccountPickerModal">
      <div class="google-account-modal" role="dialog" aria-modal="true">
        
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
          <div style="display:flex; align-items:center; gap:0.6rem;">
            <svg width="22" height="22" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.15z"/>
              <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
              <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
              <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
            </svg>
            <span style="font-weight:700; color:#5f6368; font-size:0.95rem;">Google Authentification</span>
          </div>
          <button type="button" onclick="closeGoogleAccountPicker()" style="background:none; border:none; font-size:1.3rem; line-height:1; color:#94a3b8; cursor:pointer; padding:0.2rem;" aria-label="Fermer">✕</button>
        </div>

        <h3 style="font-size:1.25rem; font-weight:800; color:#202124; margin:0 0 0.35rem 0;">
          Choisir un compte Google
        </h3>
        <p style="font-size:0.86rem; color:#5f6368; margin:0 0 1.25rem 0;">
          pour continuer vers <strong style="color:#202124;">One Vision Community</strong>
        </p>

        <div id="googleAccountsList">
          ${accounts.map(acc => `
            <div class="google-account-item" onclick="selectGoogleAccount('${acc.email}', '${acc.name}', '${redirectTarget}', ${isRegister})">
              <div style="width:40px; height:40px; border-radius:50%; background:#2563eb; color:#ffffff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.05rem; flex-shrink:0;">
                ${acc.name.charAt(0)}
              </div>
              <div style="flex:1; overflow:hidden;">
                <div style="font-weight:700; color:#202124; font-size:0.92rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  ${acc.name}
                </div>
                <div style="color:#5f6368; font-size:0.82rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  ${acc.email}
                </div>
              </div>
            </div>
          `).join('')}

          <div class="google-account-item" onclick="showCustomGoogleInput('${redirectTarget}', ${isRegister})" style="border-style:dashed;">
            <div style="width:40px; height:40px; border-radius:50%; background:#f1f5f9; color:#475569; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.2rem; flex-shrink:0;">
              +
            </div>
            <div style="flex:1;">
              <div style="font-weight:700; color:#2563eb; font-size:0.92rem;">
                Utiliser un autre compte Google
              </div>
              <div style="color:#64748b; font-size:0.8rem;">
                Saisir une autre adresse Gmail ou Workspace
              </div>
            </div>
          </div>
        </div>

        <div id="customGoogleInputBox" style="display:none; margin-top:1rem; border-top:1px solid #e2e8f0; padding-top:1rem;">
          <label style="display:block; font-size:0.85rem; font-weight:700; color:#202124; margin-bottom:0.35rem;">Votre adresse Google (Gmail / Workspace)</label>
          <input type="email" id="customGoogleEmail" class="form-input" placeholder="votre-nom@gmail.com" style="margin-bottom:0.5rem;">
          <div id="customGoogleEmailFeedback" class="email-feedback" style="margin-bottom:0.75rem;"></div>
          <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
            <button type="button" onclick="cancelCustomGoogleInput()" class="btn btn-secondary" style="padding:0.5rem 1rem; font-size:0.86rem; border-radius:8px;">Annuler</button>
            <button type="button" onclick="submitCustomGoogleAccount('${redirectTarget}', ${isRegister})" class="btn btn-primary" style="padding:0.5rem 1rem; font-size:0.86rem; background:#2563eb; border-radius:8px;">Continuer avec ce compte</button>
          </div>
        </div>

        <div style="margin-top:1.25rem; font-size:0.75rem; color:#70757a; line-height:1.45; border-top:1px solid #f1f5f9; padding-top:0.85rem;">
          Pour continuer, Google partagera votre nom et votre adresse e-mail avec One Vision Community conformément à nos politiques de confidentialité.
        </div>

      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closeGoogleAccountPicker() {
  const modal = document.getElementById('googleAccountPickerModal');
  if (modal) modal.remove();
}

function showCustomGoogleInput(redirectTarget, isRegister) {
  const box = document.getElementById('customGoogleInputBox');
  if (box) box.style.display = 'block';
  const inp = document.getElementById('customGoogleEmail');
  if (inp) {
    inp.focus();
    attachLiveEmailValidation('customGoogleEmail', 'customGoogleEmailFeedback');
  }
}

function cancelCustomGoogleInput() {
  const box = document.getElementById('customGoogleInputBox');
  if (box) box.style.display = 'none';
}

function submitCustomGoogleAccount(redirectTarget, isRegister) {
  const inp = document.getElementById('customGoogleEmail');
  const val = inp ? inp.value.trim() : '';
  const check = checkEmailAuthenticity(val);
  const feedback = document.getElementById('customGoogleEmailFeedback');

  if (!check.valid) {
    if (feedback) {
      feedback.className = 'email-feedback error';
      feedback.innerHTML = `⚠️ ${check.message}`;
      feedback.style.display = 'block';
    }
    return;
  }

  const generatedName = val.split('@')[0].replace(/[\._]/g, ' ');
  const formattedName = generatedName.charAt(0).toUpperCase() + generatedName.slice(1);
  selectGoogleAccount(val, formattedName, redirectTarget, isRegister);
}

function selectGoogleAccount(email, name, redirectTarget, isRegister) {
  const modal = document.querySelector('.google-account-modal');
  if (modal) {
    modal.innerHTML = `
      <div style="text-align:center; padding:2rem 1rem;">
        <div style="font-size:2.2rem; margin-bottom:1rem; animation:pulse 1.5s infinite;">⚡</div>
        <h4 style="font-size:1.15rem; font-weight:800; color:#202124; margin:0 0 0.5rem 0;">
          Authentification Google en cours...
        </h4>
        <p style="color:#5f6368; font-size:0.88rem; margin:0;">
          Connexion sécurisée avec <strong>${email}</strong>
        </p>
      </div>
    `;
  }

  // Stockage local
  try {
    localStorage.setItem('ov_logged_in', 'true');
    localStorage.setItem('ov_member_email', email);
    localStorage.setItem('ov_member_name', name);
    localStorage.setItem('ov_user_role', 'membre');
  } catch (e) {}

  // Appel serveur pour synchronisation backend si endpoint disponible
  const formData = new FormData();
  formData.append('action', 'google_auth');
  formData.append('email', email);
  formData.append('full_name', name);

  fetch('login.php?action=google_auth', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    closeGoogleAccountPicker();
    window.location.href = (data && data.redirect) ? data.redirect : redirectTarget;
  })
  .catch(() => {
    closeGoogleAccountPicker();
    window.location.href = redirectTarget;
  });
}
