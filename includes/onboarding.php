<?php
/**
 * ONE VISION COMMUNITY — GESTION DU PARCOURS D'ONBOARDING ET DU QUESTIONNAIRE
 * 
 * Enregistrement progressif des réponses, gestion de l'état (en_cours, termine, mineur),
 * détection des intentions et redirections contrôlées côté serveur.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/questionnaire_data.php';

/**
 * Récupère le profil d'onboarding d'un utilisateur.
 */
function get_user_onboarding_profile(int $userId): ?array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM profils_onboarding WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}

/**
 * Assure qu'un profil d'onboarding existe pour l'utilisateur et le retourne.
 */
function ensure_user_onboarding_profile(int $userId): array {
    $profile = get_user_onboarding_profile($userId);
    if ($profile) {
        return $profile;
    }

    $db = get_db();
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO profils_onboarding (user_id, date_debut, statut, current_question_index, created_at, updated_at)
        VALUES (?, ?, 'en_cours', 1, ?, ?)
    ");
    $stmt->execute([$userId, $now, $now, $now]);

    return get_user_onboarding_profile($userId);
}

/**
 * Vérifie si l'utilisateur a terminé le questionnaire.
 */
function has_user_completed_questionnaire(int $userId): bool {
    $profile = get_user_onboarding_profile($userId);
    return ($profile && $profile['statut'] === 'termine');
}

/**
 * Vérifie si l'utilisateur a été identifié comme mineur (-18 ans).
 */
function is_user_minor(int $userId): bool {
    $profile = get_user_onboarding_profile($userId);
    return ($profile && $profile['statut'] === 'mineur');
}

/**
 * Récupère toutes les réponses d'un utilisateur au questionnaire groupées par question_id.
 */
function get_user_questionnaire_responses(int $userId): array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT question_id, reponse 
        FROM reponses_questionnaire 
        WHERE user_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $r) {
        $qid = $r['question_id'];
        if (!isset($grouped[$qid])) {
            $grouped[$qid] = [];
        }
        $grouped[$qid][] = $r['reponse'];
    }

    return $grouped;
}

/**
 * Récupère la réponse pour une question précise.
 */
function get_user_question_response(int $userId, string $questionId): array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT reponse 
        FROM reponses_questionnaire 
        WHERE user_id = ? AND question_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$userId, $questionId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Enregistre progressivement la réponse d'un utilisateur à une question.
 */
function save_user_question_response(int $userId, string $questionId, $answers): array {
    $validation = validate_question_submission($questionId, $answers);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }

    $sanitized = $validation['sanitized'];
    $question = get_question_by_id($questionId);
    $db = get_db();
    $now = gmdate('Y-m-d H:i:s');

    // S'assurer que le profil existe
    ensure_user_onboarding_profile($userId);

    // Cas spécial Q1 : Détection mineur
    if ($questionId === 'q1' && !empty($question['is_minor_trigger']) && in_array($question['is_minor_trigger'], $sanitized, true)) {
        $db->beginTransaction();
        try {
            // Nettoyer les anciennes réponses
            $db->prepare("DELETE FROM reponses_questionnaire WHERE user_id = ?")->execute([$userId]);

            // Enregistrer uniquement cette information
            $stmt = $db->prepare("INSERT INTO reponses_questionnaire (user_id, question_id, reponse, date) VALUES (?, 'q1', ?, ?)");
            $stmt->execute([$userId, $question['is_minor_trigger'], $now]);

            // Marquer le profil comme mineur et clore le questionnaire sans autre donnée
            $db->prepare("
                UPDATE profils_onboarding 
                SET tranche_age = ?, statut = 'mineur', date_fin = ?, current_question_index = 1, updated_at = ?
                WHERE user_id = ?
            ")->execute([$question['is_minor_trigger'], $now, $now, $userId]);

            $db->commit();
            return [
                'success' => true,
                'is_minor' => true,
                'question_id' => 'q1',
                'answers' => $sanitized
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => "Erreur lors de l'enregistrement : " . $e->getMessage()];
        }
    }

    $db->beginTransaction();
    try {
        // Supprimer la précédente réponse pour cette question (permet la modification lors d'un retour arrière)
        $del = $db->prepare("DELETE FROM reponses_questionnaire WHERE user_id = ? AND question_id = ?");
        $del->execute([$userId, $questionId]);

        // Insérer les nouvelles réponses
        $ins = $db->prepare("INSERT INTO reponses_questionnaire (user_id, question_id, reponse, date) VALUES (?, ?, ?, ?)");
        foreach ($sanitized as $ans) {
            $ins->execute([$userId, $questionId, $ans, $now]);
        }

        // Mettre à jour les raccourcis dans profils_onboarding
        $nextIndex = (int)$question['index'] + 1;
        $stmtProf = $db->prepare("SELECT current_question_index FROM profils_onboarding WHERE user_id = ?");
        $stmtProf->execute([$userId]);
        $currentIndex = (int)$stmtProf->fetchColumn() ?: 1;
        $newIndex = max($currentIndex, $nextIndex);

        // Mises à jour de colonnes dénormalisées pour faciliter filtres et exports
        $extraUpdates = "";
        $params = [];

        if ($questionId === 'q1') {
            $extraUpdates .= ", tranche_age = ?";
            $params[] = $sanitized[0];
        } elseif ($questionId === 'q2') {
            $extraUpdates .= ", situation = ?";
            $params[] = $sanitized[0];
        } elseif ($questionId === 'q12') {
            $extraUpdates .= ", source_decouverte = ?";
            $params[] = $sanitized[0];
        } elseif ($questionId === 'q13') {
            $extraUpdates .= ", canal_rappel_prefere = ?";
            $params[] = $sanitized[0];
        }

        $sql = "UPDATE profils_onboarding SET current_question_index = ?, updated_at = ? {$extraUpdates} WHERE user_id = ?";
        array_unshift($params, $newIndex, $now);
        $params[] = $userId;

        $db->prepare($sql)->execute($params);

        $db->commit();
        return [
            'success' => true,
            'is_minor' => false,
            'question_id' => $questionId,
            'question_index' => (int)$question['index'],
            'next_index' => $nextIndex,
            'answers' => $sanitized
        ];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => "Erreur lors de l'enregistrement de la réponse : " . $e->getMessage()];
    }
}

/**
 * Finalise le questionnaire d'accueil après la dernière question.
 */
function finish_user_questionnaire(int $userId, bool $marketingConsent = false): bool {
    ensure_user_onboarding_profile($userId);
    $db = get_db();
    $now = gmdate('Y-m-d H:i:s');
    $dateConsent = $marketingConsent ? $now : null;

    $stmt = $db->prepare("
        UPDATE profils_onboarding 
        SET statut = 'termine', 
            date_fin = ?, 
            consentement_marketing = ?, 
            date_consentement = ?, 
            updated_at = ?
        WHERE user_id = ?
    ");
    return $stmt->execute([
        $now,
        $marketingConsent ? 1 : 0,
        $dateConsent,
        $now,
        $userId
    ]);
}

/**
 * Vérifie si l'utilisateur a exprimé le souhait d'animer (réponse à la Q8).
 */
function user_wants_to_animate(int $userId): bool {
    $db = get_db();
    $triggerText = "Animer mes propres lives ou masterminds et mettre mon expertise en avant";
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM reponses_questionnaire 
        WHERE user_id = ? AND question_id = 'q8' AND reponse = ?
    ");
    $stmt->execute([$userId, $triggerText]);
    return ((int)$stmt->fetchColumn() > 0);
}

/**
 * Détermine l'URL de redirection d'onboarding pour un utilisateur donné.
 * VÉRIFICATION CÔTÉ SERVEUR STRICTE.
 * 
 * Retourne null si l'utilisateur est autorisé à naviguer librement (admin ou abonné actif).
 * Sinon retourne 'questionnaire.php' ou 'choisir-abonnement.php'.
 */
function check_onboarding_redirect(?array $user = null): ?string {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) {
        return 'login.php';
    }

    // Les administrateurs ne sont JAMAIS soumis à ce parcours
    if (is_admin_user($user)) {
        return null;
    }

    $userId = (int)$user['id'];

    // Si abonnement actif -> accès autorisé au dashboard
    $sub = check_user_subscription($userId);
    if (!empty($sub['is_active'])) {
        return null;
    }

    // Si mineur -> redirection vers questionnaire (pour afficher l'écran d'explication)
    if (is_user_minor($userId)) {
        return 'questionnaire.php';
    }

    // Si le questionnaire n'est pas terminé -> questionnaire
    if (!has_user_completed_questionnaire($userId)) {
        return 'questionnaire.php';
    }

    // Questionnaire terminé mais sans abonnement actif -> choix d'abonnement
    return 'choisir-abonnement.php';
}
