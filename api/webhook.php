<?php
/**
 * ONE VISION COMMUNITY — SASPAY WEBHOOK RECEIVER
 * 
 * Réceptionne et traite les événements SasPay en temps réel (HMAC SHA-256).
 * Conforme aux spécifications de sécurité https://docs.saspay.me/api-reference/webhooks
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payment_service.php';
require_once __DIR__ . '/../includes/saspay.php';

// Accepter uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. En-têtes SasPay
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
$eventType = $_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '';

// Corps brut (RAW bytes)
$rawBody = file_get_contents('php://input');

if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps de requête vide']);
    exit;
}

// 2. Vérification cryptographique via SasPayClient
$saspay = new SasPayClient();
$check = $saspay->verifyWebhook($rawBody, $signature, $timestamp);

if (!$check['valid']) {
    SasPayClient::logWebhookEvent("Unauthorized: Webhook signature mismatch", [
        'error'              => $check['error'],
        'received_signature' => $signature,
        'received_timestamp' => $timestamp,
        'event'              => $eventType
    ]);
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $check['error']]);
    exit;
}

// 3. Décodage du corps JSON
$payload = json_decode($rawBody, true);
$event = $payload['event'] ?? $eventType;
$data = $payload['data'] ?? [];

SasPayClient::logWebhookEvent("Webhook event received: {$event}", [
    'event'     => $event,
    'id'        => $data['id'] ?? null,
    'reference' => $data['reference'] ?? null,
    'status'    => $data['status'] ?? null
]);

// 4. Traitement selon l'événement
switch ($event) {
    case 'webhook.test':
        SasPayClient::logWebhookEvent("Test webhook successfully verified!");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Webhook de test SasPay reçu et validé avec succès']);
        exit;

    case 'transaction.success':
        $transactionId = $data['id'] ?? '';
        $reference = $data['reference'] ?? '';
        $metadata = $data['metadata'] ?? [];
        $paymentId = $metadata['payment_id'] ?? null;
        $sessionId = $metadata['session_id'] ?? null;
        $orderRef = $metadata['reference'] ?? null;

        $db = get_db();
        $payment = null;

        if ($paymentId) {
            $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([(int)$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$payment && $orderRef) {
            $stmt = $db->prepare("SELECT * FROM payments WHERE reference_externe = ? OR session_id = ?");
            $stmt->execute([$orderRef, $orderRef]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$payment && $reference) {
            $stmt = $db->prepare("SELECT * FROM payments WHERE reference_externe = ? OR session_id = ?");
            $stmt->execute([$reference, $reference]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$payment && $sessionId) {
            $stmt = $db->prepare("SELECT * FROM payments WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$payment) {
            SasPayClient::logWebhookEvent("Order not found for transaction.success", [
                'txn_id'    => $transactionId,
                'reference' => $reference,
                'metadata'  => $metadata
            ]);
            http_response_code(200);
            echo json_encode(['success' => false, 'message' => 'Paiement introuvable en base']);
            exit;
        }

        // Validation atomique de l'abonnement
        $conf = PaymentService::confirmerPaiement($payment['reference_externe'], $data);

        SasPayClient::logWebhookEvent("Order #{$payment['id']} successfully marked as PAID. User #{$payment['user_id']} subscription activated.", [
            'payment_id'     => $payment['id'],
            'user_id'        => $payment['user_id'],
            'transaction_id' => $transactionId
        ]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Paiement confirmé et abonnement activé']);
        exit;

    case 'transaction.failed':
    case 'transaction.cancelled':
        $reference = $data['reference'] ?? '';
        $metadata = $data['metadata'] ?? [];
        $paymentId = $metadata['payment_id'] ?? null;

        $db = get_db();
        if ($paymentId) {
            $stmt = $db->prepare("UPDATE payments SET statut = 'echoue' WHERE id = ? AND statut = 'en_attente'");
            $stmt->execute([(int)$paymentId]);
        } elseif ($reference) {
            $stmt = $db->prepare("UPDATE payments SET statut = 'echoue' WHERE (reference_externe = ? OR session_id = ?) AND statut = 'en_attente'");
            $stmt->execute([$reference, $reference]);
        }

        SasPayClient::logWebhookEvent("Transaction marked as failed/cancelled", ['event' => $event, 'data' => $data]);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Statut échoué enregistré']);
        exit;

    default:
        SasPayClient::logWebhookEvent("Unhandled event acknowledged: {$event}");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Événement acquitté']);
        exit;
}
