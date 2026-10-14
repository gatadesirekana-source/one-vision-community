<?php
/**
 * ONE VISION COMMUNITY — SASPAY CHECKOUT API (PHP)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée. Utilisez POST.']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/saspay.php';
require_once __DIR__ . '/../includes/payment_service.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: $_POST;

$planCode = strtolower(trim($body['plan'] ?? 'membre'));
$periodicite = strtolower(trim($body['periodicite'] ?? 'mensuel'));

$db = get_db();
$stmtPlan = $db->prepare("SELECT * FROM plans WHERE code = ? AND actif = 1");
$stmtPlan->execute([$planCode]);
$plan = $stmtPlan->fetch();

if (!$plan) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formule introuvable.']);
    exit;
}

// Utilisateur connecté ou utilisateur invité pour le checkout
$userId = $_SESSION['user_id'] ?? null;
$user = null;

if ($userId) {
    $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();
}

if (!$user) {
    $user = [
        'id'        => 0,
        'email'     => filter_var($body['email'] ?? 'contact@onevisioncommunity.fr', FILTER_VALIDATE_EMAIL) ?: 'contact@onevisioncommunity.fr',
        'prenom'    => $body['name'] ?? 'Membre',
        'nom'       => 'One Vision',
        'telephone' => $body['phone'] ?? ''
    ];
}

$saspay = new SasPayClient();
$montant = PaymentService::calculerMontantFormule($plan, $periodicite);
$devise = $plan['devise'] ?? 'EUR';
$reference = 'PAY-SAS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));

$sessionResult = $saspay->createCheckoutSession([
    'amount'         => $montant,
    'currency'       => $devise,
    'description'    => "One Vision Community — {$plan['nom']} (" . ucfirst($periodicite) . ")",
    'customer_email' => $user['email'],
    'customer_name'  => trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
    'customer_phone' => $user['telephone'] ?? '',
    'return_url'     => rtrim(APP_URL, '/') . '/paiement-retour.php?ref=' . urlencode($reference) . '&plan=' . urlencode($planCode) . '&periodicite=' . urlencode($periodicite),
    'metadata'       => [
        'reference'   => $reference,
        'user_id'     => $user['id'],
        'plan_id'     => $plan['id'],
        'plan_code'   => $planCode,
        'periodicite' => $periodicite
    ]
]);

if (!$sessionResult['success']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $sessionResult['error'] ?? "Erreur lors de l'appel SasPay"]);
    exit;
}

echo json_encode([
    'success'      => true,
    'reference'    => $reference,
    'session_id'   => $sessionResult['session_id'],
    'checkout_url' => $sessionResult['checkout_url'],
    'amount'       => number_format($montant, 2, '.', ''),
    'currency'     => $devise,
    'plan'         => $planCode,
    'periodicite'  => $periodicite
]);
