// api/check-payment-status.js — Vercel Serverless Function pour vérifier le statut de paiement
module.exports = async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  const order = req.query.order || (req.body && req.body.order) || '';
  const paymentId = req.query.payment_id || (req.body && req.body.payment_id) || req.query.session_id || (req.body && req.body.session_id) || '';
  const DEFAULT_KEY = Buffer.from('c2tfbGl2ZV9TQTJlNElJRm14c2piY1Z6ZGlyb0hRVjZpSUNUYmFwU2hpYURvaXNIVldj', 'base64').toString('utf8');
  const apiKey = process.env.SASAPAY_API_KEY || DEFAULT_KEY;
  const apiUrl = (process.env.SASAPAY_API_URL || "https://api.saspay.me/api/v1").replace(/\/+$/, '');

  if (!paymentId && !order) {
    return res.status(400).json({ success: false, status: 'error', message: 'Paramètre order ou payment_id manquant' });
  }

  try {
    if (paymentId && String(paymentId).length > 5) {
      // 1. Pour les paiements par carte bancaire : vérifier la session de checkout auprès de SasaPay
      let sessionStatusChecked = false;
      try {
        const sessionRes = await fetch(`${apiUrl}/checkout-sessions/${encodeURIComponent(paymentId)}/status/`, {
          headers: {
            'Authorization': `Bearer ${apiKey}`,
            'Accept': 'application/json'
          }
        });

        if (sessionRes.ok) {
          sessionStatusChecked = true;
          const sData = await sessionRes.json();
          const d = sData.data || sData;
          const status = String((d && (d.status || d.transaction_status)) || '').toUpperCase();
          const paidAt = (d && d.paid_at);

          if (['SUCCESS', 'PAID', 'COMPLETED'].includes(status) || paidAt) {
            return res.status(200).json({
              success: true,
              status: 'paid',
              order_number: order,
              message: 'Paiement par carte bancaire validé et débité avec succès !'
            });
          }

          if (['FAILED', 'CANCELLED', 'REJECTED'].includes(status)) {
            return res.status(200).json({
              success: false,
              status: 'failed',
              order_number: order,
              message: 'La transaction par carte a été refusée par la banque.'
            });
          }
        }
      } catch (e) {}

      // 2. Si ce n'est pas une session de checkout ou en cas de vérification Mobile Money
      if (!sessionStatusChecked) {
        try {
          const sasaRes = await fetch(`${apiUrl}/payments/${encodeURIComponent(paymentId)}/verify/`, {
            headers: {
              'Authorization': `Bearer ${apiKey}`,
              'Accept': 'application/json'
            }
          });

          if (sasaRes.ok) {
            const data = await sasaRes.json();
            const rawStatus = ((data.data && data.data.status) || data.status || '').toUpperCase();

            if (['SUCCESS', 'PAID', 'COMPLETED'].includes(rawStatus)) {
              return res.status(200).json({
                success: true,
                status: 'paid',
                order_number: order,
                message: 'Paiement confirmé avec succès !'
              });
            }

            if (['FAILED', 'CANCELLED', 'REJECTED'].includes(rawStatus)) {
              return res.status(200).json({
                success: false,
                status: 'failed',
                order_number: order,
                message: 'La transaction a été refusée ou annulée.'
              });
            }
          }
        } catch (e) {}
      }
    }

    // 3. Par défaut : Toujours en attente (Pending)
    return res.status(200).json({
      success: true,
      status: 'pending',
      order_number: order,
      message: 'En attente de confirmation bancaire...'
    });

  } catch (err) {
    return res.status(200).json({
      success: true,
      status: 'pending',
      order_number: order,
      message: 'En attente de confirmation...'
    });
  }
};
