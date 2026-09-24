<?php
/**
 * ONE VISION COMMUNITY — API LIVES ET MASTERMINDS (JSON)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

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
