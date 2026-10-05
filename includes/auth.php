<?php
/**
 * ONE VISION COMMUNITY — AUTHENTIFICATION ET GESTION DES SESSIONS
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    if (!is_logged_in()) {
        return null;
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // L'utilisateur n'existe plus en base
        logout_user();
        return null;
    }

    return $user;
}

function require_auth(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header("Location: {$redirect}");
        exit;
    }
}

function get_client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

function check_rate_limit(string $ip, int $maxAttempts = 5, int $decaySeconds = 900): bool {
    try {
        $db = get_db();
        $cutoff = time() - $decaySeconds;
        $db->prepare("DELETE FROM login_attempts WHERE attempt_time < ?")->execute([$cutoff]);

        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempt_time >= ?");
        $stmt->execute([$ip, $cutoff]);
        $count = (int)$stmt->fetchColumn();

        return $count < $maxAttempts;
    } catch (Exception $e) {
        return true; // Fallback permissif si table temporairement indisponible
    }
}

function record_failed_attempt(string $ip): void {
    try {
        $db = get_db();
        $db->prepare("INSERT INTO login_attempts (ip, attempt_time) VALUES (?, ?)")->execute([$ip, time()]);
    } catch (Exception $e) {}
}

function reset_attempts(string $ip): void {
    try {
        $db = get_db();
        $db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
    } catch (Exception $e) {}
}

function login_user(string $email, string $password): array {
    $ip = get_client_ip();

    if (!check_rate_limit($ip, 5, 900)) {
        return ['success' => false, 'error' => 'Trop de tentatives de connexion échouées. Par mesure de sécurité, veuillez patienter 15 minutes avant de réessayer.'];
    }

    $db = get_db();
    $email = trim(strtolower($email));

    if (empty($email) || empty($password)) {
        return ['success' => false, 'error' => 'Veuillez saisir votre adresse email et votre mot de passe.'];
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        record_failed_attempt($ip);
        return ['success' => false, 'error' => 'Identifiants invalides. Vérifiez votre email et mot de passe.'];
    }

    // Vérification du statut du compte (suspension)
    if (($user['statut'] ?? 'actif') === 'suspendu') {
        record_failed_attempt($ip);
        return ['success' => false, 'error' => "Votre compte a été suspendu par l'administration. Veuillez contacter le support."];
    }

    // Réinitialisation du compteur de tentatives en cas de succès
    reset_attempts($ip);

    // Vérifier en temps réel l'expiration des abonnements
    require_once __DIR__ . '/subscriptions.php';
    check_user_subscription((int)$user['id']);

    // Recharger les données fraîches de l'utilisateur après éventuelle expiration
    $stmtRefresh = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmtRefresh->execute([$user['id']]);
    $user = $stmtRefresh->fetch();

    // Régénération de l'ID de session pour prévenir la fixation de session
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    return ['success' => true, 'user' => $user];
}

function register_user(string $fullName, string $email, string $password, array $extra = []): array {
    $db = get_db();
    $fullName = trim($fullName);
    $email = trim(strtolower($email));

    if (empty($fullName) || mb_strlen($fullName) > 100) {
        return ['success' => false, 'error' => 'Veuillez renseigner un nom valide (100 caractères maximum).'];
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Adresse email invalide.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Le mot de passe doit comporter au moins 6 caractères.'];
    }

    // Vérifier si l'email existe déjà
    $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Cette adresse email est déjà associée à un compte membre.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $company = trim($extra['company'] ?? '');
    $jobTitle = trim($extra['job_title'] ?? 'Entrepreneur & Membre One Vision');
    $bio = trim($extra['bio'] ?? '');
    $skills = trim($extra['skills'] ?? '');
    $phone = trim($extra['phone'] ?? '');
    $avatar = $extra['avatar'] ?? './img/avatar-maxime.jpg';

    $subscriptionStatus = $extra['subscription_status'] ?? 'pending';
    $role = $extra['role'] ?? 'membre';

    $stmt = $db->prepare("
        INSERT INTO users (full_name, email, password, phone, avatar, company, job_title, bio, skills, role, statut, subscription_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?)
    ");
    $stmt->execute([$fullName, $email, $passwordHash, $phone, $avatar, $company, $jobTitle, $bio, $skills, $role, $subscriptionStatus]);
    $newId = (int)$db->lastInsertId();

    // Initialiser systématiquement le profil d'accueil onboarding
    require_once __DIR__ . '/onboarding.php';
    ensure_user_onboarding_profile($newId);

    // Auto login
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $newId;
    $_SESSION['user_name'] = $fullName;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'membre';

    return ['success' => true, 'user_id' => $newId];
}

/**
 * Supprime définitivement le compte d'un utilisateur et résilie son adhésion.
 * Nettoie toutes les données associées en base SQLite.
 */
function delete_user_account(int $userId): array {
    $db = get_db();

    // 1. Vérification de l'utilisateur
    $stmt = $db->prepare("SELECT id, role, email, avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => "Compte introuvable."];
    }

    // Protection stricte : Le propriétaire principal ne peut pas supprimer son compte
    if ($user['role'] === 'proprietaire') {
        return ['success' => false, 'error' => "Le compte propriétaire principal ne peut pas être supprimé."];
    }

    try {
        $db->beginTransaction();

        // Nettoyage des réponses et profils d'onboarding
        try {
            $db->prepare("DELETE FROM reponses_questionnaire WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}
        try {
            $db->prepare("DELETE FROM profils_onboarding WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}

        // Nettoyage des abonnements et paiements
        try {
            $db->prepare("DELETE FROM subscriptions WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}
        try {
            $db->prepare("DELETE FROM payments WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}
        try {
            $db->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}

        // Nettoyage des permissions utilisateur
        try {
            $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}

        // Dissocier les messages et les lives créés
        try {
            $db->prepare("UPDATE lives SET user_id = NULL WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}
        try {
            $db->prepare("DELETE FROM messages WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {}

        // Supprimer l'utilisateur de la table users
        $stmtDelete = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmtDelete->execute([$userId]);

        $db->commit();

        return ['success' => true];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'error' => "Erreur lors de la suppression du compte : " . $e->getMessage()];
    }
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// Protection CSRF
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
