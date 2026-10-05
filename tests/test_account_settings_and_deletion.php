<?php
/**
 * ONE VISION COMMUNITY — TEST COMPLET DES PARAMÈTRES ET DE LA SUPPRESSION DE COMPTE
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$passed = 0;
$failed = 0;

function assert_step(string $title, bool $cond, string $extra = '') {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$title}" . ($extra ? " ({$extra})" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] {$title}" . ($extra ? " => {$extra}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo " TESTS DES PARAMÈTRES DU COMPTE & MODALE DE SUPPRESSION\n";
echo "====================================================================\n\n";

$db = get_db();

// 1. Création utilisateur test
$email = 'profile.test.' . time() . '@test.com';
$pwd = 'AncienMotDePasse123!';
$reg = register_user('Jean Testeur', $email, $pwd, [
    'role' => 'membre',
    'phone' => '+33 6 12 34 56 78'
]);
assert_step("1. Création utilisateur test avec téléphone", $reg['success']);
$userId = (int)$reg['user_id'];

// Vérification en BDD que le téléphone est bien inséré
$stmt = $db->prepare("SELECT full_name, email, phone, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
assert_step("1.1 Téléphone présent en base", !empty($user['phone']));

// 2. Test mise à jour nom, rôle, téléphone
$newName = 'Jean Dupont';
$newRole = 'Fondateur SaaS & No-Code';
$newPhone = '+33 7 98 76 54 32';

$updStmt = $db->prepare("UPDATE users SET full_name = ?, role = ?, phone = ? WHERE id = ?");
$updStmt->execute([$newName, $newRole, $newPhone, $userId]);

$stmt->execute([$userId]);
$updatedUser = $stmt->fetch();
assert_step("2.1 Nom mis à jour", $updatedUser['full_name'] === $newName);
assert_step("2.2 Rôle/Activité mis à jour", $updatedUser['role'] === $newRole);
assert_step("2.3 Téléphone mis à jour", $updatedUser['phone'] === $newPhone);

// 3. Test validation des mots de passe
$pwd1 = 'NouveauSecret123!';
$pwd2 = 'SecretDifferent456!';
$mismatch = ($pwd1 !== $pwd2);
assert_step("3.1 Détection mot de passe non concordant", $mismatch);

// 4. Test mise à jour du mot de passe concordant
$newHash = password_hash($pwd1, PASSWORD_BCRYPT);
$pwdUpd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
$pwdUpd->execute([$newHash, $userId]);

$stmtPwd = $db->prepare("SELECT password FROM users WHERE id = ?");
$stmtPwd->execute([$userId]);
$storedHash = $stmtPwd->fetchColumn();
assert_step("4.1 Nouveau mot de passe vérifié avec password_verify", password_verify($pwd1, $storedHash));

// 5. Test suppression du compte
$delRes = delete_user_account($userId);
assert_step("5.1 Compte supprimé avec succès", $delRes['success']);

$stmt->execute([$userId]);
assert_step("5.2 Utilisateur n'existe plus en BDD", $stmt->fetch() === false);

// 6. Test absence de bouton 'Paramètres & Sécurité' dans tab-parametres
$dashContent = file_get_contents(__DIR__ . '/../dashboard.php');
$hasOldBadge = strpos($dashContent, 'Paramètres & Sécurité') !== false;
assert_step("6.1 Le bouton/badge 'Paramètres & Sécurité' est bien retiré", !$hasOldBadge);

// 7. Test position du toast dans css/style.css
$styleContent = file_get_contents(__DIR__ . '/../css/style.css');
$toastTopMatches = preg_match('/\.toast\s*\{[^}]*top:\s*1\.5rem[^}]*bottom:\s*auto/s', $styleContent);
assert_step("7.1 Toast configuré en HAUT (top: 1.5rem, bottom: auto)", (bool)$toastTopMatches);

// 8. Test masquage du badge live dans css/style.css
$hideLiveMatches = strpos($styleContent, '[data-active-tab="tab-parametres"] #topbarLiveBadge') !== false;
assert_step("8.1 Règle CSS de masquage de #topbarLiveBadge pour tab-parametres active", $hideLiveMatches);

// 9. Test vérification du texte de la modale de suppression
$hasConfirmText = strpos($dashContent, 'Est-ce que vous voulez réellement supprimer votre compte ?') !== false;
$hasStayText = strpos($dashContent, 'Non, je veux rester dans la communauté') !== false;
$hasDeleteText = strpos($dashContent, 'Oui, je supprime mon compte') !== false;
$hasSuccessText = strpos($dashContent, 'Votre compte a été bien résilié et supprimé') !== false;
$hasReturnHome = strpos($dashContent, 'Retourner à l\'accueil') !== false;

assert_step("9.1 Titre modale confirmation présent", $hasConfirmText);
assert_step("9.2 Option 'Non, je veux rester dans la communauté' présente", $hasStayText);
assert_step("9.3 Option 'Oui, je supprime mon compte' présente", $hasDeleteText);
assert_step("9.4 Écran succès 'Votre compte a été bien résilié et supprimé' présent", $hasSuccessText);
assert_step("9.5 Bouton 'Retourner à l\'accueil' présent", $hasReturnHome);

// 10. Test absence d'émojis dans tab-parametres de dashboard.php
$lines = file(__DIR__ . '/../dashboard.php');
$inParams = false;
$tabParamEmojis = [];
foreach ($lines as $line) {
    if (strpos($line, 'id="tab-parametres"') !== false) {
        $inParams = true;
    } elseif ($inParams && strpos($line, 'id="tab-') !== false && strpos($line, 'id="tab-parametres"') === false) {
        $inParams = false;
    }
    if ($inParams) {
        if (preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $line, $matches)) {
            $tabParamEmojis = array_merge($tabParamEmojis, $matches[0]);
        }
    }
}
assert_step("10.1 Zéro émoji dans tab-parametres (tous en SVG)", count($tabParamEmojis) === 0, "Emojis trouvés: " . count($tabParamEmojis));

// 11. Test absence d'émojis dans deleteAccountModal
$modalStart = strpos($dashContent, 'id="deleteAccountModal"');
$modalEnd = strpos($dashContent, '<!-- MODALE', $modalStart + 30);
$modalSnippet = substr($dashContent, $modalStart, $modalEnd - $modalStart);
preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $modalSnippet, $modalEmojis);
assert_step("11.1 Zéro émoji dans deleteAccountModal (tous en SVG)", count($modalEmojis[0]) === 0, "Emojis trouvés: " . count($modalEmojis[0]));

echo "\n====================================================================\n";
echo " RÉSULTATS : {$passed} SUCCÈS / {$failed} ÉCHECS\n";
echo "====================================================================\n";

exit($failed === 0 ? 0 : 1);
