/**
 * ONE VISION COMMUNITY — GOOGLE AUTH SERVERLESS ENDPOINT (VERCEL)
 */

module.exports = async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
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
      try { body = JSON.parse(body); } catch (e) { body = {}; }
    }
    body = body || {};

    const email = (body.email || '').trim().toLowerCase();
    const fullName = (body.full_name || 'Membre One Vision').trim();

    if (!email || !email.includes('@') || !email.includes('.')) {
      return res.status(400).json({ success: false, error: 'Adresse email Google non valide.' });
    }

    return res.status(200).json({
      success: true,
      redirect: 'questionnaire.html',
      user: {
        email: email,
        full_name: fullName,
        role: 'membre'
      }
    });
  } catch (err) {
    return res.status(500).json({ success: false, error: 'Erreur interne: ' + (err.message || String(err)) });
  }
};
