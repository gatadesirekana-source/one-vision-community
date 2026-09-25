// api/initiate-payment.js — Vercel Serverless Function pour SasaPay
module.exports = async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, error: 'Méthode non autorisée. Utilisez POST.' });
  }

  try {
    let body = req.body;
    if (typeof body === 'string') {
      try {
        body = JSON.parse(body);
      } catch (e) {
        // Fallback x-www-form-urlencoded
        const params = new URLSearchParams(body);
        body = Object.fromEntries(params.entries());
      }
    }
    body = body || {};

    const apiKey = process.env.SASAPAY_API_KEY;
    if (!apiKey) {
      return res.status(500).json({ success: false, error: 'Configuration manquante: SASAPAY_API_KEY non définie sur le serveur.' });
    }
    const apiUrl = (process.env.SASAPAY_API_URL || "https://api.saspay.me/api/v1").replace(/\/+$/, '');

    const name = (body.checkoutName || body.name || 'Client One Vision').trim();
    const email = (body.checkoutEmail || body.email || 'contact@onevision.community').trim();
    const rawPhone = (body.momoPhone || body.phone || body.customer_phone || '+2250777957337').trim();
    const country = (body.momoCountry || body.country || 'Côte d\'Ivoire').trim();
    const operator = (body.momoOperator || body.operator || 'Wave').trim();

    // SasaPay impose un montant minimum strict de 200 XOF (environ 0,30 €)
    let amount = parseFloat(body.momoAmount || process.env.SASAPAY_TEST_OVERRIDE_AMOUNT || 200);
    if (isNaN(amount) || amount < 200) {
      amount = 200;
    }
    let currency = body.momoCurrency || process.env.SASAPAY_TEST_OVERRIDE_CURRENCY || 'XOF';

    // Extraction prénom et nom
    const nameParts = name.split(/\s+/);
    const firstName = nameParts[0] || 'Client';
    const lastName = nameParts.slice(1).join(' ') || 'OneVision';

    // Résolution pays & réseau officiel SasaPay
    const opLower = operator.toLowerCase();
    const cLower = country.toLowerCase();

    let countryIso = 'CI';
    let network = 'wave_ci';

    if (opLower.includes('wave')) {
      if (cLower.includes('senegal') || cLower.includes('sénégal') || cLower === 'sn') {
        countryIso = 'SN';
        network = 'wave_sn';
      } else {
        countryIso = 'CI';
        network = 'wave_ci';
      }
      currency = 'XOF';
    } else if (cLower.includes('senegal') || cLower.includes('sénégal') || cLower === 'sn') {
      countryIso = 'SN';
      if (opLower.includes('orange')) network = 'orange_sn';
      else network = 'freemoney_sn';
      currency = 'XOF';
    } else if (cLower.includes('cameroun') || cLower === 'cm') {
      countryIso = 'CM';
      network = opLower.includes('orange') ? 'orange_cm' : 'mtn_cm';
      currency = 'XAF';
    } else if (cLower.includes('benin') || cLower.includes('bénin') || cLower === 'bj') {
      countryIso = 'BJ';
      if (opLower.includes('moov')) network = 'moov_bj';
      else if (opLower.includes('celtiis')) network = 'celtiis_bj';
      else network = 'mtn_bj';
      currency = 'XOF';
    } else {
      countryIso = 'CI';
      if (opLower.includes('orange')) network = 'orange_ci';
      else if (opLower.includes('moov')) network = 'moov_ci';
      else network = 'mtn_ci';
      currency = 'XOF';
    }

    const orderNumber = 'ORD-' + new Date().getFullYear() + '-' + Math.floor(100 + Math.random() * 900) + '-' + Math.random().toString(36).substring(2, 6).toUpperCase();

    let cleanPhone = rawPhone.replace(/[^\d+]/g, '');
    if (!cleanPhone.startsWith('+')) {
      if (countryIso === 'CI') {
        cleanPhone = cleanPhone.startsWith('225') ? ('+' + cleanPhone) : ('+225' + cleanPhone);
      } else if (countryIso === 'SN') {
        cleanPhone = cleanPhone.startsWith('221') ? ('+' + cleanPhone) : ('+221' + cleanPhone);
      } else if (countryIso === 'CM') {
        cleanPhone = cleanPhone.startsWith('237') ? ('+' + cleanPhone) : ('+237' + cleanPhone);
      } else if (countryIso === 'BJ') {
        cleanPhone = cleanPhone.startsWith('229') ? ('+' + cleanPhone) : ('+229' + cleanPhone);
      } else {
        cleanPhone = '+' + cleanPhone;
      }
    }

    const payload = {
      amount: amount,
      currency: currency,
      country: countryIso,
      network: network,
      description: `Adhésion One Vision Community (${amount} ${currency})`,
      customer: {
        first_name: firstName,
        last_name: lastName,
        phone: cleanPhone || '+2250777957337',
        email: email
      },
      metadata: {
        order_number: orderNumber,
        source: 'one-vision-community'
      }
    };

    const sasaRes = await fetch(`${apiUrl}/payments/softpay/`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const sasaData = await sasaRes.json();

    if (sasaRes.ok && (sasaData.data || sasaData.checkout_url || sasaData.id)) {
      const data = sasaData.data || sasaData;
      return res.status(200).json({
        success: true,
        status: 'pending',
        order_number: orderNumber,
        payment_id: data.id || data.payment_id || '',
        checkout_url: data.checkout_url || '',
        message: 'Demande de paiement transmise. En attente de confirmation sur votre téléphone.'
      });
    }

    let errorDetail = 'Erreur SasaPay (' + sasaRes.status + ')';
    if (sasaData && sasaData.error) {
      if (typeof sasaData.error === 'string') errorDetail = sasaData.error;
      else if (sasaData.error.message) errorDetail = sasaData.error.message;
      else if (sasaData.error.detail) errorDetail = sasaData.error.detail;
      else errorDetail = JSON.stringify(sasaData.error);
    }

    return res.status(400).json({
      success: false,
      error: errorDetail,
      details: sasaData
    });

  } catch (err) {
    return res.status(500).json({
      success: false,
      error: 'Erreur serveur: ' + (err.message || 'Impossible de contacter SasaPay')
    });
  }
};
