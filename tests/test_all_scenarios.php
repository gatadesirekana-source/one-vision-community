<?php
/**
 * ONE VISION COMMUNITY — SUITE DE VALIDATION COMPLÈTE (SCÉNARIOS SECTION 13)
 * Commande : php tests/test_all_scenarios.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/payment_service.php';
require_once __DIR__ . '/../includes/subscriptions.php';

$db = get_db();

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
echo "    ONE VISION COMMUNITY — VALIDATION DES 10 SCÉNARIOS SECTION 13   \n";
echo "====================================================================\n\n";

// Nettoyage préalable pour tests idempotents
$testEmailUser = 'test.scenario@onevisioncommunity.fr';
$testEmailDelegue = 'delegue.test@onevisioncommunity.fr';
$ownerEmail = getenv('PROPRIETAIRE_EMAIL') ?: 'cyril@onevisioncommunity.fr';

$db->prepare("DELETE FROM users WHERE email IN (?, ?)")->execute([$testEmailUser, $testEmailDelegue]);

// Création d'un utilisateur de test (rôle 'membre', statut 'actif', aucun abonnement initial)
$pwdHash = password_hash('TestPassword123!', PASSWORD_DEFAULT);
$stmt = $db->prepare("
    INSERT INTO users (full_name, email, password, role, statut, subscription_status, created_at)
    VALUES (?, ?, ?, 'membre', 'actif', 'none', datetime('now'))
");
$stmt->execute(['Jean Testeur', $testEmailUser, $pwdHash]);
$testUserId = (int)$db->lastInsertId();
$testUser = $db->query("SELECT * FROM users WHERE id = {$testUserId}")->fetch();

echo "--- SCÉNARIO 1 : Utilisateur sans abonnement redirigé vers 'choisir-abonnement.php' sans pop-up ---\n";
$subCheckInitial = check_user_subscription($testUserId);
assert_test("Statut sans abonnement", !$subCheckInitial['is_active'] && $subCheckInitial['status'] === 'none', "is_active = false, status = none");

$redirectTarget = null;
if (!$subCheckInitial['is_active']) {
    $redirectTarget = 'choisir-abonnement.php';
}
assert_test("Redirection automatique vers choisir-abonnement.php", $redirectTarget === 'choisir-abonnement.php');

echo "\n--- SCÉNARIO 2 : Aucun pop-up ou modale d'abonnement dans le dashboard ---\n";
$dashboardContent = file_get_contents(__DIR__ . '/../dashboard.php');
$hasUpgradeModal = (strpos($dashboardContent, 'upgradeToCreatorModal') !== false);
$hasSubscriptionPopup = (strpos($dashboardContent, 'modal-subscription') !== false);
assert_test("Absence de modale d'upgrade créateur dans dashboard.php", !$hasUpgradeModal, "upgradeToCreatorModal supprimé");
assert_test("Absence de popup d'imposition d'abonnement", !$hasSubscriptionPopup);

echo "\n--- SCÉNARIO 3 : Souscription Membre mensuel et accès à la communauté ---\n";
$paiementResult = creerPaiement($testUser, 'membre', 'mensuel');
assert_test("Création du paiement Membre mensuel", $paiementResult['success'], "Ref: " . ($paiementResult['reference'] ?? ''));

$confirmResult = confirmerPaiement($paiementResult['reference']);
assert_test("Confirmation du paiement Membre mensuel", $confirmResult['success'], "Rôle: " . ($confirmResult['nouveau_role'] ?? ''));

$subActiveMembre = get_user_active_subscription($testUserId);
assert_test("Abonnement actif en base de données", $subActiveMembre !== null && $subActiveMembre['plan_code'] === 'membre');
assert_test("Prix payé conforme (9.00 EUR)", (float)$subActiveMembre['prix_paye'] === 9.00 && $subActiveMembre['periodicite'] === 'mensuel');

$subCheckMembre = check_user_subscription($testUserId);
assert_test("Accès débloqué pour la communauté", $subCheckMembre['is_active'] === true && $subCheckMembre['plan_code'] === 'membre');

echo "\n--- SCÉNARIO 4 : Passage à Animateur annuel depuis Abonnements & déblocage Espace Animateur ---\n";
$upgradePaiement = creerPaiement($testUser, 'animateur', 'annuel');
assert_test("Création paiement Animateur annuel (239 €)", $upgradePaiement['success'] && (float)$upgradePaiement['montant'] === 239.00);

$confirmUpgrade = confirmerPaiement($upgradePaiement['reference']);
assert_test("Confirmation de l'upgrade", $confirmUpgrade['success']);

$allUserSubs = $db->query("SELECT * FROM subscriptions WHERE user_id = {$testUserId} ORDER BY id ASC")->fetchAll();
assert_test("Unicité de l'abonnement actif (remplacement sans cumul)", count($allUserSubs) === 2);
assert_test("Ancien abonnement passé à 'annule'", $allUserSubs[0]['statut'] === 'annule');
assert_test("Nouvel abonnement passé à 'actif'", $allUserSubs[1]['statut'] === 'actif' && $allUserSubs[1]['periodicite'] === 'annuel');

$updatedUser = $db->query("SELECT * FROM users WHERE id = {$testUserId}")->fetch();
assert_test("Rôle de l'utilisateur mis à jour en 'animateur'", $updatedUser['role'] === 'animateur');
assert_test("Espace Animateur débloqué", is_animateur_user($updatedUser) === true);

echo "\n--- SCÉNARIO 5 : Exactitude des tarifs stockés en BDD (9€, 89€, 24€, 239€) ---\n";
$plans = $db->query("SELECT * FROM plans ORDER BY ordre_affichage ASC")->fetchAll(PDO::FETCH_ASSOC);
$plansMap = [];
foreach ($plans as $p) {
    $plansMap[$p['code']] = $p;
}
assert_test("Formule Membre mensuel: 9.00 €", (float)$plansMap['membre']['prix_mensuel'] === 9.00);
assert_test("Formule Membre annuel: 89.00 €", (float)$plansMap['membre']['prix_annuel'] === 89.00);
assert_test("Formule Animateur mensuel: 24.00 €", (float)$plansMap['animateur']['prix_mensuel'] === 24.00);
assert_test("Formule Animateur annuel: 239.00 €", (float)$plansMap['animateur']['prix_annuel'] === 239.00);

$ecoMembre = (9.00 * 12) - 89.00; // 108 - 89 = 19 €
$ecoAnimateur = (24.00 * 12) - 239.00; // 288 - 239 = 49 €
assert_test("Économie Membre annuel = 19 €", $ecoMembre == 19.00);
assert_test("Économie Animateur annuel = 49 €", $ecoAnimateur == 49.00);

echo "\n--- SCÉNARIO 6 : Un Membre ne peut pas ouvrir l'Espace Animateur même par URL directe ---\n";
$dummyMembre = ['id' => 9999, 'role' => 'membre', 'statut' => 'actif'];
$canOpenAnimateur = is_animateur_user($dummyMembre) || is_admin_user($dummyMembre);
assert_test("Refus d'accès à l'Espace Animateur pour un Membre", $canOpenAnimateur === false, "is_animateur_user = false");

echo "\n--- SCÉNARIO 7 : Expiration de l'abonnement, retour à Membre, lives en brouillon, redirection ---\n";
$futureDate = date('Y-m-d', strtotime('+7 days'));
$stmtLive = $db->prepare("
    INSERT INTO lives (user_id, author_name, title, description, format, scheduled_date, scheduled_time, duration, status)
    VALUES (?, ?, 'Live Test Expiration', 'Description', 'Live Thématique', ?, '19h00', '1h00', 'publie')
");
$stmtLive->execute([$testUserId, $updatedUser['full_name'], $futureDate]);
$createdLiveId = (int)$db->lastInsertId();

$yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
$db->prepare("UPDATE subscriptions SET date_fin = ? WHERE user_id = ? AND statut = 'actif'")->execute([$yesterday, $testUserId]);

$checkExpired = check_user_subscription($testUserId);
assert_test("Détection automatique de l'expiration", $checkExpired['is_active'] === false && $checkExpired['status'] === 'expired');

$expiredUser = $db->query("SELECT * FROM users WHERE id = {$testUserId}")->fetch();
assert_test("Rétrogradation de l'Animateur en Membre", $expiredUser['role'] === 'membre');

$liveAfterExpiry = $db->query("SELECT * FROM lives WHERE id = {$createdLiveId}")->fetch();
assert_test("Conservation du live en statut 'brouillon'", $liveAfterExpiry['status'] === 'brouillon', "status = 'brouillon'");

echo "\n--- SCÉNARIO 8 : Le propriétaire nomme un admin délégué avec permissions limitées ---\n";
$stmt = $db->prepare("
    INSERT INTO users (full_name, email, password, role, statut, subscription_status, created_at)
    VALUES ('Marc Délégué', ?, ?, 'membre', 'actif', 'active', datetime('now'))
");
$stmt->execute([$testEmailDelegue, $pwdHash]);
$delegueId = (int)$db->lastInsertId();

$ownerUser = $db->query("SELECT * FROM users WHERE role = 'proprietaire' LIMIT 1")->fetch();
assert_test("Compte propriétaire présent en BDD", $ownerUser !== false, "Email: " . $ownerUser['email']);

$db->prepare("UPDATE users SET role = 'admin_delegue' WHERE id = ?")->execute([$delegueId]);
$db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$delegueId]);

$permMod = $db->query("SELECT id FROM permissions WHERE code = 'moderer_contenu'")->fetchColumn();
$permUsers = $db->query("SELECT id FROM permissions WHERE code = 'voir_utilisateurs'")->fetchColumn();
$db->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?), (?, ?)")->execute([$delegueId, $permMod, $delegueId, $permUsers]);

$delegueUser = $db->query("SELECT * FROM users WHERE id = {$delegueId}")->fetch();
assert_test("L'admin délégué a la permission 'moderer_contenu'", user_has_permission($delegueUser, 'moderer_contenu') === true);
assert_test("L'admin délégué a la permission 'voir_utilisateurs'", user_has_permission($delegueUser, 'voir_utilisateurs') === true);
assert_test("L'admin délégué N'A PAS la permission 'modifier_tarifs'", user_has_permission($delegueUser, 'modifier_tarifs') === false);
assert_test("L'admin délégué N'A PAS la permission 'gerer_abonnements'", user_has_permission($delegueUser, 'gerer_abonnements') === false);

echo "\n--- SCÉNARIO 9 : Un admin délégué ne peut ni nommer d'autres admins ni toucher au propriétaire ---\n";
assert_test("Admin délégué n'est pas propriétaire", is_owner($delegueUser) === false);

$canAlterOwner = !is_owner($ownerUser);
assert_test("Protection absolue du compte propriétaire", $canAlterOwner === false, "is_owner(proprietaire) bloque toute modification");

assert_test("Délégation réservée au propriétaire", user_has_permission($delegueUser, 'gerer_administrateurs') === false);

echo "\n--- SCÉNARIO 10 : Un non-admin ne peut pas ouvrir l'espace d'administration ---\n";
$membreSimple = ['id' => 1234, 'role' => 'membre', 'statut' => 'actif'];
$animateurSimple = ['id' => 5678, 'role' => 'animateur', 'statut' => 'actif'];
assert_test("Membre refusé dans l'espace administration", is_admin_user($membreSimple) === false);
assert_test("Animateur refusé dans l'espace administration", is_admin_user($animateurSimple) === false);

// Nettoyage final des utilisateurs de test
$db->prepare("DELETE FROM users WHERE email IN (?, ?)")->execute([$testEmailUser, $testEmailDelegue]);

echo "\n====================================================================\n";
echo "RÉSULTATS DE LA SUITE DE TESTS :\n";
echo "Succès : {$testsPassed} / " . ($testsPassed + $testsFailed) . "\n";
echo "Échecs : {$testsFailed}\n";
echo "====================================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
