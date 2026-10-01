<?php
/**
 * ONE VISION COMMUNITY — WEBHOOK SASPAY API
 * Réception asynchrone des événements de paiement SasPay (transaction.success)
 * Documentation : https://docs.saspay.me/api-reference/webhooks
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/saspay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$rawPayload = file_get_contents('php://input');
if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps de requête vide']);
    exit;
}

$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null;
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? null;
$eventType = $_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '';

// Vérification de la signature HMAC SHA-256
if (!saspay_verify_webhook_signature($rawPayload, $signature, $timestamp)) {
    error_log("Webhook SasPay: Signature invalide ou horodatage expiré.");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Signature invalide']);
    exit;
}

$event = json_decode($rawPayload, true);
if (!$event || !isset($event['event'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Format d\'événement JSON invalide']);
    exit;
}

$db = get_db();

// Journalisation de l'événement webhook dans database/webhooks.log
$logLine = date('Y-m-d H:i:s') . " | SASPAY | " . ($event['event'] ?? 'unknown') . " | " . json_encode($event['data'] ?? []) . "\n";
@file_put_contents(DATA_DIR . '/webhooks.log', $logLine, FILE_APPEND);

try {
    $eventName = $event['event'];
    $data = $event['data'] ?? [];

    if ($eventName === 'transaction.success' || $eventName === 'payment.success') {
        $transactionId = $data['id'] ?? '';
        $reference = $data['reference'] ?? '';
        $msisdn = $data['msisdn'] ?? '';
        $network = $data['network'] ?? '';

        // Retrouver la commande correspondante par payment_id ou reference
        $stmt = $db->prepare("
            SELECT * FROM orders 
            WHERE payment_id = ? OR order_number = ? OR invoice_number = ?
            LIMIT 1
        ");
        $stmt->execute([$transactionId, $reference, $reference]);
        $order = $stmt->fetch();

        if ($order) {
            // Mettre à jour la commande en statut payé
            $updateOrder = $db->prepare("
                UPDATE orders 
                SET status = 'paid',
                    momo_phone = CASE WHEN momo_phone = '' THEN ? ELSE momo_phone END,
                    momo_operator = CASE WHEN momo_operator = '' THEN ? ELSE momo_operator END
                WHERE id = ?
            ");
            $updateOrder->execute([$msisdn, $network, $order['id']]);

            // Activer immédiatement l'abonnement du membre
            $updateUser = $db->prepare("
                UPDATE users 
                SET subscription_status = 'active', 
                    subscription_started_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $updateUser->execute([$order['user_id']]);
        }
    }

    // Réponse HTTP 200 immédiate requise par SasPay
    http_response_code(200);
    echo json_encode(['received' => true, 'event' => $eventName]);
    exit;

} catch (Exception $e) {
    error_log("Erreur traitement webhook SasPay : " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['received' => false, 'error' => $e->getMessage()]);
    exit;
}
