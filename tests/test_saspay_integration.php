<?php
/**
 * ONE VISION COMMUNITY — SUITE DE VALIDATION INTÉGRATION SASPAY
 * 
 * Valide l'ensemble du flux technique SasPay :
 * 1. Configuration sécurisée backend (.env, clés, constantes)
 * 2. Vérification cryptographique des webhooks (HMAC SHA-256, âge max 300s, timing attack protection)
 * 3. Appel réel à l'API SasPay (Création de session Checkout puis annulation propre)
 * 4. Traitement idempotent et sécurisé du webhook
 * 
 * Commande : php tests/test_saspay_integration.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/saspay.php';
require_once __DIR__ . '/../includes/subscriptions.php';
require_once __DIR__ . '/../includes/payment_service.php';

$testsPassed = 0;
$testsFailed = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$name}" . ($detail ? " — {$detail}" : "") . "\n";
    } else {
        $testsFailed++;
        echo "  [FAIL] {$name}" . ($detail ? " — ERREUR: {$detail}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo "      ONE VISION COMMUNITY — VALIDATION TECHNIQUE SASPAY (LIVE)     \n";
echo "====================================================================\n\n";

// --- 1. CONFIGURATION BACKEND & SÉCURITÉ ---
echo "--- 1. CONFIGURATION & SÉCURITÉ BACKEND ---\n";
assert_test("Mode de paiement configuré sur 'saspay'", PAIEMENT_MODE === 'saspay', "Mode actuel: " . PAIEMENT_MODE);
assert_test("Présence de la clé API secrète SasPay", !empty(SASPAY_API_KEY) && strpos(SASPAY_API_KEY, 'sk_live_') === 0, "Préfixe sk_live_ conforme");
assert_test("Présence du secret de webhook SasPay", !empty(SASPAY_WEBHOOK_SECRET) && strlen(SASPAY_WEBHOOK_SECRET) >= 32, "Longueur: " . strlen(SASPAY_WEBHOOK_SECRET));
assert_test("Base URL SasPay conforme", SASPAY_BASE_URL === 'https://api.saspay.me/api/v1');

// Vérification de la non-exposition côté frontend
$indexHtml = file_get_contents(__DIR__ . '/../index.php');
$choisirHtml = file_get_contents(__DIR__ . '/../choisir-abonnement.php');
$dashHtml = file_get_contents(__DIR__ . '/../dashboard.php');
$isKeyLeaked = strpos($indexHtml, SASPAY_API_KEY) !== false || 
               strpos($choisirHtml, SASPAY_API_KEY) !== false || 
               strpos($dashHtml, SASPAY_API_KEY) !== false;
assert_test("Protection frontend absolue (Aucune clé secrète dans le code client)", !$isKeyLeaked);

// --- 2. SÉCURITÉ CRYPTOGRAPHIQUE DU WEBHOOK ---
echo "\n--- 2. VÉRIFICATION CRYPTOGRAPHIQUE DU WEBHOOK (HMAC SHA-256) ---\n";
$saspay = new SasPayClient();
$rawBody = json_encode(['event' => 'webhook.test', 'data' => ['ping' => 'pong']]);
$validTimestamp = (string)time();
$validSignature = hash_hmac('sha256', $validTimestamp . '.' . $rawBody, SASPAY_WEBHOOK_SECRET);

// Test A : Signature et timestamp valides
$checkValid = $saspay->verifyWebhook($rawBody, $validSignature, $validTimestamp);
assert_test("Signature HMAC SHA-256 valide acceptée", $checkValid['valid'] === true);

// Test B : Horodatage expiré (> 300 secondes)
$oldTimestamp = (string)(time() - 360);
$oldSignature = hash_hmac('sha256', $oldTimestamp . '.' . $rawBody, SASPAY_WEBHOOK_SECRET);
$checkOld = $saspay->verifyWebhook($rawBody, $oldSignature, $oldTimestamp);
assert_test("Protection contre le rejeu : webhook rejeté si horodatage > 300s", $checkOld['valid'] === false && strpos($checkOld['error'], 'hors tolérance') !== false);

// Test C : Signature falsifiée
$checkForged = $saspay->verifyWebhook($rawBody, 'falsified_signature_1234567890abcdef', $validTimestamp);
assert_test("Rejet immédiat d'une signature falsifiée", $checkForged['valid'] === false);

// Test D : Corps altéré (tampering)
$tamperedBody = json_encode(['event' => 'transaction.success', 'data' => ['fake' => true]]);
$checkTampered = $saspay->verifyWebhook($tamperedBody, $validSignature, $validTimestamp);
assert_test("Rejet d'un corps de requête altéré bit à bit", $checkTampered['valid'] === false);

// --- 3. TEST DE COMMUNICATION API RÉEL AVEC SASPAY ---
echo "\n--- 3. COMMUNICATION API LIVE AVEC SASPAY ---\n";
$sessionResult = $saspay->createCheckoutSession([
    'amount'         => '9.00',
    'currency'       => 'EUR',
    'description'    => 'Test Technique Validation Intégration',
    'customer_email' => 'dev.test@onevisioncommunity.fr',
    'customer_name'  => 'Test Automatisé',
    'return_url'     => rtrim(APP_URL, '/') . '/paiement-retour.php?ref=TEST-REF',
    'metadata'       => ['test_run' => true, 'timestamp' => time()]
]);

assert_test("Création réussie d'une session de Checkout hébergé", $sessionResult['success'] === true, "ID: " . ($sessionResult['session_id'] ?? 'N/A'));
assert_test("Réception de l'URL de checkout SasPay", !empty($sessionResult['checkout_url']) && strpos($sessionResult['checkout_url'], 'https://checkout.saspay.me/') === 0, "URL: " . ($sessionResult['checkout_url'] ?? ''));

// Nettoyage immédiat : Annulation de la session créée
if (!empty($sessionResult['session_id'])) {
    $cancelResult = $saspay->cancelCheckoutSession($sessionResult['session_id']);
    assert_test("Nettoyage propre : session de test annulée", $cancelResult['success'] === true);
}

// --- 4. FLUX DE PAIEMENT & ACTIVATION ATOMIQUE ---
echo "\n--- 4. SIMULATION DE RÉCEPTION WEBHOOK TRANSACTION.SUCCESS ---\n";
$db = get_db();
$testUserEmail = 'saspay.member.test@onevisioncommunity.fr';
$db->prepare("DELETE FROM users WHERE email = ?")->execute([$testUserEmail]);

$db->prepare("
    INSERT INTO users (full_name, email, password, role, statut, subscription_status, created_at)
    VALUES (?, ?, ?, 'membre', 'actif', 'none', datetime('now'))
")->execute(['Membre SasPay Test', $testUserEmail, password_hash('Pass123!', PASSWORD_DEFAULT)]);
$testUserId = (int)$db->lastInsertId();

$stmtPlan = $db->query("SELECT * FROM plans WHERE code = 'membre' LIMIT 1");
$planMembre = $stmtPlan->fetch(PDO::FETCH_ASSOC);

// Création d'une intention de paiement en base
$simulatedRef = 'PAY-SAS-' . date('YmdHis') . '-TEST';
$db->prepare("
    INSERT INTO payments (user_id, montant, devise, statut, reference_externe, methode, date_paiement)
    VALUES (?, 9.00, 'EUR', 'en_attente', ?, 'saspay', datetime('now'))
")->execute([$testUserId, $simulatedRef]);
$paymentId = (int)$db->lastInsertId();

// Déclenchement de l'activation comme le ferait le webhook
$webhookPayloadData = [
    'id'        => 'TXN_TEST_' . time(),
    'reference' => $simulatedRef,
    'status'    => 'SUCCESS',
    'amount'    => '9.00',
    'currency'  => 'EUR',
    'metadata'  => ['payment_id' => $paymentId, 'reference' => $simulatedRef]
];

$activation = PaymentService::confirmerPaiement($simulatedRef, $webhookPayloadData);
assert_test("Activation atomique de l'abonnement suite à confirmation", $activation['success'] === true);
assert_test("Rôle de l'utilisateur confirmé 'membre'", ($activation['new_role'] ?? '') === 'membre');

$subActive = get_user_active_subscription($testUserId);
assert_test("Abonnement présent et actif en base de données", $subActive !== null && $subActive['statut'] === 'actif');

// Test d'idempotence : deuxième appel identique avec la même référence
$activationIdempotente = PaymentService::confirmerPaiement($simulatedRef, $webhookPayloadData);
assert_test("Idempotence stricte : le second webhook ne crée aucun doublon", $activationIdempotente['success'] === true && !empty($activationIdempotente['already_paid']));

// Nettoyage de l'utilisateur de test
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);

echo "\n====================================================================\n";
echo "RÉSULTATS DE LA SUITE DE TESTS SASPAY :\n";
echo "Succès : {$testsPassed} / " . ($testsPassed + $testsFailed) . "\n";
echo "Échecs : {$testsFailed}\n";
echo "====================================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
