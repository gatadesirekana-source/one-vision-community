<?php
/**
 * ONE VISION COMMUNITY — SCRIPT AUTOMATISÉ DE RENOUVELLEMENT ET RÉVOCATION D'ACCÈS
 * 
 * Ce script est conçu pour être exécuté quotidiennement ou toutes les heures (par cron ou tâche planifiée) :
 * Commande : php cron/renew_subscriptions.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/subscriptions.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // Si exécuté via le navigateur web, exiger un token de sécurité ou être admin
    $cronKey = $_GET['key'] ?? '';
    $expectedKey = getenv('CRON_SECRET_KEY') ?: 'ov_cron_secure_key_2026';
    if ($cronKey !== $expectedKey) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé : clé cron invalide.']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

$startTime = microtime(true);
$result = process_all_due_subscriptions();
$duration = round(microtime(true) - $startTime, 3);

$output = [
    'timestamp'       => date('Y-m-d H:i:s'),
    'execution_time'  => "{$duration}s",
    'due_members'     => $result['total_due'],
    'auto_renewed'    => $result['renewed'],
    'access_revoked'  => $result['revoked'],
    'message'         => "Traitement terminé avec succès. {$result['renewed']} abonnements renouvelés par prélèvement automatique, {$result['revoked']} accès révoqués pour fin de période."
];

if ($isCli) {
    echo "========================================================\n";
    echo "  ONE VISION COMMUNITY — BATCH RENEW & ACCESS CONTROL   \n";
    echo "========================================================\n";
    echo "Date & Heure        : {$output['timestamp']}\n";
    echo "Membres à échéance  : {$output['due_members']}\n";
    echo "Prélèvements réussis: {$output['auto_renewed']}\n";
    echo "Accès révoqués      : {$output['access_revoked']}\n";
    echo "Durée d'exécution   : {$output['execution_time']}\n";
    echo "========================================================\n";
} else {
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
