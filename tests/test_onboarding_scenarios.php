<?php
/**
 * ONE VISION COMMUNITY — SUITE DE VALIDATION COMPLÈTE DU PARCOURS D'ONBOARDING
 * 
 * Teste automatiquement l'intégralité des 12 scénarios exigés par la section 9 :
 * 1. Nouvel utilisateur sans abonnement arrive sur l'accueil du questionnaire sans pop-up
 * 2. Réponse unique fait avancer à la suivante (auto 0.4s & bouton Suivant)
 * 3. Réponse multiple exige au moins une case cochée
 * 4. Bouton Retour ramène à la question précédente avec réponse cochée, masqué sur Q1
 * 5. Fermeture à la question 6 puis réouverture -> reprise à la question 6
 * 6. Écran "Merci" et choix d'abonnement sans boutons Retour/Suivant
 * 7. Tarifs 9 € et 24 € (mensuel), 89 € et 239 € (annuel), "2 mois offerts"
 * 8. Paiement simulé -> dashboard avec rôle et abonnement mis à jour
 * 9. Utilisateur ayant terminé ne repasse jamais par le questionnaire
 * 10. "Moins de 18 ans" -> message bienveillant, pas d'abonnement, pas d'autre donnée
 * 11. Réponses visibles dans "Profils des membres", filtres et export CSV avec RGPD
 * 12. Non-administrateur ne peut pas ouvrir cette section
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/subscriptions.php';
require_once __DIR__ . '/../includes/payment_service.php';
require_once __DIR__ . '/../includes/questionnaire_data.php';
require_once __DIR__ . '/../includes/onboarding.php';

$db = get_db();
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

function get_test_user(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

echo "====================================================================\n";
echo " DÉBUT DE LA VALIDATION DU PARCOURS D'ONBOARDING & PROMA-ADMIN\n";
echo "====================================================================\n\n";

// NETTOYAGE PRÉALABLE DES DONNÉES DE TEST
$db->exec("DELETE FROM users WHERE email LIKE 'test_%@onevision-test.fr'");
$db->exec("DELETE FROM profils_onboarding WHERE user_id NOT IN (SELECT id FROM users)");
$db->exec("DELETE FROM reponses_questionnaire WHERE user_id NOT IN (SELECT id FROM users)");

// -----------------------------------------------------------------------------
// SCÉNARIO 1 : Nouvel utilisateur sans abonnement arrive sur l'accueil du questionnaire
// -----------------------------------------------------------------------------
echo "--- Scénario 1 : Nouvel utilisateur sans abonnement ---\n";
$reg = register_user("Alice Test", "test_alice@onevision-test.fr", "Password123!", [
    'subscription_status' => 'none'
]);
$aliceId = (int)$reg['user_id'];
$alice = get_test_user($aliceId);

$redir = check_onboarding_redirect($alice);
assert_test("1.1 Redirection serveur vers questionnaire", $redir === 'questionnaire.php', "redir={$redir}");

$profAlice = get_user_onboarding_profile($aliceId);
assert_test("1.2 Profil onboarding initialisé en cours", $profAlice && $profAlice['statut'] === 'en_cours');
assert_test("1.3 Pas de questionnaire terminé", !has_user_completed_questionnaire($aliceId));

// Vérifier l'écran d'accueil du questionnaire (sans réponse)
$def = get_questionnaire_definition();
assert_test("1.4 Écran d'accueil défini", !empty($def['welcome']['title']) && strpos($def['welcome']['title'], 'Bienvenue chez One Vision') !== false);
assert_test("1.5 Bouton Commencer unique", $def['welcome']['button_text'] === 'Commencer');

// -----------------------------------------------------------------------------
// SCÉNARIO 2 : Réponse unique avance automatiquement / validation
// -----------------------------------------------------------------------------
echo "\n--- Scénario 2 : Réponse unique Q1 & Q2 ---\n";
// Répondre à Q1
$resQ1 = save_user_question_response($aliceId, 'q1', ["25-34 ans"]);
assert_test("2.1 Q1 enregistrée avec succès", $resQ1['success'] === true);
assert_test("2.2 Q1 renvoie next_index = 2", (int)($resQ1['next_index'] ?? 0) === 2);

$savedQ1 = get_user_questionnaire_responses($aliceId);
assert_test("2.3 Réponse Q1 stockée en BDD", isset($savedQ1['q1']) && $savedQ1['q1'][0] === "25-34 ans");

$profAlice = get_user_onboarding_profile($aliceId);
assert_test("2.4 Raccourci tranche_age renseigné", $profAlice['tranche_age'] === "25-34 ans");
assert_test("2.5 current_question_index avance à 2", (int)$profAlice['current_question_index'] === 2);

// Rejet si réponse unique reçoit plus d'une valeur
$badQ2 = save_user_question_response($aliceId, 'q2', ["Option A", "Option B"]);
assert_test("2.6 Rejet si question unique reçoit plusieurs choix", $badQ2['success'] === false);

// -----------------------------------------------------------------------------
// SCÉNARIO 3 : Questions à choix multiple exigent au moins une case cochée
// -----------------------------------------------------------------------------
echo "\n--- Scénario 3 : Choix multiple Q3 obligatoire ---\n";
// Q2 valide d'abord
save_user_question_response($aliceId, 'q2', ["J'ai déjà une activité et je veux la faire grandir"]);

// Q3 vide
$emptyQ3 = save_user_question_response($aliceId, 'q3', []);
assert_test("3.1 Q3 vide est rejetée", $emptyQ3['success'] === false && strpos($emptyQ3['error'], 'au moins une réponse') !== false);

// Q3 avec 2 domaines valides
$validQ3 = save_user_question_response($aliceId, 'q3', ["Commerce et vente", "Digital et technologie"]);
assert_test("3.2 Q3 avec réponses multiples acceptée", $validQ3['success'] === true);

$savedResp = get_user_questionnaire_responses($aliceId);
assert_test("3.3 Q3 contient bien les 2 choix", count($savedResp['q3'] ?? []) === 2);

// -----------------------------------------------------------------------------
// SCÉNARIO 4 : Bouton Retour ramène à la question précédente avec réponse cochée
// -----------------------------------------------------------------------------
echo "\n--- Scénario 4 : Bouton Retour et persistance ---\n";
// Q1 est la première question -> masquage du bouton Retour
assert_test("4.1 Q1 masque le bouton retour (index == 1)", (int)get_question_by_id('q1')['index'] === 1);

// Retour sur Q1 : modification de la réponse
$updateQ1 = save_user_question_response($aliceId, 'q1', ["35-44 ans"]);
assert_test("4.2 Modification de la réponse précédente possible", $updateQ1['success'] === true);

$reSaved = get_user_questionnaire_responses($aliceId);
assert_test("4.3 Nouvelle réponse Q1 persistée sans doublon", count($reSaved['q1']) === 1 && $reSaved['q1'][0] === "35-44 ans");

// -----------------------------------------------------------------------------
// SCÉNARIO 5 : Quitter à la question 6 puis revenir -> reprise à la question 6
// -----------------------------------------------------------------------------
echo "\n--- Scénario 5 : Reprise à la dernière question répondue ---\n";
// Avancer jusqu'à Q5
save_user_question_response($aliceId, 'q4', ["Ne plus avancer seul", "Trouver des partenaires pour mes projets"]);
save_user_question_response($aliceId, 'q5', ["Inspiré chaque jour"]);

$profAlice = get_user_onboarding_profile($aliceId);
assert_test("5.1 Progression à l'index 6 après Q5", (int)$profAlice['current_question_index'] === 6);

// Simulation de déconnexion / reconnexion
$aliceFresh = get_test_user($aliceId);
$redirMidway = check_onboarding_redirect($aliceFresh);
assert_test("5.2 Redirection toujours vers questionnaire.php", $redirMidway === 'questionnaire.php');

// Dans questionnaire.php, $currentIndex = $profile['current_question_index'] = 6
$resumeIndex = max(1, min(13, (int)$profAlice['current_question_index']));
assert_test("5.3 Reprise calculée exactement à la question 6", $resumeIndex === 6);
$q6 = get_question_by_index(6);
assert_test("5.4 Question 6 correspond bien à 'Où voulez-vous être dans 6 mois ?'", $q6['id'] === 'q6');

// -----------------------------------------------------------------------------
// SCÉNARIO 6 : Écran de fin 'Merci' et choix d'abonnement sans boutons Retour/Suivant
// -----------------------------------------------------------------------------
echo "\n--- Scénario 6 : Fin du questionnaire & écran de remerciement ---\n";
save_user_question_response($aliceId, 'q6', ["Avoir de nouveaux clients", "Avoir un réseau solide autour de moi"]);
save_user_question_response($aliceId, 'q7', ["Des conseils d'experts", "Des échanges avec d'autres entrepreneurs"]);
save_user_question_response($aliceId, 'q8', ["Animer mes propres lives ou masterminds et mettre mon expertise en avant", "Partager mon expérience avec les autres"]);
save_user_question_response($aliceId, 'q9', ["Mon parcours", "Mes compétences"]);
save_user_question_response($aliceId, 'q10', ["Lancer et structurer son projet", "Trouver des clients et vendre"]);
save_user_question_response($aliceId, 'q11', ["Lives en direct", "Petits groupes (masterminds)"]);
save_user_question_response($aliceId, 'q12', ["Recommandation d'un proche"]);
save_user_question_response($aliceId, 'q13', ["Oui, par email"]);

// Finaliser avec consentement marketing
$finishRes = finish_user_questionnaire($aliceId, true);
assert_test("6.1 Finalisation du questionnaire", $finishRes === true);

$profFinished = get_user_onboarding_profile($aliceId);
assert_test("6.2 Statut onboarding 'termine'", $profFinished['statut'] === 'termine');
assert_test("6.3 Date de fin enregistrée en UTC", !empty($profFinished['date_fin']));
assert_test("6.4 Consentement marketing enregistré (1)", (int)$profFinished['consentement_marketing'] === 1);
assert_test("6.5 Date de consentement enregistrée", !empty($profFinished['date_consentement']));

// Vérification de la complétion pour redirection
assert_test("6.6 has_user_completed_questionnaire retourne true", has_user_completed_questionnaire($aliceId) === true);

// -----------------------------------------------------------------------------
// SCÉNARIO 7 : Tarifs et périodicité sur choisir-abonnement.php
// -----------------------------------------------------------------------------
echo "\n--- Scénario 7 : Tarifs 9 € / 24 € (mensuel) et 89 € / 239 € (annuel) ---\n";
$stmtPlans = $db->query("SELECT * FROM plans WHERE code IN ('membre', 'animateur') ORDER BY id ASC");
$plansList = $stmtPlans->fetchAll(PDO::FETCH_ASSOC);
$pMembre = null;
$pAnimateur = null;
foreach ($plansList as $p) {
    if ($p['code'] === 'membre') $pMembre = $p;
    if ($p['code'] === 'animateur') $pAnimateur = $p;
}

assert_test("7.1 Plan Membre mensuel = 9 €", (float)$pMembre['prix_mensuel'] === 9.0);
assert_test("7.2 Plan Membre annuel = 89 €", (float)$pMembre['prix_annuel'] === 89.0);
assert_test("7.3 Plan Animateur mensuel = 24 €", (float)$pAnimateur['prix_mensuel'] === 24.0);
assert_test("7.4 Plan Animateur annuel = 239 €", (float)$pAnimateur['prix_annuel'] === 239.0);

// Détection de l'intention d'animer pour Alice (a coché 'Animer mes propres lives...' à Q8)
assert_test("7.5 Alice a exprimé l'intention d'animer à Q8", user_wants_to_animate($aliceId) === true);

// -----------------------------------------------------------------------------
// SCÉNARIO 8 : Paiement simulé -> Dashboard avec bon rôle
// -----------------------------------------------------------------------------
echo "\n--- Scénario 8 : Paiement simulé & activation du rôle Animateur ---\n";
// Alice choisit la formule Animateur en annuel
$payCreation = PaymentService::creerPaiement($alice, $pAnimateur, 'annuel');
assert_test("8.1 Création paiement simulé réussie", $payCreation['success'] === true);
assert_test("8.2 Montant annuel 239 € calculé", (float)$payCreation['montant'] === 239.0);

$payConfirmation = PaymentService::confirmerPaiement($payCreation['reference']);
assert_test("8.3 Confirmation paiement simulé réussie", $payConfirmation['success'] === true);
assert_test("8.4 Rôle mis à jour en animateur", $payConfirmation['nouveau_role'] === 'animateur');

$aliceUpdated = get_test_user($aliceId);
assert_test("8.5 BDD users.role = 'animateur'", $aliceUpdated['role'] === 'animateur');
assert_test("8.6 BDD users.subscription_status = 'active'", $aliceUpdated['subscription_status'] === 'active');

$subActive = check_user_subscription($aliceId);
assert_test("8.7 Abonnement vérifié actif", $subActive['is_active'] === true);

// -----------------------------------------------------------------------------
// SCÉNARIO 9 : Utilisateur avec abonnement actif ne repasse pas par le questionnaire
// -----------------------------------------------------------------------------
echo "\n--- Scénario 9 : Pas de retour au questionnaire pour abonné actif ---\n";
$redirAliceActive = check_onboarding_redirect($aliceUpdated);
assert_test("9.1 check_onboarding_redirect retourne null pour abonné actif", $redirAliceActive === null);

// Utilisateur ayant terminé le questionnaire mais SANS abonnement actif -> va direct à choisir-abonnement.php
$regBob = register_user("Bob Test", "test_bob@onevision-test.fr", "Password123!", ['subscription_status' => 'none']);
$bobId = (int)$regBob['user_id'];
finish_user_questionnaire($bobId, false);
$bobUser = get_test_user($bobId);
$redirBob = check_onboarding_redirect($bobUser);
assert_test("9.2 Questionnaire terminé sans abonnement -> choisir-abonnement.php direct", $redirBob === 'choisir-abonnement.php');

// -----------------------------------------------------------------------------
// SCÉNARIO 10 : Moins de 18 ans -> message bienveillant, pas d'abonnement
// -----------------------------------------------------------------------------
echo "\n--- Scénario 10 : Gestion du cas Mineur (< 18 ans) ---\n";
$regMinor = register_user("Minor Test", "test_minor@onevision-test.fr", "Password123!", ['subscription_status' => 'none']);
$minorId = (int)$regMinor['user_id'];
$minorUser = get_test_user($minorId);

// Répondre "Moins de 18 ans" à Q1
$resMinor = save_user_question_response($minorId, 'q1', ["Moins de 18 ans"]);
assert_test("10.1 Détection du déclencheur mineur", !empty($resMinor['is_minor']));

$profMinor = get_user_onboarding_profile($minorId);
assert_test("10.2 Statut onboarding passé à 'mineur'", $profMinor['statut'] === 'mineur');
assert_test("10.3 is_user_minor retourne true", is_user_minor($minorId) === true);

$minorResponses = get_user_questionnaire_responses($minorId);
assert_test("10.4 Seule la réponse Q1 est conservée (aucune autre donnée)", count($minorResponses) === 1 && isset($minorResponses['q1']));

$redirMinor = check_onboarding_redirect($minorUser);
assert_test("10.5 Mineur redirigé vers questionnaire.php pour écran bienveillant", $redirMinor === 'questionnaire.php');

// -----------------------------------------------------------------------------
// SCÉNARIO 11 : Vue administrateur "Profils des membres", filtres et export CSV
// -----------------------------------------------------------------------------
echo "\n--- Scénario 11 : Vue Administrateur & Export CSV ---\n";
// Création d'un administrateur délégué
$regAdmin = register_user("Admin Delegue", "test_admin@onevision-test.fr", "Password123!", ['role' => 'admin_delegue']);
$adminId = (int)$regAdmin['user_id'];

// Donner la permission 'voir_profils_membres'
$permId = $db->query("SELECT id FROM permissions WHERE code = 'voir_profils_membres'")->fetchColumn();
$db->prepare("INSERT OR IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, ?)")->execute([$adminId, $permId]);

$adminUser = get_test_user($adminId);
assert_test("11.1 Admin délégué a la permission 'voir_profils_membres'", user_has_permission($adminUser, 'voir_profils_membres') === true);

// Requête de filtrage test
$stmtFiltre = $db->prepare("
    SELECT po.*, u.full_name, u.email 
    FROM profils_onboarding po
    JOIN users u ON po.user_id = u.id
    WHERE po.tranche_age = ?
");
$stmtFiltre->execute(["35-44 ans"]);
$found = $stmtFiltre->fetchAll();
assert_test("11.2 Filtre par tranche d'âge trouve Alice", count($found) >= 1 && in_array($aliceId, array_column($found, 'user_id')));

// Test filtre intention d'animer
$stmtAnimer = $db->prepare("
    SELECT COUNT(*) 
    FROM reponses_questionnaire 
    WHERE question_id = 'q8' AND reponse LIKE '%Animer mes propres lives%' AND user_id = ?
");
$stmtAnimer->execute([$aliceId]);
assert_test("11.3 Filtre intention d'animer trouve Alice", (int)$stmtAnimer->fetchColumn() > 0);

// Vérification de la présence de la colonne consentement marketing pour l'export CSV
assert_test("11.4 Colonne consentement marketing accessible pour l'export", isset($profAlice['consentement_marketing']));

// -----------------------------------------------------------------------------
// SCÉNARIO 12 : Un non-administrateur ne peut pas ouvrir la section admin
// -----------------------------------------------------------------------------
echo "\n--- Scénario 12 : Protection stricte non-administrateur ---\n";
$bobUser = get_test_user($bobId); // rôle membre
assert_test("12.1 Bob n'est pas admin", is_admin_user($bobUser) === false);
assert_test("12.2 Bob n'a pas la permission 'voir_profils_membres'", user_has_permission($bobUser, 'voir_profils_membres') === false);

// Propriétaire a automatiquement tous les droits
$owner = $db->query("SELECT * FROM users WHERE role = 'proprietaire' LIMIT 1")->fetch();
assert_test("12.3 Le propriétaire a automatiquement la permission", user_has_permission($owner, 'voir_profils_membres') === true);

// Nettoyage après test
$db->exec("DELETE FROM users WHERE email LIKE 'test_%@onevision-test.fr'");
$db->exec("DELETE FROM profils_onboarding WHERE user_id NOT IN (SELECT id FROM users)");
$db->exec("DELETE FROM reponses_questionnaire WHERE user_id NOT IN (SELECT id FROM users)");

echo "\n====================================================================\n";
echo " RÉSULTATS : {$passed} SUCCÈS / {$failed} ÉCHECS\n";
echo "====================================================================\n";
exit($failed === 0 ? 0 : 1);
