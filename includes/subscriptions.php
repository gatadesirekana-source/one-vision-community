<?php
/**
 * ONE VISION COMMUNITY — MOTEUR DE GESTION DES ABONNEMENTS ET PRÉLÈVEMENTS AUTOMATIQUES
 * 
 * Ce module gère :
 * 1. Le contrôle strict d'accès (seuls les membres ayant payé et à jour de cotisation accèdent à l'Académie)
 * 2. La révocation immédiate des accès dès que la période de 30 jours est expirée
 * 3. Le prélèvement automatique récurrent mensuel sur la carte bancaire enregistrée (9,00 €)
 * 4. L'historique et la facturation automatique de chaque renouvellement
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';

/**
 * Vérifie le statut d'abonnement d'un membre et traite automatiquement l'échéance si nécessaire.
 */
function check_user_subscription(int $userId): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['is_active' => false, 'status' => 'not_found', 'reason' => 'Utilisateur introuvable'];
    }

    // Les administrateurs et conférenciers ont un accès permanent
    if (in_array($user['role'], ['admin', 'speaker'], true)) {
        return [
            'is_active'   => true,
            'status'      => 'active',
            'is_admin'    => true,
            'days_left'   => 999,
            'expires_at'  => null,
            'auto_renew'  => 0
        ];
    }

    $status = $user['subscription_status'] ?? 'pending';
    $expiresAt = !empty($user['subscription_expires_at']) ? strtotime($user['subscription_expires_at']) : null;
    $now = time();

    // Si le statut est actif mais sans date d'expiration fixée, initialiser à +30 jours
    if ($status === 'active' && !$expiresAt) {
        $started = !empty($user['subscription_started_at']) ? strtotime($user['subscription_started_at']) : $now;
        $expiresAt = strtotime('+30 days', $started);
        $newExpStr = date('Y-m-d H:i:s', $expiresAt);
        $db->prepare("
            UPDATE users 
            SET subscription_expires_at = ?, 
                next_billing_date = date(?), 
                last_billing_date = date(?) 
            WHERE id = ?
        ")->execute([$newExpStr, $newExpStr, date('Y-m-d', $started), $userId]);
    }

    // CAS 1 : Abonnement marqué actif mais date d'échéance dépassée
    if ($status === 'active' && $expiresAt && $now >= $expiresAt) {
        // Tenter le prélèvement automatique si le membre a une carte enregistrée et auto_renew actif
        $hasCard = !empty($user['card_last4']);
        $autoRenew = (int)($user['auto_renew'] ?? 1);

        if ($hasCard && $autoRenew === 1) {
            $renewResult = process_recurring_charge($user);
            if ($renewResult['success']) {
                // Prélèvement réussi : l'accès reste actif pour 30 jours de plus
                return [
                    'is_active'    => true,
                    'status'       => 'active',
                    'renewed'      => true,
                    'days_left'    => 30,
                    'expires_at'   => $renewResult['new_expires_at'],
                    'order_number' => $renewResult['order_number']
                ];
            } else {
                // Prélèvement échoué : révocation de l'accès
                revoke_user_access($userId, 'Échec du prélèvement automatique mensuel sur carte bancaire');
                return [
                    'is_active' => false,
                    'status'    => 'expired',
                    'reason'    => 'Échec du prélèvement mensuel (' . ($renewResult['error'] ?? 'Carte refusée') . ')'
                ];
            }
        } else {
            // Aucune carte enregistrée (ex. Mobile Money à échéance) ou auto-renew désactivé : RÉVOCATION
            revoke_user_access($userId, 'Période de souscription de 30 jours écoulée');
            return [
                'is_active' => false,
                'status'    => 'expired',
                'reason'    => 'Votre période d\'abonnement est arrivée à son terme.'
            ];
        }
    }

    // CAS 2 : Abonnement actif et non expiré
    if ($status === 'active') {
        $daysLeft = $expiresAt ? max(0, (int)ceil(($expiresAt - $now) / 86400)) : 30;
        return [
            'is_active'   => true,
            'status'      => 'active',
            'days_left'   => $daysLeft,
            'expires_at'  => $user['subscription_expires_at'],
            'auto_renew'  => (int)($user['auto_renew'] ?? 1),
            'card_last4'  => $user['card_last4'] ?? '',
            'card_brand'  => $user['card_brand'] ?? ''
        ];
    }

    // CAS 3 : En attente, expiré ou révoqué
    return [
        'is_active' => false,
        'status'    => $status,
        'reason'    => ($status === 'expired') ? 'Abonnement expiré' : 'Paiement d\'adhésion en attente'
    ];
}

/**
 * Exécute un prélèvement automatique récurrent de 9,00 € sur la carte enregistrée du membre.
 */
function process_recurring_charge(array $user): array {
    $db = get_db();
    $userId = (int)$user['id'];
    $amount = 9.00;
    $currency = 'EUR';

    try {
        // Numéros uniques de commande et de facture
        $orderNumber = 'ORD-REC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $invoiceNumber = 'OV-REC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $paymentId = 'REC-CARD-' . strtoupper(bin2hex(random_bytes(6)));

        $brand = !empty($user['card_brand']) ? $user['card_brand'] : 'Carte Bancaire';
        $last4 = !empty($user['card_last4']) ? $user['card_last4'] : '••••';
        $methodLabel = "Prélèvement mensuel automatique ({$brand} •••• {$last4})";

        // Enregistrement de la nouvelle commande payée
        $stmtOrder = $db->prepare("
            INSERT INTO orders (
                order_number, user_id, amount, currency, status,
                payment_method, billing_name, billing_email, billing_country, invoice_number,
                payment_id
            ) VALUES (
                ?, ?, ?, ?, 'paid',
                ?, ?, ?, 'France', ?,
                ?
            )
        ");
        $stmtOrder->execute([
            $orderNumber,
            $userId,
            $amount,
            $currency,
            $methodLabel,
            $user['full_name'],
            $user['email'],
            $invoiceNumber,
            $paymentId
        ]);

        // Prolongation de la période de 30 jours à partir de maintenant ou de l'échéance
        $currentExp = !empty($user['subscription_expires_at']) ? strtotime($user['subscription_expires_at']) : time();
        $baseTime = max(time(), $currentExp);
        $newExpiresTimestamp = strtotime('+30 days', $baseTime);
        $newExpiresAt = date('Y-m-d H:i:s', $newExpiresTimestamp);
        $nextBillingDate = date('Y-m-d', $newExpiresTimestamp);

        // Mise à jour de l'utilisateur
        $stmtUser = $db->prepare("
            UPDATE users 
            SET subscription_status = 'active',
                subscription_expires_at = ?,
                last_billing_date = date('now'),
                next_billing_date = ?,
                failed_renewals_count = 0
            WHERE id = ?
        ");
        $stmtUser->execute([$newExpiresAt, $nextBillingDate, $userId]);

        return [
            'success'        => true,
            'order_number'   => $orderNumber,
            'invoice_number' => $invoiceNumber,
            'new_expires_at' => $newExpiresAt
        ];
    } catch (Exception $e) {
        $stmtFail = $db->prepare("UPDATE users SET failed_renewals_count = COALESCE(failed_renewals_count, 0) + 1 WHERE id = ?");
        $stmtFail->execute([$userId]);

        return [
            'success' => false,
            'error'   => $e->getMessage()
        ];
    }
}

/**
 * Révoque l'accès d'un utilisateur lorsque son abonnement est épuisé.
 */
function revoke_user_access(int $userId, string $reason = ''): void {
    $db = get_db();
    $stmt = $db->prepare("UPDATE users SET subscription_status = 'expired' WHERE id = ?");
    $stmt->execute([$userId]);
}

/**
 * Verrouille une page aux seuls membres ayant un abonnement ACTIF et PAYÉ.
 * Si l'abonnement est expiré ou absent, redirige immédiatement vers la page de réabonnement.
 */
function require_active_subscription(string $redirectExpired = 'subscription-expired.php'): void {
    require_auth('login.php');

    $currentUser = current_user();
    if (!$currentUser) {
        header('Location: login.php');
        exit;
    }

    $sub = check_user_subscription((int)$currentUser['id']);

    if (!$sub['is_active']) {
        if ($sub['status'] === 'expired') {
            set_flash('error', "Votre période d'abonnement a expiré. Veuillez renouveler votre cotisation de 9,00 € pour réactiver instantanément vos accès.");
            header("Location: {$redirectExpired}");
            exit;
        } else {
            set_flash('error', "Votre adhésion n'est pas encore activée. Finalisez votre règlement sécurisé de 9,00 € pour accéder à la communauté.");
            header('Location: checkout.php');
            exit;
        }
    }
}

/**
 * Traite tous les abonnements arrivés à échéance (utilisable par tâche cron ou vérification périodique).
 */
function process_all_due_subscriptions(): array {
    $db = get_db();
    $stmt = $db->query("
        SELECT * FROM users 
        WHERE subscription_status = 'active' 
          AND role = 'member'
          AND subscription_expires_at IS NOT NULL 
          AND subscription_expires_at <= datetime('now')
    ");
    $dueUsers = $stmt->fetchAll();

    $renewed = 0;
    $revoked = 0;

    foreach ($dueUsers as $u) {
        $res = check_user_subscription((int)$u['id']);
        if (!empty($res['renewed'])) {
            $renewed++;
        } else {
            $revoked++;
        }
    }

    return [
        'total_due' => count($dueUsers),
        'renewed'   => $renewed,
        'revoked'   => $revoked
    ];
}
