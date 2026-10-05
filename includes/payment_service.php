<?php
/**
 * ONE VISION COMMUNITY — MODULE DE PAIEMENT GÉNÉRIQUE & SIMULATION
 * 
 * Conçu de façon strictement isolée et modulaire :
 * - Aucun nom de prestataire n'est mentionné
 * - creerPaiement() : initialise une intention de règlement
 * - confirmerPaiement() : active la formule, met à jour le rôle et gère la transition atomique
 * - La simulation n'est active que si PAIEMENT_MODE=simulation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';

class PaymentService {

    /**
     * Règle de calcul du montant à payer pour une formule et une périodicité.
     * 
     * RÈGLE MÉTIER ACTUELLE :
     * Plein montant de la formule choisie, sans prorata ni déduction (upgrade ou premier abonnement).
     * Cette fonction isolée peut être adaptée plus tard pour intégrer un prorata si nécessaire.
     */
    public static function calculerMontantFormule(array $plan, string $periodicite, ?array $currentSubscription = null): float {
        $periodicite = strtolower(trim($periodicite));
        if ($periodicite === 'annuel') {
            return (float)$plan['prix_annuel'];
        }
        return (float)$plan['prix_mensuel'];
    }

    /**
     * Initialise un paiement pour un utilisateur, une formule et une périodicité.
     * En mode simulation, prépare une référence prête à être validée.
     */
    public static function creerPaiement(array $utilisateur, array $plan, string $periodicite): array {
        // 1. Contrôle du mode de paiement
        $mode = strtolower(trim(getenv('PAIEMENT_MODE') ?: (defined('PAIEMENT_MODE') ? PAIEMENT_MODE : 'simulation')));
        if ($mode !== 'simulation') {
            return [
                'success' => false,
                'error'   => "Aucun prestataire de paiement n'est configuré."
            ];
        }

        $periodicite = in_array(strtolower($periodicite), ['annuel', 'mensuel'], true) ? strtolower($periodicite) : 'mensuel';
        $montant = self::calculerMontantFormule($plan, $periodicite);
        $devise = $plan['devise'] ?? 'EUR';
        $userId = (int)$utilisateur['id'];

        $db = get_db();

        // 2. Génération d'une référence générique unique
        $reference = 'PAY-SIM-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));

        try {
            // Enregistrement d'un paiement en attente
            $stmt = $db->prepare("
                INSERT INTO payments (
                    user_id, montant, devise, statut, reference_externe, methode, date_paiement
                ) VALUES (
                    ?, ?, ?, 'en_attente', ?, 'simulation', datetime('now')
                )
            ");
            $stmt->execute([$userId, $montant, $devise, $reference]);
            $paymentId = $db->lastInsertId();

            return [
                'success'     => true,
                'payment_id'  => $paymentId,
                'reference'   => $reference,
                'montant'     => $montant,
                'devise'      => $devise,
                'plan_id'     => $plan['id'],
                'plan_code'   => $plan['code'],
                'plan_nom'    => $plan['nom'],
                'periodicite' => $periodicite,
                'user_id'     => $userId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => "Impossible d'initialiser le paiement : " . $e->getMessage()
            ];
        }
    }

    /**
     * Confirme un paiement et applique le nouvel abonnement et le rôle associé.
     * OPÉRATION STRICTEMENT ATOMIQUE (TRANSACTION PDO).
     * 
     * Le rôle et l'abonnement ne changent QUE dans cette fonction.
     */
    public static function confirmerPaiement(string $reference): array {
        $db = get_db();

        // 1. Rechercher le paiement associé à la référence
        $stmtPay = $db->prepare("SELECT * FROM payments WHERE reference_externe = ?");
        $stmtPay->execute([$reference]);
        $payment = $stmtPay->fetch();

        if (!$payment) {
            return [
                'success' => false,
                'error'   => "Référence de transaction introuvable."
            ];
        }

        // Si déjà confirmé (protection contre le double clic / double validation)
        if ($payment['statut'] === 'reussi' && !empty($payment['subscription_id'])) {
            $stmtSub = $db->prepare("SELECT s.*, p.code as plan_code, p.nom as plan_nom FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.id = ?");
            $stmtSub->execute([$payment['subscription_id']]);
            $sub = $stmtSub->fetch();
            return [
                'success'         => true,
                'already_paid'    => true,
                'subscription_id' => $payment['subscription_id'],
                'subscription'    => $sub
            ];
        }

        $userId = (int)$payment['user_id'];

        // 2. Récupérer l'utilisateur
        $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();

        if (!$user) {
            return ['success' => false, 'error' => "Utilisateur associé introuvable."];
        }

        if (($user['statut'] ?? 'actif') === 'suspendu') {
            return ['success' => false, 'error' => "Ce compte est actuellement suspendu."];
        }

        // 3. Déterminer le plan visé
        // Si le plan n'est pas passé dans la référence, on retrouve le plan à partir du montant ou du contexte
        $plans = $db->query("SELECT * FROM plans WHERE actif = 1 ORDER BY id ASC")->fetchAll();
        $targetPlan = null;
        $targetPeriodicite = 'mensuel';

        foreach ($plans as $p) {
            if (abs((float)$p['prix_annuel'] - (float)$payment['montant']) < 0.01) {
                $targetPlan = $p;
                $targetPeriodicite = 'annuel';
                break;
            } elseif (abs((float)$p['prix_mensuel'] - (float)$payment['montant']) < 0.01) {
                $targetPlan = $p;
                $targetPeriodicite = 'mensuel';
                break;
            }
        }

        if (!$targetPlan) {
            $targetPlan = $plans[0] ?? null;
        }
        if (!$targetPlan) {
            return ['success' => false, 'error' => "Aucune formule valide trouvée pour ce règlement."];
        }

        // 4. Début de la TRANSACTION ATOMIQUE
        $db->beginTransaction();

        try {
            // A. Annuler tout abonnement actif existant de cet utilisateur (un seul abonnement actif à la fois)
            $stmtCancelOld = $db->prepare("
                UPDATE subscriptions 
                SET statut = 'annule', date_resiliation = datetime('now'), renouvellement_auto = 0, updated_at = datetime('now')
                WHERE user_id = ? AND statut = 'actif'
            ");
            $stmtCancelOld->execute([$userId]);

            // B. Calcul des dates de début et d'échéance (UTC)
            $dateDebut = gmdate('Y-m-d H:i:s');
            if ($targetPeriodicite === 'annuel') {
                $dateFin = gmdate('Y-m-d H:i:s', strtotime('+1 year'));
            } else {
                $dateFin = gmdate('Y-m-d H:i:s', strtotime('+1 month'));
            }

            // C. Création du nouvel abonnement actif
            $stmtNewSub = $db->prepare("
                INSERT INTO subscriptions (
                    user_id, plan_id, periodicite, prix_paye, statut,
                    date_debut, date_fin, renouvellement_auto, reference_paiement, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, 'actif',
                    ?, ?, 1, ?, datetime('now'), datetime('now')
                )
            ");
            $stmtNewSub->execute([
                $userId,
                $targetPlan['id'],
                $targetPeriodicite,
                $payment['montant'],
                $dateDebut,
                $dateFin,
                $reference
            ]);
            $newSubId = $db->lastInsertId();

            // D. Mettre à jour le paiement
            $stmtUpdatePay = $db->prepare("
                UPDATE payments 
                SET statut = 'reussi', subscription_id = ?
                WHERE id = ?
            ");
            $stmtUpdatePay->execute([$newSubId, $payment['id']]);

            // E. Mettre à jour le profil de l'utilisateur
            // Les propriétaires et administrateurs délégués conservent leur rôle d'administration
            $currentRole = get_user_role($user);
            $newRole = $currentRole;

            if (!in_array($currentRole, ['proprietaire', 'admin_delegue'], true)) {
                $newRole = ($targetPlan['code'] === 'animateur') ? 'animateur' : 'membre';
            }

            $stmtUpdateUser = $db->prepare("
                UPDATE users 
                SET role = ?,
                    subscription_status = 'active',
                    subscription_plan = ?,
                    subscription_started_at = ?,
                    subscription_expires_at = ?,
                    auto_renew = 1
                WHERE id = ?
            ");
            $stmtUpdateUser->execute([
                $newRole,
                $targetPlan['code'],
                $dateDebut,
                $dateFin,
                $userId
            ]);

            // F. Si l'utilisateur est devenu Animateur, débloquer ses lives passés en brouillon
            if ($newRole === 'animateur' || $targetPlan['code'] === 'animateur') {
                $stmtUnlockLives = $db->prepare("
                    UPDATE lives 
                    SET status = 'publie'
                    WHERE user_id = ? AND status = 'brouillon'
                ");
                $stmtUnlockLives->execute([$userId]);
            }

            // G. Synchroniser la session courante si c'est l'utilisateur connecté
            if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
                $_SESSION['user_role'] = $newRole;
            }

            $db->commit();

            return [
                'success'         => true,
                'subscription_id' => $newSubId,
                'plan_code'       => $targetPlan['code'],
                'plan_nom'        => $targetPlan['nom'],
                'new_role'        => $newRole,
                'nouveau_role'    => $newRole,
                'periodicite'     => $targetPeriodicite,
                'date_fin'        => $dateFin
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return [
                'success' => false,
                'error'   => "Échec de l'activation atomique de l'abonnement : " . $e->getMessage()
            ];
        }
    }
}

/**
 * Fonctions globales prévues par la spécification :
 * creerPaiement(utilisateur, plan, periodicite)
 * confirmerPaiement(reference)
 */
function creerPaiement($utilisateur, $plan, string $periodicite): array {
    if (is_string($plan)) {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM plans WHERE code = ?");
        $stmt->execute([$plan]);
        $plan = $stmt->fetch();
        if (!$plan) {
            return ['success' => false, 'error' => "Formule demandée introuvable."];
        }
    }
    return PaymentService::creerPaiement($utilisateur, $plan, $periodicite);
}

function confirmerPaiement(string $reference): array {
    return PaymentService::confirmerPaiement($reference);
}

