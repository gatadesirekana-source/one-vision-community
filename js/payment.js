/**
 * ONE VISION COMMUNITY — GESTION DE LA PAGE DE PAIEMENT & QR CODE DÉDIÉE
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Récupération des données de session (Query params ou sessionStorage)
  const urlParams = new URLSearchParams(window.location.search);
  let session = {};

  try {
    const stored = sessionStorage.getItem('ov_payment_session');
    if (stored) {
      session = JSON.parse(stored);
    }
  } catch (e) {
    session = {};
  }

  const orderNum = urlParams.get('order') || session.order_number || ('ORD-' + new Date().getFullYear() + '-' + Math.floor(100 + Math.random() * 900) + '-A8F2');
  const paymentId = urlParams.get('payment_id') || session.payment_id || '';
  const checkoutUrl = urlParams.get('url') || session.checkout_url || `https://pay.wave.com/c/ovc-${orderNum.toLowerCase()}`;
  const operator = urlParams.get('op') || session.operator || 'Wave';
  const country = urlParams.get('country') || session.country || "Côte d'Ivoire";
  const amount = urlParams.get('amount') || session.amount || '200';
  const currency = urlParams.get('currency') || session.currency || 'XOF';
  const phone = urlParams.get('phone') || session.phone || localStorage.getItem('ov_member_phone') || '+225 0777957337';
  const clientName = urlParams.get('name') || session.name || localStorage.getItem('ov_member_name') || 'Membre One Vision';

  // Éléments du DOM
  const paySummaryAmount = document.getElementById('paySummaryAmount');
  const summaryPriceDisplay = document.getElementById('summaryPriceDisplay');
  const paySummaryMethod = document.getElementById('paySummaryMethod');
  const paySummaryClient = document.getElementById('paySummaryClient');
  const paySummaryOrder = document.getElementById('paySummaryOrder');
  const qrHeaderBadge = document.getElementById('qrHeaderBadge');
  const qrTitle = document.getElementById('qrTitle');
  const paymentQrCanvas = document.getElementById('paymentQrCanvas');
  const paymentDirectLinkBtn = document.getElementById('paymentDirectLinkBtn');
  const instructionsList = document.getElementById('instructionsList');
  const paymentTimerText = document.getElementById('paymentTimerText');
  const paymentProgressBar = document.getElementById('paymentProgressBar');
  const paymentStatusText = document.getElementById('paymentStatusText');
  const sandboxValidateBtn = document.getElementById('sandboxValidateBtn');

  // Formatage Montant & Devise
  const formattedAmount = `${amount} ${currency}`;
  if (paySummaryAmount) paySummaryAmount.textContent = formattedAmount;
  if (summaryPriceDisplay) summaryPriceDisplay.textContent = formattedAmount;
  if (paySummaryClient) paySummaryClient.textContent = `${clientName} (${phone})`;
  if (paySummaryOrder) paySummaryOrder.textContent = orderNum;

  // Détection du mode opérateur
  const opLower = operator.toLowerCase();
  const isWave = opLower.includes('wave') || checkoutUrl.toLowerCase().includes('wave');
  const isOrange = opLower.includes('orange');
  const isMtn = opLower.includes('mtn');
  const isMoov = opLower.includes('moov');
  const isCard = opLower.includes('carte') || opLower.includes('card');

  // Rendu de l'icône et libellé de méthode
  function getMethodIconSvg(op) {
    if (isWave) {
      return `<svg class="pay-logo pay-logo-wave" viewBox="0 0 44 24" width="38" height="22" fill="none"><rect width="44" height="24" rx="4" fill="#1DC4FF"/><g transform="translate(3, 2.5) scale(0.8)"><path d="M12 2C9.5 2 7.5 4 7.5 6.5C7.5 7.7 8 8.8 8.7 9.6C8 10.9 7.5 12.6 7.5 14.6C7.5 18.5 9.5 21.6 12 21.6C14.5 21.6 16.5 18.5 16.5 14.6C16.5 12.6 16 10.9 15.3 9.6C16 8.8 16.5 7.7 16.5 6.5C16.5 4 14.5 2 12 2Z" fill="#FFFFFF"/><circle cx="10.5" cy="5.5" r="0.8" fill="#1DC4FF"/><circle cx="13.5" cy="5.5" r="0.8" fill="#1DC4FF"/><path d="M11 7L12 8.2L13 7Z" fill="#FF9900"/></g><text x="20" y="15.5" font-family="sans-serif" font-weight="900" font-size="9.5" fill="#FFFFFF" letter-spacing="-0.5">wave</text></svg>`;
    }
    if (isOrange) {
      return `<svg class="pay-logo pay-logo-orange" viewBox="0 0 44 24" width="38" height="22" fill="none"><rect width="44" height="24" rx="4" fill="#000000"/><rect x="3" y="3.5" width="17" height="17" rx="2" fill="#FF7900"/><text x="23" y="11.5" font-family="sans-serif" font-weight="900" font-size="6.5" fill="#FF7900">orange</text><text x="23" y="18" font-family="sans-serif" font-weight="800" font-size="5.5" fill="#FFFFFF">money</text></svg>`;
    }
    if (isMtn) {
      return `<svg class="pay-logo pay-logo-mtn" viewBox="0 0 44 24" width="38" height="22" fill="none"><rect width="44" height="24" rx="4" fill="#FFCC00"/><ellipse cx="11.5" cy="12" rx="8" ry="7.5" fill="#002F6C"/><text x="11.5" y="14.5" font-family="sans-serif" font-weight="900" font-size="6" fill="#FFCC00" text-anchor="middle">MTN</text><text x="22" y="15.5" font-family="sans-serif" font-weight="900" font-size="8.5" fill="#002F6C" letter-spacing="-0.5">MoMo</text></svg>`;
    }
    if (isMoov) {
      return `<svg class="pay-logo pay-logo-moov" viewBox="0 0 46 24" width="40" height="22" fill="none"><rect width="46" height="24" rx="4" fill="#005BAA"/><circle cx="10" cy="12" r="6" fill="#F37021"/><text x="10" y="15.2" font-family="sans-serif" font-weight="900" font-size="8" fill="#FFFFFF" text-anchor="middle">M</text><text x="18" y="13" font-family="sans-serif" font-weight="900" font-size="6.5" fill="#FFFFFF">moov</text><text x="18" y="19" font-family="sans-serif" font-weight="800" font-size="5" fill="#F37021">MONEY</text></svg>`;
    }
    return `<svg class="pay-logo pay-logo-cb" viewBox="0 0 44 24" width="38" height="22" fill="none"><rect width="44" height="24" rx="4" fill="#007953"/><text x="22" y="16.5" font-family="sans-serif" font-weight="900" font-size="11" fill="#FFFFFF" text-anchor="middle" font-style="italic">CB</text></svg>`;
  }

  if (paySummaryMethod) {
    paySummaryMethod.innerHTML = `
      ${getMethodIconSvg(operator)}
      <span>${operator} (${country})</span>
    `;
  }

  // Configuration personnalisée selon l'opérateur
  if (isWave) {
    if (qrHeaderBadge) qrHeaderBadge.textContent = "QR Code Wave Officiel";
    if (qrTitle) qrTitle.textContent = "Scannez avec votre application Wave";
    if (paymentDirectLinkBtn) {
      paymentDirectLinkBtn.href = checkoutUrl;
      paymentDirectLinkBtn.innerHTML = `<span>📱 Ouvrir directement dans l'application Wave →</span>`;
    }
    if (instructionsList) {
      instructionsList.innerHTML = `
        <li>Ouvrez l'application <strong>Wave</strong> sur votre téléphone.</li>
        <li>Appuyez sur <strong>Scanner</strong> et cadrez ce QR Code.</li>
        <li>Validez le débit de <strong>${formattedAmount}</strong> avec votre code PIN secret.</li>
      `;
    }
  } else if (isCard) {
    if (qrHeaderBadge) qrHeaderBadge.textContent = "Authentification 3D-Secure";
    if (qrTitle) qrTitle.textContent = "Scannez pour valider sur votre mobile";
    if (paymentDirectLinkBtn) {
      paymentDirectLinkBtn.href = checkoutUrl;
      paymentDirectLinkBtn.innerHTML = `<span>🔒 Ouvrir la session bancaire 3D Secure →</span>`;
    }
    if (instructionsList) {
      instructionsList.innerHTML = `
        <li>Scannez ce QR Code ou ouvrez la notification sur votre application bancaire.</li>
        <li>Confirmez l'opération 3D Secure pour One Vision Community.</li>
        <li>Votre accès sera activé automatiquement dès confirmation de votre banque.</li>
      `;
    }
  } else {
    // Orange / MTN / Moov
    if (qrHeaderBadge) qrHeaderBadge.textContent = `Paiement Mobile ${operator}`;
    if (qrTitle) qrTitle.textContent = `Scannez ou validez l'invite sur votre mobile`;
    if (paymentDirectLinkBtn) {
      paymentDirectLinkBtn.href = checkoutUrl;
      paymentDirectLinkBtn.innerHTML = `<span>📱 Ouvrir la passerelle de paiement ${operator} →</span>`;
    }
    if (instructionsList) {
      instructionsList.innerHTML = `
        <li>Une invite de validation a été transmise au <strong>${phone}</strong>.</li>
        <li>Déverrouillez votre téléphone et saisissez votre code PIN secret.</li>
        <li>Vous pouvez aussi scanner le QR Code ci-dessus avec votre caméra.</li>
      `;
    }
  }

  // 2. Rendu haute résolution du QR Code
  if (paymentQrCanvas) {
    paymentQrCanvas.innerHTML = '';
    const qrData = checkoutUrl || `https://onevision.community/checkout?order=${encodeURIComponent(orderNum)}`;

    let rendered = false;
    if (typeof QRCode !== 'undefined') {
      try {
        new QRCode(paymentQrCanvas, {
          text: qrData,
          width: 220,
          height: 220,
          colorDark: "#0f172a",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.M
        });
        rendered = true;
      } catch (err) {
        console.warn("Erreur QRCode canvas:", err);
      }
    }

    if (!rendered) {
      paymentQrCanvas.innerHTML = `
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=${encodeURIComponent(qrData)}" alt="QR Code de paiement" style="width:220px;height:220px;display:block;border-radius:10px;" />
      `;
    }
  }

  // 3. Gestion du Compte à Rebours (180 secondes = 3 minutes réelles)
  let secondsRemaining = 180;
  const totalSeconds = 180;

  function formatTime(s) {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
  }

  const timerInterval = setInterval(() => {
    secondsRemaining--;
    if (paymentTimerText) {
      paymentTimerText.textContent = `Temps restant pour valider : ${formatTime(Math.max(0, secondsRemaining))}`;
    }
    if (paymentProgressBar) {
      const pct = Math.max(0, (secondsRemaining / totalSeconds) * 100);
      paymentProgressBar.style.width = pct + '%';
    }

    if (secondsRemaining <= 0) {
      clearInterval(timerInterval);
      clearInterval(pollInterval);
      if (paymentStatusText) {
        paymentStatusText.innerHTML = `<span style="color:#dc2626; font-weight:700;">⏱️ Le délai d'attente a expiré. Aucun prélèvement n'a été effectué.</span>`;
      }
    }
  }, 1000);

  // 4. Polling actif du statut de la transaction (Toutes les 2.5 secondes)
  const isPhp = window.location.pathname.endsWith('.php');
  const pollEndpoint = isPhp
    ? `api/check-order-status.php?order=${encodeURIComponent(orderNum)}&payment_id=${encodeURIComponent(paymentId)}`
    : `/api/check-payment-status?order=${encodeURIComponent(orderNum)}&payment_id=${encodeURIComponent(paymentId)}`;

  let isRedirecting = false;

  function triggerSuccessRedirect() {
    if (isRedirecting) return;
    isRedirecting = true;
    clearInterval(timerInterval);
    clearInterval(pollInterval);

    localStorage.setItem('ov_has_paid', 'true');
    localStorage.setItem('ov_member_name', clientName);
    localStorage.setItem('ov_current_order', orderNum);

    if (paymentStatusText) {
      paymentStatusText.innerHTML = `<span style="color:#16a34a; font-weight:800; font-size:1rem;">✅ Paiement confirmé avec succès ! Redirection en cours...</span>`;
    }
    if (paymentProgressBar) {
      paymentProgressBar.style.width = '100%';
      paymentProgressBar.style.background = '#16a34a';
    }

    setTimeout(() => {
      const successTarget = isPhp ? 'checkout-success.php' : 'checkout-success.html';
      window.location.href = `${successTarget}?order=${encodeURIComponent(orderNum)}&amount=${encodeURIComponent(formattedAmount)}`;
    }, 1200);
  }

  const pollInterval = setInterval(() => {
    if (isRedirecting) return;

    fetch(pollEndpoint)
      .then(res => res.json())
      .then(data => {
        if (data && (data.status === 'paid' || data.status === 'success' || data.success === true && data.status !== 'pending')) {
          triggerSuccessRedirect();
        } else if (data && data.status === 'failed') {
          clearInterval(timerInterval);
          clearInterval(pollInterval);
          if (paymentStatusText) {
            paymentStatusText.innerHTML = `<span style="color:#dc2626; font-weight:700;">❌ Paiement refusé ou rejeté.</span>`;
          }
        }
      })
      .catch(err => {
        // En attente, silencieux
      });
  }, 2500);

  // 5. Bouton Simulation Sandbox
  if (sandboxValidateBtn) {
    sandboxValidateBtn.addEventListener('click', () => {
      triggerSuccessRedirect();
    });
  }
});
