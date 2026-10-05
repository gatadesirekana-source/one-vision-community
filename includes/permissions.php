<?php
/**
 * ONE VISION COMMUNITY — GESTION DES RÔLES, PERMISSIONS FINES & DÉLÉGATION
 * 
 * Rôles :
 * - proprietaire (Super-administrateur, tous les droits sans exception)
 * - admin_delegue (Administrateur délégué avec permissions configurées par le propriétaire)
 * - animateur (Abonné Animateur actif, hérite de Membre + anime ses propres lives)
 * - membre (Abonné Membre actif)
 * - visiteur (Non connecté ou non abonné)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';

/**
 * Récupère le rôle normalisé d'un utilisateur.
 */
function get_user_role($user): string {
    if (is_numeric($user)) {
        $db = get_db();
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([(int)$user]);
        $row = $stmt->fetch();
        $role = $row['role'] ?? 'visiteur';
    } elseif (is_array($user)) {
        $role = $user['role'] ?? 'visiteur';
    } else {
        return 'visiteur';
    }

    // Normalisation de rétrocompatibilité
    if ($role === 'admin') return 'proprietaire';
    if ($role === 'speaker') return 'animateur';
    if ($role === 'member') return 'membre';

    return $role;
}

/**
 * Vérifie si l'utilisateur est le Propriétaire (Super-administrateur).
 */
function is_owner($user = null): bool {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) return false;
    return get_user_role($user) === 'proprietaire';
}

/**
 * Vérifie si l'utilisateur est un Administrateur Délégué.
 */
function is_admin_delegue($user = null): bool {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) return false;
    return get_user_role($user) === 'admin_delegue';
}

/**
 * Vérifie si l'utilisateur a un rôle d'administration (Propriétaire ou Délégué).
 */
function is_admin_user($user = null): bool {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) return false;
    $role = get_user_role($user);
    return in_array($role, ['proprietaire', 'admin_delegue'], true);
}

/**
 * Vérifie si l'utilisateur a le statut Animateur (ou supérieur).
 */
function is_animateur_user($user = null): bool {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) return false;
    $role = get_user_role($user);
    return in_array($role, ['animateur', 'proprietaire', 'admin_delegue'], true);
}

/**
 * Vérifie si l'utilisateur possède une permission fine spécifique.
 * VÉRIFICATION CÔTÉ SERVEUR STRICTE.
 */
function user_has_permission($user, string $permissionCode): bool {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) return false;

    $userId = is_array($user) ? (int)$user['id'] : (int)$user;

    // Vérifier si le compte est suspendu
    $db = get_db();
    $stmtUser = $db->prepare("SELECT role, statut FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $uData = $stmtUser->fetch();
    if (!$uData || ($uData['statut'] ?? 'actif') === 'suspendu') {
        return false;
    }

    $role = get_user_role($uData);

    // 1. Le propriétaire a TOUS les droits sans exception
    if ($role === 'proprietaire') {
        return true;
    }

    // 2. La permission 'gerer_administrateurs' est STRICTEMENT RÉSERVÉE AU PROPRIÉTAIRE
    if ($permissionCode === 'gerer_administrateurs') {
        return false;
    }

    // 3. Administrateur délégué : vérification des permissions accordées
    if ($role === 'admin_delegue') {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.code = ?
        ");
        $stmt->execute([$userId, $permissionCode]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // 4. Animateur : droit de gérer ses propres lives (vérification de propriété additionnelle sur l'action)
    if ($role === 'animateur' && $permissionCode === 'gerer_lives') {
        return true;
    }

    return false;
}

/**
 * Récupère la liste de toutes les permissions accordées à un utilisateur (codes).
 */
function get_user_permissions(int $userId): array {
    $db = get_db();
    $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $role = get_user_role($stmtUser->fetch());

    // Le propriétaire a toutes les permissions
    if ($role === 'proprietaire') {
        return $db->query("SELECT code FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmt = $db->prepare("
        SELECT p.code 
        FROM user_permissions up
        JOIN permissions p ON up.permission_id = p.id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Met à jour les permissions accordées à un administrateur délégué.
 * Seul le propriétaire peut appeler cette fonction.
 */
function set_user_permissions(int $targetUserId, array $permissionCodes, int $actingAdminId): bool {
    if (!is_owner($actingAdminId)) {
        return false;
    }

    $db = get_db();
    $db->beginTransaction();

    try {
        // Supprimer les permissions actuelles
        $del = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $del->execute([$targetUserId]);

        // Interdire d'attribuer 'gerer_administrateurs' à un délégué
        $permissionCodes = array_diff($permissionCodes, ['gerer_administrateurs']);

        if (!empty($permissionCodes)) {
            $inClause = implode(',', array_fill(0, count($permissionCodes), '?'));
            $stmtPerms = $db->prepare("SELECT id, code FROM permissions WHERE code IN ($inClause)");
            $stmtPerms->execute(array_values($permissionCodes));
            $foundPerms = $stmtPerms->fetchAll();

            $insert = $db->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
            foreach ($foundPerms as $p) {
                $insert->execute([$targetUserId, $p['id']]);
            }
        }

        // Journaliser
        log_admin_action($actingAdminId, 'modification_permissions', "Utilisateur #{$targetUserId}", implode(', ', $permissionCodes));

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * Applique un modèle prédéfini de permissions à un administrateur délégué.
 */
function apply_permission_template(int $targetUserId, string $template, int $actingAdminId): bool {
    switch ($template) {
        case 'gestionnaire_complet':
            // Tout sauf la gestion de l'équipe d'administration
            $codes = ['voir_utilisateurs', 'gerer_abonnements', 'gerer_roles', 'moderer_contenu', 'gerer_lives', 'modifier_tarifs', 'voir_revenus', 'voir_profils_membres'];
            break;
        case 'moderateur':
            // Modération du contenu et consultation des profils / suspension
            $codes = ['moderer_contenu', 'voir_utilisateurs'];
            break;
        case 'support_abonnements':
            // Consultation et gestion des abonnements
            $codes = ['voir_utilisateurs', 'gerer_abonnements', 'voir_revenus'];
            break;
        default:
            return false;
    }

    return set_user_permissions($targetUserId, $codes, $actingAdminId);
}

/**
 * Journalise une action administrative dans la table journal_actions.
 */
function log_admin_action(int $adminId, string $action, string $cible = '', string $details = ''): void {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO journal_actions (admin_id, action, cible, details, date)
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$adminId, $action, $cible, $details]);
    } catch (Exception $e) {
        // Ne jamais bloquer l'application en cas d'erreur de log
    }
}

/**
 * Verrouille une action ou une page à une permission donnée.
 */
function require_permission(string $permissionCode, string $redirect = 'index.php'): void {
    require_auth('login.php');

    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    if (($user['statut'] ?? 'actif') === 'suspendu') {
        logout_user();
        set_flash('error', "Votre compte a été suspendu par l'administration. Veuillez contacter le support.");
        header('Location: login.php');
        exit;
    }

    if (!user_has_permission($user, $permissionCode)) {
        set_flash('error', "Accès refusé : vous ne disposez pas des permissions nécessaires.");
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * Verrouille une page exclusivement au Propriétaire (Super-administrateur).
 */
function require_owner(string $redirect = 'admin/index.php'): void {
    require_auth('login.php');
    $user = current_user();

    if (!is_owner($user)) {
        set_flash('error', "Action réservée exclusivement au Propriétaire du site.");
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * Verrouille l'accès à l'Espace Administration (Propriétaire ou Administrateur délégué).
 */
function require_admin_access(string $redirect = 'dashboard.php'): void {
    require_auth('login.php');
    $user = current_user();

    if (!is_admin_user($user)) {
        set_flash('error', "Accès refusé : espace réservé à l'équipe d'administration.");
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * Verrouille l'accès à l'Espace Animateur.
 * Règle stricte (Section 3) : Un Membre qui tente d'ouvrir l'Espace Animateur, même en tapant l'adresse directement, est redirigé vers la section Abonnements.
 */
function require_animateur_access(string $redirect = 'abonnements.php'): void {
    require_auth('login.php');
    $user = current_user();

    if (!is_animateur_user($user)) {
        set_flash('info', "L'Espace Animateur est réservé aux abonnés de la formule Animateur. Découvrez tous les avantages et passez à la formule Animateur.");
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * Transfert sécurisé de propriété du site (avec confirmation par mot de passe).
 * Réservé au Propriétaire actuel.
 */
function transfer_site_ownership(int $currentOwnerId, int $newOwnerId, string $currentOwnerPassword): array {
    $db = get_db();

    // 1. Vérification du mot de passe du propriétaire actuel
    $stmtOwner = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmtOwner->execute([$currentOwnerId]);
    $currentOwner = $stmtOwner->fetch();

    if (!$currentOwner || !is_owner($currentOwner)) {
        return ['success' => false, 'error' => "Seul le propriétaire actuel peut initier un transfert de propriété."];
    }

    if (!password_verify($currentOwnerPassword, $currentOwner['password'])) {
        return ['success' => false, 'error' => "Mot de passe actuel incorrect. Le transfert a été annulé par sécurité."];
    }

    // 2. Vérification du nouveau propriétaire cible
    if ($currentOwnerId === $newOwnerId) {
        return ['success' => false, 'error' => "Vous êtes déjà le propriétaire du site."];
    }

    $stmtNew = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmtNew->execute([$newOwnerId]);
    $newOwner = $stmtNew->fetch();

    if (!$newOwner) {
        return ['success' => false, 'error' => "L'utilisateur désigné est introuvable."];
    }

    if (($newOwner['statut'] ?? 'actif') === 'suspendu') {
        return ['success' => false, 'error' => "Impossible de transférer la propriété à un compte suspendu."];
    }

    // 3. Exécution atomique du transfert
    $db->beginTransaction();
    try {
        // Promouvoir le nouveau propriétaire
        $stmt1 = $db->prepare("UPDATE users SET role = 'proprietaire', statut = 'actif' WHERE id = ?");
        $stmt1->execute([$newOwnerId]);

        // Attribuer toutes les permissions au nouveau propriétaire
        $allPerms = $db->query("SELECT id FROM permissions")->fetchAll();
        $stmtUP = $db->prepare("INSERT OR IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
        foreach ($allPerms as $p) {
            $stmtUP->execute([$newOwnerId, $p['id']]);
        }

        // Rétrograder l'ancien propriétaire en administrateur délégué avec gestionnaire complet
        $stmt2 = $db->prepare("UPDATE users SET role = 'admin_delegue' WHERE id = ?");
        $stmt2->execute([$currentOwnerId]);

        // Appliquer le template gestionnaire complet à l'ancien propriétaire
        apply_permission_template($currentOwnerId, 'gestionnaire_complet', $newOwnerId);

        // Journaliser
        log_admin_action($currentOwnerId, 'transfert_propriete', "Nouveau propriétaire: {$newOwner['email']}", "Transfert validé par mot de passe.");

        $db->commit();

        return [
            'success' => true,
            'message' => "La propriété du site a été transférée avec succès à {$newOwner['full_name']} ({$newOwner['email']})."
        ];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => "Erreur lors du transfert : " . $e->getMessage()];
    }
}
