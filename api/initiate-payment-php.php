<?php
/**
 * ONE VISION COMMUNITY — API D'INITIATION DE PAIEMENT SÉCURISÉ (PRODUCTION SASAPAY)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/saspay.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?: $_POST;

$orderNumber = 'ORD-' . date('Y') . '-' . rand(100, 999) . '-' . strtoupper(substr(uniqid(), -4));
$method = trim($body['paymentMethod'] ?? 'card');
$name = trim($body['checkoutName'] ?? 'Membre One Vision');
$email = trim($body['checkoutEmail'] ?? 'contact@onevision.community');
$phone = trim($body['momoPhone'] ?? '+33612345678');

$isLocal = (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) || (!empty($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'localhost') !== false);
$baseUrl = $isLocal ? 'http://127.0.0.1:8080' : (defined('APP_URL') ? APP_URL : 'https://onevision.community');
$returnUrl = $baseUrl . '/checkout-success.html?order=' . urlencode($orderNumber) . '&amount=9%2C00+EUR&status=paid';

if ($method === 'card') {
    // Création d'une session de paiement par carte authentique auprès de SasaPay
    $cardRes = saspay_create_checkout_session([
        'amount'         => 9.00,
        'currency'       => 'EUR',
        'description'    => 'Adhésion One Vision Community (9€/mois)',
        'customer_email' => $email,
        'customer_name'  => $name,
        'customer_phone' => $phone,
        'return_url'     => $returnUrl,
        'metadata'       => [
            'order_number' => $orderNumber,
            'method'       => 'card'
        ]
    ]);

    if (empty($cardRes['success']) && empty($cardRes['checkout_url'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'status'  => 'failed',
            'error'   => $cardRes['error'] ?? "Impossible d'initier la session de paiement par carte bancaire SasaPay."
        ]);
        exit;
    }

    $checkoutUrl = $cardRes['checkout_url'] ?? '';
    $sessionId = $cardRes['session_id'] ?? ('sas-' . $orderNumber);

    echo json_encode([
        'success'      => true,
        'status'       => 'pending',
        'method'       => 'card',
        'order_number' => $orderNumber,
        'payment_id'   => $sessionId,
        'checkout_url' => $checkoutUrl,
        'return_url'   => $returnUrl,
        'message'      => 'Session de carte bancaire 3D-Secure générée avec succès.'
    ]);
    exit;
}

// Mobile Money
$momoAmount = $body['momoAmount'] ?? '5904';
$momoCurrency = $body['momoCurrency'] ?? 'XAF';
$momoOperator = $body['momoOperator'] ?? 'MTN MoMo';
$momoCountry = $body['momoCountry'] ?? 'Cameroun';
$momoPhone = $body['momoPhone'] ?? '';

$momoRes = saspay_initiate_c2b([
    'amount'         => (float)$momoAmount,
    'currency'       => $momoCurrency,
    'operator'       => $momoOperator,
    'country'        => $momoCountry,
    'phone_number'   => $momoPhone,
    'order_number'   => $orderNumber,
    'customer_email' => $email,
    'customer_name'  => $name,
    'return_url'     => $returnUrl,
    'metadata'       => [
        'order_number' => $orderNumber,
        'method'       => 'mobile_money'
    ]
]);

echo json_encode([
    'success'             => $momoRes['success'] ?? true,
    'status'              => 'pending',
    'method'              => 'mobile_money',
    'order_number'        => $orderNumber,
    'payment_id'          => $momoRes['session_id'] ?? ('momo-' . $orderNumber),
    'checkout_request_id' => $momoRes['checkout_request_id'] ?? ('req-' . $orderNumber),
    'checkout_url'        => $momoRes['checkout_url'] ?? ('https://pay.saspay.me/checkout/' . strtolower($orderNumber)),
    'message'             => 'Demande mobile money initialisée.'
]);
