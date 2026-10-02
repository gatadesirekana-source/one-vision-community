# One Vision Community — Architecture PHP & SQLite

Bienvenue sur la version dynamisée en PHP de **One Vision Community**.
Le site a été transformé d'un ensemble de pages statiques en une application web PHP complète, sécurisée et modulaire, connectée à une base de données **SQLite** (zéro configuration requise).

---

## 🚀 Démarrage Rapide

Pour lancer le site en local, ouvrez un terminal dans ce dossier et exécutez :

```bash
php -S localhost:8000
```

Accédez ensuite à l'application dans votre navigateur :
👉 **[http://localhost:8000/index.php](http://localhost:8000/index.php)**

---

## 🔑 Comptes de Test Pré-configurés

La base de données s'auto-initialise avec des comptes de démonstration prêts à l'emploi :

| Rôle | Email | Mot de passe |
| :--- | :--- | :--- |
| **Fondateur / Admin** | `cyril@onevisioncommunity.fr` | `password123` |
| **Membre Entrepreneur** | `katahana@onevisioncommunity.fr` | `password123` |
| **Speaker / Coach** | `sophie@onevisioncommunity.fr` | `password123` |

---

## 📁 Architecture du Projet

```text
├── api/
│   ├── chat.php                   # API REST JSON pour les messages des salons
│   └── lives.php                  # API REST JSON pour la liste des sessions live
├── database/
│   └── database.sqlite            # Base de données SQLite (auto-générée & auto-seedée)
├── includes/
│   ├── config.php                 # Configuration globale, sessions et constantes
│   ├── db.php                     # Connexion PDO SQLite, migrations et seeds initiaux
│   ├── auth.php                   # Fonctions d'authentification (login, register, logout, CSRF)
│   ├── flash.php                  # Gestion des notifications flash
│   ├── header.php                 # Navigation globale avec état connecté/déconnecté
│   └── footer.php                 # Pied de page unifié avec date dynamique
├── index.php                      # Page d'accueil dynamique avec stats et CTA adaptatifs
├── dashboard.php                  # Dashboard membre protégé (Lives, Salons, Réseau, Factures)
├── creer-live.php                 # Formulaire de programmation d'un live avec enregistrement en BDD
├── checkout.php                   # Tunnel de paiement et adhésion 9€/mois avec création de commande
├── facture.php                    # Facture officielle dynamique imprimable / exportable PDF
├── support.php                    # Formulaire de contact et d'assistance avec tickets en BDD
├── login.php                      # Page de connexion officielle des membres
├── register.php                   # Page d'inscription pour nouveaux membres
├── logout.php                     # Déconnexion et nettoyage de session
├── conditions-generales.php       # Mentions contractuelles dynamisées
├── mentions-legales.php           # Mentions légales dynamisées
├── politique-confidentialite.php  # Politique RGPD
└── gestion-cookies.php            # Charte des cookies
```

---

## 🗄️ Structure de la Base de Données (SQLite)

- **`users`** : Comptes membres, hash des mots de passe (`password_hash`), rôles (`admin`, `speaker`, `member`), bio, entreprise, avatar et statut d'abonnement.
- **`lives`** : Sessions lives et masterminds programmés, date, heure, durée, intervenant, description, ressources offertes et participants.
- **`messages`** : Messages des salons thématiques (`general`, `retours`, `entraide`, `partenariats`, etc.) avec auteur et horodatage.
- **`orders`** : Commandes et abonnements validés à 9€/mois, numéros de factures séquentiels officiels.
- **`support_tickets`** : Demandes et messages envoyés via le formulaire de support.

---

## ✨ Fonctionnalités Dynamiques Implémentées

1. **Authentification & Sécurité** :
   - Session native PHP avec régénération d'ID anti-fixation.
   - Protection des routes sensibles (`require_auth()`).
   - Mots de passe chiffrés avec `PASSWORD_DEFAULT` (Bcrypt).
   - Jetons CSRF pour sécuriser les soumissions de formulaires.

2. **Création & Gestion des Lives** :
   - Tout live programmé sur `creer-live.php` est sauvegardé en BDD et s'affiche immédiatement dans le calendrier de `dashboard.php`.

3. **Salons de Discussion en Temps Réel** :
   - Les messages sont sauvegardés en BDD SQLite et synchronisés en AJAX via `api/chat.php`.

4. **Adhésion & Facturation Immédiate** :
   - Tunnel d'adhésion sécurisé (`checkout.php`).
   - Prise en charge des coordonnées d'adhésion (Carte Bancaire & Paiement Mobile).
   - Activation instantanée de l'accès membre dans la base de données.
   - Page de confirmation d'adhésion (`checkout-success.php`).
   - Génération immédiate de la facture officielle acquittée (`facture.php?id=...`).

5. **Centre d'Administration (Rôle Admin)** :
   - Tableau de bord dédié pour l'administrateur avec indicateurs clés (KPIs), suivi des tickets de support reçus et liste exhaustive des commandes avec liens de factures.

6. **Gestion du Profil Membre** :
   - Mise à jour du nom, du rôle, du mot de passe et de l'avatar depuis l'onglet paramètres de `dashboard.php`.
