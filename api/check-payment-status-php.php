<?php
/**
 * ONE VISION COMMUNITY — VÉRIFICATION EN DIRECT DU STATUT DE PAIEMENT SASAPAY
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/saspay.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$sessionId = trim($_GET['payment_id'] ?? ($_GET['session_id'] ?? ($_POST['payment_id'] ?? '')));
$orderNum = trim($_GET['order'] ?? ($_POST['order'] ?? ''));

if (empty($sessionId) && empty($orderNum)) {
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'message' => 'Paramètre session_id ou order manquant.'
    ]);
    exit;
}

// 1. Vérification en base locale si le webhook a déjà confirmé la commande
if (!empty($orderNum)) {
    try {
        require_once __DIR__ . '/../includes/db.php';
        $db = get_db();
        $stmt = $db->prepare("SELECT status, user_id FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$orderNum]);
        $orderRow = $stmt->fetch();
        if ($orderRow && in_array(strtolower($orderRow['status']), ['paid', 'completed', 'active'])) {
            echo json_encode([
                'success'      => true,
                'status'       => 'paid',
                'order_number' => $orderNum,
                'message'      => 'Paiement confirmé et validé avec succès !'
            ]);
            exit;
        }
    } catch (Throwable $e) {}
}

// 2. Vérification auprès de la passerelle SasaPay si un session_id est fourni
if (!empty($sessionId) && strlen($sessionId) > 10) {
    // Interrogation du statut de la session de checkout
    $res = saspay_request('/checkout-sessions/' . urlencode($sessionId) . '/status/', 'GET');
    
    // Si la route status retourne un résultat
    if ($res['success'] && !empty($res['data'])) {
        $status = strtoupper($res['data']['status'] ?? ($res['data']['transaction_status'] ?? ''));
        $paidAt = $res['data']['paid_at'] ?? null;
        if (in_array($status, ['SUCCESS', 'PAID', 'COMPLETED']) || !empty($paidAt)) {
            // Mettre à jour la commande en base si besoin
            if (!empty($orderNum)) {
                try {
                    require_once __DIR__ . '/../includes/db.php';
                    $db = get_db();
                    $stmt = $db->prepare("UPDATE orders SET status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE order_number = ?");
                    $stmt->execute([$orderNum]);
                } catch (Throwable $t) {}
            }

            echo json_encode([
                'success'      => true,
                'status'       => 'paid',
                'order_number' => $orderNum,
                'message'      => 'Paiement confirmé et débité avec succès par la banque !'
            ]);
            exit;
        } elseif (in_array($status, ['FAILED', 'CANCELLED', 'REJECTED'])) {
            echo json_encode([
                'success'      => false,
                'status'       => 'failed',
                'order_number' => $orderNum,
                'message'      => 'La transaction a été refusée par la banque.'
            ]);
            exit;
        }
    }
}

// 3. Par défaut : Toujours en attente (PENDING)
echo json_encode([
    'success'      => true,
    'status'       => 'pending',
    'order_number' => $orderNum,
    'message'      => 'En attente de confirmation bancaire...'
]);
