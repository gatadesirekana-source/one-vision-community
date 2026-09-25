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

$signatureHeader = strtolower(trim($headerMap['x-webhook-signature'] 
    ?? $headerMap['x-saspay-signature'] 
    ?? $headerMap['saspay-signature'] 
    ?? $headerMap['x-signature'] 
    ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] 
    ?? $_SERVER['HTTP_X_SASPAY_SIGNATURE'] 
    ?? ''));

$timestampHeader = trim($headerMap['x-webhook-timestamp'] 
    ?? $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] 
    ?? '');

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
    // A. Contrôle de l'âge de l'événement (tolérance 300s = 5 min selon la doc SasPay)
    if (!empty($timestampHeader) && abs(time() - (int)$timestampHeader) > 300) {
        log_webhook('Forbidden: Webhook timestamp out of tolerance', ['timestamp' => $timestampHeader]);
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Timestamp out of tolerance']);
        exit;
    }

    // B. Vérification par jeton secret direct
    if (!empty($tokenParam) && hash_equals($secret, $tokenParam)) {
        $isVerified = true;
    }

    // C. Vérification par signature officielle SasPay HMAC SHA-256 (timestamp . '.' . rawPayload)
    if (!$isVerified && !empty($signatureHeader)) {
        if (!empty($timestampHeader)) {
            $expectedOfficial = hash_hmac('sha256', $timestampHeader . '.' . $rawPayload, $secret);
            if (hash_equals($expectedOfficial, $signatureHeader)) {
                $isVerified = true;
            }
        }

        // Fallback sans timestamp
        if (!$isVerified) {
            $expectedDirect = hash_hmac('sha256', $rawPayload, $secret);
            if (hash_equals($expectedDirect, $signatureHeader)) {
                $isVerified = true;
            }
        }
    }

    if (!$isVerified) {
        // En mode production, AUCUN webhook sans signature valide n'est toléré (protection anti-falsification)
        if (defined('SASPAY_ENV') && SASPAY_ENV === 'production') {
            log_webhook('Security Alert: Unauthorized webhook rejected in production', [
                'received_signature' => $signatureHeader,
                'received_token'     => $tokenParam
            ]);
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid or missing webhook signature']);
            exit;
        }

        // En environnement sandbox / local de développement uniquement
        if (empty($signatureHeader) && empty($tokenParam)) {
            log_webhook('Dev Warning: Webhook received without signature header in sandbox/local');
            $isVerified = true; 
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid webhook signature or token']);
            exit;
        }
    }
} else {
    // Si aucun secret n'est configuré en production : interdiction stricte de traiter le webhook
    if (defined('SASPAY_ENV') && SASPAY_ENV === 'production') {
        log_webhook('Security Alert: Webhook received in production without SASPAY_WEBHOOK_SECRET configured');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server configuration error: Webhook secret missing']);
        exit;
    }
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

// 5. Normalisation des identifiants SasaPay / SasPay
$checkoutReqId = $payload['CheckoutRequestID'] 
    ?? $payload['checkout_request_id'] 
    ?? $data['CheckoutRequestID'] 
    ?? $data['checkout_request_id'] 
    ?? '';

$billRef = $payload['BillRefNumber'] 
    ?? $payload['AccountReference'] 
    ?? $data['BillRefNumber'] 
    ?? $data['AccountReference'] 
    ?? '';

$txnId = $payload['TransactionID'] 
    ?? $data['TransactionID'] 
    ?? $data['id'] 
    ?? $data['transaction_id'] 
    ?? $data['transactionId'] 
    ?? $data['reference'] 
    ?? '';

$sessionId = $data['session_id'] 
    ?? $data['sessionId'] 
    ?? $data['checkout_session_id'] 
    ?? '';

$orderRef = $billRef 
    ?? $data['reference'] 
    ?? $data['order_number'] 
    ?? $data['order_id'] 
    ?? $data['metadata']['order_number'] 
    ?? $data['metadata']['order_id'] 
    ?? $data['client_reference_id'] 
    ?? '';

$resultCode = isset($payload['ResultCode']) ? (int)$payload['ResultCode'] : (isset($data['ResultCode']) ? (int)$data['ResultCode'] : null);
$isResultCodeSuccess = ($resultCode !== null && $resultCode === 0);
$isResultCodeFailed = ($resultCode !== null && $resultCode !== 0);

// Traitement des événements de succès de paiement
$successEvents = [
    'transaction.success', 
    'payment.success', 
    'payment.completed', 
    'checkout.session.completed', 
    'charge.success',
    'SUCCESS',
    'PAID'
];

$isSuccessEvent = in_array($event, $successEvents, true) 
    || strtoupper($payload['status'] ?? '') === 'SUCCESS' 
    || $isResultCodeSuccess;

if ($isSuccessEvent) {
    // Recherche de la commande associée dans la base
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE (saspay_session_id != '' AND (saspay_session_id = ? OR saspay_session_id = ?)) 
           OR (saspay_transaction_id != '' AND (saspay_transaction_id = ? OR saspay_transaction_id = ?)) 
           OR order_number = ?
           OR order_number = ?
           OR invoice_number = ?
        LIMIT 1
    ");
    $stmt->execute([$sessionId, $checkoutReqId, $txnId, $checkoutReqId, $orderRef, $billRef, $orderRef]);
    $order = $stmt->fetch();

    if ($order) {
        $finalTxnId = !empty($txnId) ? $txnId : (!empty($checkoutReqId) ? $checkoutReqId : ($order['saspay_transaction_id'] ?: 'TXN_' . uniqid()));

        // Mettre à jour la commande à 'paid'
        $updateOrder = $db->prepare("
            UPDATE orders 
            SET status = 'paid', saspay_transaction_id = ? 
            WHERE id = ?
        ");
        $updateOrder->execute([$finalTxnId, $order['id']]);

        // ACTIVATION OFFICIELLE DE L'ABONNEMENT APRÈS CONFIRMATION RÉELLE
        $updateUser = $db->prepare("
            UPDATE users 
            SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $updateUser->execute([$order['user_id']]);

        log_webhook("Order #{$order['order_number']} successfully marked as PAID via IPN. User #{$order['user_id']} subscription activated.", [
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
            'checkout_request_id' => $checkoutReqId,
            'reference' => $orderRef
        ]);
        
        echo json_encode([
            'success' => true,
            'event' => $event,
            'message' => 'Event acknowledged, but no corresponding pending order found'
        ]);
        exit;
    }
}

// Traitement des échecs ou annulations
$failEvents = ['transaction.failed', 'payment.failed', 'checkout.session.expired', 'FAILED', 'CANCELLED'];
if (in_array($event, $failEvents, true) || $isResultCodeFailed) {
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE (saspay_transaction_id != '' AND (saspay_transaction_id = ? OR saspay_transaction_id = ?)) 
           OR order_number = ?
           OR order_number = ?
        LIMIT 1
    ");
    $stmt->execute([$txnId, $checkoutReqId, $orderRef, $billRef]);
    $order = $stmt->fetch();

    if ($order && $order['status'] === 'pending') {
        $db->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
        log_webhook("Order #{$order['order_number']} marked as failed via webhook IPN.");
        echo json_encode([
            'success' => true,
            'event' => $event,
            'message' => "Order #{$order['order_number']} marked as failed"
        ]);
        exit;
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
