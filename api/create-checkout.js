/**
 * ONE VISION COMMUNITY — SASPAY SERVERLESS CHECKOUT HANDLER (VERCEL / NODE.JS)
 * 
 * Permet d'initialiser une session de paiement SasPay officielle côté serveur.
 * La clé API 'sk_live_...' reste strictement sécurisée dans ce module backend.
 */

const crypto = require('crypto');

const SASPAY_API_KEY = process.env.SASPAY_API_KEY || 'sk_live_Ecoz4FOTUB21-kWstdXwE5UFuAyo07f4SeAMQPwVh8U';
const SASPAY_BASE_URL = 'https://api.saspay.me/api/v1';

module.exports = async function handler(req, res) {
  // En-têtes CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({
      success: false,
      error: 'Méthode non autorisée. Seul POST est accepté.'
    });
  }

  try {
    let body = req.body;
    if (typeof body === 'string') {
      try {
        body = JSON.parse(body);
      } catch (e) {
        body = {};
      }
    }
    body = body || {};

    const plan = (body.plan || 'membre').toLowerCase();
    const periodicite = (body.periodicite || 'mensuel').toLowerCase();

    // Détermination stricte des tarifs One Vision Community
    let amount = '9.00';
    let description = 'One Vision Community — Formule Membre (Mensuel)';

    if (plan === 'animateur') {
      if (periodicite === 'annuel') {
        amount = '239.00';
        description = 'One Vision Community — Formule Animateur & Expert (Annuel)';
      } else {
        amount = '24.00';
        description = 'One Vision Community — Formule Animateur & Expert (Mensuel)';
      }
    } else {
      if (periodicite === 'annuel') {
        amount = '89.00';
        description = 'One Vision Community — Formule Membre (Annuel)';
      } else {
        amount = '9.00';
        description = 'One Vision Community — Formule Membre (Mensuel)';
      }
    }

    const host = req.headers['x-forwarded-host'] || req.headers.host || 'one-vision-community.vercel.app';
    const proto = req.headers['x-forwarded-proto'] || 'https';
    const origin = `${proto}://${host}`;
    const reference = 'PAY-SAS-' + Date.now() + '-' + crypto.randomBytes(3).toString('hex').toUpperCase();

    // URL de retour client après règlement sur SasPay
    const returnUrl = `${origin}/paiement-retour.html?ref=${encodeURIComponent(reference)}&plan=${encodeURIComponent(plan)}&periodicite=${encodeURIComponent(periodicite)}`;

    const payload = {
      amount: amount,
      currency: 'EUR',
      description: description,
      customer_email: body.email || 'contact@onevisioncommunity.fr',
      customer_name: body.name || 'Membre One Vision',
      customer_phone: body.phone || '',
      return_url: returnUrl,
      metadata: {
        reference: reference,
        plan: plan,
        periodicite: periodicite,
        timestamp: Date.now()
      }
    };

    const response = await fetch(`${SASPAY_BASE_URL}/checkout-sessions/`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${SASPAY_API_KEY}`,
        'X-Api-Key': SASPAY_API_KEY
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json();

    const checkoutData = data.data || data;
    const checkoutUrl = checkoutData.checkout_url;
    const sessionId = checkoutData.id || checkoutData.session_id;

    if (!checkoutUrl) {
      return res.status(response.status || 500).json({
        success: false,
        error: data.message || data.error || 'Erreur lors de la création de la session SasPay'
      });
    }

    return res.status(200).json({
      success: true,
      reference: reference,
      session_id: sessionId,
      checkout_url: checkoutUrl,
      amount: amount,
      currency: 'EUR',
      plan: plan,
      periodicite: periodicite
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      error: 'Erreur interne du serveur : ' + (error.message || String(error))
    });
  }
};
