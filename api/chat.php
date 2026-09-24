<?php
/**
 * ONE VISION COMMUNITY — API SALONS DE DISCUSSION (CHAT AJAX / JSON)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $channel = $_GET['channel'] ?? 'general';
    $limit = min((int)($_GET['limit'] ?? 50), 100);

    $stmt = $db->prepare("
        SELECT id, channel_id, user_id, user_name, user_role, user_avatar, content, created_at
        FROM messages
        WHERE channel_id = ?
        ORDER BY id ASC
        LIMIT ?
    ");
    $stmt->execute([$channel, $limit]);
    $messages = $stmt->fetchAll();

    // Formater l'heure
    foreach ($messages as &$msg) {
        $timestamp = strtotime($msg['created_at']);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            $msg['time_display'] = "À l'instant";
        } elseif ($diff < 3600) {
            $msg['time_display'] = "Il y a " . floor($diff / 60) . " min";
        } elseif ($diff < 86400) {
            $msg['time_display'] = "Aujourd'hui à " . date('H:i', $timestamp);
        } else {
            $msg['time_display'] = date('d/m à H:i', $timestamp);
        }
    }

    echo json_encode([
        'success' => true,
        'channel' => $channel,
        'messages' => $messages
    ]);
    exit;
}

if ($method === 'POST') {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Vous devez être connecté pour publier un message.']);
        exit;
    }

    $currentUser = current_user();

    // Support payload JSON ou POST classique
    $inputData = json_decode(file_get_contents('php://input'), true);
    if (!is_array($inputData)) {
        $inputData = $_POST;
    }

    $channel = trim($inputData['channel'] ?? 'general');
    $content = trim($inputData['content'] ?? '');

    if (empty($content)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Le message ne peut pas être vide.']);
        exit;
    }

    if (strlen($content) > 2000) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Le message est trop long (maximum 2000 caractères).']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO messages (channel_id, user_id, user_name, user_role, user_avatar, content)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $channel,
        $currentUser['id'],
        $currentUser['full_name'],
        $currentUser['job_title'] ?: 'Membre',
        $currentUser['avatar'] ?: './img/avatar-maxime.jpg',
        $content
    ]);

    $newId = $db->lastInsertId();

    $newMessage = [
        'id' => (int)$newId,
        'channel_id' => $channel,
        'user_id' => $currentUser['id'],
        'user_name' => $currentUser['full_name'],
        'user_role' => $currentUser['job_title'] ?: 'Membre',
        'user_avatar' => $currentUser['avatar'] ?: './img/avatar-maxime.jpg',
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s'),
        'time_display' => "À l'instant"
    ];

    echo json_encode([
        'success' => true,
        'message' => $newMessage
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
exit;
