<?php
/**
 * ONE VISION COMMUNITY — GESTION DE LA BASE DE DONNÉES (PDO SQLITE)
 * Initialisation automatique des tables et injection de données de départ (Seeders).
 */

require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dbDir = dirname(DB_FILE);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $isNewDb = !file_exists(DB_FILE);

        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Activer les clés étrangères dans SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');

        if ($isNewDb || filesize(DB_FILE) === 0) {
            init_database($pdo);
        }
    }

    return $pdo;
}

function init_database(PDO $pdo): void {
    // 1. Table Utilisateurs / Membres
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            avatar TEXT DEFAULT './img/avatar-maxime.jpg',
            role TEXT DEFAULT 'member', -- member, speaker, admin
            company TEXT DEFAULT '',
            job_title TEXT DEFAULT '',
            bio TEXT DEFAULT '',
            skills TEXT DEFAULT '',
            subscription_status TEXT DEFAULT 'active', -- active, trial, cancelled
            subscription_started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Table Lives & Masterminds
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lives (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            format TEXT NOT NULL DEFAULT 'Live Thématique', -- Mastermind Q&A, Live Thématique, Retour d'Expérience, Atelier Pratique
            category_tag TEXT NOT NULL DEFAULT 'Général',
            section TEXT NOT NULL DEFAULT 'current', -- current (cette semaine), next (semaine pro), replay
            scheduled_date DATE NOT NULL,
            scheduled_time TEXT NOT NULL DEFAULT '19h00',
            duration TEXT NOT NULL DEFAULT '1h00',
            author_name TEXT NOT NULL,
            author_role TEXT DEFAULT 'Membre One Vision',
            author_avatar TEXT DEFAULT './img/avatar-maxime.jpg',
            resources TEXT DEFAULT '',
            video_url TEXT DEFAULT '',
            is_live_now INTEGER DEFAULT 0,
            attendees_count INTEGER DEFAULT 140,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");

    // 3. Table Messages des Salons de discussion (Chat)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_id TEXT NOT NULL DEFAULT 'general', -- general, retours, entraide, victoires, partenariats
            user_id INTEGER,
            user_name TEXT NOT NULL,
            user_role TEXT DEFAULT 'Membre',
            user_avatar TEXT DEFAULT './img/avatar-maxime.jpg',
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");

    // 4. Table Commandes & Abonnements (Checkout / Facturation)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number TEXT UNIQUE NOT NULL,
            user_id INTEGER NOT NULL,
            amount REAL NOT NULL DEFAULT 9.00,
            currency TEXT NOT NULL DEFAULT 'EUR',
            status TEXT NOT NULL DEFAULT 'paid', -- paid, pending, failed
            payment_method TEXT NOT NULL DEFAULT 'card', -- card, sepa, paypal
            billing_name TEXT NOT NULL,
            billing_email TEXT NOT NULL,
            billing_address TEXT DEFAULT '',
            billing_country TEXT DEFAULT 'France',
            invoice_number TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    // 5. Table Support & Tickets Contact
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'open', -- open, in_progress, resolved
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );
    ");

    // Injection des données de test initiales (Seeds)
    seed_database($pdo);
}

function seed_database(PDO $pdo): void {
    // Vérifier si des utilisateurs existent déjà
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    if ($count > 0) return;

    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);

    // Création d'utilisateurs réalistes
    $insertUser = $pdo->prepare("
        INSERT INTO users (full_name, email, password, avatar, role, company, job_title, bio, skills, subscription_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    $insertUser->execute([
        'Cyril D.',
        'cyril@onevisioncommunity.fr',
        $passwordHash,
        './img/avatar-cyril.jpg',
        'admin',
        'One Vision Group',
        'Fondateur & Mentor',
        'Entrepreneur depuis 12 ans. Accompagne les solopreneurs à structurer leur croissance sans s\'épuiser.',
        'Stratégie, Vente B2B, Automatisation'
    ]);
    $cyrilId = $pdo->lastInsertId();

    $insertUser->execute([
        'Katahana Désiré',
        'katahana@onevisioncommunity.fr',
        $passwordHash,
        './img/avatar-maxime.jpg',
        'member',
        'Stratégie Digitale',
        'Consultant en Croissance',
        'Spécialiste de l\'acquisition organique et du packaging d\'offres haut de gamme.',
        'Copywriting, Offres High-Ticket, Acquisition'
    ]);
    $katahanaId = $pdo->lastInsertId();

    $insertUser->execute([
        'Sophie Laurent',
        'sophie@onevisioncommunity.fr',
        $passwordHash,
        './img/avatar-sophie.jpg',
        'speaker',
        'Cabinet SL Conseil',
        'Coach Business & Mindset',
        'Aide les créatrices et dirigeantes à surmonter le plafond de verre des 5k€/mois.',
        'Mindset, Gestion du temps, Négociation'
    ]);
    $sophieId = $pdo->lastInsertId();

    $insertUser->execute([
        'Thomas Mercier',
        'thomas@onevisioncommunity.fr',
        $passwordHash,
        './img/avatar-thomas.jpg',
        'member',
        'SaaS Pulse',
        'Développeur Fullstack & Builder',
        'Création de micro-SaaS rentables et mise en place de tunnels de vente ultra-rapides.',
        'NoCode, SaaS, APIs'
    ]);

    // Insertion des Lives & Masterminds de départ
    $insertLive = $pdo->prepare("
        INSERT INTO lives (user_id, title, description, format, category_tag, section, scheduled_date, scheduled_time, duration, author_name, author_role, author_avatar, resources, is_live_now, attendees_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $today = date('Y-m-d');
    $nextDays = date('Y-m-d', strtotime('+3 days'));
    $nextWeek = date('Y-m-d', strtotime('+7 days'));

    $insertLive->execute([
        $cyrilId,
        'Mastermind Q&A en Direct : Débloquer vos freins de vente ce trimestre',
        'Session ouverte d\'analyse directe de vos offres. Venez poser vos questions à micro ouvert, pitcher votre produit et recevoir des retours sans concession.',
        'Mastermind Q&A',
        'Vente & Stratégie',
        'current',
        $today,
        '19h00',
        '1h30',
        'Cyril D.',
        'Fondateur One Vision',
        './img/avatar-cyril.jpg',
        'Grille d\'audit de proposition de valeur (PDF)',
        1, // Live en cours !
        142
    ]);

    $insertLive->execute([
        $katahanaId,
        'Comment j\'ai signé 3 clients à 2 500€ en 30 jours sans prospection froide',
        'Partage d\'un cas réel sans langue de bois : mon offre d\'appel, la trame de cadrage utilisée et les erreurs évitées.',
        'Retour d\'Expérience',
        'Acquisition B2B',
        'current',
        $nextDays,
        '19h00',
        '1h00',
        'Katahana Désiré',
        'Stratège Digital & Membre One Vision',
        './img/avatar-maxime.jpg',
        'Trame d\'appel de découverte (.PDF)',
        0,
        96
    ]);

    $insertLive->execute([
        $sophieId,
        'Atelier Pratique : Construire une offre irrésistible de A à Z',
        'Exercice en direct : nous prenons l\'offre d\'un participant et nous la réécrivons ensemble pas à pas pour la rendre évidente et désirable.',
        'Atelier Pratique',
        'Offres & Pricing',
        'next',
        $nextWeek,
        '18h30',
        '1h15',
        'Sophie Laurent',
        'Coach Business & Speaker',
        './img/avatar-sophie.jpg',
        'Workbook d\'exercices (Notion & PDF)',
        0,
        115
    ]);

    // Insertion des premiers messages de salons (Chat)
    $insertMsg = $pdo->prepare("
        INSERT INTO messages (channel_id, user_id, user_name, user_role, user_avatar, content, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $insertMsg->execute([
        'general',
        $cyrilId,
        'Cyril D.',
        'Fondateur One Vision',
        './img/avatar-cyril.jpg',
        'Bienvenue à tous les nouveaux arrivants dans la communauté ! N\'hésitez pas à vous présenter et partager le défi principal sur lequel vous bossez en ce moment.',
        date('Y-m-d H:i:s', strtotime('-2 hours'))
    ]);

    $insertMsg->execute([
        'general',
        $katahanaId,
        'Katahana Désiré',
        'Membre',
        './img/avatar-maxime.jpg',
        'Ravi d\'échanger avec tout le monde ! On se retrouve au Mastermind ce soir pour décortiquer les tunnels de vente.',
        date('Y-m-d H:i:s', strtotime('-45 minutes'))
    ]);

    $insertMsg->execute([
        'retours',
        $sophieId,
        'Sophie Laurent',
        'Speaker',
        './img/avatar-sophie.jpg',
        'Qui souhaite que l\'on analyse sa page de vente lors de l\'atelier pratique de la semaine prochaine ? Laissez votre lien ci-dessous !',
        date('Y-m-d H:i:s', strtotime('-1 hour'))
    ]);

    $insertMsg->execute([
        'entraide',
        $katahanaId,
        'Katahana Désiré',
        'Membre',
        './img/avatar-maxime.jpg',
        'Besoin d\'un retour rapide sur mon nouveau slogan de positionnement B2B pour les agences. Des volontaires en DM ou ici ?',
        date('Y-m-d H:i:s', strtotime('-20 minutes'))
    ]);

    // Insertion d'une première commande de test
    $insertOrder = $pdo->prepare("
        INSERT INTO orders (order_number, user_id, amount, currency, status, payment_method, billing_name, billing_email, billing_country, invoice_number)
        VALUES (?, ?, 9.00, 'EUR', 'paid', 'card', ?, ?, 'France', ?)
    ");
    $insertOrder->execute([
        'ORD-2026-00101',
        $katahanaId,
        'Katahana Désiré',
        'katahana@onevisioncommunity.fr',
        'INV-2026-00101'
    ]);
}
