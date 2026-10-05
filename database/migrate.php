<?php
/**
 * ONE VISION COMMUNITY — SCRIPT DE MIGRATION ET DONNÉES INITIALES
 * 
 * Crée et met à jour toutes les tables requises :
 * - plans (tarifs et avantages dynamiques)
 * - subscriptions (abonnements utilisateurs)
 * - payments (historique des règlements)
 * - roles, permissions, role_permissions, user_permissions (système fin de rôles & délégation)
 * - journal_actions (journal d'audit des actions administratives)
 * - mise à jour des tables existantes (users.statut, lives.status)
 * - injection du compte propriétaire depuis .env
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

function run_migrations(?PDO $pdo = null): array {
    if ($pdo === null) {
        $pdo = get_db();
    }

    $log = [];

    // 1. Mise à jour de la table users (statut, role, etc.)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN statut TEXT DEFAULT 'actif'");
        $log[] = "Colonne 'statut' ajoutée à la table users.";
    } catch (Exception $e) {}

    // Normaliser les rôles existants vers la nouvelle nomenclature
    try {
        $pdo->exec("UPDATE users SET role = 'membre' WHERE role = 'member'");
        $pdo->exec("UPDATE users SET role = 'animateur' WHERE role = 'speaker'");
        $pdo->exec("UPDATE users SET role = 'proprietaire' WHERE role = 'admin'");
        $log[] = "Normalisation des rôles des utilisateurs existants effectuée.";
    } catch (Exception $e) {}

    // 2. Mise à jour de la table lives (status)
    try {
        $pdo->exec("ALTER TABLE lives ADD COLUMN status TEXT DEFAULT 'publie'");
        $log[] = "Colonne 'status' ajoutée à la table lives.";
    } catch (Exception $e) {}

    // 3. Table des Formules (plans)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            nom TEXT NOT NULL,
            prix_mensuel REAL NOT NULL,
            prix_annuel REAL NOT NULL,
            devise TEXT NOT NULL DEFAULT 'EUR',
            avantages TEXT NOT NULL,
            actif INTEGER DEFAULT 1,
            ordre_affichage INTEGER DEFAULT 1,
            identifiant_offre_externe TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $log[] = "Table 'plans' vérifiée/créée.";

    // 4. Table des Abonnements (subscriptions)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            plan_id INTEGER NOT NULL,
            periodicite TEXT NOT NULL, -- 'mensuel', 'annuel'
            prix_paye REAL NOT NULL,
            statut TEXT NOT NULL, -- 'actif', 'expire', 'annule'
            date_debut DATETIME NOT NULL,
            date_fin DATETIME NOT NULL,
            renouvellement_auto INTEGER DEFAULT 1,
            reference_paiement TEXT DEFAULT NULL,
            date_resiliation DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES plans(id)
        );
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_subs_user_statut ON subscriptions(user_id, statut);");
    $log[] = "Table 'subscriptions' vérifiée/créée.";

    // 5. Table des Paiements (payments)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subscription_id INTEGER,
            user_id INTEGER NOT NULL,
            montant REAL NOT NULL,
            devise TEXT NOT NULL DEFAULT 'EUR',
            statut TEXT NOT NULL DEFAULT 'reussi', -- 'reussi', 'en_attente', 'echoue'
            reference_externe TEXT DEFAULT NULL,
            methode TEXT DEFAULT 'simulation',
            date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_user ON payments(user_id);");
    $log[] = "Table 'payments' vérifiée/créée.";

    // 6. Tables Rôles et Permissions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            nom TEXT NOT NULL,
            description TEXT DEFAULT ''
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            nom TEXT NOT NULL,
            description TEXT DEFAULT '',
            categorie TEXT DEFAULT 'Général'
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INTEGER NOT NULL,
            permission_id INTEGER NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            user_id INTEGER NOT NULL,
            permission_id INTEGER NOT NULL,
            PRIMARY KEY (user_id, permission_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        );
    ");
    $log[] = "Tables de rôles et permissions vérifiées/créées.";

    // 7. Table Journal d'actions administratives
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS journal_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            cible TEXT DEFAULT '',
            details TEXT DEFAULT '',
            date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    $log[] = "Table 'journal_actions' vérifiée/créée.";

    // 8. Tables du Questionnaire d'Accueil (Onboarding)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profils_onboarding (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            tranche_age TEXT DEFAULT NULL,
            situation TEXT DEFAULT NULL,
            date_debut DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_fin DATETIME DEFAULT NULL,
            statut TEXT NOT NULL DEFAULT 'en_cours', -- 'en_cours', 'termine', 'mineur'
            consentement_marketing INTEGER DEFAULT 0,
            date_consentement DATETIME DEFAULT NULL,
            canal_rappel_prefere TEXT DEFAULT NULL,
            source_decouverte TEXT DEFAULT NULL,
            current_question_index INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_onboarding_user ON profils_onboarding(user_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_onboarding_statut ON profils_onboarding(statut);");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reponses_questionnaire (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            question_id TEXT NOT NULL,
            reponse TEXT NOT NULL,
            date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reponses_user_q ON reponses_questionnaire(user_id, question_id);");
    $log[] = "Tables 'profils_onboarding' et 'reponses_questionnaire' créées/vérifiées.";

    // =========================================================================
    // INJECTION DES DONNÉES DE BASE (SEEDERS)
    // =========================================================================

    // A. Formules (plans)
    $plansData = [
        [
            'code' => 'membre',
            'nom' => 'Formule Membre',
            'prix_mensuel' => 9.00,
            'prix_annuel' => 89.00,
            'devise' => 'EUR',
            'ordre_affichage' => 1,
            'avantages' => json_encode([
                "Accès à la communauté One Vision",
                "Échanges quotidiens avec les autres entrepreneurs",
                "Partage de sa vision et de ses ambitions, feedbacks, collaboration sur des projets communs",
                "Participation aux lives et aux masterminds organisés par les animateurs",
                "Accès aux replays",
                "Profil personnel"
            ], JSON_UNESCAPED_UNICODE)
        ],
        [
            'code' => 'animateur',
            'nom' => 'Formule Animateur',
            'prix_mensuel' => 24.00,
            'prix_annuel' => 239.00,
            'devise' => 'EUR',
            'ordre_affichage' => 2,
            'avantages' => json_encode([
                "TOUT ce qui est inclus dans la formule Membre",
                "Création et animation de ses propres lives",
                "Création et animation de ses propres masterminds",
                "Un Espace Animateur dédié pour programmer, modifier, annuler et suivre ses lives et masterminds",
                "Une page d'expert visible par toute la communauté, pour présenter son expertise",
                "Des certifications One Vision et un badge \"Animateur certifié\" affiché sur son profil",
                "Des statistiques sur ses lives (participants inscrits, présents)",
                "La possibilité d'exposer et de valoriser son savoir-faire auprès de toute la communauté"
            ], JSON_UNESCAPED_UNICODE)
        ]
    ];

    $stmtPlan = $pdo->prepare("
        INSERT INTO plans (code, nom, prix_mensuel, prix_annuel, devise, avantages, actif, ordre_affichage)
        VALUES (:code, :nom, :prix_mensuel, :prix_annuel, :devise, :avantages, 1, :ordre_affichage)
        ON CONFLICT(code) DO UPDATE SET
            nom = excluded.nom,
            prix_mensuel = excluded.prix_mensuel,
            prix_annuel = excluded.prix_annuel,
            avantages = excluded.avantages,
            ordre_affichage = excluded.ordre_affichage
    ");
    foreach ($plansData as $plan) {
        $stmtPlan->execute($plan);
    }
    $log[] = "Formules (Membre 9€/89€ et Animateur 24€/239€) initialisées.";

    // B. Rôles
    $rolesData = [
        ['code' => 'proprietaire', 'nom' => 'Propriétaire', 'description' => 'Super-administrateur créateur du site. Possède tous les droits sans exception.'],
        ['code' => 'admin_delegue', 'nom' => 'Administrateur délégué', 'description' => 'Personne de confiance à qui le propriétaire délègue des permissions précises.'],
        ['code' => 'animateur', 'nom' => 'Animateur', 'description' => 'Abonné Animateur actif. Anime ses lives, masterminds et dispose de son espace expert.'],
        ['code' => 'membre', 'nom' => 'Membre', 'description' => 'Abonné Membre actif. Accède aux salons, replays et participe aux sessions.'],
        ['code' => 'visiteur', 'nom' => 'Visiteur', 'description' => 'Visiteur non abonné ou non connecté.']
    ];
    $stmtRole = $pdo->prepare("
        INSERT INTO roles (code, nom, description)
        VALUES (:code, :nom, :description)
        ON CONFLICT(code) DO UPDATE SET nom = excluded.nom, description = excluded.description
    ");
    foreach ($rolesData as $role) {
        $stmtRole->execute($role);
    }
    $log[] = "Rôles de base initialisés.";

    // C. Permissions
    $permsData = [
        ['code' => 'voir_utilisateurs', 'nom' => 'Voir les utilisateurs', 'description' => 'Consulter la liste et la fiche détaillée des membres.', 'categorie' => 'Utilisateurs'],
        ['code' => 'voir_profils_membres', 'nom' => 'Voir les profils des membres', 'description' => 'Consulter les profils, les réponses au questionnaire d\'accueil et les statistiques de conversion.', 'categorie' => 'Utilisateurs'],
        ['code' => 'gerer_abonnements', 'nom' => 'Gérer les abonnements', 'description' => 'Prolonger, modifier ou annuler manuellement un abonnement.', 'categorie' => 'Abonnements'],
        ['code' => 'gerer_roles', 'nom' => 'Gérer les rôles et formules', 'description' => 'Changer manuellement le rôle ou la formule d\'un membre.', 'categorie' => 'Utilisateurs'],
        ['code' => 'moderer_contenu', 'nom' => 'Modérer le contenu', 'description' => 'Modérer les messages des salons et les échanges.', 'categorie' => 'Modération'],
        ['code' => 'gerer_lives', 'nom' => 'Gérer les lives', 'description' => 'Créer, modifier, annuler et superviser tous les lives du calendrier.', 'categorie' => 'Événements'],
        ['code' => 'modifier_tarifs', 'nom' => 'Modifier les tarifs', 'description' => 'Modifier les prix mensuels/annuels et les avantages des formules.', 'categorie' => 'Tarification'],
        ['code' => 'voir_revenus', 'nom' => 'Voir les revenus', 'description' => 'Consulter les statistiques financières et les revenus du mois et cumulés.', 'categorie' => 'Finance'],
        ['code' => 'gerer_administrateurs', 'nom' => 'Gérer l\'équipe d\'administration', 'description' => 'Nommer des administrateurs délégués et ajuster leurs droits (Réservé au Propriétaire).', 'categorie' => 'Administration']
    ];
    $stmtPerm = $pdo->prepare("
        INSERT INTO permissions (code, nom, description, categorie)
        VALUES (:code, :nom, :description, :categorie)
        ON CONFLICT(code) DO UPDATE SET nom = excluded.nom, description = excluded.description, categorie = excluded.categorie
    ");
    foreach ($permsData as $perm) {
        $stmtPerm->execute($perm);
    }
    $log[] = "Permissions fines initialisées.";

    // Associer toutes les permissions au rôle Propriétaire
    $proprioRole = $pdo->query("SELECT id FROM roles WHERE code = 'proprietaire'")->fetch();
    if ($proprioRole) {
        $perms = $pdo->query("SELECT id FROM permissions")->fetchAll();
        $stmtRP = $pdo->prepare("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($perms as $p) {
            $stmtRP->execute([$proprioRole['id'], $p['id']]);
        }
    }

    // D. Initialisation ou mise à jour du Compte Propriétaire depuis .env
    $ownerEmail = trim(strtolower(getenv('PROPRIETAIRE_EMAIL') ?: 'cyril@onevisioncommunity.fr'));
    $ownerPassword = getenv('PROPRIETAIRE_PASSWORD') ?: 'password123';
    $passwordHash = password_hash($ownerPassword, PASSWORD_DEFAULT);

    $existingOwner = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
    $existingOwner->execute([$ownerEmail]);
    $ownerUser = $existingOwner->fetch();

    if ($ownerUser) {
        $pdo->prepare("
            UPDATE users 
            SET role = 'proprietaire', statut = 'actif', password = ?
            WHERE id = ?
        ")->execute([$passwordHash, $ownerUser['id']]);
        $ownerId = $ownerUser['id'];
        $log[] = "Compte propriétaire ({$ownerEmail}) mis à jour avec le rôle 'proprietaire' et statut 'actif'.";
    } else {
        $pdo->prepare("
            INSERT INTO users (full_name, email, password, avatar, role, statut, subscription_status)
            VALUES (?, ?, ?, './img/avatar-cyril.jpg', 'proprietaire', 'actif', 'active')
        ")->execute(['Cyril D. (Fondateur)', $ownerEmail, $passwordHash]);
        $ownerId = $pdo->lastInsertId();
        $log[] = "Nouveau compte propriétaire créé ({$ownerEmail}).";
    }

    // Donner toutes les permissions directes au propriétaire dans user_permissions
    $allPerms = $pdo->query("SELECT id FROM permissions")->fetchAll();
    $stmtUP = $pdo->prepare("INSERT OR IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
    foreach ($allPerms as $p) {
        $stmtUP->execute([$ownerId, $p['id']]);
    }

    // E. Initialiser un abonnement pour les membres existants s'ils n'en ont pas dans subscriptions
    $activeUsers = $pdo->query("
        SELECT id, role, subscription_started_at, subscription_expires_at, subscription_plan 
        FROM users 
        WHERE subscription_status = 'active'
    ")->fetchAll();

    $memberPlan = $pdo->query("SELECT id FROM plans WHERE code = 'membre'")->fetch();
    $animateurPlan = $pdo->query("SELECT id FROM plans WHERE code = 'animateur'")->fetch();

    $stmtCreateSub = $pdo->prepare("
        INSERT INTO subscriptions (
            user_id, plan_id, periodicite, prix_paye, statut,
            date_debut, date_fin, renouvellement_auto, reference_paiement
        ) VALUES (
            ?, ?, 'mensuel', ?, 'actif',
            ?, ?, 1, ?
        )
    ");

    $stmtPayment = $pdo->prepare("
        INSERT INTO payments (
            subscription_id, user_id, montant, devise, statut, reference_externe, methode
        ) VALUES (?, ?, ?, 'EUR', 'reussi', ?, 'simulation')
    ");

    $importedSubs = 0;
    foreach ($activeUsers as $u) {
        // Vérifier si un abonnement existe déjà
        $check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ?");
        $check->execute([$u['id']]);
        if (!$check->fetch()) {
            $isAnimateur = in_array($u['role'], ['animateur', 'speaker'], true);
            $planId = $isAnimateur ? ($animateurPlan['id'] ?? 2) : ($memberPlan['id'] ?? 1);
            $price = $isAnimateur ? 24.00 : 9.00;
            $startDate = !empty($u['subscription_started_at']) ? $u['subscription_started_at'] : date('Y-m-d H:i:s');
            $endDate = !empty($u['subscription_expires_at']) ? $u['subscription_expires_at'] : date('Y-m-d H:i:s', strtotime('+30 days'));
            $ref = 'INIT-' . strtoupper(bin2hex(random_bytes(4)));

            $stmtCreateSub->execute([$u['id'], $planId, $price, $startDate, $endDate, $ref]);
            $newSubId = $pdo->lastInsertId();
            $stmtPayment->execute([$newSubId, $u['id'], $price, $ref]);
            $importedSubs++;
        }
    }
    if ($importedSubs > 0) {
        $log[] = "{$importedSubs} abonnements actifs initialisés pour les membres existants.";
    }

    return $log;
}

// Si exécuté directement en CLI
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'migrate.php') {
    echo "========================================================\n";
    echo "  ONE VISION COMMUNITY — EXÉCUTION DES MIGRATIONS       \n";
    echo "========================================================\n";
    $results = run_migrations();
    foreach ($results as $msg) {
        echo "  [OK] {$msg}\n";
    }
    echo "========================================================\n";
    echo "Migration terminée avec succès.\n";
}
