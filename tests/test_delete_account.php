<?php
/**
 * ONE VISION COMMUNITY — TEST UNITAIRE DE LA RÉSILIATION ET SUPPRESSION DE COMPTE
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/onboarding.php';
require_once __DIR__ . '/../includes/subscriptions.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($detail ? " => {$detail}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo " TEST DE LA RÉSILIATION ET SUPPRESSION DE COMPTE UTILISATEUR\n";
echo "====================================================================\n\n";

$db = get_db();

// 1. Création d'un compte de test
$testEmail = 'suppression.test.' . time() . '@test.com';
$regResult = register_user('Test Deletion Member', $testEmail, 'MotDePasse123!', [
    'role' => 'membre',
    'subscription_status' => 'active'
]);

assert_test("1.1 Création d'un membre de test", $regResult['success']);
$testUserId = (int)$regResult['user_id'];

// 2. Ajouter des réponses d'onboarding
save_user_question_response($testUserId, 'q1', ["25-34 ans"]);
save_user_question_response($testUserId, 'q2', ["Je me lance en ce moment"]);
finish_user_questionnaire($testUserId, true);

$profile = get_user_onboarding_profile($testUserId);
assert_test("2.1 Profil onboarding créé", $profile !== null && $profile['statut'] === 'termine');

// 3. Ajouter un abonnement et paiement fictif
$stmtSub = $db->prepare("
    INSERT INTO subscriptions (user_id, plan_id, periodicite, prix_paye, statut, date_debut, date_fin)
    VALUES (?, 1, 'mensuel', 9.0, 'actif', datetime('now'), datetime('now', '+30 days'))
");
$stmtSub->execute([$testUserId]);
$subId = (int)$db->lastInsertId();

$stmtPay = $db->prepare("
    INSERT INTO payments (subscription_id, user_id, montant, devise, statut, methode)
    VALUES (?, ?, 9.0, 'EUR', 'reussi', 'simulation')
");
$stmtPay->execute([$subId, $testUserId]);

// 4. Test protection : un compte propriétaire ne peut pas être supprimé
$ownerUser = $db->query("SELECT id FROM users WHERE role = 'proprietaire' LIMIT 1")->fetch();
if ($ownerUser) {
    $ownerDelRes = delete_user_account((int)$ownerUser['id']);
    assert_test("4.1 Le compte propriétaire ne peut pas être supprimé", $ownerDelRes['success'] === false);
}

// 5. Suppression effective du compte de test
$delResult = delete_user_account($testUserId);
assert_test("5.1 Appel delete_user_account() réussi", $delResult['success']);

// 6. Vérifications que tout a été purgé en BDD
$checkUser = $db->prepare("SELECT id FROM users WHERE id = ?");
$checkUser->execute([$testUserId]);
assert_test("6.1 L'utilisateur n'existe plus dans users", $checkUser->fetch() === false);

$checkProfile = $db->prepare("SELECT id FROM profils_onboarding WHERE user_id = ?");
$checkProfile->execute([$testUserId]);
assert_test("6.2 Le profil onboarding n'existe plus", $checkProfile->fetch() === false);

$checkResponses = $db->prepare("SELECT COUNT(*) FROM reponses_questionnaire WHERE user_id = ?");
$checkResponses->execute([$testUserId]);
assert_test("6.3 Les réponses au questionnaire n'existent plus", (int)$checkResponses->fetchColumn() === 0);

$checkSubs = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ?");
$checkSubs->execute([$testUserId]);
assert_test("6.4 Les abonnements ont été purgés", (int)$checkSubs->fetchColumn() === 0);

$checkPayments = $db->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ?");
$checkPayments->execute([$testUserId]);
assert_test("6.5 Les paiements ont été purgés", (int)$checkPayments->fetchColumn() === 0);

echo "\n====================================================================\n";
echo " RÉSULTATS : {$passed} SUCCÈS / {$failed} ÉCHECS\n";
echo "====================================================================\n";
exit($failed === 0 ? 0 : 1);
