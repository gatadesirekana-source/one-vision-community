<?php
/**
 * ONE VISION COMMUNITY — API D'INITIATION DE PAIEMENT POUR LOCAL PHP
 */

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

if ($method === 'card') {
    echo json_encode([
        'success'      => true,
        'status'       => 'pending',
        'method'       => 'card',
        'order_number' => $orderNumber,
        'payment_id'   => 'sas-' . $orderNumber,
        'checkout_url' => '',
        'redirect_url' => 'card-payment.php?order=' . urlencode($orderNumber),
        'message'      => 'Session de paiement par carte prête.'
    ]);
    exit;
}

// Mobile Money
$momoAmount = $body['momoAmount'] ?? '5904';
$momoCurrency = $body['momoCurrency'] ?? 'XAF';
$momoOperator = $body['momoOperator'] ?? 'MTN MoMo';
$momoCountry = $body['momoCountry'] ?? 'Cameroun';
$momoPhone = $body['momoPhone'] ?? '';

echo json_encode([
    'success'             => true,
    'status'              => 'pending',
    'method'              => 'mobile_money',
    'order_number'        => $orderNumber,
    'payment_id'          => 'momo-' . $orderNumber,
    'checkout_request_id' => 'req-' . $orderNumber,
    'checkout_url'        => 'https://pay.saspay.me/checkout/' . strtolower($orderNumber),
    'message'             => 'Demande mobile money initialisée.'
]);
