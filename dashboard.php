<?php
/**
 * ONE VISION COMMUNITY — ESPACE MEMBRE & DASHBOARD DYNAMIQUE (PHP & SQLITE)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/subscriptions.php';
require_once __DIR__ . '/includes/permissions.php';

// Contrôle d'accès strict : seuls les membres payés et à jour accèdent au dashboard
require_active_subscription('choisir-abonnement.php');

$db = get_db();
$currentUser = current_user();
$userSub = check_user_subscription((int)$currentUser['id']);

// En-têtes de sécurité HTTP
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Traitement POST : Actions membre (Profil, Prélèvement mensuel, Simulation d'échéance)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Session expirée. Veuillez actualiser et réessayer.']);
                exit;
            }
            set_flash('error', 'Session de formulaire expirée. Veuillez actualiser et réessayer.');
            header('Location: dashboard.php?tab=tab-parametres');
            exit;
        }

        $fullName = trim($_POST['settingsFullName'] ?? '');
        $role = trim($_POST['settingsRole'] ?? '');
        $phone = trim($_POST['settingsPhone'] ?? '');
        $avatar = trim($_POST['settingsAvatar'] ?? ($currentUser['avatar'] ?? './img/avatar-maxime.jpg'));
        $newPwd = $_POST['settingsNewPassword'] ?? '';
        $confirmPwd = $_POST['settingsConfirmPassword'] ?? '';
        $oldPwd = $_POST['settingsOldPassword'] ?? '';

        // Validation de l'avatar
        $allowedAvatars = [
            './img/avatar-maxime.jpg', './img/avatar-florian.jpg',
            './img/avatar-cyril.jpg',  './img/avatar-aurore.jpg',
            './img/avatar-sarah.jpg'
        ];
        if (!in_array($avatar, $allowedAvatars, true)) {
            if (preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $avatar)) {
                if (strlen($avatar) > 2800000) {
                    $avatar = $currentUser['avatar'] ?? './img/avatar-maxime.jpg';
                }
            } else {
                $avatar = $currentUser['avatar'] ?? './img/avatar-maxime.jpg';
            }
        }

        if (empty($fullName)) {
            $err = 'Veuillez renseigner votre nom complet.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $err]);
                exit;
            }
            set_flash('error', $err);
            header('Location: dashboard.php?tab=tab-parametres');
            exit;
        }

        if (!empty($newPwd)) {
            if ($newPwd !== $confirmPwd) {
                $err = 'Les deux nouveaux mots de passe ne correspondent pas.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $err]);
                    exit;
                }
                set_flash('error', $err);
                header('Location: dashboard.php?tab=tab-parametres');
                exit;
            }

            if (strlen($newPwd) < 6) {
                $err = 'Le mot de passe doit comporter au moins 6 caractères.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $err]);
                    exit;
                }
                set_flash('error', $err);
                header('Location: dashboard.php?tab=tab-parametres');
                exit;
            }

            if (!empty($currentUser['password']) && !password_verify($oldPwd, $currentUser['password'])) {
                $err = 'Le mot de passe actuel saisi est incorrect.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $err]);
                    exit;
                }
                set_flash('error', $err);
                header('Location: dashboard.php?tab=tab-parametres');
                exit;
            }

            $hash = password_hash($newPwd, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET full_name = ?, job_title = ?, phone = ?, avatar = ?, password = ? WHERE id = ?");
            $stmt->execute([$fullName, $role, $phone, $avatar, $hash, $currentUser['id']]);
            $msg = 'Votre profil et votre mot de passe ont été mis à jour avec succès.';
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, job_title = ?, phone = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$fullName, $role, $phone, $avatar, $currentUser['id']]);
            $msg = 'Vos paramètres de compte ont été enregistrés avec succès.';
        }

        $currentUser = current_user();
        $_SESSION['user_name'] = $currentUser['full_name'];

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'user' => [
                    'full_name' => $fullName,
                    'job_title' => $role,
                    'phone' => $phone,
                    'avatar' => $avatar
                ]
            ]);
            exit;
        }

        set_flash('success', $msg);
        header('Location: dashboard.php?tab=tab-parametres');
        exit;
    } elseif ($_POST['action'] === 'trigger_recurring_charge') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Session de formulaire expirée.');
        } else {
            $renewResult = process_recurring_charge($currentUser);
            if ($renewResult['success']) {
                set_flash('success', "Prélèvement automatique mensuel de 9,00 € effectué avec succès ! Facture #" . $renewResult['invoice_number'] . " émise et adhésion prolongée de 30 jours.");
            } else {
                set_flash('error', "Échec du prélèvement automatique : " . ($renewResult['error'] ?? 'Carte refusée'));
            }
            header('Location: dashboard.php');
            exit;
        }
    } elseif ($_POST['action'] === 'simulate_expire_subscription') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Session de formulaire expirée.');
        } else {
            revoke_user_access((int)$currentUser['id'], 'Simulation de fin de période de 30 jours');
            set_flash('error', "Votre période de 30 jours est expirée. Vos accès ont été révoqués.");
            header('Location: subscription-expired.php');
            exit;
        }
    } elseif ($_POST['action'] === 'delete_account') {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Session de formulaire expirée.']);
                exit;
            }
            set_flash('error', 'Session de formulaire expirée. Veuillez actualiser et réessayer.');
            header('Location: dashboard.php');
            exit;
        }

        $userId = (int)$currentUser['id'];

        if ($currentUser['role'] === 'proprietaire') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Le compte propriétaire principal ne peut pas être supprimé.']);
                exit;
            }
            set_flash('error', 'Le compte propriétaire principal ne peut pas être supprimé.');
            header('Location: dashboard.php');
            exit;
        }

        require_once __DIR__ . '/includes/auth.php';
        $res = delete_user_account($userId);

        if ($res['success']) {
            logout_user();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Votre compte a été bien résilié, votre compte a été bien supprimé.']);
                exit;
            }
            set_flash('info', 'Votre compte et toutes vos données ont été définitivement résiliés et supprimés. Nous vous souhaitons une excellente continuation.');
            header('Location: index.php');
            exit;
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $res['error'] ?? 'Une erreur est survenue lors de la suppression de votre compte.']);
                exit;
            }
            set_flash('error', $res['error'] ?? 'Une erreur est survenue lors de la suppression de votre compte.');
            header('Location: dashboard.php');
            exit;
        }
    } elseif ($_POST['action'] === 'delete_live') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Session de formulaire expirée.');
        } else {
            $liveId = (int)($_POST['live_id'] ?? $_POST['delete_live_id'] ?? 0);
            if ($liveId > 0) {
                $stmt = $db->prepare("SELECT * FROM lives WHERE id = ?");
                $stmt->execute([$liveId]);
                $targetLive = $stmt->fetch();
                if ($targetLive) {
                    $canDelete = ($targetLive['user_id'] == $currentUser['id']) || is_owner($currentUser) || is_admin_user($currentUser) || user_has_permission($currentUser, 'gerer_lives');
                    if ($canDelete) {
                        $db->prepare("DELETE FROM lives WHERE id = ?")->execute([$liveId]);
                        set_flash('success', "Le live « " . htmlspecialchars($targetLive['title']) . " » a été supprimé du calendrier avec succès.");
                    } else {
                        set_flash('error', "Vous n'avez pas l'autorisation de supprimer ce live. Seul son créateur peut le supprimer.");
                    }
                }
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            $targetTab = (!empty($_GET['tab']) && $_GET['tab'] === 'tab-lives') ? 'tab-lives' : 'tab-calendrier';
            header('Location: dashboard.php?tab=' . $targetTab);
            exit;
        }
    } elseif ($_POST['action'] === 'edit_live') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Session de formulaire expirée.');
        } else {
            $liveId = (int)($_POST['live_id'] ?? $_POST['edit_live_id'] ?? 0);
            $title = trim($_POST['title'] ?? $_POST['edit_live_title'] ?? '');
            $desc = trim($_POST['description'] ?? $_POST['edit_live_desc'] ?? '');
            $format = trim($_POST['format'] ?? $_POST['edit_live_format'] ?? 'Live Thématique');
            $date = trim($_POST['date'] ?? $_POST['edit_live_date'] ?? '');
            $time = trim($_POST['time'] ?? $_POST['edit_live_time'] ?? '19h00');
            $duration = trim($_POST['duration'] ?? $_POST['edit_live_duration'] ?? '1h00');
            $resources = trim($_POST['resources'] ?? $_POST['edit_live_resources'] ?? '');

            if ($liveId > 0 && !empty($title)) {
                $stmt = $db->prepare("SELECT * FROM lives WHERE id = ?");
                $stmt->execute([$liveId]);
                $targetLive = $stmt->fetch();
                if ($targetLive) {
                    $canEdit = ($targetLive['user_id'] == $currentUser['id']) || is_owner($currentUser) || is_admin_user($currentUser) || user_has_permission($currentUser, 'gerer_lives');
                    if ($canEdit) {
                        $db->prepare("
                            UPDATE lives 
                            SET title = ?, description = ?, format = ?, scheduled_date = ?, scheduled_time = ?, duration = ?, resources = ?
                            WHERE id = ?
                        ")->execute([$title, $desc, $format, $date, $time, $duration, $resources, $liveId]);
                        set_flash('success', "La session « " . htmlspecialchars($title) . " » a été modifiée avec succès.");
                    } else {
                        set_flash('error', "Vous n'avez pas l'autorisation de modifier ce live.");
                    }
                }
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            $targetTab = (!empty($_GET['tab']) && $_GET['tab'] === 'tab-lives') ? 'tab-lives' : 'tab-calendrier';
            header('Location: dashboard.php?tab=' . $targetTab);
            exit;
        }
    } elseif ($_POST['action'] === 'resilier') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', "Session expirée. Veuillez actualiser et réessayer.");
        } else {
            $resResult = resilier_abonnement((int)$currentUser['id']);
            if ($resResult['success']) {
                set_flash('info', $resResult['message']);
            } else {
                set_flash('error', $resResult['error'] ?? "Impossible de traiter la résiliation.");
            }
        }
        header('Location: dashboard.php?tab=tab-compte');
        exit;
    } elseif ($_POST['action'] === 'souscrire') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', "Session expirée. Veuillez actualiser et réessayer.");
        } else {
            require_once __DIR__ . '/includes/payment_service.php';
            $planCode = trim($_POST['plan_code'] ?? 'membre');
            $periodicite = in_array($_POST['periodicite'] ?? '', ['annuel', 'mensuel'], true) ? $_POST['periodicite'] : 'mensuel';
            $stmtP = $db->prepare("SELECT * FROM plans WHERE code = ? AND actif = 1");
            $stmtP->execute([$planCode]);
            $targetPlan = $stmtP->fetch();
            if (!$targetPlan) {
                set_flash('error', "La formule sélectionnée est introuvable.");
            } else {
                $creation = PaymentService::creerPaiement($currentUser, $targetPlan, $periodicite);
                if ($creation['success']) {
                    $conf = PaymentService::confirmerPaiement($creation['reference']);
                    if ($conf['success']) {
                        $periodLabel = ($periodicite === 'annuel') ? 'annuel' : 'mensuel';
                        set_flash('success', "Félicitations ! Votre abonnement {$targetPlan['nom']} ({$periodLabel}) est désormais actif.");
                        if ($targetPlan['code'] === 'animateur') {
                            header('Location: espace-animateur.php');
                            exit;
                        }
                    } else {
                        set_flash('error', $conf['error'] ?? "Erreur lors de la validation du règlement.");
                    }
                } else {
                    set_flash('error', $creation['error'] ?? "Erreur lors de l'initialisation du règlement.");
                }
            }
        }
        header('Location: dashboard.php?tab=tab-compte');
        exit;
    } elseif ($_POST['action'] === 'upgrade_animateur') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', "Session expirée. Veuillez actualiser et réessayer.");
        } else {
            require_once __DIR__ . '/includes/payment_service.php';
            $periodicite = in_array($_POST['periodicite'] ?? '', ['annuel', 'mensuel'], true) ? $_POST['periodicite'] : 'mensuel';
            $stmtP = $db->prepare("SELECT * FROM plans WHERE code = 'animateur' AND actif = 1");
            $stmtP->execute();
            $targetPlan = $stmtP->fetch();
            if ($targetPlan) {
                $creation = PaymentService::creerPaiement($currentUser, $targetPlan, $periodicite);
                if ($creation['success']) {
                    $conf = PaymentService::confirmerPaiement($creation['reference']);
                    if ($conf['success']) {
                        set_flash('success', "Félicitations ! Vous êtes désormais Animateur One Vision. Votre formule est active immédiatement.");
                        header('Location: espace-animateur.php');
                        exit;
                    } else {
                        set_flash('error', $conf['error'] ?? "Erreur lors de la validation.");
                    }
                } else {
                    set_flash('error', $creation['error'] ?? "Erreur lors de l'initialisation.");
                }
            }
        }
        header('Location: dashboard.php?tab=tab-compte');
        exit;
    } elseif ($_POST['action'] === 'passer_annuel') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('error', "Session expirée. Veuillez actualiser et réessayer.");
        } else {
            require_once __DIR__ . '/includes/payment_service.php';
            $activeSub = get_user_active_subscription((int)$currentUser['id']);
            $currentPlanCode = $activeSub['plan_code'] ?? 'membre';
            $stmtP = $db->prepare("SELECT * FROM plans WHERE code = ? AND actif = 1");
            $stmtP->execute([$currentPlanCode]);
            $targetPlan = $stmtP->fetch();
            if ($targetPlan) {
                $creation = PaymentService::creerPaiement($currentUser, $targetPlan, 'annuel');
                if ($creation['success']) {
                    $conf = PaymentService::confirmerPaiement($creation['reference']);
                    if ($conf['success']) {
                        set_flash('success', "Votre abonnement est maintenant annuel ! Vous bénéficiez de 2 mois offerts.");
                    } else {
                        set_flash('error', $conf['error'] ?? "Erreur validation.");
                    }
                } else {
                    set_flash('error', $creation['error'] ?? "Erreur initialisation.");
                }
            }
        }
        header('Location: dashboard.php?tab=tab-compte');
        exit;
    } elseif ($_POST['action'] === 'upgrade_to_creator') {
        header('Location: dashboard.php?tab=tab-compte');
        exit;
    }
}

// Récupération des Lives depuis SQLite
$livesStmt = $db->query("SELECT * FROM lives ORDER BY scheduled_date ASC, scheduled_time ASC");
$allLives = $livesStmt->fetchAll();

// Récupération des membres pour l'annuaire Réseau
$membersStmt = $db->query("SELECT * FROM users ORDER BY id ASC");
$allMembers = $membersStmt->fetchAll();

// Récupération des factures de l'utilisateur
$ordersStmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$ordersStmt->execute([$currentUser['id']]);
$userOrders = $ordersStmt->fetchAll();

// Données d'administration si l'utilisateur est admin
$adminTickets = [];
$adminOrders = [];
$isAnimateur = is_animateur_user($currentUser);
$isAdminUser = is_admin_user($currentUser);
$isOwnerUser = is_owner($currentUser);
$isAdminDelegue = is_admin_delegue($currentUser);
$isCreator = $isAnimateur || $isAdminUser;
$userPlan = $isAnimateur ? 'animateur' : 'membre';

// Détection de l'accès animateur / hôte de la session
$isLiveHost = $isOwnerUser || $isAdminUser || $isAnimateur || user_has_permission($currentUser, 'gerer_lives');
foreach ($allLives as $l) {
    if (!empty($l['is_live_now']) && ((int)$l['user_id'] === (int)$currentUser['id'])) {
        $isLiveHost = true;
        break;
    }
}

if ($isAdminUser) {
    $adminTickets = $db->query("SELECT * FROM support_tickets ORDER BY id DESC")->fetchAll();
    $adminOrders = $db->query("SELECT o.*, u.full_name as user_name, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.id DESC")->fetchAll();
}

$userId = (int)$currentUser['id'];
$activeSub = get_user_active_subscription($userId);
$subInfo = $userSub;
$plans = $db->query("SELECT * FROM plans WHERE actif = 1 ORDER BY ordre_affichage ASC")->fetchAll();
$indexedPlans = [];
foreach ($plans as $p) {
    $p['avantages_list'] = json_decode($p['avantages'], true) ?: array_filter(array_map('trim', explode("\n", $p['avantages'])));
    $indexedPlans[$p['code']] = $p;
}
$animateurPlan = $indexedPlans['animateur'] ?? null;
$membrePlan = $indexedPlans['membre'] ?? null;
$userPayments = get_user_payments_history($userId);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Espace Membre & Dashboard — One Vision Community</title>
  <meta name="description" content="Votre espace membre One Vision Community. Accédez aux sessions live hebdomadaires, replays HD, salons d'échanges et ressources exclusives.">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎯</text></svg>">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="./css/style.css?v=3">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
  <script>
    window.IS_LIVE_HOST = <?= $isLiveHost ? 'true' : 'false' ?>;
    window.CURRENT_USER_NAME = <?= json_encode($currentUser['full_name']) ?>;
    window.CURRENT_USER_AVATAR = <?= json_encode($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>;
    window.CURRENT_USER_ID = <?= (int)$currentUser['id'] ?>;
  </script>
  <style>
    /* Styles pour la modération et l'épinglage du Chat Live */
    .live-chat-pinned-box {
      background: #eff6ff;
      border-bottom: 2px solid #bfdbfe;
      padding: 0.65rem 0.85rem;
      transition: all 0.25s ease;
    }
    .pinned-box-inner {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 0.6rem;
    }
    .pinned-box-left {
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
    }
    .pinned-icon-badge {
      font-size: 1.15rem;
      line-height: 1;
    }
    .pinned-badge-title {
      font-size: 0.72rem;
      font-weight: 800;
      text-transform: uppercase;
      color: #1d4ed8;
      letter-spacing: 0.4px;
      margin-bottom: 2px;
    }
    .pinned-message-content {
      font-size: 0.84rem;
      color: #0f172a;
      font-weight: 600;
      line-height: 1.35;
    }
    .pinned-message-author {
      font-size: 0.72rem;
      color: #64748b;
      margin-top: 2px;
    }
    .btn-unpin-action {
      background: #ffffff;
      border: 1px solid #cbd5e1;
      color: #64748b;
      font-size: 0.72rem;
      padding: 3px 8px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      white-space: nowrap;
      transition: all 0.15s ease;
    }
    .btn-unpin-action:hover {
      background: #fee2e2;
      color: #dc2626;
      border-color: #fca5a5;
    }
    .chat-msg {
      position: relative;
      transition: background 0.2s ease;
    }
    .chat-msg-actions {
      display: none;
      position: absolute;
      top: 6px;
      right: 8px;
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(4px);
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 2px 4px;
      gap: 4px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      z-index: 10;
    }
    .chat-msg:hover .chat-msg-actions {
      display: flex;
    }
    .btn-chat-pin, .btn-chat-delete {
      background: none;
      border: none;
      font-size: 0.72rem;
      cursor: pointer;
      padding: 2px 6px;
      border-radius: 4px;
      font-weight: 700;
      transition: all 0.15s ease;
    }
    .btn-chat-pin {
      color: #2563eb;
    }
    .btn-chat-pin:hover {
      background: #eff6ff;
    }
    .btn-chat-delete {
      color: #dc2626;
    }
    .btn-chat-delete:hover {
      background: #fee2e2;
    }
    .chat-msg.is-pinned-highlight {
      background: #f0fdf4 !important;
      border-left: 3px solid #16a34a !important;
    }
    .participant-mod-actions {
      display: flex;
      gap: 0.35rem;
      margin-top: 0.4rem;
    }
    .btn-mod-mic, .btn-mod-cam {
      font-size: 0.72rem;
      padding: 3px 7px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      border: 1px solid #cbd5e1;
      background: #ffffff;
      color: #475569;
      transition: all 0.15s ease;
    }
    .btn-mod-mic:hover, .btn-mod-cam:hover {
      background: #fee2e2;
      color: #dc2626;
      border-color: #fca5a5;
    }
    .btn-mod-mic.is-muted, .btn-mod-cam.is-cut {
      background: #fee2e2;
      color: #dc2626;
      border-color: #fca5a5;
    }
  </style>
</head>
<body class="dashboard-body">

  <!-- TOPBAR DU DASHBOARD -->
  <header class="dash-topbar">
    <div class="dash-topbar-left">
      <a href="index.php" class="logo" aria-label="One Vision Community">

        <div class="logo-text">
          <span class="logo-brand"><span class="logo-one-script">One</span> Vision</span>
          <span class="logo-sub">Community</span>
        </div>
      </a>

      <!-- Badge Live en cours -->
      <div class="dash-live-indicator" id="topbarLiveBadge" style="cursor:pointer;" data-target-tab="tab-lives" title="Accéder directement au Live en cours">
        <span class="pulse-live-dot"></span>
        <span class="live-text-main">EN DIRECT</span>
        <span class="live-text-sub">• Mastermind Q&A (142 connectés)</span>
      </div>
    </div>

    <div class="dash-topbar-right">
      <!-- Notification avec Panneau Déroulant -->
      <div class="dash-notif-wrapper">
        <button type="button" class="dash-icon-btn" id="notifBellBtn" title="Notifications" aria-expanded="false">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span class="badge-dot-alert"></span>
        </button>

        <div class="dash-dropdown-panel" id="notifDropdownPanel" style="display:none;">
          <div class="dropdown-header">
            <span class="dropdown-header-title">Notifications en direct</span>
            <button type="button" class="mark-read-btn" id="markAllReadBtn">Tout marquer comme lu</button>
          </div>
          <div class="dropdown-list" id="notifDropdownList">
            <div class="dropdown-item unread" data-target-tab="tab-lives" style="cursor:pointer;" title="Rejoindre le Live Mastermind">
              <span class="dropdown-dot"></span>
              <div class="dropdown-item-content">
                <p class="dropdown-item-title">🔴 <strong>Nouveau Live en cours</strong> : Mastermind Q&A en direct avec Cyril</p>
                <span class="dropdown-item-time">En ce moment • Cliquer pour rejoindre</span>
              </div>
            </div>
            <div class="dropdown-item unread" data-target-tab="tab-salons" style="cursor:pointer;" title="Voir le salon">
              <span class="dropdown-dot"></span>
              <div class="dropdown-item-content">
                <p class="dropdown-item-title">💬 <strong>Cyril D.</strong> a réagi à votre intervention dans le salon Général</p>
                <span class="dropdown-item-time">Il y a 10 minutes</span>
              </div>
            </div>
            <div class="dropdown-item unread" data-target-tab="tab-reseau" style="cursor:pointer;" title="Voir le profil">
              <span class="dropdown-dot"></span>
              <div class="dropdown-item-content">
                <p class="dropdown-item-title">👋 <strong>Aurore M.</strong> vous a envoyé une demande de mise en relation</p>
                <span class="dropdown-item-time">Il y a 45 minutes</span>
              </div>
            </div>
            <div class="dropdown-item" data-target-tab="tab-ressources" style="cursor:pointer;" title="Accéder aux ressources">
              <div class="dropdown-item-content">
                <p class="dropdown-item-title">📥 <strong>Nouveau modèle ajouté</strong> : Contrat de Prestation Freelance 2026 (.DOCX & .PDF)</p>
                <span class="dropdown-item-time">Hier à 18h30</span>
              </div>
            </div>
            <div class="dropdown-item" data-target-tab="tab-calendrier" style="cursor:pointer;" title="Voir le calendrier">
              <div class="dropdown-item-content">
                <p class="dropdown-item-title">📅 <strong>Rappel Session</strong> : Masterclass Prospection B2B demain à 19h00</p>
                <span class="dropdown-item-time">Hier à 14h00</span>
              </div>
            </div>
            <div class="dropdown-item" data-target-tab="tab-lives" style="cursor:pointer;" title="Voir le replay">
              <div class="dropdown-item-content">
                <p class="dropdown-item-title">🎬 <strong>Replay disponible</strong> : Session Négociation & Closing High-Ticket</p>
                <span class="dropdown-item-time">Il y a 2 jours</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Profil Utilisateur & Menu Déroulant (au bout à droite) -->
      <div class="dash-user-dropdown-wrapper">
        <button type="button" class="dash-user-pill" id="dashUserDropdownTrigger" aria-expanded="false" title="Mon Compte">
          <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="Photo profil" class="dash-user-avatar" id="dashAvatar">
          <div class="dash-user-meta">
            <span class="dash-user-name" id="dashUserName"><?= htmlspecialchars($currentUser['full_name']) ?></span>
            <span class="dash-user-badge"><?= $isOwnerUser ? '👑 Propriétaire' : ($isAdminDelegue ? '🛡️ Admin Délégué' : ($isAnimateur ? '🌟 Animateur' : '💼 Membre Actif')) ?></span>
          </div>
          <svg class="dropdown-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Menu déroulant du profil -->
        <div class="dash-user-dropdown-menu" id="dashUserDropdownMenu" style="display:none;">
          <div class="user-dropdown-header">
            <strong id="dropdownUserTitle"><?= htmlspecialchars($currentUser['full_name']) ?></strong>
            <span class="badge-role-admin"><?= $isOwnerUser ? '👑 Propriétaire' : ($isAdminDelegue ? '🛡️ Admin Délégué' : ($isAnimateur ? '🌟 Animateur' : '💼 Membre One Vision')) ?></span>
            <span class="user-dropdown-email" id="dropdownUserEmail"><?= htmlspecialchars($currentUser['email']) ?></span>
          </div>
          <div class="user-dropdown-divider"></div>
          
          <button type="button" class="user-dropdown-item active-dropdown-item" id="menuItemDashboard">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Mon Dashboard</span>
          </button>

          <a href="abonnements.php" class="user-dropdown-item" id="menuItemAbonnement" style="text-decoration:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <span>Mon Abonnement</span>
          </a>

          <?php if ($isAnimateur || $isAdminUser): ?>
          <a href="espace-animateur.php" class="user-dropdown-item" style="text-decoration:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
            </svg>
            <span>Espace Animateur</span>
          </a>
          <?php endif; ?>

          <?php if ($isAdminUser): ?>
          <a href="admin/index.php" class="user-dropdown-item" style="text-decoration:none; color:#b45309; font-weight:600;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
            <span>Administration</span>
          </a>
          <?php endif; ?>

          <button type="button" class="user-dropdown-item" id="menuItemParams">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Paramètres du compte</span>
          </button>

          <a href="index.php" class="user-dropdown-item" title="Revenir à la page vitrine">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>Retour au site vitrine</span>
          </a>

          <div class="user-dropdown-divider"></div>

          <a href="logout.php?token=<?= urlencode(csrf_token()) ?>" class="user-dropdown-item item-danger" id="dashLogoutBtn" style="text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Se déconnecter</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- DISPOSITION PRINCIPALE DU DASHBOARD -->
  <div class="dash-layout">
    
    <!-- SIDEBAR DE NAVIGATION -->
    <aside class="dash-sidebar">
      <nav class="dash-nav">
        <div class="dash-nav-section-label">Espace Collaboratif</div>
        
        <button type="button" class="dash-nav-item active" data-dash-tab="tab-lives">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="23 7 16 12 23 17 23 7"></polygon>
            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
          </svg>
          <span>Lives & Masterminds</span>
          <span class="nav-counter live-count">Live</span>
        </button>

        <button type="button" class="dash-nav-item" data-dash-tab="tab-calendrier">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <span>Calendrier des Lives</span>
          <span class="nav-counter" style="background:#e0f2fe;color:#0369a1;">5</span>
        </button>

        <button type="button" class="dash-nav-item" data-dash-tab="tab-salons">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
          <span>Salons d'Échange</span>
          <span class="nav-counter">6</span>
        </button>

        <button type="button" class="dash-nav-item" data-dash-tab="tab-ressources">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <span>Modèles & Outils</span>
          <span class="nav-counter">3</span>
        </button>

        <button type="button" class="dash-nav-item" data-dash-tab="tab-reseau">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span>Membres & Réseau</span>
          <span class="nav-counter" style="white-space:nowrap;padding:0.2rem 0.5rem;font-size:0.75rem;">1 240</span>
        </button>

        <div class="dash-nav-section-label" style="margin-top:1.25rem;">Mon Espace</div>

        <?php if ($isAnimateur || $isAdminUser): ?>
        <a href="espace-animateur.php" class="dash-nav-item" style="text-decoration:none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
          </svg>
          <span>Espace Animateur</span>
          <span class="nav-counter" style="background:#fef3c7;color:#b45309;font-weight:700;">★ Host</span>
        </a>
        <?php else: ?>
        <a href="abonnements.php" class="dash-nav-item" style="text-decoration:none;opacity:0.85;" title="Réservé aux Animateurs — cliquez pour découvrir la formule">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <span>Espace Animateur</span>
          <span class="nav-counter" style="background:#fee2e2;color:#991b1b;">🔒</span>
        </a>
        <?php endif; ?>

        <button type="button" class="dash-nav-item" data-dash-tab="tab-compte" id="sideNavAbonnement">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
          <span>Mon Abonnement</span>
        </button>

        <a href="questionnaire.php" class="dash-nav-item" style="text-decoration:none;" title="Voir ou modifier le questionnaire d'onboarding">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <polyline points="9 15 11 17 15 13"></polyline>
          </svg>
          <span>Mon Questionnaire</span>
          <span class="nav-counter" style="background:#e0e7ff;color:#3730a3;font-weight:700;">13 Q</span>
        </a>

        <?php if ($isAdminUser): ?>
        <div class="dash-nav-section-label" style="margin-top:1.5rem;color:#d97706;font-weight:700;">Administration</div>
        <a href="admin/index.php" class="dash-nav-item" style="text-decoration:none;border-left: 2px solid #f59e0b;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"></rect>
            <rect x="14" y="3" width="7" height="7"></rect>
            <rect x="14" y="14" width="7" height="7"></rect>
            <rect x="3" y="14" width="7" height="7"></rect>
          </svg>
          <span>Gestion & Délégation</span>
          <span class="nav-counter" style="background:#fef3c7;color:#b45309;font-weight:700;">Admin</span>
        </a>
        <button type="button" class="dash-nav-item" data-dash-tab="tab-admin" style="border-left: 2px solid #f59e0b;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
          </svg>
          <span>Support & Commandes</span>
          <span class="nav-counter" style="background:#fef3c7;color:#b45309;font-weight:700;"><?= count($adminTickets) ?></span>
        </button>
        <?php endif; ?>
      </nav>

      <!-- Bouton Contact WhatsApp Direct (en bas de la sidebar) -->
      <div class="dash-sidebar-footer">
        <a href="https://wa.me/33600000000?text=Bonjour%20One%20Vision,%20j'ai%20une%20question%20concernant%20mon%20espace%20membre" target="_blank" rel="noopener noreferrer" class="dash-whatsapp-btn" id="sidebarWhatsappBtn" title="Contacter l'équipe One Vision sur WhatsApp">
          <div class="whatsapp-icon-circle">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.472 14.382c-.301-.15-1.78-.878-2.056-.979-.275-.1-.475-.15-.675.15-.2.3-.775.978-.95 1.178-.175.2-.351.225-.651.075s-1.267-.467-2.414-1.49c-.893-.795-1.496-1.777-1.671-2.077-.175-.3-.019-.462.13-.611.136-.134.301-.35.451-.525.15-.175.2-.3.3-.5.1-.2.05-.375-.025-.525s-.675-1.625-.925-2.225c-.244-.584-.492-.505-.675-.514-.175-.009-.375-.009-.575-.009s-.525.075-.8.375c-.275.3-1.05 1.026-1.05 2.502s1.075 2.901 1.225 3.101c.15.2 2.115 3.23 5.124 4.53 3.01 1.3 3.01.867 3.56.817.55-.05 1.78-.727 2.03-1.428.25-.701.25-1.302.175-1.428-.075-.125-.275-.2-.575-.35zM12 2C6.48 2 2 6.48 2 12c0 1.95.56 3.77 1.53 5.31L2 22l4.83-1.5c1.48.91 3.23 1.5 5.17 1.5 5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18.2c-1.68 0-3.24-.52-4.54-1.42l-.33-.23-3.37 1.05 1.07-3.28-.24-.35C3.62 14.65 3.1 13.37 3.1 12c0-4.91 3.99-8.9 8.9-8.9s8.9 3.99 8.9 8.9-3.99 8.9-8.9 8.9z"/>
            </svg>
          </div>
          <div class="whatsapp-btn-meta">
            <span class="whatsapp-btn-title">Support WhatsApp</span>
            <span class="whatsapp-btn-sub">Assistance directe 7j/7</span>
          </div>
        </a>
      </div>
    </aside>

    <!-- ZONE PRINCIPALE DE CONTENU -->
    <main class="dash-main">
      <?= render_flash() ?>
      
      <!-- ======================================================================
           ONGLET 1 : LIVES & MASTERMINDS (VUE PAR DÉFAUT)
           ====================================================================== -->
      <section id="tab-lives" class="dash-tab-content active">
        
        <!-- En-tête de la section -->
        <div class="dash-section-header">
          <div>
            <h1 class="dash-title">Session Live & Mastermind Hebdomadaire</h1>
            <p class="dash-desc">Participez aux échanges en visio HD, posez vos questions en direct et consultez les replays complets.</p>
          </div>
          <div class="dash-header-actions">
            <span class="live-status-pill">
              <span class="pulse-live-dot"></span> 142 entrepreneurs en direct
            </span>
          </div>
        </div>

        <!-- GRANDE SALLE DU LIVE EN COURS (VIDEO STREAM + CHAT INTERACTIF) -->
        <div class="dash-live-room-grid">
          
          <!-- Lecteur Vidéo Principal avec Contrôles & Égaliseur Animé -->
          <div class="dash-player-wrapper">
            <div class="dash-video-screen" id="liveScreenBox">
              <img src="./img/feature-lives.jpg" alt="Diffusion Mastermind en direct" class="dash-video-poster" id="liveVideoPoster">
              
              <!-- Bouton Play interactif central -->
              <button type="button" class="live-play-pulse-btn" id="livePlayBtn" aria-label="Lancer le Live Stream">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                  <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
              </button>

              <div class="dash-video-overlay">
                <div class="overlay-top-tags">
                  <span class="badge-live-stream" id="liveStatusBadge">DIRECT HD 1080p</span>
                  <div class="live-equalizer" id="liveEqualizer" title="Flux audio en direct">
                    <div class="eq-bar"></div>
                    <div class="eq-bar"></div>
                    <div class="eq-bar"></div>
                    <div class="eq-bar"></div>
                  </div>
                  <span class="badge-session-title">Mastermind : Lever ses blocages et closer ses premières offres</span>
                </div>

                <!-- Badge Flottant : Main Levée en Direct (Visible dès qu'on lève la main) -->
                <div class="live-hand-raised-pill" id="liveHandRaisedOverlayPill" style="display:none;">
                  <span class="hand-raised-icon">✋</span>
                  <span class="hand-raised-text"><strong id="handRaisedAuthorName">Katahana (Vous)</strong> lève la main pour intervenir</span>
                  <span class="hand-raised-rank">Rang #1</span>
                </div>

                <!-- Bandeau de Prise de Parole Active en Direct (au centre haut) -->
                <div class="live-speaking-banner" id="liveSpeakingBanner" style="display:none;">
                  <div class="speaking-banner-pulse"></div>
                  <div class="speaking-banner-content">
                    <div class="speaking-banner-icon">🎙️</div>
                    <div class="speaking-banner-text">
                      <span class="speaking-title">PRISE DE PAROLE EN DIRECT</span>
                      <span class="speaking-sub"><strong id="speakingAuthorName">Katahana Désiré</strong> échange avec <strong>Cyril D.</strong> (Animateur)</span>
                    </div>
                    <div class="speaking-audio-waves">
                      <span></span><span></span><span></span><span></span><span></span>
                    </div>
                  </div>
                  <button type="button" class="speaking-banner-mute-btn" id="btnMuteActiveSpeech" title="Rendre le micro">Rendre le micro</button>
                </div>

                <!-- Fenêtre Caméra Utilisateur (Picture-in-Picture) -->
                <div class="dash-user-cam-box" id="liveUserCamBox" style="display:none;">
                  <video id="userCamVideo" autoplay playsinline muted></video>
                  <div class="user-cam-fallback" id="userCamFallback" style="display:none;">
                    <img src="./img/avatar-maxime.jpg" alt="Katahana Désiré" class="user-cam-avatar" id="userCamAvatarImg">
                    <div class="user-cam-waves">
                      <span></span><span></span><span></span>
                    </div>
                  </div>
                  <div class="user-cam-badge">
                    <span class="cam-dot"></span> <span id="userCamLabel">Katahana Désiré (Vous)</span>
                  </div>
                  <button type="button" class="user-cam-close-btn" id="userCamCloseBtn" title="Couper ma caméra">✕</button>
                  <button type="button" class="btn-host-cut-user-cam" id="btnHostCutUserCam" title="Couper la caméra de l'intervenant (Action Hôte)" style="position:absolute; bottom:6px; left:6px; font-size:0.68rem; background:rgba(220,38,38,0.92); color:#fff; border:none; padding:3px 7px; border-radius:6px; font-weight:700; cursor:pointer; z-index:10; display:flex; align-items:center; gap:3px;">🚫 Couper caméra (Hôte)</button>
                </div>
                
                <div class="overlay-bottom-bar">
                  <div class="speaker-tag" id="mainSpeakerTag">
                    <img src="./img/avatar-cyril.jpg" alt="Cyril D." class="speaker-avatar">
                    <div>
                      <div class="speaker-name">Cyril D. (Intervenant) <span class="speaker-live-pill">Direct</span></div>
                      <div class="speaker-role">Coach Business & Mentor Vente • One Vision</div>
                    </div>
                  </div>
                  <div class="overlay-live-indicator">
                    <span class="pulse-live-dot"></span> 142 EN LIGNE
                  </div>
                </div>
              </div>
            </div>

            <!-- Barre de Contrôles du Direct (Placée juste en bas de la vidéo) -->
            <div class="dash-live-controls-bar">
              <div class="live-controls-left">
                <span class="live-controls-label">Contrôles Session :</span>
                <span class="speaker-sound-wave" id="cyrilSoundWave"><span></span><span></span><span></span></span>
              </div>

              <div class="player-controls-row">
                <button type="button" class="player-ctrl-btn" id="camToggleBtn" title="Activer / Désactiver ma caméra">
                  📹 Activer ma caméra
                </button>
                <button type="button" class="player-ctrl-btn" id="micToggleBtn" title="Activer / Couper mon micro">
                  🎙️ Micro : Coupé
                </button>
                <button type="button" class="player-ctrl-btn" id="handRaiseBtn" title="Lever la main pour prendre la parole">
                  ✋ Lever la main
                </button>
                <button type="button" class="player-ctrl-btn ctrl-primary" id="askQuestionBtn">
                  🎤 Demander le micro
                </button>
              </div>
            </div>

            <!-- Bandeau des participants actifs au Mastermind (Épuré avec Pop-up Modal) -->
            <div class="dash-live-participants-bar">
              <div class="participants-bar-left">
                <div class="participants-bar-label">
                  <span class="live-pulse-dot-small"></span> En direct dans la salle :
                </div>

                <div class="participant-item host-item" title="Cyril D. (Animateur)">
                  <div class="participant-avatar-wrap">
                    <img src="./img/avatar-cyril.jpg" alt="Cyril D.">
                    <span class="participant-status-dot is-speaking"></span>
                  </div>
                  <span class="participant-name">Cyril D. (Host)</span>
                  <span class="participant-audio-icon">🎙️</span>
                </div>

                <div class="participant-item self-item" id="participantSelfItem" title="Katahana Désiré (Vous)">
                  <div class="participant-avatar-wrap">
                    <img src="./img/avatar-maxime.jpg" alt="Katahana Désiré" id="participantSelfAvatar">
                    <span class="participant-status-dot" id="participantSelfStatusDot"></span>
                  </div>
                  <span class="participant-name">Katahana (Vous)</span>
                  <span class="participant-badge-admin">👑</span>
                  <span class="participant-audio-icon" id="participantSelfAudioIcon">🔇</span>
                </div>
              </div>

              <!-- Bouton Pop-up avec pile d'avatars et flèche pour ouvrir la liste complète -->
              <button type="button" class="btn-participants-popup" id="btnOpenParticipantsList" title="Ouvrir la liste complète des participants">
                <div class="participants-avatar-stack">
                  <img src="./img/avatar-sarah.jpg" alt="Sarah">
                  <img src="./img/avatar-florian.jpg" alt="Florian">
                  <img src="./img/avatar-aurore.jpg" alt="Aurore">
                  <img src="./img/avatar-marc.jpg" alt="Marc">
                </div>
                <span class="participants-popup-text"><strong id="participantsTotalCount">142 connectés</strong> • Voir la liste</span>
                <svg class="chevron-pop" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </button>
            </div>

            <!-- Barre d'outils sous le player (Consulter la fiche en ligne uniquement) -->
            <div class="player-toolbar">
              <div class="toolbar-info">
                <strong>Session en cours • Mercredi 19h00 à 20h30</strong>
                <span>Replay HD disponible dès 21h00 avec la synthèse écrite & la fiche pratique</span>
              </div>
              <div class="toolbar-actions">
                <button type="button" class="btn btn-primary btn-sm" id="btnViewNotesModal">
                  📄 Consulter la fiche de l'atelier en ligne
                </button>
              </div>
            </div>
          </div>

          <!-- Chat Interactif en Direct -->
          <div class="dash-chat-wrapper">
            <div class="dash-chat-header">
              <div class="chat-header-title">
                <span>Discussion en direct</span>
                <span class="chat-online-badge" id="liveChatCount">● 142 actifs</span>
              </div>
              <?php if ($isLiveHost): ?>
                <span class="badge-chat-admin-pill" title="Vous administrez la session en tant qu'Animateur">👑 Hôte / Animateur</span>
              <?php else: ?>
                <span class="badge-chat-admin-pill" style="background:#f1f5f9; color:#475569;" title="Participant à la session">🎧 Participant</span>
              <?php endif; ?>
            </div>

            <!-- Message Épinglé en Direct par l'Animateur -->
            <div id="liveChatPinnedMessage" class="live-chat-pinned-box" style="display:none;">
              <div class="pinned-box-inner">
                <div class="pinned-box-left">
                  <span class="pinned-icon-badge">📌</span>
                  <div class="pinned-text-wrap">
                    <div class="pinned-badge-title">Message épinglé par l'animateur</div>
                    <div class="pinned-message-content" id="pinnedMessageContent"></div>
                    <div class="pinned-message-author" id="pinnedMessageAuthor"></div>
                  </div>
                </div>
                <button type="button" class="btn-unpin-action" id="btnUnpinLiveMessage" title="Dépingler ce commentaire">
                  ✕ Dépingler
                </button>
              </div>
            </div>

            <div class="dash-chat-messages" id="liveChatMessages">
              <div class="chat-msg">
                <img src="./img/avatar-aurore.jpg" alt="Aurore" class="msg-avatar">
                <div class="msg-body">
                  <div class="msg-author">Aurore M. <span class="msg-time">19:14</span></div>
                  <div class="msg-text">Totalement d'accord avec Cyril sur l'importance de simplifier la page de capture !</div>
                </div>
                <?php if ($isLiveHost): ?>
                <div class="chat-msg-actions">
                  <button type="button" class="btn-chat-pin" title="Épingler ce commentaire">📌 Épingler</button>
                  <button type="button" class="btn-chat-delete" title="Supprimer ce commentaire">🗑️ Supprimer</button>
                </div>
                <?php endif; ?>
              </div>

              <div class="chat-msg">
                <img src="./img/avatar-florian.jpg" alt="Florian" class="msg-avatar">
                <div class="msg-body">
                  <div class="msg-author">Florian L. <span class="msg-time">19:16</span></div>
                  <div class="msg-text">Est-ce que tu recommandes de proposer une offre d'entrée à 9€ avant un forfait coaching ?</div>
                </div>
                <?php if ($isLiveHost): ?>
                <div class="chat-msg-actions">
                  <button type="button" class="btn-chat-pin" title="Épingler ce commentaire">📌 Épingler</button>
                  <button type="button" class="btn-chat-delete" title="Supprimer ce commentaire">🗑️ Supprimer</button>
                </div>
                <?php endif; ?>
              </div>

              <div class="chat-msg msg-highlight">
                <img src="./img/avatar-cyril.jpg" alt="Cyril" class="msg-avatar">
                <div class="msg-body">
                  <div class="msg-author">Cyril D. (Host) <span class="msg-time">19:18</span></div>
                  <div class="msg-text">@Florian Absolument, c'est ce qu'on va détailler dans 5 minutes avec le partage d'écran !</div>
                </div>
                <?php if ($isLiveHost): ?>
                <div class="chat-msg-actions">
                  <button type="button" class="btn-chat-pin" title="Épingler ce commentaire">📌 Épingler</button>
                  <button type="button" class="btn-chat-delete" title="Supprimer ce commentaire">🗑️ Supprimer</button>
                </div>
                <?php endif; ?>
              </div>

              <div class="chat-msg">
                <img src="./img/avatar-sarah.jpg" alt="Sarah" class="msg-avatar">
                <div class="msg-body">
                  <div class="msg-author">Sarah B. <span class="msg-time">19:21</span></div>
                  <div class="msg-text">La trame de contrat freelance partagée la semaine dernière m'a sauvé un closing de 3 200€ 🙌</div>
                </div>
                <?php if ($isLiveHost): ?>
                <div class="chat-msg-actions">
                  <button type="button" class="btn-chat-pin" title="Épingler ce commentaire">📌 Épingler</button>
                  <button type="button" class="btn-chat-delete" title="Supprimer ce commentaire">🗑️ Supprimer</button>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Formulaire d'envoi de message dans le chat -->
            <form id="liveChatForm" class="dash-chat-input-row">
              <input type="text" id="liveChatInput" class="dash-chat-input" placeholder="Participez à la discussion..." required autocomplete="off">
              <button type="submit" class="dash-chat-send-btn" title="Envoyer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="22" y1="2" x2="11" y2="13"></line>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
              </button>
            </form>
          </div>

        </div>

        <!-- PROCHAINES SESSIONS PROGRAMMÉES -->
        <div class="dash-sub-block" style="margin-top:2.5rem;">
          <div class="sub-block-header">
            <h2 class="dash-sub-title">Calendrier des prochains Lives</h2>
            <?php if ($isCreator): ?>
              <a href="creer-live.php" class="btn btn-primary btn-sm btn-create-session-dash" title="Proposer un nouveau live ou mastermind">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="16"></line>
                  <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                <span>Créer un Live ou Mastermind</span>
              </a>
            <?php endif; ?>
          </div>
          
          <div class="dash-cards-grid" id="dashUpcomingLivesGrid">
            <?php foreach ($allLives as $live): 
                $liveTimestamp = strtotime($live['scheduled_date']);
                $dayNames = ['DIM', 'LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM'];
                $dayLetter = $dayNames[date('w', $liveTimestamp)];
                $dayNum = date('d', $liveTimestamp);
                $canManage = ($live['user_id'] == $currentUser['id']) || is_owner($currentUser) || is_admin_user($currentUser) || user_has_permission($currentUser, 'gerer_lives');
                $authorAvatar = !empty($live['author_avatar']) ? $live['author_avatar'] : './img/avatar-maxime.jpg';
            ?>
            <div class="dash-event-card" id="dash-live-<?= $live['id'] ?>" data-live-id="<?= $live['id'] ?>">
              <div class="event-card-date">
                <span class="event-day"><?= $dayLetter ?></span>
                <span class="event-num"><?= $dayNum ?></span>
              </div>
              <div class="event-card-content">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                  <span class="event-tag"><?= htmlspecialchars($live['format']) ?></span>
                  <span style="font-size:0.8rem; color:#64748b; font-weight:600;">🕒 <?= htmlspecialchars($live['scheduled_time']) ?> (<?= htmlspecialchars($live['duration']) ?>)</span>
                </div>
                <h3 class="event-title"><?= htmlspecialchars($live['title']) ?></h3>
                <p class="event-desc"><?= htmlspecialchars($live['description']) ?></p>
                
                <div class="event-footer" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem; margin-top:0.75rem;">
                  <!-- Profil et visage réel de l'Animateur -->
                  <div class="event-host-profile" style="display:flex; align-items:center; gap:0.65rem;">
                    <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($live['author_name']) ?>" style="width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0; box-shadow:0 2px 5px rgba(0,0,0,0.08);" onerror="this.src='./img/avatar-maxime.jpg'">
                    <div>
                      <div style="font-size:0.88rem; font-weight:700; color:#0f172a; line-height:1.2;"><?= htmlspecialchars($live['author_name']) ?></div>
                      <div style="font-size:0.75rem; color:#64748b;"><?= htmlspecialchars($live['author_role'] ?? 'Animateur One Vision') ?></div>
                    </div>
                  </div>
                  
                  <div class="event-actions-row" style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary btn-sm btn-add-google-cal" data-cal-title="<?= htmlspecialchars($live['title']) ?>" data-cal-date="<?= date('Ymd\THis\Z', $liveTimestamp) ?>" data-cal-desc="<?= htmlspecialchars($live['description']) ?>" data-cal-loc="Espace Live One Vision">📅 Rappel agenda</button>
                    
                    <?php if ($canManage): ?>
                      <button type="button" class="btn btn-outline btn-sm btn-edit-live-trigger" 
                        data-id="<?= $live['id'] ?>"
                        data-title="<?= htmlspecialchars($live['title']) ?>"
                        data-desc="<?= htmlspecialchars($live['description']) ?>"
                        data-format="<?= htmlspecialchars($live['format']) ?>"
                        data-date="<?= htmlspecialchars($live['scheduled_date']) ?>"
                        data-time="<?= htmlspecialchars($live['scheduled_time']) ?>"
                        data-duration="<?= htmlspecialchars($live['duration']) ?>"
                        data-resources="<?= htmlspecialchars($live['resources']) ?>"
                        style="font-size:0.78rem; padding:0.35rem 0.65rem; border:1px solid #bfdbfe; color:#2563eb; background:#eff6ff;"
                        title="Modifier cette session">
                        ✏️ Modifier
                      </button>
                      <button type="button" class="btn btn-outline btn-sm btn-delete-live-trigger" 
                        data-id="<?= $live['id'] ?>"
                        data-title="<?= htmlspecialchars($live['title']) ?>"
                        style="font-size:0.78rem; padding:0.35rem 0.65rem; border:1px solid #fecaca; color:#dc2626; background:#fef2f2;"
                        title="Supprimer cette session">
                        🗑️ Supprimer
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- REPLAYS POPULAIRES DISPONIBLES EN HD -->
        <div class="dash-sub-block" style="margin-top:2.5rem;">
          <div class="sub-block-header">
            <h2 class="dash-sub-title">Replays HD & Masterclasses Archivées</h2>
            <span class="sub-badge-count">36 sessions complètes</span>
          </div>

          <div class="dash-replays-grid">
            <div class="replay-card" 
                 data-replay-id="1"
                 data-replay-title="Comment structurer et tarifer son accompagnement à 2 000€+" 
                 data-replay-cat="Vente & Offres" 
                 data-replay-host="Cyril D." 
                 data-replay-views="468" 
                 data-replay-duration="1h 18min"
                 data-replay-thumb="./img/feature-replays.jpg"
                 data-replay-desc="Dans ce Mastermind complet, Cyril D. décortique la psychologie de la valeur perçue, la structure d'une proposition commerciale irréprochable et la méthode exacte pour fixer un prix supérieur à 2 000€ sans trembler au moment de l'annonce.">
              <div class="replay-thumb">
                <img src="./img/feature-replays.jpg" alt="Replay Tarification">
                <span class="replay-duration">1h 18min</span>
                <div class="replay-play-icon">▶</div>
              </div>
              <div class="replay-body">
                <span class="replay-cat">Vente & Offres</span>
                <h4 class="replay-title">Comment structurer et tarifer son accompagnement à 2 000€+</h4>
                <p class="replay-meta">Par Cyril D. • 468 vues • Modèle de contrat inclus</p>
              </div>
            </div>

            <div class="replay-card" 
                 data-replay-id="2"
                 data-replay-title="Les 5 leviers pour trouver ses 10 premiers clients sans pub" 
                 data-replay-cat="Acquisition" 
                 data-replay-host="Sarah B." 
                 data-replay-views="392" 
                 data-replay-duration="54min"
                 data-replay-thumb="./img/feature-network.jpg"
                 data-replay-desc="Sarah B. partage son système en 5 étapes pour convertir des prises de contact directes en mandats payants. Découvrez comment transformer une conversation informelle en client signé sans dépenser 1€ en acquisition payante.">
              <div class="replay-thumb">
                <img src="./img/feature-network.jpg" alt="Replay Réseau">
                <span class="replay-duration">54min</span>
                <div class="replay-play-icon">▶</div>
              </div>
              <div class="replay-body">
                <span class="replay-cat">Acquisition</span>
                <h4 class="replay-title">Les 5 leviers pour trouver ses 10 premiers clients sans pub</h4>
                <p class="replay-meta">Par Sarah B. • 392 vues • Fiche méthode incluse</p>
              </div>
            </div>

            <div class="replay-card" 
                 data-replay-id="3"
                 data-replay-title="Automatiser son back-office pour libérer 12h chaque semaine" 
                 data-replay-cat="Organisation" 
                 data-replay-host="Maxime T." 
                 data-replay-views="512" 
                 data-replay-duration="1h 05min"
                 data-replay-thumb="./img/feature-projects.jpg"
                 data-replay-desc="Atelier pratique et démonstration en direct : automatisation de la facturation, synchronisation Notion / Stripe, relances devis automatiques et gestion de projet sans stress.">
              <div class="replay-thumb">
                <img src="./img/feature-projects.jpg" alt="Replay Productivité">
                <span class="replay-duration">1h 05min</span>
                <div class="replay-play-icon">▶</div>
              </div>
              <div class="replay-body">
                <span class="replay-cat">Organisation</span>
                <h4 class="replay-title">Automatiser son back-office pour libérer 12h chaque semaine</h4>
                <p class="replay-meta">Par Maxime T. • 512 vues • Trames Notion offertes</p>
              </div>
            </div>
          </div>
        </div>

      </section>

      <!-- ======================================================================
           ONGLET 2 : CALENDRIER DES LIVES & SESSIONS
           ====================================================================== -->
      <section id="tab-calendrier" class="dash-tab-content">
        <div class="dash-section-header">
          <div>
            <h1 class="dash-title">Calendrier des Sessions & Lives</h1>
            <p class="dash-desc">Découvrez l'agenda des prochains masterminds, ateliers de co-working et sessions de questions/réponses en direct.</p>
          </div>
          <div class="dash-header-actions">
            <?php if ($isCreator): ?>
              <a href="espace-animateur.php" class="btn btn-primary btn-create-session-dash" id="btnCreateLiveDashTop" title="Programmer un live dans l'Espace Animateur">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="16"></line>
                  <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                <span>Créer un Live ou Mastermind</span>
              </a>
            <?php else: ?>
              <a href="abonnements.php" class="btn btn-primary btn-create-session-dash" id="btnCreateLiveDashTop" title="Réservé aux Animateurs — Voir la formule Animateur">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Créer un Live (Formule Animateur)</span>
              </a>
            <?php endif; ?>
          </div>
        </div>

        <div class="calendar-events-list">
          
          <!-- LIVES CHARGÉS DYNAMIQUEMENT DEPUIS LA BASE DE DONNÉES SQLITE -->
          <?php foreach ($allLives as $live): 
              $liveTimestamp = strtotime($live['scheduled_date']);
              $dayNames = ['DIM', 'LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM'];
              $dayLetter = $dayNames[date('w', $liveTimestamp)];
              $dayNum = date('d', $liveTimestamp);
              $canManage = ($live['user_id'] == $currentUser['id']) || is_owner($currentUser) || is_admin_user($currentUser) || user_has_permission($currentUser, 'gerer_lives');
              $authorAvatar = !empty($live['author_avatar']) ? $live['author_avatar'] : './img/avatar-maxime.jpg';
          ?>
          <div class="calendar-card" id="cal-card-<?= $live['id'] ?>" data-live-id="<?= $live['id'] ?>">
            <div class="calendar-date-badge">
              <span class="calendar-date-month"><?= $dayLetter ?></span>
              <span class="calendar-date-day"><?= $dayNum ?></span>
            </div>
            <div class="calendar-card-body">
              <div class="calendar-card-top">
                <span class="badge-plan-active" style="background:#2563eb;"><?= htmlspecialchars($live['format']) ?></span>
                <span class="calendar-time-tag"><?= htmlspecialchars($live['scheduled_time']) ?> (<?= htmlspecialchars($live['duration']) ?>)</span>
              </div>
              <h3 class="calendar-card-title"><?= htmlspecialchars($live['title']) ?></h3>
              <p class="calendar-card-desc"><?= htmlspecialchars($live['description']) ?></p>
              
              <!-- Profil et visage réel de l'Animateur -->
              <div class="calendar-host-row" style="display:flex; align-items:center; gap:0.65rem; margin:0.6rem 0 0.85rem 0;">
                <img src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($live['author_name']) ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0; box-shadow:0 2px 4px rgba(0,0,0,0.06);" onerror="this.src='./img/avatar-maxime.jpg'">
                <div>
                  <strong style="font-size:0.86rem; color:#0f172a;"><?= htmlspecialchars($live['author_name']) ?></strong>
                  <span style="font-size:0.75rem; color:#64748b; margin-left:0.35rem;">(<?= htmlspecialchars($live['author_role'] ?? 'Animateur One Vision') ?>)</span>
                </div>
              </div>

              <?php if (!empty($live['resources'])): ?>
                <div style="font-size:0.83rem; color:#0369a1; background:#e0f2fe; padding:0.35rem 0.65rem; border-radius:6px; margin-bottom:0.75rem; display:inline-block;">
                  📎 Ressource offerte : <?= htmlspecialchars($live['resources']) ?>
                </div>
              <?php endif; ?>
              <div class="calendar-card-actions" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                  <button type="button" class="btn btn-primary btn-sm btn-join-live-direct">
                    Rejoindre la salle Live
                  </button>
                  <button type="button" class="btn btn-secondary btn-sm btn-add-google-cal" data-cal-title="<?= htmlspecialchars($live['title']) ?>" data-cal-date="<?= date('Ymd\THis\Z', $liveTimestamp) ?>" data-cal-desc="<?= htmlspecialchars($live['description']) ?>" data-cal-loc="Espace Live One Vision">
                    📅 Rappel agenda
                  </button>
                </div>
                
                <?php if ($canManage): ?>
                <div class="live-creator-actions" style="display:flex; align-items:center; gap:0.45rem;">
                  <button type="button" class="btn btn-outline btn-sm btn-edit-live-trigger" 
                    data-id="<?= $live['id'] ?>"
                    data-title="<?= htmlspecialchars($live['title']) ?>"
                    data-desc="<?= htmlspecialchars($live['description']) ?>"
                    data-format="<?= htmlspecialchars($live['format']) ?>"
                    data-date="<?= htmlspecialchars($live['scheduled_date']) ?>"
                    data-time="<?= htmlspecialchars($live['scheduled_time']) ?>"
                    data-duration="<?= htmlspecialchars($live['duration']) ?>"
                    data-resources="<?= htmlspecialchars($live['resources']) ?>"
                    style="font-size:0.78rem; padding:0.35rem 0.65rem; border:1px solid #bfdbfe; color:#2563eb; background:#eff6ff;"
                    title="Modifier cette session">
                    ✏️ Modifier
                  </button>
                  <button type="button" class="btn btn-outline btn-sm btn-delete-live-trigger"
                    data-id="<?= $live['id'] ?>"
                    data-title="<?= htmlspecialchars($live['title']) ?>"
                    style="font-size:0.78rem; padding:0.35rem 0.65rem; border:1px solid #fecaca; color:#dc2626; background:#fef2f2;"
                    title="Supprimer cette session">
                    🗑️ Supprimer
                  </button>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- ======================================================================
           ONGLET 3 : SALONS D'ÉCHANGE DE LA COMMUNAUTÉ
           ====================================================================== -->
      <section id="tab-salons" class="dash-tab-content">
        <div class="dash-section-header">
          <div>
            <h1 class="dash-title" id="salonMainTitle">Salons d'Échanges & Entraide</h1>
            <p class="dash-desc">Échangez librement avec plus de 1 200 pairs. Pas d'algorithme, que de l'entraide directe et productive.</p>
          </div>
          <button type="button" class="btn btn-primary btn-sm" id="btnOpenNewSalonModal">
            + Créer un salon <span style="font-size:0.75rem;opacity:0.9;margin-left:4px;background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:12px;">👑 Admin</span>
          </button>
        </div>

        <div class="salons-layout">
          <!-- Canaux -->
          <div class="salons-channels-list" id="salonsChannelsList">
            <div class="channel-item active" data-channel="general"># 💬 général</div>
            <div class="channel-item" data-channel="entraide"># 🤝 entraide-et-solutions</div>
            <div class="channel-item" data-channel="feedback"># 🔍 feedback-projets</div>
            <div class="channel-item" data-channel="partenariats"># 💼 partenariats-offres</div>
            <div class="channel-item" data-channel="coworking"># 💻 co-working-virtuel</div>
            <div class="channel-item" data-channel="victoires"># 🎉 victoires-et-bilans</div>
          </div>

          <!-- Fil de discussion actif & Compositeur -->
          <div class="salons-main-column">
            <div class="salon-feed" id="salonFeedContainer">
              <!-- Chargé et géré dynamiquement par JavaScript avec réponses imbriquées et interactions -->
            </div>

            <!-- Compositeur de message intégré en bas du salon actif -->
            <div class="salon-composer-box">
              <form id="inlineSalonPostForm" class="salon-inline-form">
                <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="<?= htmlspecialchars($currentUser['full_name']) ?>" class="composer-avatar" id="salonComposerAvatar" data-user-name="<?= htmlspecialchars($currentUser['full_name']) ?>" data-user-role="<?= htmlspecialchars($currentUser['job_title'] ?: 'Membre') ?>">
                <div class="composer-input-wrap">
                  <textarea id="inlineSalonPostInput" placeholder="Partager un message, poser une question ou donner un retour dans ce salon..." rows="2" required></textarea>
                  
                  <!-- Aperçu de l'image jointe -->
                  <div id="salonImageAttachmentPreview" class="attachment-preview-box" style="display:none;">
                    <div class="attachment-thumb-wrap">
                      <img id="attachedImageElement" src="" alt="Aperçu image jointe">
                      <button type="button" class="btn-remove-attachment" id="btnRemoveAttachedImage" title="Supprimer l'image">&times;</button>
                    </div>
                    <span class="attachment-label" id="attachmentLabel">Image prête à être partagée</span>
                  </div>

                  <!-- Sélecteur rapide d'émojis -->
                  <div id="salonEmojiPicker" class="quick-emoji-bar" style="display:none;">
                    <span class="emoji-btn" data-emoji="🔥" title="Feu">🔥</span>
                    <span class="emoji-btn" data-emoji="🚀" title="Fusée">🚀</span>
                    <span class="emoji-btn" data-emoji="💡" title="Idée">💡</span>
                    <span class="emoji-btn" data-emoji="👏" title="Bravo">👏</span>
                    <span class="emoji-btn" data-emoji="🎯" title="Cible">🎯</span>
                    <span class="emoji-btn" data-emoji="❤️" title="Cœur">❤️</span>
                    <span class="emoji-btn" data-emoji="🎉" title="Fête">🎉</span>
                    <span class="emoji-btn" data-emoji="✨" title="Étincelles">✨</span>
                    <span class="emoji-btn" data-emoji="🤝" title="Partenariat">🤝</span>
                    <span class="emoji-btn" data-emoji="💪" title="Force">💪</span>
                    <span class="emoji-btn" data-emoji="📈" title="Croissance">📈</span>
                    <span class="emoji-btn" data-emoji="🙌" title="Célébration">🙌</span>
                    <span class="emoji-btn" data-emoji="👍" title="Pouce">👍</span>
                    <span class="emoji-btn" data-emoji="🏆" title="Trophée">🏆</span>
                  </div>

                  <div class="composer-actions">
                    <div class="composer-tools">
                      <button type="button" class="btn-composer-tool" id="btnToggleSalonEmoji" title="Insérer un émoji">
                        <span>😊 Émoji</span>
                      </button>
                      <label for="salonImageFileInput" class="btn-composer-tool" id="btnAttachSalonImage" title="Joindre une image (PNG, JPG, WEBP)">
                        <span>🖼️ Image</span>
                        <input type="file" id="salonImageFileInput" accept="image/*" style="display:none;">
                      </label>
                      <span class="composer-hint">💡 Entraide bienveillante & sans pitch</span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                      <span>💬 Publier dans le salon</span>
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>

      <!-- ======================================================================
           ONGLET 4 : RESSOURCES & MODÈLES OFFERTS
           ====================================================================== -->
      <section id="tab-ressources" class="dash-tab-content">
        <div class="dash-section-header">
          <div>
            <h1 class="dash-title">Modèles, Trames & Boîte à Outils</h1>
            <p class="dash-desc">Tous les documents prêts à l'emploi inclus avec votre adhésion de 9€/mois. Téléchargement direct et immédiat.</p>
          </div>
        </div>

        <div class="resources-grid">
          <div class="resource-card">
            <div class="resource-icon">📄</div>
            <div class="resource-info">
              <h3 class="resource-title">Modèle de Contrat de Prestation Freelance</h3>
              <p class="resource-desc">Rédigé et validé par un juriste spécialisé web & coaching. Clauses de propriété intellectuelle et de paiement sécurisé.</p>
              <div class="resource-meta">Formats .PDF & .DOCX disponibles • 240 Ko • Modèle complet prêt à l'emploi</div>
            </div>
            <div class="resource-download-actions">
              <div class="split-download-group">
                <button type="button" class="btn btn-primary btn-sm btn-split-main btn-download-res" data-res="contrat" data-ext="pdf" title="Télécharger la version standard (.PDF)">
                  <span>📥 Télécharger (.PDF)</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm btn-split-toggle" aria-expanded="false" title="Choisir un autre format (Word .docx, PDF)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </button>
                <div class="split-download-menu" style="display:none;">
                  <button type="button" class="split-menu-item btn-download-res" data-res="contrat" data-ext="pdf">
                    <span class="split-icon">📄</span>
                    <div class="split-text">
                      <strong>Version .PDF</strong>
                      <small>Prêt à signer ou imprimer (240 Ko)</small>
                    </div>
                  </button>
                  <button type="button" class="split-menu-item btn-download-res" data-res="contrat" data-ext="docx">
                    <span class="split-icon">📝</span>
                    <div class="split-text">
                      <strong>Version Word (.docx)</strong>
                      <small>Modifiable, clauses personnalisables</small>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="resource-card">
            <div class="resource-icon">🎯</div>
            <div class="resource-info">
              <h3 class="resource-title">Trame d'Appel Découverte & Closing Éthique</h3>
              <p class="resource-desc">Le script étape par étape en 6 phases pour qualifier les prospects et closer sans paraître insistant.</p>
              <div class="resource-meta">Formats .PDF & .DOCX disponibles • 180 Ko • Guide pratique & scripts mot-à-mot</div>
            </div>
            <div class="resource-download-actions">
              <div class="split-download-group">
                <button type="button" class="btn btn-primary btn-sm btn-split-main btn-download-res" data-res="trame" data-ext="pdf" title="Télécharger la fiche (.PDF)">
                  <span>📥 Télécharger (.PDF)</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm btn-split-toggle" aria-expanded="false" title="Choisir un autre format (Word .docx, PDF)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </button>
                <div class="split-download-menu" style="display:none;">
                  <button type="button" class="split-menu-item btn-download-res" data-res="trame" data-ext="pdf">
                    <span class="split-icon">🎯</span>
                    <div class="split-text">
                      <strong>Version .PDF</strong>
                      <small>Fiche mémo 6 phases (180 Ko)</small>
                    </div>
                  </button>
                  <button type="button" class="split-menu-item btn-download-res" data-res="trame" data-ext="docx">
                    <span class="split-icon">📝</span>
                    <div class="split-text">
                      <strong>Version Word (.docx)</strong>
                      <small>Trame modifiable avec vos offres</small>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="resource-card">
            <div class="resource-icon">📊</div>
            <div class="resource-info">
              <h3 class="resource-title">Simulateur de Rentabilité & Tarifs Horaires</h3>
              <p class="resource-desc">Calculateur automatisé pour déterminer son TJM et le prix de ses packages de coaching/prestations.</p>
              <div class="resource-meta">Formats .PDF (Guide) & .CSV / Excel disponibles • Formules automatiques</div>
            </div>
            <div class="resource-download-actions">
              <div class="split-download-group">
                <button type="button" class="btn btn-primary btn-sm btn-split-main btn-download-res" data-res="simulateur" data-ext="pdf" title="Télécharger le guide (.PDF)">
                  <span>📥 Télécharger (.PDF)</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm btn-split-toggle" aria-expanded="false" title="Choisir un autre format (Tableur Excel / .CSV, PDF)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </button>
                <div class="split-download-menu" style="display:none;">
                  <button type="button" class="split-menu-item btn-download-res" data-res="simulateur" data-ext="pdf">
                    <span class="split-icon">📄</span>
                    <div class="split-text">
                      <strong>Guide Méthodologique (.pdf)</strong>
                      <small>Formules & méthode de calcul TJM</small>
                    </div>
                  </button>
                  <button type="button" class="split-menu-item btn-download-res" data-res="simulateur" data-ext="csv">
                    <span class="split-icon">📊</span>
                    <div class="split-text">
                      <strong>Tableur Excel / .CSV</strong>
                      <small>Matrice de calcul dynamique</small>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="resources-bottom-banner">
          <div class="resources-bottom-content">
            <span class="resources-bottom-icon">🔒</span>
            <div>
              <strong>Documents & Outils Officiels One Vision Community</strong>
              <p>Tous les modèles et tableurs sont révisés par nos experts partenaires et 100% exploitables pour votre activité commerciale.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ======================================================================
           ONGLET 5 : ANNUAIRE DES MEMBRES
           ====================================================================== -->
      <section id="tab-reseau" class="dash-tab-content">
        <div class="dash-section-header">
          <div>
            <h1 class="dash-title">Annuaire des Membres Actifs</h1>
            <p class="dash-desc">Découvrez les profils de la communauté, trouvez vos partenaires de projet et échangez directement.</p>
          </div>
        </div>

        <div class="members-directory-grid">
          <!-- Membre 1 : Aurore M. -->
          <div class="member-profile-card" data-member-id="aurore">
            <div class="member-card-header">
              <div class="member-card-avatar-wrap">
                <img src="./img/avatar-aurore.jpg" alt="Aurore M." class="member-card-avatar">
                <span class="member-status-online" title="En ligne"></span>
              </div>
            </div>
            <h3 class="member-card-name">Aurore M.</h3>
            <span class="member-card-role">Fondatrice Studio Créatif & DA</span>
            <p class="member-card-bio">Spécialiste en identité visuelle premium et branding haut de gamme pour consultants et créateurs.</p>
            <div class="member-card-actions-group">
              <button type="button" class="btn btn-secondary btn-sm btn-view-profile" data-member-id="aurore">
                👤 Voir le profil
              </button>
              <button type="button" class="btn btn-primary btn-sm btn-send-dm" data-member-name="Aurore M." data-member-role="Fondatrice Studio Créatif" data-member-avatar="./img/avatar-aurore.jpg">
                ✉️ Message
              </button>
            </div>
          </div>

          <!-- Membre 2 : Cyril D. -->
          <div class="member-profile-card" data-member-id="cyril">
            <div class="member-card-header">
              <div class="member-card-avatar-wrap">
                <img src="./img/avatar-cyril.jpg" alt="Cyril D." class="member-card-avatar">
                <span class="member-status-online" title="En direct"></span>
              </div>
            </div>
            <h3 class="member-card-name">Cyril D.</h3>
            <span class="member-card-role">Coach Business & Mentor High-Ticket</span>
            <p class="member-card-bio">Aide les indépendants à packager leurs offres à haute valeur ajoutée (2 000€+) et closer sans pression.</p>
            <div class="member-card-actions-group">
              <button type="button" class="btn btn-secondary btn-sm btn-view-profile" data-member-id="cyril">
                👤 Voir le profil
              </button>
              <button type="button" class="btn btn-primary btn-sm btn-send-dm" data-member-name="Cyril D." data-member-role="Coach Business & Mentor" data-member-avatar="./img/avatar-cyril.jpg">
                ✉️ Message
              </button>
            </div>
          </div>

          <!-- Membre 3 : Florian L. -->
          <div class="member-profile-card" data-member-id="florian">
            <div class="member-card-header">
              <div class="member-card-avatar-wrap">
                <img src="./img/avatar-florian.jpg" alt="Florian L." class="member-card-avatar">
                <span class="member-status-online" title="En ligne"></span>
              </div>
            </div>
            <h3 class="member-card-name">Florian L.</h3>
            <span class="member-card-role">Stratégie B2B & Prospection</span>
            <p class="member-card-bio">Accompagnement sur les tunnels de vente, la génération de rendez-vous et la conversion LinkedIn.</p>
            <div class="member-card-actions-group">
              <button type="button" class="btn btn-secondary btn-sm btn-view-profile" data-member-id="florian">
                👤 Voir le profil
              </button>
              <button type="button" class="btn btn-primary btn-sm btn-send-dm" data-member-name="Florian L." data-member-role="Stratégie B2B & Prospection" data-member-avatar="./img/avatar-florian.jpg">
                ✉️ Message
              </button>
            </div>
          </div>

          <!-- Membre 4 : Sarah B. -->
          <div class="member-profile-card" data-member-id="sarah">
            <div class="member-card-header">
              <div class="member-card-avatar-wrap">
                <img src="./img/avatar-sarah.jpg" alt="Sarah B." class="member-card-avatar">
                <span class="member-status-online" title="En ligne"></span>
              </div>
            </div>
            <h3 class="member-card-name">Sarah B.</h3>
            <span class="member-card-role">Copywriter & Formatrice</span>
            <p class="member-card-bio">Création de pages de vente persuasives, de lancements par emails et de newsletters engageantes.</p>
            <div class="member-card-actions-group">
              <button type="button" class="btn btn-secondary btn-sm btn-view-profile" data-member-id="sarah">
                👤 Voir le profil
              </button>
              <button type="button" class="btn btn-primary btn-sm btn-send-dm" data-member-name="Sarah B." data-member-role="Copywriter & Formatrice" data-member-avatar="./img/avatar-sarah.jpg">
                ✉️ Message
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- ======================================================================
           PAGE DÉDIÉE 1 : MON ABONNEMENT (9€/MOIS)
           ====================================================================== -->
      <section id="tab-compte" class="dash-tab-content dash-account-page">
        <div class="account-page-topbar">
          <button type="button" class="btn-back-to-dashboard btnBackToDashboardAction" id="btnBackFromSub" title="Retour au Dashboard">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span>Retour</span>
          </button>
          <div class="account-badge-pill">
            <span>💳 Mon Abonnement & Factures</span>
          </div>
        </div>

        <div class="dash-section-header">
          <div>
            <h1 class="dash-title">Gestion de mon Abonnement</h1>
            <p class="dash-desc">Suivez votre formule, vos échéances, personnalisez votre niveau d'adhésion et consultez vos factures officielles.</p>
          </div>
        </div>

        <!-- 1. CARTE DE L'ABONNEMENT ACTUEL -->
        <?php if ($isOwnerUser || $isAdminUser): ?>
          <div class="account-card" style="border: 1px solid #cbd5e1; margin-bottom: 2rem;">
            <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1rem;">
              <span style="font-size:2.2rem;">👑</span>
              <div>
                <h2 style="font-size:1.35rem; font-weight:800; color:#0f172a; margin:0;">
                  Compte d'Administration Permanente
                </h2>
                <span style="font-size:0.85rem; color:#d97706; font-weight:700; text-transform:uppercase;">
                  <?= $isOwnerUser ? 'Propriétaire du site (Super-admin)' : 'Administrateur Délégué' ?>
                </span>
              </div>
            </div>
            <p style="color:#475569; font-size:0.92rem; line-height:1.6; margin:0 0 1.25rem 0;">
              Votre compte dispose d'un accès intégral et illimité à l'Académie, à la communauté et aux outils de gestion sans aucune restriction d'abonnement.
            </p>
            <a href="admin/index.php" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:0.5rem; text-decoration:none;">
              <span>Accéder à l'Espace Administration</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
          </div>

        <?php elseif ($activeSub): ?>
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem 2.25rem; margin-bottom:2rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
            <!-- En-tête de la formule active -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.25rem; border-bottom:1px solid #f1f5f9; padding-bottom:1.5rem; margin-bottom:1.5rem;">
              <div>
                <?php if (!empty($subInfo['is_cancelled'])): ?>
                  <span style="background:#fee2e2; color:#991b1b; padding:0.35rem 0.9rem; border-radius:9999px; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; display:inline-flex; align-items:center; gap:0.45rem; margin-bottom:0.75rem;">
                    <span style="width:7px; height:7px; border-radius:50%; background:#ef4444;"></span>
                    Résiliation programmée
                  </span>
                <?php else: ?>
                  <span style="background:#dcfce7; color:#15803d; padding:0.35rem 0.9rem; border-radius:9999px; font-weight:800; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; display:inline-flex; align-items:center; gap:0.45rem; margin-bottom:0.75rem;">
                    <span style="width:7px; height:7px; border-radius:50%; background:#16a34a;"></span>
                    ABONNEMENT ACTIF
                  </span>
                <?php endif; ?>

                <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0; letter-spacing:-0.02em;">
                  <?= ($activeSub['plan_code'] === 'animateur') ? 'Formule Animateur & Expert (Abonnement Plus)' : 'Formule Membre Standard' ?>
                </h2>
                <p style="color:#64748b; font-size:0.92rem; margin:0;">
                  <?php if (!empty($subInfo['is_cancelled'])): ?>
                    Vos privilèges restent valides jusqu'au <strong><?= ov_format_date_fr($activeSub['date_fin']) ?></strong>.
                  <?php else: ?>
                    Prochain prélèvement automatique le <strong><?= ov_format_date_fr($activeSub['date_fin']) ?></strong>.
                  <?php endif; ?>
                </p>
              </div>

              <div style="text-align:right;">
                <div style="font-size:2.35rem; font-weight:900; color:#0f172a; letter-spacing:-0.03em; line-height:1.1;">
                  <?= number_format((float)$activeSub['prix_paye'], 2, ',', ' ') ?> €
                </div>
                <span style="font-size:0.85rem; color:#64748b; font-weight:600;">/ <?= ($activeSub['periodicite'] === 'annuel') ? 'an' : 'mois' ?> TTC</span>
              </div>
            </div>

            <!-- Détails 3 colonnes -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.5rem; font-size:0.9rem; margin-bottom:1.5rem;">
              <div>
                <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em; display:block; margin-bottom:0.35rem;">Date de souscription</span>
                <div style="font-weight:700; color:#0f172a; font-size:0.98rem;"><?= ov_format_date_fr($activeSub['date_debut']) ?></div>
              </div>
              <div>
                <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em; display:block; margin-bottom:0.35rem;">Renouvellement</span>
                <div style="font-weight:700; color:<?= empty($subInfo['is_cancelled']) ? '#15803d' : '#dc2626' ?>; font-size:0.98rem;">
                  <?= empty($subInfo['is_cancelled']) ? 'Automatique (Résiliable 1 clic)' : 'Résiliation programmée' ?>
                </div>
              </div>
              <div>
                <span style="color:#64748b; font-size:0.78rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em; display:block; margin-bottom:0.35rem;">Mode de paiement</span>
                <div style="font-weight:700; color:#0f172a; font-size:0.98rem; display:flex; align-items:center; gap:0.45rem;">
                  <svg width="18" height="14" viewBox="0 0 24 18" fill="none" style="vertical-align:middle; flex-shrink:0;"><rect width="24" height="18" rx="3" fill="#2563eb"/><rect y="4" width="24" height="3" fill="#1e293b"/><rect x="3" y="11" width="6" height="3" rx="1" fill="#cbd5e1"/></svg>
                  <span>Visa terminant par 4242</span>
                </div>
              </div>
            </div>

            <!-- Boutons d'action : pilules conformes à la maquette -->
            <div style="display:flex; justify-content:flex-end; align-items:center; gap:1rem; flex-wrap:wrap; border-top:1px solid #f1f5f9; padding-top:1.5rem;">
              <a href="choisir-abonnement.php" style="background:#f1f5f9; border:1.5px solid #cbd5e1; color:#0f172a; font-weight:700; font-size:0.92rem; padding:0.65rem 1.6rem; border-radius:9999px; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; transition:all 0.2s ease; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                Changer de formule d'abonnement
              </a>
              <form method="POST" action="dashboard.php" style="display:inline; margin:0;" onsubmit="return confirm('Êtes-vous certain de vouloir résilier votre abonnement ?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resilier">
                <button type="submit" style="background:#fef2f2; border:1.5px solid #fecaca; color:#dc2626; font-weight:700; font-size:0.92rem; padding:0.65rem 1.6rem; border-radius:9999px; cursor:pointer; display:inline-flex; align-items:center; gap:0.5rem; transition:all 0.2s ease;">
                  Résilier mon abonnement
                </button>
              </form>
            </div>
          </div>

        <?php else: ?>
          <!-- Utilisateur sans abonnement actif : carte unique -->
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:2rem 2.25rem; margin-bottom:2rem; box-shadow:0 8px 25px rgba(0,0,0,0.04);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.25rem; border-bottom:1px solid #f1f5f9; padding-bottom:1.5rem; margin-bottom:1.5rem;">
              <div>
                <span style="font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; background:#fee2e2; color:#dc2626; padding:0.35rem 0.9rem; border-radius:9999px; display:inline-flex; align-items:center; gap:0.45rem; margin-bottom:0.75rem;">
                  <span style="width:7px; height:7px; border-radius:50%; background:#dc2626; display:inline-block;"></span>
                  Abonnement Inactif
                </span>
                <h2 style="font-size:1.6rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem 0; letter-spacing:-0.02em;">
                  Aucun abonnement en cours
                </h2>
                <p style="color:#64748b; font-size:0.92rem; margin:0;">
                  Vous ne bénéficiez actuellement plus d'un abonnement actif.
                </p>
              </div>
            </div>

            <p style="color:#475569; font-size:0.95rem; line-height:1.6; margin:0 0 1.5rem 0;">
              Pour continuer à profiter de l'ensemble des masterminds, replays vidéo et salons d'échange de la communauté, choisissez une formule d'abonnement.
            </p>

            <div style="display:flex; justify-content:flex-end; gap:1rem; border-top:1px solid #f1f5f9; padding-top:1.5rem;">
              <a href="choisir-abonnement.php" style="background:#f1f5f9; border:1.5px solid #cbd5e1; color:#0f172a; font-weight:700; font-size:0.92rem; padding:0.65rem 1.6rem; border-radius:9999px; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; transition:all 0.2s ease;">
                <span>Changer de formule d'abonnement</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- 3. HISTORIQUE DES FACTURES & PAIEMENTS -->
        <div class="settings-card" style="margin-top:1.5rem;">
          <h3 style="font-size:1.15rem;font-weight:700;color:#0f172a;margin-bottom:0.4rem;">Historique de vos Paiements & Factures</h3>
          <p style="color:#64748b;font-size:0.88rem;margin-bottom:1.2rem;">Téléchargez vos justificatifs fiscaux et reçus officiels pour chaque échéance.</p>
          
          <div class="invoices-list" id="dashboardInvoicesList">
            <?php if (!empty($userPayments)): ?>
              <?php foreach ($userPayments as $pay): ?>
                <div class="invoice-item">
                  <div class="invoice-meta">
                    <span class="invoice-icon">🧾</span>
                    <div>
                      <strong><?= htmlspecialchars($pay['plan_nom'] ?? 'Cotisation One Vision') ?> (<?= ucfirst($pay['periodicite'] ?? 'mensuel') ?>)</strong>
                      <span class="invoice-date"><?= date('d F Y à H:i', strtotime($pay['date_paiement'])) ?> • <?= number_format((float)$pay['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($pay['devise']) ?></span>
                      <span style="font-family:monospace; font-size:0.78rem; color:#94a3b8; display:block;">Réf: <?= htmlspecialchars($pay['reference_externe'] ?? $pay['reference']) ?></span>
                    </div>
                  </div>
                  <span class="badge-invoice-paid"><?= ($pay['statut'] === 'reussi') ? 'Réglé' : htmlspecialchars(ucfirst($pay['statut'])) ?></span>
                  <a href="facture.php?ref=<?= urlencode($pay['reference']) ?>" target="_blank" class="btn-invoice-dl" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                    Télécharger (.PDF)
                  </a>
                </div>
              <?php endforeach; ?>
            <?php elseif (!empty($userOrders)): ?>
              <?php foreach ($userOrders as $ord): ?>
                <div class="invoice-item">
                  <div class="invoice-meta">
                    <span class="invoice-icon">🧾</span>
                    <div>
                      <strong>Facture #<?= htmlspecialchars($ord['invoice_number']) ?></strong>
                      <span class="invoice-date"><?= date('d F Y', strtotime($ord['created_at'])) ?> • <?= number_format($ord['amount'], 2, ',', ' ') ?> € TTC</span>
                    </div>
                  </div>
                  <span class="badge-invoice-paid">Payée</span>
                  <a href="facture.php?id=<?= $ord['id'] ?>" target="_blank" class="btn-invoice-dl" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                    Télécharger (.PDF)
                  </a>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="invoice-item">
                <div class="invoice-meta">
                  <span class="invoice-icon">🧾</span>
                  <div>
                    <strong>Facture #OV-2026-01</strong>
                    <span class="invoice-date"><?= date('d F Y') ?> • 9,00 € TTC</span>
                  </div>
                </div>
                <span class="badge-invoice-paid">Payée</span>
                <a href="facture.php" target="_blank" class="btn-invoice-dl" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                  Télécharger (.PDF)
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- ======================================================================
           PAGE DÉDIÉE 2 : PARAMÈTRES DU COMPTE
           ====================================================================== -->
      <section id="tab-parametres" class="dash-tab-content dash-account-page">
        <div class="account-page-topbar">
          <button type="button" class="btn-back-to-dashboard btnBackToDashboardAction" id="btnBackFromParams" title="Retour au Dashboard">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span>Retour</span>
          </button>
        </div>

        <div class="dash-section-header">
          <div>
            <h1 class="dash-title">Paramètres du Compte</h1>
            <p class="dash-desc">Personnalisez votre photo de profil, votre nom de membre, vos coordonnées de contact et sécurisez votre accès.</p>
          </div>
        </div>

        <form id="accountSettingsForm" method="POST" action="dashboard.php" class="settings-form-wrapper">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <input type="hidden" name="settingsAvatar" id="settingsAvatarHidden" value="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>">
          <!-- 1. PHOTO DE PROFIL -->
          <div class="settings-card">
            <h3 class="settings-card-title">Photo de Profil</h3>
            <p class="settings-card-desc">Cette photo s'affiche auprès des autres membres lors des Masterminds, dans les salons et dans vos messages.</p>
            
            <div class="settings-avatar-flex">
              <div class="settings-avatar-preview-box">
                <img src="<?= htmlspecialchars($currentUser['avatar'] ?? './img/avatar-maxime.jpg') ?>" alt="Aperçu Photo" id="settingsAvatarPreview" class="settings-avatar-large">
                <span class="badge-role-avatar" style="display:inline-flex; align-items:center; gap:0.35rem;">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2 4l3 12h14l3-12-5 7-5-7-5 7-5-7z"></path>
                  </svg>
                  <span>Admin</span>
                </span>
              </div>
              <div class="settings-avatar-controls">
                <input type="file" id="settingsAvatarInput" accept="image/png, image/jpeg, image/jpg, image/webp" style="display:none;">
                <div class="settings-avatar-btns">
                  <label for="settingsAvatarInput" class="btn btn-secondary btn-sm" id="btnUploadAvatar" style="cursor:pointer;display:inline-flex;align-items:center;gap:0.4rem;margin:0;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                      <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>Changer ma photo</span>
                  </label>
                  <button type="button" class="btn btn-outline-dash btn-sm" id="btnResetAvatar">
                    Réinitialiser
                  </button>
                </div>
                <span class="settings-hint">Formats acceptés : JPG, PNG, WEBP. Aperçu instantané.</span>

                <!-- Sélection rapide parmi les avatars de démo -->
                <div class="quick-avatars-row">
                  <span class="quick-avatars-label">Ou choisir un modèle :</span>
                  <div class="quick-avatars-list">
                    <img src="./img/avatar-maxime.jpg" alt="Preset 1" class="quick-avatar-item active" data-src="./img/avatar-maxime.jpg">
                    <img src="./img/avatar-florian.jpg" alt="Preset 2" class="quick-avatar-item" data-src="./img/avatar-florian.jpg">
                    <img src="./img/avatar-cyril.jpg" alt="Preset 3" class="quick-avatar-item" data-src="./img/avatar-cyril.jpg">
                    <img src="./img/avatar-aurore.jpg" alt="Preset 4" class="quick-avatar-item" data-src="./img/avatar-aurore.jpg">
                    <img src="./img/avatar-sarah.jpg" alt="Preset 5" class="quick-avatar-item" data-src="./img/avatar-sarah.jpg">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 2. COORDONNÉES & INFORMATIONS PERSONNELLES -->
          <div class="settings-card">
            <h3 class="settings-card-title">Informations Personnelles & Contact</h3>
            <p class="settings-card-desc">Gérez votre identité visible et votre numéro de téléphone direct pour les échanges.</p>
            
            <div class="settings-fields-grid">
              <div class="form-group">
                <label for="settingsFullName" class="form-label">Nom complet ou Prénom & Nom *</label>
                <input type="text" id="settingsFullName" name="settingsFullName" class="form-input" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required placeholder="Ex: Katahana Désiré">
              </div>

              <div class="form-group">
                <label for="settingsRole" class="form-label">Activité ou Titre professionnel</label>
                <input type="text" id="settingsRole" name="settingsRole" class="form-input" value="<?= htmlspecialchars($currentUser['job_title'] ?: 'Entrepreneur & Membre One Vision') ?>" placeholder="Ex: Entrepreneur, Développeur, Coach...">
              </div>

              <div class="form-group">
                <label for="settingsPhone" class="form-label">Numéro de téléphone direct *</label>
                <div class="input-with-icon">
                  <span class="input-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                  </span>
                  <input type="tel" id="settingsPhone" name="settingsPhone" class="form-input" value="<?= htmlspecialchars(!empty($currentUser['phone']) ? $currentUser['phone'] : '+33 6 84 92 10 33') ?>" placeholder="+33 6 12 34 56 78" required>
                </div>
                <span class="settings-hint">Utile pour être contacté directement par la communauté et l'équipe One Vision.</span>
              </div>

              <div class="form-group">
                <label for="settingsEmail" class="form-label">Adresse Email de connexion</label>
                <input type="email" id="settingsEmail" class="form-input" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly style="background:#f8fafc;color:#64748b;cursor:not-allowed;">
                <span class="settings-hint">L'email est lié à votre identifiant Stripe (9€/mois).</span>
              </div>
            </div>
          </div>

          <!-- 3. MOT DE PASSE & SÉCURITÉ -->
          <div class="settings-card">
            <h3 class="settings-card-title">Sécurité & Mot de Passe</h3>
            <p class="settings-card-desc">Modifiez votre mot de passe pour protéger votre accès à la communauté.</p>
            
            <div class="settings-fields-grid">
              <div class="form-group">
                <label for="settingsOldPassword" class="form-label">Mot de passe actuel</label>
                <input type="password" id="settingsOldPassword" name="settingsOldPassword" class="form-input" placeholder="••••••••••••">
              </div>

              <div class="form-group">
                <label for="settingsNewPassword" class="form-label">Nouveau mot de passe</label>
                <input type="password" id="settingsNewPassword" name="settingsNewPassword" class="form-input" placeholder="Min. 8 caractères sécurisés">
              </div>

              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="settingsConfirmPassword" class="form-label">Confirmer le nouveau mot de passe</label>
                <input type="password" id="settingsConfirmPassword" name="settingsConfirmPassword" class="form-input" placeholder="Confirmez à l'identique">
              </div>
            </div>
          </div>

          <!-- 4. BOUTONS D'ACTION -->
          <div class="settings-form-actions">
            <button type="submit" class="btn btn-primary" id="btnSaveAccountSettings" style="display:inline-flex; align-items:center; gap:0.5rem;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
              </svg>
              <span>Enregistrer les modifications</span>
            </button>
            <button type="button" class="btn btn-secondary" id="btnCancelAccountSettings">
              Annuler
            </button>
          </div>
        </form>

                <!-- 4. SECTION ABONNEMENT DANS LES PARAMÈTRES DU SITE -->
        <div class="settings-card" id="settingsAbonnementSection" style="margin-top: 1.75rem; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 1.5rem;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                <span style="font-size: 1.25rem;">💳</span>
                <h3 class="settings-card-title" style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Abonnement & Cotisations</h3>
              </div>
              <p class="settings-card-desc" style="margin: 0; color: #64748b; font-size: 0.88rem;">
                Retrouvez les formules One Vision, votre abonnement en cours, l'évolution vers Animateur et vos options de résiliation.
              </p>
            </div>
            <div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="switchToTab('tab-compte')" style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; border-radius: 8px;">
                <span>Gérer mon abonnement</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>
          </div>

          <!-- Récapitulatif statut en cours -->
          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
              <span style="font-size: 0.78rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; display: block; margin-bottom: 0.2rem;">Formule actuelle</span>
              <strong style="color: #0f172a; font-size: 1.05rem;">
                <?= $activeSub ? htmlspecialchars($activeSub['plan_nom'] . ' (' . ucfirst($activeSub['periodicite']) . ' — ' . number_format((float)$activeSub['prix_paye'], 2, ',', ' ') . ' €)') : ($isAdminUser ? 'Compte d\'Administration Permanente' : 'Aucun abonnement actif') ?>
              </strong>
            </div>
            <div>
              <?php if ($isOwnerUser || $isAdminUser): ?>
                <span style="background: #fef3c7; color: #92400e; font-weight: 700; font-size: 0.8rem; padding: 0.3rem 0.75rem; border-radius: 20px;">
                  👑 Accès Illimité Admin
                </span>
              <?php elseif ($activeSub): ?>
                <span style="background: #dcfce7; color: #15803d; font-weight: 700; font-size: 0.8rem; padding: 0.3rem 0.75rem; border-radius: 20px;">
                  ● Actif (Échéance : <?= date('d/m/Y', strtotime($activeSub['date_fin'])) ?>)
                </span>
              <?php else: ?>
                <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.8rem; padding: 0.3rem 0.75rem; border-radius: 20px;">
                  ⚠️ Inactif
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Présentation des 2 formules disponibles -->
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
            <div style="border: 1px solid #bfdbfe; background: #eff6ff; border-radius: 10px; padding: 0.85rem 1rem;">
              <strong style="color: #1e40af; font-size: 0.92rem; display: block; margin-bottom: 0.2rem;">Formule Membre</strong>
              <div style="color: #1d4ed8; font-size: 1.15rem; font-weight: 800; margin-bottom: 0.25rem;">9 € <span style="font-size: 0.85rem; font-weight: 500;">/ mois</span> <span style="font-size: 0.78rem; font-weight: 600; color: #059669;">(ou 89 €/an)</span></div>
              <p style="color: #3b82f6; font-size: 0.82rem; margin: 0;">Salons d'échanges, lives hebdomadaires, replays vidéo et réseau de pairs.</p>
            </div>
            <div style="border: 1px solid #fed7aa; background: #fff7ed; border-radius: 10px; padding: 0.85rem 1rem;">
              <strong style="color: #9a3412; font-size: 0.92rem; display: block; margin-bottom: 0.2rem;">Formule Animateur</strong>
              <div style="color: #c2410c; font-size: 1.15rem; font-weight: 800; margin-bottom: 0.25rem;">24 € <span style="font-size: 0.85rem; font-weight: 500;">/ mois</span> <span style="font-size: 0.78rem; font-weight: 600; color: #059669;">(ou 239 €/an)</span></div>
              <p style="color: #ea580c; font-size: 0.82rem; margin: 0;">Tous les avantages Membre + Espace Animateur, organisation de lives et profil certifié.</p>
            </div>
          </div>

          <!-- Boutons d'actions rapides -->
          <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="switchToTab('tab-compte')">
              💳 Voir le détail & mes factures
            </button>
            <?php if ($activeSub && $activeSub['plan_code'] === 'membre'): ?>
              <button type="button" class="btn btn-primary btn-sm" onclick="switchToTab('tab-compte')" style="background: linear-gradient(135deg, #f97316, #ea580c); border: none;">
                🚀 Évoluer vers Animateur (24 €/mois)
              </button>
            <?php endif; ?>
          </div>
        </div>

<!-- 5. ZONE CRITIQUE : RÉSILIATION ET SUPPRESSION DU COMPTE -->
        <div class="settings-card danger-zone-card" style="border: 1.5px solid #fecaca; background: #fff5f5; margin-top: 1.75rem; border-radius: 16px; padding: 1.5rem;">
          <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;">
            <div style="max-width: 600px;">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <h3 class="settings-card-title" style="color: #991b1b; margin: 0; font-size: 1.15rem; font-weight: 800;">Résilier et supprimer mon compte</h3>
              </div>
              <p class="settings-card-desc" style="color: #7f1d1d; margin: 0 0 0.5rem 0; line-height: 1.5; font-size: 0.88rem;">
                Si vous ne souhaitez plus utiliser One Vision Community, vous pouvez résilier votre adhésion et supprimer définitivement votre compte ainsi que l'ensemble de vos données personnelles de la plateforme.
              </p>
              <span style="font-size: 0.8rem; color: #b91c1c; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="flex-shrink:0;">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Cette action est irréversible. Votre accès aux salons d'échanges, masterminds, lives hebdomadaires et replays sera immédiatement interrompu.</span>
              </span>
            </div>
            <div style="align-self: center;">
              <button type="button" class="btn btn-danger btn-open-delete-account" id="btnOpenDeleteAccountModal" onclick="openDeleteAccountModal()" style="background: #dc2626; color: #ffffff; border: none; padding: 0.75rem 1.35rem; font-weight: 700; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: background 0.2s; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2); font-size: 0.9rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                  <line x1="10" y1="11" x2="10" y2="17"></line>
                  <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
                <span>Supprimer mon compte</span>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- ======================================================================
           PAGE DÉDIÉE : PROFIL DÉTAILLÉ DU MEMBRE
           ====================================================================== -->
      <section id="tab-profil-membre" class="dash-tab-content dash-account-page" style="display:none;">
        <div class="account-page-topbar">
          <button type="button" class="btn-back-to-dashboard" id="btnBackFromMemberProfile" title="Retour à l'Annuaire des Membres">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            <span>Retour à l'annuaire</span>
          </button>
          <div class="account-badge-pill">
            <span>👤 Profil Membre</span>
          </div>
        </div>

        <div class="member-page-card">
          <!-- Bannière de couverture haute qualité -->
          <div class="member-page-banner" id="memberPageBanner">
            <div class="banner-overlay-decor"></div>
          </div>

          <!-- Header avec Avatar, Nom, Rôle, Badges & Actions -->
          <div class="member-page-header">
            <div class="member-page-avatar-wrap">
              <img src="./img/avatar-aurore.jpg" alt="Photo membre" class="member-page-avatar" id="memberPageAvatar">
              <span class="member-status-online member-status-online-lg" title="En ligne"></span>
            </div>
            <div class="member-page-header-info">
              <div class="member-page-title-row">
                <h1 class="member-page-name" id="memberPageName">Aurore M.</h1>
                <span class="member-page-badge-role" id="memberPageBadgeRole">🎨 Experte Branding & DA</span>
                <span class="member-page-badge-verified" id="memberPageBadgeVerified">✓ Profil 100% Complété</span>
              </div>
              <p class="member-page-role-sub" id="memberPageRoleSub">Fondatrice Studio Créatif & Directrice Artistique</p>
              <div class="member-page-meta-row">
                <span id="memberPageLocation">📍 Paris, France</span>
                <span class="meta-dot">•</span>
                <span id="memberPageTenure">🗓️ Membre actif depuis 8 mois (One Vision)</span>
                <span class="meta-dot">•</span>
                <span class="meta-presence-badge">🟢 Actif récemment</span>
              </div>
            </div>
            <div class="member-page-header-actions">
              <button type="button" class="btn btn-primary" id="btnMemberPageSendDm">
                ✉️ Envoyer un message
              </button>
              <button type="button" class="btn btn-secondary" id="btnMemberPageCopyLink">
                🔗 Copier le profil
              </button>
            </div>
          </div>

          <!-- Grille des Chiffres Clés / Statistiques -->
          <div class="member-page-stats-grid">
            <div class="member-page-stat-card">
              <div class="stat-icon-wrap">🏆</div>
              <div class="stat-content">
                <strong class="stat-number" id="memberPageStatProjects">85+</strong>
                <span class="stat-label">Projets Réalisés</span>
              </div>
            </div>
            <div class="member-page-stat-card">
              <div class="stat-icon-wrap">⭐</div>
              <div class="stat-content">
                <strong class="stat-number" id="memberPageStatRating">4.9/5</strong>
                <span class="stat-label">Avis des Pairs (34 notes)</span>
              </div>
            </div>
            <div class="member-page-stat-card">
              <div class="stat-icon-wrap">🎙️</div>
              <div class="stat-content">
                <strong class="stat-number" id="memberPageStatLives">14</strong>
                <span class="stat-label">Lives & Ateliers animés</span>
              </div>
            </div>
            <div class="member-page-stat-card">
              <div class="stat-icon-wrap">🤝</div>
              <div class="stat-content">
                <strong class="stat-number" id="memberPageStatPartners">28</strong>
                <span class="stat-label">Collaborations One Vision</span>
              </div>
            </div>
          </div>

          <!-- Contenu du Profil : Bio, Compétences, Réseaux Sociaux -->
          <div class="member-page-body-grid">
            <div class="member-page-main-col">
              <!-- Section Activité / Ce que la personne fait dans la vie -->
              <div class="member-page-section-card">
                <h3 class="member-page-section-title">
                  <span>💼</span> Ce que je fais dans la vie & Activité
                </h3>
                <p class="member-page-about-text" id="memberPageAbout">
                  J'accompagne les consultants, coachs et créateurs indépendants à bâtir une identité visuelle et un univers de marque haut de gamme. Mon métier est de transformer votre expertise en une présence irrésistible qui justifie naturellement des tarifs 2 à 3 fois supérieurs.
                </p>
              </div>

              <!-- Section Compétences & Outils -->
              <div class="member-page-section-card">
                <h3 class="member-page-section-title">
                  <span>🛠️</span> Compétences clés & Domaines d'intervention
                </h3>
                <div class="member-page-skills-list" id="memberPageSkills">
                  <!-- Badges compétences injectés dynamiquement -->
                </div>
              </div>
            </div>

            <div class="member-page-side-col">
              <!-- Réseaux Sociaux & Liens Cliquables Directs -->
              <div class="member-page-section-card">
                <h3 class="member-page-section-title">
                  <span>🌐</span> Réseaux Sociaux & Liens Professionnels
                </h3>
                <p class="member-socials-hint">Cliquez sur un réseau pour consulter directement son profil :</p>
                <div class="member-page-socials-list" id="memberPageSocialsList">
                  <!-- Liens réseaux sociaux dynamiques -->
                </div>
              </div>

              <!-- Disponibilité pour entraide & partenariats -->
              <div class="member-page-section-card member-availability-card">
                <h3 class="member-page-section-title">
                  <span>🤝</span> Entraide & Disponibilité
                </h3>
                <p class="member-availability-text">
                  Ouvert(e) aux échanges de compétences, retours constructifs et partenariats avec les membres actifs de One Vision Community.
                </p>
                <button type="button" class="btn btn-outline-dash btn-sm btn-block" id="btnMemberPageQuickConnect">
                  💬 Proposer une collaboration
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
      <!-- ======================================================================
           ONGLET ADMIN : GESTION DES TICKETS ET COMMANDES
           ====================================================================== -->
      <section id="tab-admin" class="dash-tab-content">
        <div class="dash-section-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
          <div>
            <h1 class="dash-title" style="display:flex; align-items:center; gap:0.5rem;">
              <span>👑</span> Centre d'Administration One Vision
            </h1>
            <p class="dash-desc">Supervisez les demandes de support, visualisez les commandes et pilotez la communauté.</p>
          </div>
          <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <a href="creer-live.php" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:0.5rem; text-decoration:none;">
              <span>📹</span> Programmer un Live
            </a>
          </div>
        </div>

        <!-- KPIs Admin -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:1rem; margin-bottom:2rem;">
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
            <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">Membres Inscrits</div>
            <div style="font-size:1.85rem; font-weight:800; color:#0f172a; margin-top:0.35rem;"><?= count($allMembers) ?></div>
            <div style="font-size:0.75rem; color:#10b981; margin-top:0.25rem;">● Enregistrés en base SQLite</div>
          </div>
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
            <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">Commandes Validées</div>
            <div style="font-size:1.85rem; font-weight:800; color:#2563eb; margin-top:0.35rem;"><?= count($adminOrders) ?></div>
            <div style="font-size:0.75rem; color:#64748b; margin-top:0.25rem;">Abonnements 9€/mois</div>
          </div>
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
            <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">Tickets Support</div>
            <div style="font-size:1.85rem; font-weight:800; color:#f59e0b; margin-top:0.35rem;"><?= count($adminTickets) ?></div>
            <div style="font-size:0.75rem; color:#64748b; margin-top:0.25rem;">Formulaire de contact</div>
          </div>
          <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.5rem; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
            <div style="font-size:0.8rem; color:#64748b; font-weight:600; text-transform:uppercase;">Sessions Programmées</div>
            <div style="font-size:1.85rem; font-weight:800; color:#8b5cf6; margin-top:0.35rem;"><?= count($allLives) ?></div>
            <div style="font-size:0.75rem; color:#64748b; margin-top:0.25rem;">Lives & Masterminds</div>
          </div>
        </div>

        <!-- Section 1 : Tickets de Support reçus -->
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.75rem; margin-bottom:2rem; box-shadow:0 6px 20px rgba(0,0,0,0.03);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h2 style="font-size:1.15rem; font-weight:700; color:#0f172a; margin:0; display:flex; align-items:center; gap:0.5rem;">
              <span>📬</span> Demandes de Support Récentes (<?= count($adminTickets) ?>)
            </h2>
            <span style="font-size:0.78rem; background:#f1f5f9; padding:0.25rem 0.65rem; border-radius:20px; color:#475569;">Table : support_tickets</span>
          </div>

          <?php if (empty($adminTickets)): ?>
            <p style="color:#64748b; font-size:0.9rem; text-align:center; padding:2rem;">Aucun ticket de support pour l'instant.</p>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.88rem; text-align:left;">
                <thead>
                  <tr style="border-bottom:2px solid #f1f5f9; color:#64748b; font-size:0.75rem; text-transform:uppercase;">
                    <th style="padding:0.75rem 0.5rem;">Date</th>
                    <th style="padding:0.75rem 0.5rem;">Nom & Email</th>
                    <th style="padding:0.75rem 0.5rem;">Sujet</th>
                    <th style="padding:0.75rem 0.5rem;">Message</th>
                    <th style="padding:0.75rem 0.5rem;">Statut</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($adminTickets as $t): ?>
                    <tr style="border-bottom:1px solid #f8fafc;">
                      <td style="padding:0.85rem 0.5rem; color:#64748b; white-space:nowrap;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['created_at']))) ?></td>
                      <td style="padding:0.85rem 0.5rem;">
                        <strong><?= htmlspecialchars($t['name']) ?></strong><br>
                        <a href="mailto:<?= htmlspecialchars($t['email']) ?>" style="color:#2563eb; font-size:0.8rem;"><?= htmlspecialchars($t['email']) ?></a>
                      </td>
                      <td style="padding:0.85rem 0.5rem; font-weight:600; color:#0f172a;"><?= htmlspecialchars($t['subject'] ?: 'Sans objet') ?></td>
                      <td style="padding:0.85rem 0.5rem; color:#475569; max-width:320px;"><?= nl2br(htmlspecialchars($t['message'])) ?></td>
                      <td style="padding:0.85rem 0.5rem;">
                        <span style="display:inline-block; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; background:#fef3c7; color:#b45309;">
                          <?= htmlspecialchars($t['status']) ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Section 2 : Commandes & Factures globales -->
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:1.75rem; box-shadow:0 6px 20px rgba(0,0,0,0.03);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h2 style="font-size:1.15rem; font-weight:700; color:#0f172a; margin:0; display:flex; align-items:center; gap:0.5rem;">
              <span>💳</span> Dernières Adhésions & Commandes (<?= count($adminOrders) ?>)
            </h2>
            <span style="font-size:0.78rem; background:#f1f5f9; padding:0.25rem 0.65rem; border-radius:20px; color:#475569;">Table : orders</span>
          </div>

          <?php if (empty($adminOrders)): ?>
            <p style="color:#64748b; font-size:0.9rem; text-align:center; padding:2rem;">Aucune commande enregistrée pour l'instant.</p>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.88rem; text-align:left;">
                <thead>
                  <tr style="border-bottom:2px solid #f1f5f9; color:#64748b; font-size:0.75rem; text-transform:uppercase;">
                    <th style="padding:0.75rem 0.5rem;">N° Commande</th>
                    <th style="padding:0.75rem 0.5rem;">Date</th>
                    <th style="padding:0.75rem 0.5rem;">Client</th>
                    <th style="padding:0.75rem 0.5rem;">Montant</th>
                    <th style="padding:0.75rem 0.5rem;">Statut</th>
                    <th style="padding:0.75rem 0.5rem;">Facture</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($adminOrders as $ord): ?>
                    <tr style="border-bottom:1px solid #f8fafc;">
                      <td style="padding:0.85rem 0.5rem; font-family:monospace; font-weight:600; color:#0f172a;"><?= htmlspecialchars($ord['order_number']) ?></td>
                      <td style="padding:0.85rem 0.5rem; color:#64748b; white-space:nowrap;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ord['created_at']))) ?></td>
                      <td style="padding:0.85rem 0.5rem;">
                        <strong><?= htmlspecialchars($ord['user_name'] ?? 'Membre') ?></strong><br>
                        <span style="color:#64748b; font-size:0.8rem;"><?= htmlspecialchars($ord['user_email'] ?? '') ?></span>
                      </td>
                      <td style="padding:0.85rem 0.5rem; font-weight:700; color:#0f172a;"><?= number_format($ord['amount'], 2, ',', ' ') ?> €</td>
                      <td style="padding:0.85rem 0.5rem;">
                        <span style="display:inline-block; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; background:#dcfce7; color:#15803d;">
                          <?= htmlspecialchars($ord['status']) ?>
                        </span>
                      </td>
                      <td style="padding:0.85rem 0.5rem;">
                        <a href="facture.php?id=<?= $ord['id'] ?>" target="_blank" class="btn btn-outline-dash btn-sm" style="font-size:0.78rem; padding:0.3rem 0.65rem; text-decoration:none;">
                          📄 Voir facture
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

    </main>
  </div>

  <!-- ======================================================================
       MODALE 1 : CRÉATION D'UN NOUVEAU SALON (RÉSERVÉ ADMINISTRATEUR)
       ====================================================================== -->
  <div id="createSalonModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="createSalonModalTitle">
    <div class="dash-modal-backdrop" id="backdropCreateSalon"></div>
    <div class="dash-modal-dialog">
      <div class="dash-modal-header">
        <div style="display:flex;align-items:center;gap:0.5rem;">
          <span style="font-size:1.3rem;">💬</span>
          <div>
            <h3 class="dash-modal-title" id="createSalonModalTitle" style="margin:0;font-size:1.15rem;">Créer un nouveau salon thématique</h3>
            <span class="badge-role-admin" style="font-size:0.75rem;padding:2px 6px;border-radius:10px;margin-top:2px;display:inline-block;">👑 Réservé aux Administrateurs</span>
          </div>
        </div>
        <button type="button" class="dash-modal-close" id="closeCreateSalonModalBtn" aria-label="Fermer">&times;</button>
      </div>
      <form id="createSalonForm">
        <div class="form-group" style="margin-bottom:1rem;">
          <label for="newSalonEmoji" class="form-label">Icône ou Emoji du salon</label>
          <input type="text" id="newSalonEmoji" class="form-input" value="🚀" maxlength="4" style="width:70px;text-align:center;font-size:1.2rem;" required>
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
          <label for="newSalonNameInput" class="form-label">Nom du salon (slug sans espaces)</label>
          <input type="text" id="newSalonNameInput" class="form-input" placeholder="ex. closing-et-ventes ou ia-automatisation" required>
        </div>
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label for="newSalonDescInput" class="form-label">Description & objectif du salon</label>
          <textarea id="newSalonDescInput" class="form-input" rows="3" placeholder="Présentez la thématique pour guider les échanges des membres..." required></textarea>
        </div>
        <div class="dash-modal-actions">
          <button type="button" class="btn btn-secondary btn-sm" id="cancelCreateSalonBtn">Annuler</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <span>✨ Créer et ouvrir le salon</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======================================================================
       MODALE 2 : MESSAGERIE PRIVÉE AVEC HISTORIQUE D'ÉCHANGES
       ====================================================================== -->
  <div id="directMessageModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="dmRecipientName">
    <div class="dash-modal-backdrop" id="backdropDirectMessage"></div>
    <div class="dash-modal-dialog dash-modal-dialog-md">
      <div class="dash-modal-header">
        <div class="dm-recipient-box">
          <img src="./img/avatar-aurore.jpg" id="dmRecipientAvatar" alt="" class="dm-recipient-avatar">
          <div>
            <h3 class="dash-modal-title" id="dmRecipientName" style="margin:0;font-size:1.1rem;">Message privé avec Aurore M.</h3>
            <span class="dm-recipient-role" id="dmRecipientRole">Fondatrice Studio Créatif • En ligne</span>
          </div>
        </div>
        <button type="button" class="dash-modal-close" id="closeDirectMessageModalBtn" aria-label="Fermer">&times;</button>
      </div>

      <!-- Historique des messages de la conversation -->
      <div class="dm-conversation-box" id="dmConversationBox">
        <!-- Messages injectés dynamiquement -->
      </div>

      <!-- Formulaire d'envoi rapide -->
      <form id="directMessageForm" class="dm-reply-form">
        <div class="dm-reply-input-row">
          <input type="text" id="dmMessageInput" class="form-input" placeholder="Écrivez votre message privé..." required autocomplete="off">
          <button type="submit" class="btn btn-primary btn-sm dm-send-btn" id="btnSendDm">
            <span>Envoyer</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======================================================================
       MODALE REPLAY HD : LECTEUR VIDÉO INTERACTIF DES MASTERCLASSES
       ====================================================================== -->
  <div id="replayPlayerModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="replayModalTitle">
    <div class="dash-modal-backdrop" id="backdropReplayPlayer"></div>
    <div class="dash-modal-dialog dash-modal-dialog-lg">
      <div class="dash-modal-header">
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <span style="font-size:1.4rem;">🎬</span>
          <div>
            <span class="badge-plan-active" id="replayModalCategory" style="font-size:0.72rem;background:#2563eb;margin-bottom:4px;display:inline-block;">Masterclass HD</span>
            <h3 class="dash-modal-title" id="replayModalTitle" style="margin:0;font-size:1.15rem;">Titre du Replay</h3>
          </div>
        </div>
        <button type="button" class="dash-modal-close" id="closeReplayPlayerModalBtn" aria-label="Fermer le lecteur">&times;</button>
      </div>

      <div class="replay-player-modal-body">
        <!-- Conteneur Vidéo HD Interactif -->
        <div class="replay-video-screen-container" id="replayVideoScreen">
          <img src="./img/feature-replays.jpg" alt="Poster replay" class="replay-video-poster" id="replayVideoPoster">
          <div class="replay-video-overlay-gradient"></div>
          
          <button type="button" class="replay-big-play-btn" id="replayBigPlayBtn" title="Lire le replay">
            <span class="replay-play-triangle">▶</span>
          </button>

          <div class="replay-video-hud">
            <div class="replay-hud-top">
              <span class="replay-hd-badge">● 1080p HD 60fps</span>
              <span class="replay-duration-badge" id="replayHudDuration">1h 18min</span>
            </div>

            <div class="replay-hud-bottom">
              <div class="replay-timeline-bar" id="replayTimelineBar">
                <div class="replay-timeline-progress" id="replayTimelineProgress" style="width:28%;"></div>
              </div>
              <div class="replay-controls-row">
                <div class="replay-controls-left">
                  <button type="button" class="replay-ctrl-btn" id="replayPlayPauseToggle" title="Lecture / Pause">⏸️ Pause</button>
                  <span class="replay-timestamp" id="replayTimestamp">22:14 / 1:18:00</span>
                </div>
                <div class="replay-controls-right">
                  <button type="button" class="replay-ctrl-btn replay-speed-btn" id="replaySpeedBtn">⚡ 1.25x</button>
                  <button type="button" class="replay-ctrl-btn" id="replayFullscreenBtn" title="Plein écran">⛶ Plein écran</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Métadonnées & Fiche du Replay -->
        <div class="replay-details-grid">
          <div class="replay-info-col">
            <div class="replay-host-badge-row">
              <img src="./img/avatar-cyril.jpg" alt="Host" class="replay-host-avatar" id="replayModalHostAvatar">
              <div>
                <strong id="replayModalHostName">Cyril D.</strong>
                <span id="replayModalMeta">Session enregistrée • 468 vues</span>
              </div>
            </div>
            <p class="replay-full-description" id="replayModalDescription">
              Description complète de la masterclass...
            </p>
          </div>

          <div class="replay-downloads-col">
            <h5 style="margin:0 0 0.5rem 0;color:#0f172a;font-size:0.9rem;font-weight:700;">Ressources & Exercices liés :</h5>
            <button type="button" class="btn btn-secondary btn-sm btn-replay-download" style="width:100%;margin-bottom:0.5rem;justify-content:flex-start;" id="btnReplayActionDownload">
              📄 Télécharger la synthèse & trame (.pdf)
            </button>
            <button type="button" class="btn btn-primary btn-sm" style="width:100%;justify-content:flex-start;" id="btnReplayOpenWorksheet">
              📋 Ouvrir la fiche pratique de l'atelier
            </button>
          </div>
        </div>
      </div>

      <div class="dash-modal-actions">
        <button type="button" class="btn btn-secondary btn-sm" id="closeReplayPlayerModalFooterBtn">Fermer la vidéo</button>
      </div>
    </div>
  </div>

  <!-- ======================================================================
       MODALE 3 : FICHE PRATIQUE DE L'ATELIER MASTERMIND (CONSULTATION EN LIGNE)
       ====================================================================== -->
  <div id="atelierWorksheetModal" class="dash-modal" style="display:none;">
    <div class="dash-modal-backdrop" id="backdropAtelierWorksheet"></div>
    <div class="dash-modal-dialog dash-modal-dialog-lg">
      <div class="dash-modal-header">
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <span style="font-size:1.6rem;">📋</span>
          <div>
            <h3 class="dash-modal-title" style="margin:0;font-size:1.15rem;">Fiche Pratique • Atelier Mastermind #14</h3>
            <span style="font-size:0.8rem;color:#64748b;">Méthode de Closing & Négociation High-Ticket • Animé par Cyril D.</span>
          </div>
        </div>
        <button type="button" class="dash-modal-close" id="closeAtelierWorksheetModalBtn" aria-label="Fermer">&times;</button>
      </div>

      <div class="worksheet-modal-body" id="worksheetModalBody">
        <div class="worksheet-header-banner">
          <div class="worksheet-tag">One Vision Mastermind Series</div>
          <h4>Framework en 6 Étapes : Transformer une Découverte en Vente Ferme</h4>
          <p>Cette trame est le script exact utilisé par nos mentors pour closer des prestations de conseil, coaching et services entre 1 500€ et 8 000€ sans forcer.</p>
        </div>

        <div class="worksheet-sections-grid">
          <!-- Étape 1 -->
          <div class="worksheet-step-card">
            <div class="step-num-badge">1</div>
            <div class="step-content">
              <h5>Étape 1 : Diagnostic & Questionnement Stratégique</h5>
              <p><strong>Objectif :</strong> Faire parler le prospect à 80% du temps.</p>
              <ul class="step-bullets">
                <li><em>« Quelle est la priorité n°1 pour votre entreprise sur les 90 prochains jours ? »</em></li>
                <li><em>« Qu'avez-vous déjà testé pour résoudre ce problème, et qu'est-ce qui a bloqué ? »</em></li>
              </ul>
            </div>
          </div>

          <!-- Étape 2 -->
          <div class="worksheet-step-card">
            <div class="step-num-badge">2</div>
            <div class="step-content">
              <h5>Étape 2 : Ancrage de la Douleur & Coût de l'Inaction</h5>
              <p><strong>Objectif :</strong> Rendre le coût de ne rien faire plus élevé que votre tarif.</p>
              <ul class="step-bullets">
                <li><em>« Si rien ne change d'ici 6 mois, quel est l'impact financier direct sur votre CA ? »</em></li>
                <li><em>« Combien de temps et d'énergie perdez-vous chaque semaine sur ce blocage ? »</em></li>
              </ul>
            </div>
          </div>

          <!-- Étape 3 -->
          <div class="worksheet-step-card">
            <div class="step-num-badge">3</div>
            <div class="step-content">
              <h5>Étape 3 : Présentation Sur-Mesure de l'Accompagnement</h5>
              <p><strong>Objectif :</strong> Relier chaque livrable directement à un problème cité par le client.</p>
              <ul class="step-bullets">
                <li>Ne listez pas des fonctionnalités, présentez des <strong>piliers de transformation</strong>.</li>
                <li>Validez à chaque jalon : <em>« Est-ce que cette approche répond précisément à votre attente ? »</em></li>
              </ul>
            </div>
          </div>

          <!-- Étape 4 -->
          <div class="worksheet-step-card">
            <div class="step-num-badge">4</div>
            <div class="step-content">
              <h5>Étape 4 : Annonce du Tarif & Règle d'Or du Silence</h5>
              <p><strong>Objectif :</strong> Annoncer un prix net avec assurance sans se justifier.</p>
              <ul class="step-bullets">
                <li><em>« L'investissement pour ce dispositif complet est de 2 900€ HT. »</em></li>
                <li><strong>Règle d'or :</strong> Taisez-vous immédiatement après avoir prononcé le prix. Le premier qui parle cède du terrain.</li>
              </ul>
            </div>
          </div>

          <!-- Étape 5 -->
          <div class="worksheet-step-card">
            <div class="step-num-badge">5</div>
            <div class="step-content">
              <h5>Étape 5 : Désamorçage des 4 Objections Universelles</h5>
              <div class="objection-pill-item">
                <strong>« C'est trop cher »</strong> : <em>« Par rapport à quoi ? Ou est-ce que c'est une question de trésorerie disponible immédiatement ? »</em>
              </div>
              <div class="objection-pill-item">
                <strong>« Je dois réfléchir »</strong> : <em>« Bien sûr. En général, c'est soit parce que le plan ne vous semble pas réaliste, soit que vous doutez du retour sur investissement. Lequel est-ce ? »</em>
              </div>
              <div class="objection-pill-item">
                <strong>« Je dois en parler à mon associé/conjoint »</strong> : <em>« Que pensez-vous qu'il/elle va vous demander en premier ? Préparons la réponse ensemble. »</em>
              </div>
            </div>
          </div>

          <!-- Étape 6 -->
          <div class="worksheet-step-card">
            <div class="step-num-badge">6</div>
            <div class="step-content">
              <h5>Étape 6 : Accord d'Engagement & Démarrage</h5>
              <p><strong>Objectif :</strong> Verrouiller la date du premier point d'étape dès l'accord verbal.</p>
              <ul class="step-bullets">
                <li>Envoyer le lien de contractualisation ou l'acompte pendant l'appel ou sous 2 heures max.</li>
                <li>Bloquer la date de session de lancement dans l'agenda partagé.</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Checklist d'action -->
        <div class="worksheet-checklist-box">
          <h5 style="margin:0 0 0.5rem 0;color:#0f172a;font-weight:700;">✅ Checklist d'Action Immédiate pour cette semaine :</h5>
          <label class="checklist-item"><input type="checkbox" checked> Réécrire mes 3 questions de qualification d'après l'Étape 1</label>
          <label class="checklist-item"><input type="checkbox" checked> Fixer mon offre socle avec un engagement minimum de 90 jours</label>
          <label class="checklist-item"><input type="checkbox"> Planifier 5 appels de diagnostic avec mon réseau ou mes prospects</label>
          <label class="checklist-item"><input type="checkbox"> Partager mes retours et mes blocages dans le salon #ventes-closing de One Vision</label>
        </div>
      </div>

      <div class="dash-modal-actions" style="margin-top:1.25rem;">
        <button type="button" class="btn btn-secondary btn-sm" id="btnPrintWorksheetBtn">
          🖨️ Imprimer / Exporter
        </button>
        <button type="button" class="btn btn-secondary btn-sm" id="btnDownloadWorksheetFromModal">
          📥 Télécharger (.pdf)
        </button>
        <button type="button" class="btn btn-primary btn-sm" id="closeAtelierWorksheetModalFooterBtn">
          Fermer la fiche
        </button>
      </div>
    </div>
  </div>

  <!-- MODALE : LISTE COMPLÈTE DES PARTICIPANTS EN DIRECT -->
  <div class="dash-modal" id="liveParticipantsModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="liveParticipantsModalTitle">
    <div class="dash-modal-backdrop" id="backdropLiveParticipants"></div>
    <div class="dash-modal-dialog dash-modal-dialog-md">
      <div class="dash-modal-header">
        <div class="modal-header-with-badge">
          <h3 class="dash-modal-title" id="liveParticipantsModalTitle">Participants au Mastermind</h3>
          <span class="live-pill-count" id="modalLivePillCount">● 142 connectés en direct</span>
        </div>
        <button type="button" class="dash-modal-close" id="closeLiveParticipantsModalBtn" aria-label="Fermer la liste">✕</button>
      </div>

      <!-- Recherche rapide d'un membre -->
      <div class="participants-search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchParticipantInput" placeholder="Rechercher un membre par nom ou métier..." class="form-input">
      </div>

      <?php if ($isLiveHost): ?>
      <!-- Panneau d'Actions Animateur / Hôte de la Session -->
      <div class="host-moderation-banner" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:0.85rem 1.15rem; margin:1rem 1.5rem 0.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem;">
        <div style="display:flex; align-items:center; gap:0.55rem;">
          <span style="font-size:1.2rem;">🛡️</span>
          <div>
            <strong style="font-size:0.86rem; color:#0f172a; display:block;">Contrôles d'Administration de Session</strong>
            <span style="font-size:0.75rem; color:#64748b;">En tant qu'Animateur, modérez les micros et les flux caméras</span>
          </div>
        </div>
        <div style="display:flex; gap:0.45rem;">
          <button type="button" class="btn btn-sm btn-host-ctrl" id="btnHostMuteAll" style="font-size:0.78rem; padding:0.38rem 0.75rem; border:1px solid #fecaca; background:#fff; color:#dc2626; border-radius:8px; cursor:pointer; font-weight:700;">
            🔇 Couper tous les micros
          </button>
          <button type="button" class="btn btn-sm btn-host-ctrl" id="btnHostCutAllCams" style="font-size:0.78rem; padding:0.38rem 0.75rem; border:1px solid #fecaca; background:#fff; color:#dc2626; border-radius:8px; cursor:pointer; font-weight:700;">
            📷 Couper toutes les caméras
          </button>
        </div>
      </div>
      <?php endif; ?>

      <div class="participants-modal-body" id="participantsModalListBody">
        <!-- Section Animateur -->
        <div class="participants-section-label">Animateur de la session</div>
        <div class="participant-row-card host-card">
          <img src="./img/avatar-cyril.jpg" alt="Cyril D." class="row-avatar">
          <div class="row-info">
            <div class="row-name">Cyril D. <span class="badge-role-host">Host / Mentor</span></div>
            <div class="row-desc">Coach Business & Négociation • Intervenant principal</div>
          </div>
          <div class="row-status">
            <span class="badge-speaking-tag">🎙️ Au micro</span>
          </div>
        </div>

        <!-- Section Vous -->
        <div class="participants-section-label">Vous</div>
        <div class="participant-row-card self-card">
          <img src="./img/avatar-maxime.jpg" alt="Katahana Désiré" class="row-avatar" id="modalSelfAvatar">
          <div class="row-info">
            <div class="row-name" id="modalSelfName">Katahana Désiré <span class="badge-role-admin">👑 Admin</span></div>
            <div class="row-desc">Membre One Vision • Actif en session</div>
          </div>
          <div class="row-status">
            <span class="badge-listen-tag" id="modalSelfStatusBadge">🎧 Écoute</span>
          </div>
        </div>

        <!-- Section Membres Connectés -->
        <div class="participants-section-label" id="modalOtherCountLabel">Membres connectés (140)</div>
        <div class="participants-scrollable-list" id="modalOtherParticipantsList">
          <!-- Injecté dynamiquement par JS -->
        </div>
      </div>

      <div class="dash-modal-actions">
        <button type="button" class="btn btn-secondary btn-sm" id="closeLiveParticipantsFooterBtn">Fermer</button>
      </div>
    </div>
  </div>

  <!-- ======================================================================
       MODALE 5 : PROFIL DÉTAILLÉ & ACCOMPLISSEMENTS DU MEMBRE
       ====================================================================== -->
  <div id="memberProfileModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="profileModalName">
    <div class="dash-modal-backdrop" id="backdropMemberProfile"></div>
    <div class="dash-modal-dialog dash-modal-dialog-md member-profile-dialog">
      
      <!-- Bannière de couverture & Bouton de fermeture -->
      <div class="profile-modal-banner">
        <button type="button" class="dash-modal-close profile-modal-close" id="closeMemberProfileModalBtn" aria-label="Fermer le profil">&times;</button>
      </div>

      <!-- En-tête du profil avec photo, nom, titre & statut -->
      <div class="profile-modal-header-wrap">
        <div class="profile-avatar-stack">
          <img src="./img/avatar-aurore.jpg" id="profileModalAvatar" alt="Membre" class="profile-modal-avatar">
          <span class="profile-online-status" title="En ligne"></span>
        </div>
        <div class="profile-header-meta">
          <div class="profile-name-row">
            <h3 class="profile-modal-name" id="profileModalName">Aurore M.</h3>
            <span class="badge-role-pill" id="profileModalBadge">🎨 Experte Branding & DA</span>
          </div>
          <div class="profile-modal-role" id="profileModalRole">Fondatrice Studio Créatif & Directrice Artistique</div>
          <div class="profile-modal-location" id="profileModalLocation">📍 Paris, France • Membre actif depuis 8 mois</div>
        </div>
      </div>

      <div class="profile-modal-body">
        <!-- Chiffres & Accomplissements vérifiés -->
        <div class="profile-stats-row">
          <div class="profile-stat-box">
            <span class="profile-stat-num" id="profileStatMissions">85+</span>
            <span class="profile-stat-lbl">Projets Réalisés</span>
          </div>
          <div class="profile-stat-box">
            <span class="profile-stat-num" id="profileStatRating">4.9/5</span>
            <span class="profile-stat-lbl">Note Pairs (34 avis)</span>
          </div>
          <div class="profile-stat-box">
            <span class="profile-stat-num" id="profileStatMasterminds">14</span>
            <span class="profile-stat-lbl">Lives & Ateliers</span>
          </div>
        </div>

        <!-- Ce que la personne fait dans la vie / Activité professionnelle -->
        <div class="profile-section">
          <h4 class="profile-section-title">💼 Ce que je fais dans la vie & Activité</h4>
          <p class="profile-about-text" id="profileModalAbout">
            J'accompagne les consultants, coachs et créateurs indépendants à bâtir une identité visuelle et un univers de marque haut de gamme. Mon métier est de transformer votre expertise en une présence irrésistible qui justifie naturellement des tarifs 2 à 3 fois supérieurs.
          </p>
        </div>

        <!-- Compétences Clés & Domaines d'Intervention -->
        <div class="profile-section">
          <h4 class="profile-section-title">🛠️ Compétences & Domaines d'Intervention</h4>
          <div class="profile-skills-pills" id="profileModalSkills">
            <span class="profile-skill-tag">Branding Premium</span>
            <span class="profile-skill-tag">Direction Artistique</span>
            <span class="profile-skill-tag">Webflow & Figma</span>
            <span class="profile-skill-tag">Design de Pages de Vente</span>
          </div>
        </div>

        <!-- Liens Externes, Site Web & Communautés -->
        <div class="profile-section">
          <h4 class="profile-section-title">🌐 Liens Externes, Site Web & Communautés</h4>
          <div class="profile-links-grid" id="profileModalLinks">
            <a href="https://aurore-studio.design" target="_blank" rel="noopener noreferrer" class="profile-link-btn" id="profileLinkWebsite">
              <span class="link-icon">🌐</span>
              <div class="link-info">
                <strong class="link-title">Site Web & Portfolio Pro</strong>
                <span class="link-url" id="profileLinkWebsiteUrl">aurore-studio.design</span>
              </div>
              <span class="link-arrow">↗</span>
            </a>

            <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" class="profile-link-btn" id="profileLinkLinkedin">
              <span class="link-icon">💼</span>
              <div class="link-info">
                <strong class="link-title">Réseau LinkedIn Pro</strong>
                <span class="link-url" id="profileLinkLinkedinUrl">linkedin.com/in/aurore-design</span>
              </div>
              <span class="link-arrow">↗</span>
            </a>

            <a href="https://t.me" target="_blank" rel="noopener noreferrer" class="profile-link-btn" id="profileLinkCommunity">
              <span class="link-icon">💬</span>
              <div class="link-info">
                <strong class="link-title">Canal / Communauté Privée</strong>
                <span class="link-url" id="profileLinkCommunityUrl">t.me/creatif_brand_hub</span>
              </div>
              <span class="link-arrow">↗</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Actions de contact et de partage -->
      <div class="profile-modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" id="btnCopyProfileLink">
          🔗 Copier le profil
        </button>
        <button type="button" class="btn btn-primary btn-sm" id="btnProfileSendDm">
          ✉️ Envoyer un message privé
        </button>
      </div>
    </div>
  </div>

  <!-- ======================================================================
       MODALE 6 : CHOIX DES VERSIONS DE TÉLÉCHARGEMENT (RESSOURCES)
       ====================================================================== -->
  <div id="resourceDownloadModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="resModalTitle">
    <div class="dash-modal-backdrop" id="backdropResourceDownload"></div>
    <div class="dash-modal-dialog dash-modal-dialog-md resource-download-dialog">
      <div class="dash-modal-header" style="align-items:flex-start;">
        <div style="display:flex;align-items:center;gap:0.85rem;">
          <div class="resource-modal-icon-badge" id="resModalIcon">📄</div>
          <div>
            <h3 class="dash-modal-title" id="resModalTitle" style="margin:0;font-size:1.15rem;font-weight:700;">Télécharger la ressource</h3>
            <span class="resource-modal-subtitle" id="resModalSubtitle" style="font-size:0.82rem;color:#64748b;">Choisissez le format adapté à votre utilisation</span>
          </div>
        </div>
        <button type="button" class="dash-modal-close" id="closeResourceDownloadModalBtn" aria-label="Fermer la boîte de dialogue">&times;</button>
      </div>

      <div class="resource-download-modal-body" style="padding:1.5rem;">
        <p class="resource-modal-lead" id="resModalDescription" style="margin:0 0 1.25rem 0;font-size:0.88rem;color:#475569;line-height:1.55;">
          Sélectionnez le format que vous souhaitez obtenir. Vous pouvez télécharger le document en version PDF prête à l'emploi ou en version modifiable pour l'adapter à vos besoins.
        </p>

        <div class="resource-versions-container" id="resModalVersionsContainer">
          <!-- Versions injectées dynamiquement par JS -->
        </div>
      </div>

      <div class="dash-modal-actions" style="margin-top:0;padding:1rem 1.5rem;background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 16px 16px;display:flex;justify-content:flex-end;">
        <button type="button" class="btn btn-secondary btn-sm" id="closeResourceDownloadModalFooterBtn">Fermer</button>
      </div>
    </div>
  <!-- ======================================================================
       MODALE 7 : MODIFICATION D'UN LIVE OU MASTERMIND
       ====================================================================== -->
  <div id="editLiveModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="editLiveModalTitle">
    <div class="dash-modal-backdrop" id="backdropEditLive"></div>
    <div class="dash-modal-dialog dash-modal-dialog-md" style="max-width: 620px; background:#fff; border-radius:16px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid #e2e8f0; overflow:hidden;">
      <div class="dash-modal-header" style="padding:1.25rem 1.5rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <span style="font-size:1.4rem;line-height:1;">✏️</span>
          <div>
            <h3 class="dash-modal-title" id="editLiveModalTitle" style="margin:0;font-size:1.15rem;font-weight:700;color:#0f172a;">Modifier la session Live / Mastermind</h3>
            <span style="font-size:0.82rem;color:#64748b;">Mettez à jour les informations diffusées dans le calendrier</span>
          </div>
        </div>
        <button type="button" class="dash-modal-close" id="closeEditLiveModalBtn" aria-label="Fermer la boîte de dialogue" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b;">&times;</button>
      </div>

      <form id="editLiveForm" method="POST" action="dashboard.php?tab=tab-calendrier" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="edit_live">
        <input type="hidden" name="live_id" id="editLiveId" value="">

        <div style="padding:1.5rem;display:flex;flex-direction:column;gap:1.15rem;">
          <div>
            <label for="editLiveTitle" style="display:block;font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.35rem;">Titre de la session *</label>
            <input type="text" id="editLiveTitle" name="title" required style="width:100%;padding:0.65rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;background:#fff;color:#0f172a;box-sizing:border-box;">
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <label for="editLiveFormat" style="display:block;font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.35rem;">Format *</label>
              <select id="editLiveFormat" name="format" style="width:100%;padding:0.65rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.92rem;background:#fff;color:#0f172a;box-sizing:border-box;">
                <option value="Mastermind Stratégie">🧠 Mastermind Stratégie</option>
                <option value="Live Session & Q&A">🎙️ Live Session & Q&A</option>
                <option value="Co-Working & Feedback">🛠️ Co-Working & Feedback</option>
                <option value="Conférence No-Code">💻 Conférence No-Code / IA</option>
                <option value="Atelier Pratique">⚡ Atelier Pratique</option>
              </select>
            </div>
            <div>
              <label for="editLiveDuration" style="display:block;font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.35rem;">Durée estimée</label>
              <input type="text" id="editLiveDuration" name="duration" placeholder="ex: 1h30 (90 min)" style="width:100%;padding:0.65rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.92rem;background:#fff;color:#0f172a;box-sizing:border-box;">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
              <label for="editLiveDate" style="display:block;font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.35rem;">Date *</label>
              <input type="date" id="editLiveDate" name="date" required style="width:100%;padding:0.65rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.92rem;background:#fff;color:#0f172a;box-sizing:border-box;">
            </div>
            <div>
              <label for="editLiveTime" style="display:block;font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.35rem;">Heure (UTC+1 / Paris) *</label>
              <input type="time" id="editLiveTime" name="time" required style="width:100%;padding:0.65rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.92rem;background:#fff;color:#0f172a;box-sizing:border-box;">
            </div>
          </div>

          <div>
            <label for="editLiveDescription" style="display:block;font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.35rem;">Description & Objectifs</label>
            <textarea id="editLiveDescription" name="description" rows="3" style="width:100%;padding:0.65rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;background:#fff;color:#0f172a;resize:vertical;box-sizing:border-box;" placeholder="Précisez les objectifs et thématiques abordées..."></textarea>
          </div>

          <div>
            <label for="editLiveResources" style="display:block;font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.35rem;">Ressources partagées ou Lien</label>
            <input type="text" id="editLiveResources" name="resources" placeholder="ex: Notion doc, template Figma, Google Drive..." style="width:100%;padding:0.65rem 0.85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.92rem;background:#fff;color:#0f172a;box-sizing:border-box;">
          </div>
        </div>

        <div class="dash-modal-actions" style="margin-top:0;padding:1rem 1.5rem;background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 16px 16px;display:flex;justify-content:flex-end;gap:0.75rem;">
          <button type="button" class="btn btn-secondary btn-sm" id="btnCancelEditLive">Annuler</button>
          <button type="submit" class="btn btn-primary btn-sm" id="btnSaveEditLive">💾 Enregistrer les modifications</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======================================================================
       MODALE 8 : CONFIRMATION DE SUPPRESSION D'UN LIVE OU MASTERMIND
       ====================================================================== -->
  <div id="deleteLiveModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="deleteLiveModalTitle">
    <div class="dash-modal-backdrop" id="backdropDeleteLive"></div>
    <div class="dash-modal-dialog" style="max-width: 480px;text-align:center;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);border:1px solid #fee2e2;">
      <div style="width:64px;height:64px;border-radius:50%;background:#fef2f2;color:#ef4444;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:1rem;border:2px solid #fee2e2;">
        🗑️
      </div>
      <h3 class="dash-modal-title" id="deleteLiveModalTitle" style="font-size:1.25rem;font-weight:800;color:#0f172a;margin-bottom:0.5rem;">
        Supprimer cette session ?
      </h3>
      <p style="font-size:0.9rem;color:#64748b;line-height:1.55;margin-bottom:1.5rem;">
        Êtes-vous certain de vouloir supprimer cette session <strong id="deleteLiveTitlePreview" style="color:#0f172a;">« »</strong> ?<br>
        Cette action est irréversible et la session sera retirée du calendrier pour tous les membres.
      </p>

      <form id="deleteLiveForm" method="POST" action="dashboard.php?tab=tab-calendrier" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_live">
        <input type="hidden" name="live_id" id="deleteLiveId" value="">

        <div style="display:flex;justify-content:center;gap:0.75rem;">
          <button type="button" class="btn btn-secondary" id="btnCancelDeleteLive" style="padding:0.7rem 1.25rem;">
            Non, conserver
          </button>
          <button type="submit" class="btn" id="btnConfirmDeleteLive" style="background:#ef4444;color:#fff;border:none;padding:0.7rem 1.25rem;border-radius:10px;font-weight:600;cursor:pointer;">
            Oui, supprimer définitivement
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======================================================================
       MODALE 9 : CONFIRMATION DE RÉSILIATION ET SUPPRESSION DU COMPTE MEMBRE
       ====================================================================== -->
  <!-- ======================================================================
       MODALE 9 : CONFIRMATION DE RÉSILIATION ET SUPPRESSION DU COMPTE MEMBRE
       ====================================================================== -->
  <div id="deleteAccountModal" class="dash-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="deleteAccountModalTitle">
    <div class="dash-modal-backdrop" id="backdropDeleteAccount" onclick="closeDeleteAccountModal()"></div>
    <div class="dash-modal-dialog" style="max-width: 490px; text-align: center; padding: 2.25rem 2rem; background: #ffffff; border-radius: 18px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #fee2e2; position: relative;">
      
      <!-- ÉTAPE 1 : CONFIRMATION -->
      <div id="deleteAccountConfirmView">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: #fef2f2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.2rem; border: 2px solid #fee2e2;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </div>

        <h3 class="dash-modal-title" id="deleteAccountModalTitle" style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.6rem; line-height: 1.3;">
          Est-ce que vous voulez réellement supprimer votre compte ?
        </h3>

        <p style="font-size: 0.92rem; color: #64748b; line-height: 1.55; margin-bottom: 1.25rem;">
          Vous êtes sur le point de résilier votre adhésion à <strong>One Vision Community</strong>.<br>
          Votre profil, vos accès aux salons d'échanges et toutes vos données personnelles seront définitivement supprimés de la plateforme.
        </p>

        <div style="background: #fff1f2; border: 1px solid #ffe4e6; border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.5rem; text-align: left; font-size: 0.84rem; color: #9f1239; display: flex; align-items: flex-start; gap: 0.6rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" style="flex-shrink: 0; margin-top: 1px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div>
            <strong style="color: #991b1b;">Attention :</strong> Cette action est immédiate et irréversible. Aucun retour en arrière n'est possible.
          </div>
        </div>

        <form id="deleteAccountConfirmForm" method="POST" action="dashboard.php" style="margin: 0;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete_account">

          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button type="button" class="btn btn-secondary" id="btnCancelDeleteAccount" onclick="closeDeleteAccountModal()" style="padding: 0.8rem 1.25rem; font-size: 0.92rem; font-weight: 700; width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; cursor: pointer;">
              Non, je veux rester dans la communauté
            </button>
            <button type="submit" class="btn" id="btnConfirmDeleteAccount" style="background: #dc2626; color: #ffffff; border: none; padding: 0.8rem 1.25rem; border-radius: 10px; font-weight: 700; font-size: 0.92rem; cursor: pointer; width: 100%; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3); display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                <line x1="10" y1="11" x2="10" y2="17"></line>
                <line x1="14" y1="11" x2="14" y2="17"></line>
              </svg>
              <span>Oui, je supprime mon compte</span>
            </button>
          </div>
        </form>
      </div>

      <!-- ÉTAPE 2 : SUCCÈS APRÈS SUPPRESSION -->
      <div id="deleteAccountSuccessView" style="display: none;">
        <div style="width: 68px; height: 68px; border-radius: 50%; background: #ecfdf5; color: #059669; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; border: 2px solid #a7f3d0;">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
        </div>

        <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.6rem; line-height: 1.3;">
          Votre compte a été bien résilié et supprimé
        </h3>

        <p style="font-size: 0.92rem; color: #64748b; line-height: 1.55; margin-bottom: 1.75rem;">
          Nous vous remercions pour le parcours partagé au sein de One Vision Community.<br>
          Votre compte a été résilié et l'ensemble de vos données a été définitivement supprimé.
        </p>

        <a href="index.php" class="btn btn-primary" id="btnReturnHomeAfterDelete" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%; padding: 0.85rem 1.25rem; font-weight: 700; text-decoration: none; border-radius: 10px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          <span>Retourner à l'accueil</span>
        </a>
      </div>

    </div>
  </div>

  <!-- BARRE FLOTTANTE DE TEST / DÉMONSTRATION DES 3 ÉTATS D'ABONNEMENT -->
  <div id="demoStateSwitcherBar" style="position:fixed; bottom:18px; right:18px; z-index:99999; background:rgba(15, 23, 42, 0.95); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.15); border-radius:50px; padding:6px 12px; display:flex; align-items:center; gap:8px; box-shadow:0 12px 32px rgba(0,0,0,0.35);">
    <span style="color:#94a3b8; font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; padding-left:4px; padding-right:2px;">
      Mode Démo :
    </span>
    <button type="button" class="demo-switch-btn" id="btnDemoPlus" onclick="setDashboardPlanMode('animateur')" style="background:#f97316; color:#ffffff; border:none; padding:5px 12px; border-radius:20px; font-size:0.78rem; font-weight:700; cursor:pointer; transition:all 0.2s ease;">
      🌟 Plus (24€)
    </button>
    <button type="button" class="demo-switch-btn" id="btnDemoMembre" onclick="setDashboardPlanMode('membre')" style="background:rgba(255,255,255,0.12); color:#ffffff; border:none; padding:5px 12px; border-radius:20px; font-size:0.78rem; font-weight:700; cursor:pointer; transition:all 0.2s ease;">
      💼 Membre (9€)
    </button>
    <button type="button" class="demo-switch-btn" id="btnDemoExpired" onclick="setDashboardPlanMode('expired')" style="background:rgba(255,255,255,0.12); color:#ffffff; border:none; padding:5px 12px; border-radius:20px; font-size:0.78rem; font-weight:700; cursor:pointer; transition:all 0.2s ease;">
      ⏳ Expiré
    </button>
    <a href="questionnaire.php" title="Refaire ou voir le questionnaire" style="background:rgba(255,255,255,0.12); color:#ffffff; text-decoration:none; padding:5px 11px; border-radius:20px; font-size:0.78rem; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
      📋 Questionnaire
    </a>
  </div>

  <script src="./js/main.js?v=4"></script>
  <script>
    
    function setUpgradePeriod(mode) {
      const btnM = document.getElementById('btnUpgradeMonthly');
      const btnY = document.getElementById('btnUpgradeYearly');
      const inp = document.getElementById('upgradePeriodiciteInput');
      if (!btnM || !btnY || !inp) return;
      if (mode === 'annuel') {
        btnY.style.background = '#0f172a';
        btnY.style.color = '#fff';
        btnM.style.background = 'transparent';
        btnM.style.color = '#64748b';
        inp.value = 'annuel';
      } else {
        btnM.style.background = '#0f172a';
        btnM.style.color = '#fff';
        btnY.style.background = 'transparent';
        btnY.style.color = '#64748b';
        inp.value = 'mensuel';
      }
    }

    function setDashboardPlanMode(mode) {
      const badge = document.querySelector('.dash-user-badge');
      const roleBadge = document.getElementById('dropdownRoleBadge');
      const bPlus = document.getElementById('btnDemoPlus');
      const bMembre = document.getElementById('btnDemoMembre');
      const bExpired = document.getElementById('btnDemoExpired');
      [bPlus, bMembre, bExpired].forEach(b => {
        if (b) {
          b.style.background = 'rgba(255,255,255,0.12)';
          b.style.color = '#ffffff';
        }
      });

      if (mode === 'animateur') {
        if (badge) {
          badge.textContent = '🌟 Abonnement Plus • 24€/mois';
          badge.style.background = '#ffedd5';
          badge.style.color = '#c2410c';
        }
        if (roleBadge) {
          roleBadge.textContent = '🌟 Animateur One Vision';
          roleBadge.style.background = '#ffedd5';
          roleBadge.style.color = '#c2410c';
        }
        if (bPlus) bPlus.style.background = '#f97316';
      } else if (mode === 'membre') {
        if (badge) {
          badge.textContent = '💼 Membre Actif • 9€/mois';
          badge.style.background = '#f1f5f9';
          badge.style.color = '#334155';
        }
        if (roleBadge) {
          roleBadge.textContent = '💼 Membre One Vision';
          roleBadge.style.background = '#f1f5f9';
          roleBadge.style.color = '#334155';
        }
        if (bMembre) bMembre.style.background = '#2563eb';
      } else if (mode === 'expired') {
        if (badge) {
          badge.textContent = '⚠️ Abonnement Expiré';
          badge.style.background = '#fee2e2';
          badge.style.color = '#991b1b';
        }
        if (roleBadge) {
          roleBadge.textContent = '⏳ Non-abonné / Expiré';
          roleBadge.style.background = '#fee2e2';
          roleBadge.style.color = '#991b1b';
        }
        if (bExpired) bExpired.style.background = '#dc2626';
      }
    }
  </script>
</body>
</html>
