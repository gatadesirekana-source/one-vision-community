<?php
/**
 * ONE VISION COMMUNITY — VÉRIFICATION DU STATUT DE PAIEMENT EN TEMPS RÉEL (POLLING)
 * Utilisé par le frontend pour vérifier le statut réel (pending, paid, failed, expired)
 * sans jamais afficher "succès" prématurément.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/saspay.php';

$orderNumber = trim($_GET['order'] ?? $_POST['order'] ?? '');

if (empty($orderNumber)) {
    http_response_code(400);
    echo json_encode([
        'success'      => false,
        'status'       => 'error',
        'is_completed' => true,
        'message'      => 'Numéro de commande manquant'
    ]);
    exit;
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode([
        'success'      => false,
        'status'       => 'not_found',
        'is_completed' => true,
        'message'      => 'Commande introuvable'
    ]);
    exit;
}

// 1. Si la commande a déjà été confirmée (ex: par le webhook IPN)
if ($order['status'] === 'paid') {
    // S'assurer que l'abonnement du membre est bien actif
    $db->prepare("UPDATE users SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([$order['user_id']]);

    // Connecter l'utilisateur dans la session
    $_SESSION['user_id'] = $order['user_id'];
    $_SESSION['user_name'] = $order['billing_name'];
    $_SESSION['user_email'] = $order['billing_email'];
    $_SESSION['user_role'] = 'member';

    echo json_encode([
        'success'      => true,
        'status'       => 'paid',
        'is_completed' => true,
        'order_number' => $order['order_number'],
        'redirect_url' => 'checkout-success.php?order=' . urlencode($order['order_number']),
        'message'      => 'Paiement confirmé avec succès !'
    ]);
    exit;
}

$isCard = (stripos($order['payment_method'] ?? '', 'Carte') !== false);
$requestId = !empty($order['saspay_transaction_id']) ? $order['saspay_transaction_id'] : ($order['saspay_session_id'] ?? '');

// 2. Interroger TOUJOURS en priorité la passerelle SasPay pour obtenir le statut réel
if (!empty($requestId)) {
    $verifyRes = saspay_check_transaction_status($requestId, $order['order_number']);
    $saspayStatus = strtoupper($verifyRes['status'] ?? 'PENDING');

    if (in_array($saspayStatus, ['PAID', 'SUCCESS', 'COMPLETED'], true)) {
        $finalTxnId = $verifyRes['transaction_id'] ?? $order['saspay_transaction_id'] ?: ('TXN_' . uniqid());

        // Mise à jour de la commande
        $db->prepare("UPDATE orders SET status = 'paid', saspay_transaction_id = ? WHERE id = ?")
           ->execute([$finalTxnId, $order['id']]);

        // ACTIVATION STRICTE DE L'ABONNEMENT DU MEMBRE
        $db->prepare("UPDATE users SET subscription_status = 'active', subscription_started_at = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$order['user_id']]);

        // Connexion immédiate dans la session
        $_SESSION['user_id'] = $order['user_id'];
        $_SESSION['user_name'] = $order['billing_name'];
        $_SESSION['user_email'] = $order['billing_email'];
        $_SESSION['user_role'] = 'member';

        echo json_encode([
            'success'      => true,
            'status'       => 'paid',
            'is_completed' => true,
            'order_number' => $order['order_number'],
            'redirect_url' => 'checkout-success.php?order=' . urlencode($order['order_number']),
            'message'      => 'Paiement confirmé avec succès !'
        ]);
        exit;
    } elseif (in_array($saspayStatus, ['FAILED', 'CANCELLED', 'REJECTED'], true)) {
        $db->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
        echo json_encode([
            'success'      => false,
            'status'       => 'failed',
            'is_completed' => true,
            'order_number' => $order['order_number'],
            'redirect_url' => 'checkout-success.php?order=' . urlencode($order['order_number']),
            'message'      => $isCard 
                ? "L'authentification 3D Secure ou le paiement par carte a été refusé par votre banque." 
                : "La transaction a été refusée ou a échoué sur votre mobile."
        ]);
        exit;
    } elseif (in_array($saspayStatus, ['EXPIRED'], true)) {
        $db->prepare("UPDATE orders SET status = 'expired' WHERE id = ?")->execute([$order['id']]);
        echo json_encode([
            'success'      => false,
            'status'       => 'expired',
            'is_completed' => true,
            'order_number' => $order['order_number'],
            'redirect_url' => 'checkout-success.php?order=' . urlencode($order['order_number']),
            'message'      => $isCard 
                ? "Délai d'authentification bancaire dépassé. L'autorisation 3D Secure a expiré."
                : "Délai de confirmation dépassé. Aucune validation reçue sur votre téléphone."
        ]);
        exit;
    }
}

// 3. Si la commande a déjà été marquée en échec
if (in_array($order['status'], ['failed', 'cancelled', 'rejected'], true)) {
    echo json_encode([
        'success'      => false,
        'status'       => 'failed',
        'is_completed' => true,
        'order_number' => $order['order_number'],
        'redirect_url' => 'checkout-success.php?order=' . urlencode($order['order_number']),
        'message'      => 'La transaction a été refusée ou annulée.'
    ]);
    exit;
}

// 4. Calcul du délai d'attente (timeout de 300 secondes / 5 minutes)
$createdAt = strtotime($order['created_at'] ?? 'now');
$now = time();
$elapsed = max(0, $now - $createdAt);
$timeout = 300; // 5 minutes pour laisser le temps de valider sur l'app

if ($elapsed >= $timeout) {
    $db->prepare("UPDATE orders SET status = 'expired' WHERE id = ?")->execute([$order['id']]);
    echo json_encode([
        'success'      => false,
        'status'       => 'expired',
        'is_completed' => true,
        'order_number' => $order['order_number'],
        'elapsed'      => $elapsed,
        'redirect_url' => 'checkout-success.php?order=' . urlencode($order['order_number']),
        'message'      => $isCard 
            ? "Délai d'authentification bancaire dépassé. L'autorisation 3D Secure a expiré."
            : "Délai de confirmation dépassé. Aucune validation reçue sur votre téléphone."
    ]);
    exit;
}

// 5. Toujours en attente de confirmation
echo json_encode([
    'success'      => true,
    'status'       => 'pending',
    'is_completed' => false,
    'order_number' => $order['order_number'],
    'elapsed'      => $elapsed,
    'remaining'    => max(0, $timeout - $elapsed),
    'message'      => $isCard 
        ? "En attente d'authentification bancaire..." 
        : "En attente de confirmation sur votre téléphone..."
]);
