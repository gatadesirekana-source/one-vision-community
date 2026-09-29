<?php
/**
 * ONE VISION COMMUNITY — API LIVES ET MASTERMINDS (JSON)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Authentification requise pour accéder aux lives et replays.'
    ]);
    exit;
}

$db = get_db();
$section = $_GET['section'] ?? null;

$sql = "SELECT * FROM lives";
$params = [];

if ($section) {
    $sql .= " WHERE section = ?";
    $params[] = $section;
}

$sql .= " ORDER BY scheduled_date ASC, scheduled_time ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$lives = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count' => count($lives),
    'lives' => $lives
]);
exit;
