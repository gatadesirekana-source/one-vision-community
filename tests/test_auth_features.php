<?php
/**
 * Test unitaire & d'intégration des fonctionnalités d'authentification :
 * 1. Validation stricte et détection des fausses adresses email (RFC + blacklist domaines jetables + DNS).
 * 2. Présence et conformité de l'icône oeil (toggle mot de passe) sur Login et Register (.php et .html).
 * 3. Présence du sélecteur multi-comptes Google sur Login et Register (.php et .html).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

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
echo "   ONE VISION COMMUNITY — TESTS AUTHENTIFICATION & SÉCURITÉ EMAIL   \n";
echo "====================================================================\n\n";

// --- 1. VALIDATION STRICTE DES ADRESSES EMAIL AUTHENTIQUES ---
echo "--- 1. VALIDATION STRICTE DES EMAILS (includes/auth.php) ---\n";

$validEmails = [
    'gatadesirekana@gmail.com',
    'alexandre.martin@gmail.com',
    'contact@google.com',
    'cyril@onevisioncommunity.fr'
];

foreach ($validEmails as $email) {
    $res = validate_authentic_email($email);
    it("Accepte l'adresse email authentique '{$email}'", $res['valid'] === true, $res['error'] ?? 'Valide');
}

$fakeEmails = [
    'user@tempmail.com' => 'domaine jetable tempmail.com',
    'hacker@mailinator.com' => 'domaine jetable mailinator.com',
    'spam@yopmail.com' => 'domaine jetable yopmail.com',
    'fake@fake.com' => 'domaine jetable fake.com',
    'test@test.com' => 'domaine bidon test.com',
    'invalid-email' => 'format invalide sans @',
    'user@' => 'format invalide sans domaine',
    '@domain.com' => 'format invalide sans nom',
    'user@domain' => 'TLD manquant',
    'user@@gmail.com' => 'arobase en double'
];

foreach ($fakeEmails as $fakeEmail => $reason) {
    $res = validate_authentic_email($fakeEmail);
    it("Rejette '{$fakeEmail}' ({$reason})", $res['valid'] === false, $res['error'] ?? '');
}

// --- 2. VÉRIFICATION DES FICHIERS FRONTEND (PHP & HTML) ---
echo "\n--- 2. CONFORMITÉ DES INTERFACES (Login & Register) ---\n";

$filesToCheck = [
    'login.php' => __DIR__ . '/../login.php',
    'register.php' => __DIR__ . '/../register.php',
    'login.html' => __DIR__ . '/../login.html',
    'register.html' => __DIR__ . '/../register.html',
];

foreach ($filesToCheck as $fileName => $filePath) {
    $content = file_get_contents($filePath);
    it("Le fichier {$fileName} existe et n'est pas vide", !empty($content));
    
    // Vérification de l'icône oeil pour afficher/masquer le mot de passe
    $hasToggleWrapper = strpos($content, 'password-input-wrapper') !== false;
    $hasToggleBtn = strpos($content, 'password-toggle-btn') !== false;
    $hasToggleFn = strpos($content, 'togglePasswordVisibility') !== false;
    it("{$fileName} intègre le wrapper et bouton oeil pour le mot de passe", $hasToggleWrapper && $hasToggleBtn && $hasToggleFn);

    // Vérification du conteneur de message d'erreur d'email
    $hasEmailFeedback = strpos($content, 'email-feedback') !== false;
    it("{$fileName} intègre le feedback de validation d'email authentique", $hasEmailFeedback);

    // Vérification du bouton multi-comptes Google
    $hasGooglePicker = strpos($content, 'showGoogleAccountPicker') !== false;
    it("{$fileName} intègre le sélecteur multi-comptes Google", $hasGooglePicker);

    // Vérification de l'inclusion de auth-utils.js
    $hasAuthUtils = strpos($content, 'auth-utils.js') !== false;
    it("{$fileName} charge le module js/auth-utils.js", $hasAuthUtils);
}

echo "\n====================================================================\n";
echo "RÉSULTATS DES TESTS D'AUTHENTIFICATION :\n";
echo "Succès : {$passedTests} / {$totalTests}\n";
echo "Échecs : {$failedTests}\n";
echo "====================================================================\n\n";

exit($failedTests === 0 ? 0 : 1);
