<?php
/**
 * Test unitaire & d'intégration des retours de paiement SasPay :
 * 1. Paiement confirmé : communication SasPay, message explicite, bouton Continuer vers le dashboard.
 * 2. Paiement échoué : détection échec / non-règlement, message clair "Nous n'avons pas reçu le paiement", bouton Recommencer le paiement.
 * 3. Existence des pages dédiées paiement-echoue.php et paiement-echoue.html.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/payment_service.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function it(string $description, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}" . ($detail ? " — {$detail}" : '') . "\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

echo "\n====================================================================\n";
echo "   ONE VISION COMMUNITY — TESTS RETOUR & GESTION PAIEMENTS SASPAY   \n";
echo "====================================================================\n\n";

$db = get_db();

// 1. CRÉATION D'UN PAIEMENT DE TEST POUR SIMULER UN ÉCHEC
echo "--- 1. TEST PAIEMENT ÉCHOUÉ ---\n";
$refFailed = 'PAY-TEST-FAIL-' . bin2hex(random_bytes(4));
$stmtInsert = $db->prepare("
    INSERT INTO payments (user_id, montant, devise, statut, reference_externe, session_id, methode, date_paiement)
    VALUES (127, 9.00, 'EUR', 'en_attente', ?, ?, 'saspay', datetime('now'))
");
$stmtInsert->execute([$refFailed, 'sess-fail-123']);

// Simulation de confirmation avec échec (test_status = failed)
$_GET['test_status'] = 'failed';
$resFailed = PaymentService::confirmerPaiement($refFailed);

it("Le service de paiement détecte l'échec et retourne failed = true", !empty($resFailed['failed']));
it("Le statut retourné est 'echoue'", ($resFailed['status'] ?? '') === 'echoue');

// Vérification en base de données
$stmtCheck = $db->prepare("SELECT statut FROM payments WHERE reference_externe = ?");
$stmtCheck->execute([$refFailed]);
$statusInDb = $stmtCheck->fetchColumn();
it("Le statut en base de données a été mis à jour à 'echoue'", $statusInDb === 'echoue');

unset($_GET['test_status']);

// 2. TEST D'UN PAIEMENT RÉUSSI
echo "\n--- 2. TEST PAIEMENT RÉUSSI ---\n";
$refSuccess = 'PAY-TEST-SUCC-' . bin2hex(random_bytes(4));
$stmtInsert->execute([$refSuccess, 'sess-succ-123']);

$_GET['test_status'] = 'success';
$resSuccess = PaymentService::confirmerPaiement($refSuccess);

it("Le service confirme le paiement avec success = true", $resSuccess['success'] === true);
it("Un abonnement a été créé et activé", !empty($resSuccess['subscription_id']));

$stmtCheck->execute([$refSuccess]);
$statusSuccInDb = $stmtCheck->fetchColumn();
it("Le statut en base de données est 'reussi'", $statusSuccInDb === 'reussi');

unset($_GET['test_status']);

// 3. VÉRIFICATION DU CONTENU DES FICHIERS
echo "\n--- 3. CONFORMITÉ DES INTERFACES (Paiement Retour & Paiement Échoué) ---\n";

$files = [
    'paiement-retour.php' => __DIR__ . '/../paiement-retour.php',
    'paiement-retour.html' => __DIR__ . '/../paiement-retour.html',
    'paiement-echoue.php' => __DIR__ . '/../paiement-echoue.php',
    'paiement-echoue.html' => __DIR__ . '/../paiement-echoue.html'
];

foreach ($files as $name => $path) {
    it("Le fichier {$name} existe", file_exists($path));
    $content = file_get_contents($path);
    it("Le fichier {$name} contient le bouton Recommencer le paiement", strpos($content, 'Recommencer le paiement') !== false);
}

// Vérifier que paiement-retour contient le bouton Continuer vers le dashboard
$retourPhp = file_get_contents($files['paiement-retour.php']);
it("paiement-retour.php contient le bouton Continuer vers le dashboard", strpos($retourPhp, 'Continuer vers le dashboard') !== false);
it("paiement-retour.php mentionne la réception SasPay", strpos($retourPhp, 'bel et bien reçu et validé par SasPay') !== false);
it("paiement-retour.php contient le message 'Nous n\'avons pas reçu le paiement'", strpos($retourPhp, "Nous n'avons pas reçu le paiement") !== false);

$retourHtml = file_get_contents($files['paiement-retour.html']);
it("paiement-retour.html contient le bouton Continuer vers le dashboard", strpos($retourHtml, 'Continuer vers le dashboard') !== false);
it("paiement-retour.html contient la vue d'échec dynamique", strpos($retourHtml, 'sectionFailed') !== false);

echo "\n====================================================================\n";
echo "RÉSULTATS DES TESTS RETOUR & PAIEMENT SASPAY :\n";
echo "Succès : {$passedTests} / {$totalTests}\n";
echo "Échecs : {$failedTests}\n";
echo "====================================================================\n\n";

// Nettoyage des tests
$db->prepare("DELETE FROM payments WHERE reference_externe IN (?, ?)")->execute([$refFailed, $refSuccess]);

exit($failedTests === 0 ? 0 : 1);
