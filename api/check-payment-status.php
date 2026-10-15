<?php
/**
 * ONE VISION COMMUNITY — API VÉRIFICATION TEMPS RÉEL DU STATUT SASPAY
 * 
 * Permet au frontend (ou au serveur) de sonder l'état réel d'un paiement SasPay.
 * Interroge l'API SasPay en direct, confirme l'abonnement si validé,
 * ou marque le paiement comme échoué si refusé / annulé.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/saspay.php';
require_once __DIR__ . '/../includes/payment_service.php';

$reference = trim($_GET['ref'] ?? $_GET['reference'] ?? $_GET['session_id'] ?? $_GET['id'] ?? '');

if (empty($reference) && !empty($_SESSION['last_payment_ref'])) {
    $reference = $_SESSION['last_payment_ref'];
}

if (empty($reference)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'paid'    => false,
        'error'   => 'Référence de paiement manquante.'
    ]);
    exit;
}

$db = get_db();

// 1. Recherche du paiement en base
$stmt = $db->prepare("
    SELECT p.*, u.email, u.full_name 
    FROM payments p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.reference_externe = ? OR p.session_id = ?
    ORDER BY p.id DESC LIMIT 1
");
$stmt->execute([$reference, $reference]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'paid'    => false,
        'error'   => 'Paiement introuvable en base.'
    ]);
    exit;
}

// 2. Si déjà marqué 'reussi'
if ($payment['statut'] === 'reussi') {
    echo json_encode([
        'success'      => true,
        'paid'         => true,
        'status'       => 'reussi',
        'message'      => 'Paiement validé avec succès par SasPay.',
        'redirect_url' => 'dashboard.php'
    ]);
    exit;
}

// 3. Si déjà marqué 'echoue'
if ($payment['statut'] === 'echoue') {
    echo json_encode([
        'success' => true,
        'paid'    => false,
        'status'  => 'echoue',
        'message' => "Nous n'avons pas reçu le paiement. Le règlement a échoué."
    ]);
    exit;
}

// 4. Si encore 'en_attente', vérifier auprès de l'API SasPay en direct
$conf = PaymentService::confirmerPaiement($reference);

if ($conf['success']) {
    echo json_encode([
        'success'      => true,
        'paid'         => true,
        'status'       => 'reussi',
        'plan_code'    => $conf['plan_code'] ?? 'membre',
        'message'      => 'Paiement validé avec succès par SasPay.',
        'redirect_url' => (($conf['new_role'] ?? '') === 'animateur') ? 'espace-animateur.php' : 'dashboard.php'
    ]);
    exit;
}

if (!empty($conf['failed'])) {
    echo json_encode([
        'success' => true,
        'paid'    => false,
        'status'  => 'echoue',
        'message' => $conf['error'] ?? "Nous n'avons pas reçu le paiement."
    ]);
    exit;
}

// Toujours en attente
echo json_encode([
    'success' => true,
    'paid'    => false,
    'status'  => 'en_attente',
    'message' => 'En attente de règlement auprès de votre opérateur...'
]);
