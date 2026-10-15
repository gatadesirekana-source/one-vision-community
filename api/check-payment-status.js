/**
 * ONE VISION COMMUNITY — SERVERLESS CHECK PAYMENT STATUS (VERCEL / NODE.JS)
 */

export default async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

  const { ref, session_id } = req.query;
  const targetId = session_id || ref;

  if (!targetId) {
    return res.status(400).json({
      success: false,
      paid: false,
      error: 'Référence ou ID de session manquant.'
    });
  }

  const SASPAY_API_KEY = process.env.SASPAY_API_KEY || 'sk_live_Ecoz4FOTUB21-kWstdXwE5UFuAyo07f4SeAMQPwVh8U';
  const SASPAY_BASE_URL = 'https://api.saspay.me/api/v1';

  try {
    const url = `${SASPAY_BASE_URL}/checkout-sessions/${encodeURIComponent(targetId)}/status/`;
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${SASPAY_API_KEY}`,
        'Accept': 'application/json'
      }
    });

    const data = await response.json();
    const session = data.data || {};
    const status = (session.status || '').toUpperCase();
    const txnStatus = (session.transaction_status || '').toUpperCase();

    if (['SUCCESS', 'PAID', 'COMPLETED'].includes(status) || ['SUCCESS', 'PAID'].includes(txnStatus)) {
      return res.status(200).json({
        success: true,
        paid: true,
        status: 'reussi',
        message: 'Paiement validé avec succès par SasPay.',
        redirect_url: 'dashboard.html'
      });
    }

    if (['FAILED', 'CANCELLED', 'EXPIRED', 'REJECTED'].includes(status) || ['FAILED', 'CANCELLED'].includes(txnStatus)) {
      return res.status(200).json({
        success: true,
        paid: false,
        status: 'echoue',
        message: "Nous n'avons pas reçu le paiement. Le règlement a échoué."
      });
    }

    return res.status(200).json({
      success: true,
      paid: false,
      status: 'en_attente',
      message: 'En attente de confirmation SasPay...'
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      paid: false,
      error: error.message || 'Erreur lors de la vérification'
    });
  }
}
