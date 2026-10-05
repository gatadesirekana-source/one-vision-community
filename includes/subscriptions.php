<?php
/**
 * ONE VISION COMMUNITY — GESTION DES ABONNEMENTS, FORMULES & CONTRÔLE D'ACCÈS
 * 
 * Ce module gère :
 * 1. Le contrôle d'accès strict (seuls les membres abonnés actifs accèdent au dashboard et à la communauté)
 * 2. L'accès permanent pour le Propriétaire et les Administrateurs délégués (jamais bloqués)
 * 3. La vérification dynamique de l'expiration et le passage en "brouillon" des lives futurs d'un Animateur expiré
 * 4. La résiliation avec fin d'accès à l'échéance déjà réglée
 * 5. La compatibilité avec les pages de choix d'abonnement (sans aucun pop-up)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/payment_service.php';

/**
 * Récupère l'abonnement actif actuel d'un utilisateur avec les données de sa formule.
 */
function get_user_active_subscription(int $userId): ?array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT s.*, p.code as plan_code, p.nom as plan_nom, p.prix_mensuel, p.prix_annuel, p.devise, p.avantages
        FROM subscriptions s
        JOIN plans p ON s.plan_id = p.id
        WHERE s.user_id = ? AND s.statut = 'actif'
        ORDER BY s.id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $sub = $stmt->fetch();
    return $sub ?: null;
}

/**
 * Récupère l'historique complet des abonnements d'un utilisateur.
 */
function get_user_subscriptions_history(int $userId): array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT s.*, p.code as plan_code, p.nom as plan_nom, p.devise
        FROM subscriptions s
        JOIN plans p ON s.plan_id = p.id
        WHERE s.user_id = ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Récupère l'historique des paiements d'un utilisateur.
 */
function get_user_payments_history(int $userId): array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT pay.*, p.nom as plan_nom, s.periodicite
        FROM payments pay
        LEFT JOIN subscriptions s ON pay.subscription_id = s.id
        LEFT JOIN plans p ON s.plan_id = p.id
        WHERE pay.user_id = ?
        ORDER BY pay.id DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Vérifie le statut d'abonnement d'un utilisateur et traite l'expiration en temps réel.
 */
function check_user_subscription(int $userId): array {
    $db = get_db();
    $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        return ['is_active' => false, 'status' => 'not_found', 'reason' => 'Utilisateur introuvable'];
    }

    // Le Propriétaire et les Administrateurs délégués ne sont JAMAIS bloqués
    if (is_admin_user($user)) {
        return [
            'is_active'  => true,
            'status'     => 'active',
            'is_admin'   => true,
            'plan_code'  => 'admin',
            'plan_nom'   => 'Administration Complète',
            'days_left'  => 9999,
            'expires_at' => null
        ];
    }

    $sub = get_user_active_subscription($userId);
    $nowUtc = gmdate('Y-m-d H:i:s');

    // 1. Cas : Aucun abonnement actif en base
    if (!$sub) {
        // Vérifier si l'utilisateur a déjà eu un abonnement par le passé
        $hasHistory = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ?");
        $hasHistory->execute([$userId]);
        $count = (int)$hasHistory->fetchColumn();

        $status = ($count > 0 || $user['subscription_status'] === 'expired') ? 'expired' : 'none';

        return [
            'is_active' => false,
            'status'    => $status,
            'reason'    => ($status === 'expired') 
                ? 'Votre abonnement a expiré, renouvelez-le pour continuer.' 
                : 'Aucun abonnement actif souscrit.'
        ];
    }

    // 2. Cas : Abonnement actif mais date d'échéance dépassée -> EXPIRATION
    if ($sub['date_fin'] < $nowUtc) {
        // Traiter l'expiration
        expire_user_subscription($sub, $user);

        return [
            'is_active' => false,
            'status'    => 'expired',
            'reason'    => 'Votre abonnement a expiré, renouvelez-le pour continuer.',
            'plan_code' => $sub['plan_code']
        ];
    }

    // 3. Cas : Abonnement actif et valide
    $daysLeft = max(0, (int)ceil((strtotime($sub['date_fin']) - strtotime($nowUtc)) / 86400));
    $isCancelled = ((int)$sub['renouvellement_auto'] === 0);

    return [
        'is_active'     => true,
        'status'        => 'active',
        'subscription'  => $sub,
        'plan_code'     => $sub['plan_code'],
        'plan_nom'      => $sub['plan_nom'],
        'periodicite'   => $sub['periodicite'],
        'prix_paye'     => $sub['prix_paye'],
        'date_debut'    => $sub['date_debut'],
        'date_fin'      => $sub['date_fin'],
        'days_left'     => $daysLeft,
        'is_cancelled'  => $isCancelled,
        'cancellation_notice' => $isCancelled 
            ? "Votre abonnement prendra fin le " . date('d/m/Y', strtotime($sub['date_fin'])) 
            : null
    ];
}

/**
 * Traite l'expiration d'un abonnement :
 * - Passe la souscription en statut 'expire'
 * - Rétrograde l'utilisateur en rôle 'membre' s'il était Animateur
 * - Passe ses lives futurs en 'brouillon'
 */
function expire_user_subscription(array $sub, array $user): void {
    $db = get_db();
    $userId = (int)$user['id'];

    $db->beginTransaction();
    try {
        // 1. Mettre à jour la souscription
        $stmtSub = $db->prepare("UPDATE subscriptions SET statut = 'expire', updated_at = datetime('now') WHERE id = ?");
        $stmtSub->execute([$sub['id']]);

        // 2. Si Animateur, rétrograder en Membre
        $newRole = ($user['role'] === 'animateur') ? 'membre' : $user['role'];

        $stmtUser = $db->prepare("
            UPDATE users 
            SET role = ?, subscription_status = 'expired'
            WHERE id = ?
        ");
        $stmtUser->execute([$newRole, $userId]);

        // 3. Passer les lives à venir de cet animateur en 'brouillon'
        if ($user['role'] === 'animateur') {
            $today = date('Y-m-d');
            $stmtLives = $db->prepare("
                UPDATE lives 
                SET status = 'brouillon' 
                WHERE user_id = ? AND scheduled_date >= ?
            ");
            $stmtLives->execute([$userId, $today]);
        }

        // Mettre à jour la session courante si applicable
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['user_role'] = $newRole;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
    }
}

/**
 * Verrouille une page aux seuls membres ayant un abonnement ACTIF et non expiré.
 * Redirige vers 'choisir-abonnement.php' sans aucun pop-up.
 */
function require_active_subscription(string $redirect = 'choisir-abonnement.php'): void {
    require_auth('login.php');

    $currentUser = current_user();
    if (!$currentUser) {
        header('Location: login.php');
        exit;
    }

    // Compte suspendu
    if (($currentUser['statut'] ?? 'actif') === 'suspendu') {
        logout_user();
        set_flash('error', "Votre compte a été suspendu par l'administration. Veuillez contacter le support.");
        header('Location: login.php');
        exit;
    }

    // Le Propriétaire et les Administrateurs délégués ne sont jamais bloqués
    if (is_admin_user($currentUser)) {
        return;
    }

    $subCheck = check_user_subscription((int)$currentUser['id']);

    if (!$subCheck['is_active']) {
        require_once __DIR__ . '/onboarding.php';
        $redir = check_onboarding_redirect($currentUser);

        if ($redir === 'questionnaire.php') {
            header("Location: questionnaire.php");
            exit;
        }

        if ($subCheck['status'] === 'expired') {
            set_flash('warning', "Votre abonnement a expiré, renouvelez-le pour continuer.");
        } else {
            set_flash('info', "Veuillez choisir votre formule d'abonnement pour accéder à la communauté One Vision.");
        }
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * Résilie le renouvellement automatique d'un abonnement.
 * L'accès reste actif jusqu'à la date d'échéance déjà payée.
 */
function resilier_abonnement(int $userId): array {
    $db = get_db();
    $sub = get_user_active_subscription($userId);

    // Si aucun actif, chercher le dernier abonnement
    if (!$sub) {
        $stmtLast = $db->prepare("SELECT s.*, p.code as plan_code, p.nom as plan_nom FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.user_id = ? ORDER BY s.id DESC LIMIT 1");
        $stmtLast->execute([$userId]);
        $sub = $stmtLast->fetch();
    }

    if (!$sub) {
        return ['success' => false, 'error' => "Aucun abonnement trouvé à résilier."];
    }

    try {
        $stmt = $db->prepare("
            UPDATE subscriptions 
            SET statut = 'resilie',
                renouvellement_auto = 0, 
                date_resiliation = datetime('now'),
                updated_at = datetime('now')
            WHERE user_id = ? AND (statut = 'actif' OR id = ?)
        ");
        $stmt->execute([$userId, $sub['id']]);

        // Mise à jour de l'utilisateur : rétrogradation du rôle à 'membre', statut 'expired'
        $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $uRole = $stmtUser->fetchColumn();

        $newRole = in_array($uRole, ['proprietaire', 'admin_delegue'], true) ? $uRole : 'membre';

        $db->prepare("
            UPDATE users 
            SET role = ?,
                subscription_status = 'expired',
                subscription_plan = 'none',
                auto_renew = 0 
            WHERE id = ?
        ")->execute([$newRole, $userId]);

        // Dépublier les futurs lives si c'était un animateur
        if ($uRole === 'animateur') {
            $today = date('Y-m-d');
            $db->prepare("UPDATE lives SET status = 'brouillon' WHERE user_id = ? AND scheduled_date >= ?")->execute([$userId, $today]);
        }

        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['user_role'] = $newRole;
        }

        $planNom = $sub['plan_nom'] ?? 'Animateur';
        $message = "Votre formule {$planNom} a bien été résiliée. Vos privilèges ont été désactivés. Veuillez choisir une formule ci-dessous pour continuer à profiter de la communauté.";

        return [
            'success'   => true,
            'message'   => $message
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => "Erreur lors de la résiliation : " . $e->getMessage()];
    }
}

/**
 * Exécute la vérification de toutes les expirations d'abonnements.
 * Idéal pour l'exécution par tâche automatique quotidienne (cron) ou vérification périodique.
 */
function process_all_due_subscriptions(): array {
    $db = get_db();
    $nowUtc = gmdate('Y-m-d H:i:s');

    $stmt = $db->prepare("
        SELECT s.*, u.role, u.full_name, u.email 
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        WHERE s.statut = 'actif' AND s.date_fin <= ?
    ");
    $stmt->execute([$nowUtc]);
    $dueSubs = $stmt->fetchAll();

    $expiredCount = 0;
    foreach ($dueSubs as $sub) {
        expire_user_subscription($sub, $sub);
        $expiredCount++;
    }

    return [
        'total_due' => count($dueSubs),
        'expired'   => $expiredCount
    ];
}
