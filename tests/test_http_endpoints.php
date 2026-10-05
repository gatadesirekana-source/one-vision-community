<?php
/**
 * ONE VISION COMMUNITY — VALIDATION DES ENDPOINTS HTTP EN CONDITIONS RÉELLES
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$baseUrl = 'http://127.0.0.1:8000';
$passed = 0;
$failed = 0;

function assert_http(string $name, bool $condition, string $detail = '') {
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
echo " TEST DES REQUÊTES HTTP SUR LE SERVEUR LOCAL ({$baseUrl})\n";
echo "====================================================================\n\n";

// 1. Accès anonyme à questionnaire.php -> redirection 302 vers login.php
$ch = curl_init("{$baseUrl}/questionnaire.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_http("1. Accès anonyme à questionnaire.php redirige (302)", $httpCode === 302, "code={$httpCode}");
assert_http("1b. Redirection pointe vers login.php", strpos($response, 'Location: login.php') !== false);

// 2. Accès anonyme à admin/onboarding_profils.php -> redirection vers login.php
$ch = curl_init("{$baseUrl}/admin/onboarding_profils.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_http("2. Accès anonyme à admin/onboarding_profils.php redirige (302)", $httpCode === 302, "code={$httpCode}");

// 3. Accès à choisir-abonnement.php en visiteur -> statut 200 et formules affichées
$ch = curl_init("{$baseUrl}/choisir-abonnement.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_http("3.1 choisir-abonnement.php renvoie 200 OK", $httpCode === 200);
assert_http("3.2 Formule Membre 9 € présente", strpos($html, '9 €') !== false);
assert_http("3.3 Formule Animateur 24 € présente", strpos($html, '24 €') !== false);
assert_http("3.4 Badge '2 mois offerts' présent", strpos($html, '2 mois offerts') !== false);
assert_http("3.5 Tarif annuel Membre 89 € présent", strpos($html, '89 €') !== false);
assert_http("3.6 Tarif annuel Animateur 239 € présent", strpos($html, '239 €') !== false);
assert_http("3.7 Aucun bouton Retour ni Suivant sur cette page", strpos($html, 'onboarding-btn-back') === false && strpos($html, 'onboarding-btn-next') === false);
assert_http("3.8 Aucun footer présent sur choisir-abonnement.php", strpos($html, '<footer') === false);

echo "\n====================================================================\n";
echo " RÉSULTATS HTTP : {$passed} SUCCÈS / {$failed} ÉCHECS\n";
echo "====================================================================\n";
exit($failed === 0 ? 0 : 1);
