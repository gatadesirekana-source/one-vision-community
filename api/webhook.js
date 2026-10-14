/**
 * ONE VISION COMMUNITY — SASPAY SERVERLESS WEBHOOK HANDLER (VERCEL / NODE.JS)
 * 
 * Traitement sécurisé des événements webhook envoyés par SasPay :
 * - Vérification cryptographique de la signature HMAC SHA-256
 * - Protection anti-rejeu (tolérance maximale de 300 secondes)
 * - Traitement temps-constant (timing attack safe)
 */

const crypto = require('crypto');

const SASPAY_WEBHOOK_SECRET = process.env.SASPAY_WEBHOOK_SECRET || 'f54886cb396f31a266eca274a15191f82cf63ed6cffc4d80e27ddc0ae51ff9ea';

module.exports = async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Méthode non autorisée. Utilisez POST.' });
  }

  try {
    const rawBody = typeof req.body === 'string' ? req.body : JSON.stringify(req.body);

    const sigHeader = req.headers['x-saspay-signature'] || req.headers['x-webhook-signature'] || '';
    const timeHeader = req.headers['x-saspay-timestamp'] || req.headers['x-webhook-timestamp'] || '';

    let timestamp = parseInt(timeHeader, 10);
    let signature = sigHeader;

    if (sigHeader.includes('t=') && sigHeader.includes('v1=')) {
      const parts = sigHeader.split(',');
      for (const p of parts) {
        const [k, v] = p.split('=');
        if (k && k.trim() === 't') timestamp = parseInt(v.trim(), 10);
        if (k && k.trim() === 'v1') signature = v.trim();
      }
    }

    if (!timestamp || isNaN(timestamp) || !signature) {
      return res.status(400).json({ error: 'Signature ou horodatage manquant.' });
    }

    // 1. Contrôle d'âge (max 300s)
    const now = Math.floor(Date.now() / 1000);
    if (Math.abs(now - timestamp) > 300) {
      return res.status(400).json({ error: 'Événement expiré (> 300s).' });
    }

    // 2. Calcul du HMAC SHA-256
    const signedPayload = `${timestamp}.${rawBody}`;
    const expectedSignature = crypto
      .createHmac('sha256', SASPAY_WEBHOOK_SECRET)
      .update(signedPayload)
      .digest('hex');

    // 3. Comparaison sécurisée timing-safe
    const sigBuf = Buffer.from(signature, 'hex');
    const expBuf = Buffer.from(expectedSignature, 'hex');

    if (sigBuf.length !== expBuf.length || !crypto.timingSafeEqual(sigBuf, expBuf)) {
      return res.status(401).json({ error: 'Signature invalide.' });
    }

    const payload = typeof req.body === 'object' ? req.body : JSON.parse(rawBody);
    console.log('[SasPay Webhook] Événement validé avec succès:', payload.event || payload.type);

    return res.status(200).json({
      status: 'success',
      received: true,
      event: payload.event || payload.type
    });
  } catch (error) {
    return res.status(500).json({
      error: 'Erreur interne : ' + (error.message || String(error))
    });
  }
};
