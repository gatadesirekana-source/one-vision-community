<?php
/**
 * ONE VISION COMMUNITY — WEBHOOK MONEROO API
 * Réception asynchrone des événements de paiement Moneroo (payment.success)
 * Documentation: https://docs.moneroo.io/
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/moneroo.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$rawPayload = file_get_contents('php://input');
if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps de requête vide.']);
    exit;
}

// 1. Vérification de la signature HMAC-SHA256 si la clé secrète de webhook est configurée
$signature = $_SERVER['HTTP_X_MONEROO_SIGNATURE'] ?? null;
if (!moneroo_verify_webhook_signature($rawPayload, $signature)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Signature de webhook invalide.']);
    exit;
}

$data = json_decode($rawPayload, true);
if (!$data || !isset($data['event'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload JSON invalide.']);
    exit;
}

$event = $data['event'] ?? '';
$paymentData = $data['data'] ?? [];
$paymentId = $paymentData['id'] ?? null;

// Journalisation locale sécurisée du webhook
$logFile = BASE_DIR . '/database/webhooks.log';
$logEntry = date('Y-m-d H:i:s') . " | EVENT: {$event} | PAYMENT_ID: " . ($paymentId ?? 'N/A') . "\n";
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// 2. Traitement de l'événement payment.success
if ($event === 'payment.success' && !empty($paymentId)) {
    try {
        $db = get_db();

        // 3. Re-vérification stricte auprès de l'API Moneroo pour garantir l'intégrité
        $verification = moneroo_verify_payment($paymentId);

        if ($verification['success'] && in_array($verification['status'], ['success', 'completed', 'paid'], true)) {
            // Mettre à jour la commande
            $stmt = $db->prepare("UPDATE orders SET status = 'paid' WHERE payment_id = ?");
            $stmt->execute([$paymentId]);

            // Récupérer la commande pour activer l'utilisateur
            $stmt = $db->prepare("SELECT user_id, order_number FROM orders WHERE payment_id = ?");
            $stmt->execute([$paymentId]);
            $order = $stmt->fetch();

            if ($order && !empty($order['user_id'])) {
                $db->prepare("UPDATE users SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP WHERE id = ?")
                   ->execute([$order['user_id']]);
            }
        }
    } catch (Exception $e) {
        error_log("Erreur traitement webhook Moneroo: " . $e->getMessage());
    }
}

// Réponse 200 OK requise par Moneroo en moins de 3 secondes
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Webhook traité avec succès.'
]);
exit;
