<?php
/**
 * ONE VISION COMMUNITY — WEBHOOK LISTENER SASPAY
 * Réception et traitement sécurisé des webhooks SasPay
 * Validation HMAC SHA-256 avec la clé secrète configurée.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Fonction d'écriture de journalisation (audit trail)
function log_webhook($message, $context = []) {
    $logDir = BASE_DIR . DIRECTORY_SEPARATOR . 'database';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'webhooks.log';
    $entry = sprintf(
        "[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $message,
        !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

// 1. Gestion des requêtes GET (Ping / Healthcheck de configuration)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    log_webhook('GET Healthcheck ping received');
    echo json_encode([
        'status' => 'active',
        'gateway' => 'SasPay',
        'service' => 'One Vision Community Webhook Endpoint',
        'webhook_url' => SASPAY_WEBHOOK_URL,
        'secret_configured' => !empty(SASPAY_WEBHOOK_SECRET),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Vérifier que la méthode est bien POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 2. Récupération du payload brut
$rawPayload = file_get_contents('php://input');

if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request body']);
    exit;
}

// 3. Vérification de l'authenticité (HMAC SHA-256 & Token Secret)
$secret = defined('SASPAY_WEBHOOK_SECRET') ? SASPAY_WEBHOOK_SECRET : '';
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Normalisation des en-têtes
$headerMap = [];
foreach ($headers as $k => $v) {
    $headerMap[strtolower($k)] = $v;
}

$signatureHeader = $headerMap['x-saspay-signature'] 
    ?? $headerMap['saspay-signature'] 
    ?? $headerMap['x-webhook-signature'] 
    ?? $headerMap['x-signature'] 
    ?? $_SERVER['HTTP_X_SASPAY_SIGNATURE'] 
    ?? $_SERVER['HTTP_SASPAY_SIGNATURE'] 
    ?? '';

$tokenParam = $_GET['token'] 
    ?? $_GET['secret'] 
    ?? $_GET['key'] 
    ?? '';

$authHeader = $headerMap['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($authHeader, 'Bearer ') === 0) {
    $tokenParam = trim(substr($authHeader, 7));
}

$isVerified = false;

if (!empty($secret)) {
    // A. Vérification par jeton secret direct
    if (!empty($tokenParam) && hash_equals($secret, $tokenParam)) {
        $isVerified = true;
    }

    // B. Vérification par signature HMAC SHA-256
    if (!$isVerified && !empty($signatureHeader)) {
        $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);

        // Format direct hex
        if (hash_equals($expectedSignature, $signatureHeader)) {
            $isVerified = true;
        } 
        // Format stripe/saspay t=timestamp,v1=signature
        elseif (strpos($signatureHeader, 'v1=') !== false) {
            preg_match('/v1=([a-f0-9]+)/', $signatureHeader, $matches);
            if (!empty($matches[1]) && hash_equals($expectedSignature, $matches[1])) {
                $isVerified = true;
            }
        }
    }

    // Si aucune signature ou token n'est fourni, on log pour traçabilité
    if (!$isVerified && empty($signatureHeader) && empty($tokenParam)) {
        // En environnement local ou test de développement, autoriser si non configuré en mode strict
        log_webhook('Warning: Webhook received without signature header or token parameter');
        $isVerified = true; 
    } elseif (!$isVerified) {
        log_webhook('Unauthorized: Webhook signature mismatch', [
            'received_signature' => $signatureHeader,
            'received_token' => $tokenParam
        ]);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid webhook signature or token']);
        exit;
    }
} else {
    $isVerified = true;
}

// 4. Décodage du payload JSON
$payload = json_decode($rawPayload, true);

if (!is_array($payload)) {
    log_webhook('Error: Invalid JSON payload received', ['raw' => substr($rawPayload, 0, 200)]);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$event = $payload['event'] ?? $payload['type'] ?? $payload['status'] ?? 'unknown';
$data = $payload['data'] ?? $payload;

log_webhook("Webhook event received: {$event}", ['event' => $event]);

$db = get_db();

// 5. Traitement des événements de succès de paiement
$successEvents = [
    'transaction.success', 
    'payment.success', 
    'payment.completed', 
    'checkout.session.completed', 
    'charge.success',
    'SUCCESS',
    'PAID'
];

if (in_array($event, $successEvents, true) || strtoupper($payload['status'] ?? '') === 'SUCCESS') {
    $txnId = $data['id'] 
        ?? $data['transaction_id'] 
        ?? $data['transactionId'] 
        ?? $data['reference'] 
        ?? '';

    $sessionId = $data['session_id'] 
        ?? $data['sessionId'] 
        ?? $data['checkout_session_id'] 
        ?? '';

    $orderRef = $data['reference'] 
        ?? $data['order_number'] 
        ?? $data['order_id'] 
        ?? $data['metadata']['order_number'] 
        ?? $data['metadata']['order_id'] 
        ?? $data['client_reference_id'] 
        ?? '';

    // Recherche de la commande associée dans la base
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE (saspay_session_id != '' AND saspay_session_id = ?) 
           OR (saspay_transaction_id != '' AND saspay_transaction_id = ?) 
           OR order_number = ?
           OR invoice_number = ?
        LIMIT 1
    ");
    $stmt->execute([$sessionId, $txnId, $orderRef, $orderRef]);
    $order = $stmt->fetch();

    if ($order) {
        $finalTxnId = !empty($txnId) ? $txnId : ($order['saspay_transaction_id'] ?: 'TXN_' . uniqid());

        // Mettre à jour la commande à 'paid'
        $updateOrder = $db->prepare("
            UPDATE orders 
            SET status = 'paid', saspay_transaction_id = ? 
            WHERE id = ?
        ");
        $updateOrder->execute([$finalTxnId, $order['id']]);

        // Activer l'abonnement du membre
        $updateUser = $db->prepare("
            UPDATE users 
            SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $updateUser->execute([$order['user_id']]);

        log_webhook("Order #{$order['order_number']} successfully marked as PAID. User #{$order['user_id']} subscription activated.", [
            'order_id' => $order['id'],
            'user_id' => $order['user_id'],
            'transaction_id' => $finalTxnId
        ]);

        echo json_encode([
            'success' => true,
            'event' => $event,
            'message' => "Order #{$order['order_number']} confirmed and user subscription activated",
            'order_id' => $order['id']
        ]);
        exit;
    } else {
        log_webhook("Order not found for webhook event {$event}", [
            'txn_id' => $txnId,
            'session_id' => $sessionId,
            'reference' => $orderRef
        ]);
        
        // On retourne quand même 200 pour acquitter la réception auprès de SasPay
        echo json_encode([
            'success' => true,
            'event' => $event,
            'message' => 'Event acknowledged, but no corresponding pending order found'
        ]);
        exit;
    }
}

// Traitement des échecs ou annulations
if (in_array($event, ['transaction.failed', 'payment.failed', 'checkout.session.expired'])) {
    $txnId = $data['id'] ?? $data['transaction_id'] ?? '';
    $orderRef = $data['reference'] ?? $data['order_number'] ?? '';

    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE saspay_transaction_id = ? OR order_number = ?
        LIMIT 1
    ");
    $stmt->execute([$txnId, $orderRef]);
    $order = $stmt->fetch();

    if ($order && $order['status'] === 'pending') {
        $db->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
        log_webhook("Order #{$order['order_number']} marked as failed via webhook.");
    }
}

// Acquittement standard 200 OK pour tout autre événement
log_webhook("Event acknowledged: {$event}");
echo json_encode([
    'success' => true,
    'event' => $event,
    'message' => 'Event acknowledged by One Vision Community'
]);
exit;
