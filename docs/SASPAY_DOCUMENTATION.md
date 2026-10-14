# Documentation Complète de l'API SasPay (de A à Z)

> **Source officielle :** [https://docs.saspay.me/](https://docs.saspay.me/)  
> **Spécification OpenAPI :** `https://docs.saspay.me/api-reference/openapi.json`  
> **Base URL :** `https://api.saspay.me/api/v1`  
> **Version API :** 1.0.0

---

## 1. Vue d'Ensemble & Architecture

**SasPay** est une plateforme unifiée et une API REST unique permettant :
1. **L'encaissement (Payin)** via Mobile Money (MTN, Orange, Moov, Wave, Celtiis, Airtel, etc.) et Cartes bancaires (Visa, Mastercard via Stripe) en Afrique de l'Ouest, Afrique Centrale et à l'international.
2. **Les retraits / paiements de bénéficiaires (Payout)** directement vers les portefeuilles Mobile Money.
3. **La gestion de portefeuille multi-devises (Wallet)** avec conversion automatique selon les taux de change du marché.
4. **La notification en temps réel par Webhooks** sécurisés par signature cryptographique HMAC SHA-256.

### Environnements & Clés API
SasPay propose deux environnements distincts :

| Environnement | Préfixe Clé API | Destination | Argent Réel |
|---|---|---|---|
| **Test / Sandbox** | `sk_test_...` | Développement, tests automatisés | Non |
| **Production / Live** | `sk_live_...` | Encaissement et paiements réels | Oui |

### Règle d'or de sécurité
> **Sécurité absolue :**
> **La clé API secrète ne doit JAMAIS être exposée côté frontend (navigateur, application mobile, code client JS) ni dans un dépôt public ou des logs.**
> Elle doit être stockée exclusivement sur le **backend** (dans un fichier d'environnement `.env` protégé) et transmise uniquement via le header HTTP `Authorization: Bearer <clé>`.

---

## 2. Principes Fondamentaux de Conception

1. **Format des Montants (Chaîne Décimale Obligatoire) :**
   - Toujours envoyer des chaînes avec deux décimales : `"2500.00"`, `"9.00"`.
   - **JAMAIS** d'entier brut (`2500`) ni de nombre à virgule flottante (`2500.0` ou `9`).
2. **Idempotence sur les Mouvements Financiers :**
   - Transmettre l'en-tête `Idempotency-Key: <UUID>` sur tous les endpoints déplaçant de l'argent (`/payments/softpay/`, `/payouts/initialize/`, `/wallet-transfers/`, `/payments/{id}/retry/`).
   - Même clé + même corps = renvoi de la réponse d'origine sans re-débit.
   - Même clé + corps modifié = renvoi de l'erreur `409 Conflict`.
3. **Vérification Obligatoire avant Livraison :**
   - Une redirection ou un statut `PENDING` ne prouve **rien**.
   - Seul le statut `SUCCESS` vérifié via `GET /payments/{id}/verify/` ou via un webhook authentifié autorise la livraison ou l'activation d'un abonnement.
4. **Comptabilisation des Frais (`fee_charge_mode`) :**
   - Mode `ADD_ON` : Les frais s'ajoutent au montant payé par le client (`charged = amount + fee`, `net_amount = amount`).
   - Mode `DEDUCTED` : Les frais sont prélevés sur votre gain net (`charged = amount`, `net_amount = amount - fee`).
   - Pour la comptabilité, utiliser **toujours** `charged` (déboursé par le client) et `net_amount` (crédité sur votre compte).
5. **Limite de Débit (Rate Limiting) :**
   - 300 requêtes / minute par compte. Le dépassement déclenche une réponse HTTP `429 Too Many Requests`.

---

## 3. Format des Réponses & Gestion des Erreurs

Toutes les réponses de l'API partagent une enveloppe standardisée.

### Enveloppe de Succès (HTTP 200 / 201)
```json
{
  "success": true,
  "data": { ... },
  "code": 200
}
```

### Enveloppe d'Erreur Métier (HTTP 4xx / 5xx)
```json
{
  "success": false,
  "error": {
    "message": "Solde disponible insuffisant.",
    "code": "insufficient_balance"
  },
  "code": 422
}
```

### Tableau des Codes HTTP
| Code | Cause | Action requise |
|---|---|---|
| `200` / `201` | Succès | Traiter le payload `data` |
| `400` | Requête invalide | Corriger les paramètres ou le format JSON |
| `401` | Clé API absente, invalide ou expirée | Vérifier la clé Bearer dans `.env` |
| `403` | Droits insuffisants ou IP non whitelistée | Vérifier scope (`PAYIN`/`PAYOUT`) ou IP serveur |
| `404` | Ressource introuvable | Vérifier les identifiants transmis |
| `409` | Conflit d'idempotence ou d'état | Ne pas rejouer sans vérifier l'opération |
| `410` | Session ou lien expiré | Créer une nouvelle session |
| `422` | Erreur métier (ex: solde insuffisant, méthode invalide) | Informer l'utilisateur selon `error.code` |
| `429` | Trop de requêtes | Espacer les requêtes (backoff exponentiel) |

---

## 4. Les Deux Flux d'Encaissement (Payin)

### Option A : Checkout Hébergé (`POST /checkout-sessions/`)
*Recommandé pour laisser le client choisir son mode de paiement (Mobile Money ou Carte).*

```http
POST https://api.saspay.me/api/v1/checkout-sessions/
Authorization: Bearer sk_live_...
Content-Type: application/json

{
  "amount": "9.00",
  "currency": "EUR",
  "description": "Formule Membre - 1 mois",
  "customer_email": "client@example.com",
  "customer_name": "Jean Dupont",
  "customer_phone": "+22997000000",
  "return_url": "https://votre-site.com/paiement-retour.php",
  "metadata": {
    "user_id": 42,
    "plan_code": "membre",
    "periodicite": "mensuel"
  }
}
```

**Réponse reçue :**
```json
{
  "success": true,
  "data": {
    "id": "b17e5370-2db7-470e-9de5-4a40dd15bcc6",
    "slug": "vYgHUyNKp4_DIqkM3EkPxqsru2pz5SPy",
    "checkout_url": "https://checkout.saspay.me/vYgHUyNKp4_DIqkM3EkPxqsru2pz5SPy",
    "status": "PENDING"
  },
  "code": 201
}
```
**Action à mener :** Rediriger immédiatement le navigateur du client vers `checkout_url`. Une fois le paiement validé, SasPay redirige le client vers `return_url`.

### Option B : Softpay / Paiement Direct (`POST /payments/softpay/`)
*Permet de déclencher directement un Push USSD sur le téléphone du client lorsqu'on connaît déjà son pays et son réseau.*

```http
POST https://api.saspay.me/api/v1/payments/softpay/
Authorization: Bearer sk_live_...
Content-Type: application/json
Idempotency-Key: 3fa85f64-5717-4562-b3fc-2c963f66afa6

{
  "amount": "2500.00",
  "currency": "XOF",
  "country": "BJ",
  "network": "mtn_bj",
  "description": "Abonnement Mensuel",
  "customer": {
    "email": "client@example.com",
    "first_name": "Awa",
    "last_name": "Sossou",
    "phone": "+22997505050"
  }
}
```

**Attention Softpay :** Toujours vérifier si `data.checkout_url` est retourné non-vide.  
Sur Wave, Orange Money, Djamo et Cartes, SasPay ne pousse pas d'USSD et renvoie une URL : **vous devez rediriger le client vers `checkout_url`**, sinon aucun débit n'a lieu.

---

## 5. Vérification Active d'un Paiement

Pour connaître le statut réel d'une transaction sans attendre ou en complément du webhook :

```http
GET https://api.saspay.me/api/v1/payments/{payment_id}/verify/
Authorization: Bearer sk_live_...
```

**Réponse :**
```json
{
  "success": true,
  "data": {
    "id": "9c3f2a10-4b7e-4f1a-9d2e-9b6a7c1e4a02",
    "reference": "TXN-20260812-000512",
    "status": "SUCCESS",
    "net_amount": "2500.00",
    "currency": "XOF"
  },
  "code": 200
}
```

---

## 6. Webhooks & Sécurité Cryptographique (HMAC SHA-256)

### En-têtes HTTP envoyés par SasPay
```http
POST https://votre-site.com/api/webhook.php
Content-Type: application/json
X-Webhook-Signature: t=1734567890,v1=abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789
X-Webhook-Timestamp: 1734567890
X-Webhook-Event: transaction.success
```

### Algorithme de Vérification Strict
1. **Contrôle d'âge :** Rejeter la requête si `abs(time() - timestamp) > 300` secondes (5 minutes). Empêche les attaques par rejeu.
2. **Contrôle de signature :**
   - Prendre le **corps brut (RAW bytes)** de la requête (`file_get_contents('php://input')`).
   - Concaténer `${timestamp}.${raw_body}`.
   - Calculer le HMAC SHA-256 avec la clé secrète du webhook (`signing_secret`).
   - Comparer en temps constant avec `hash_equals()`.

---

## 7. Retraits vers Bénéficiaires (Payouts)

```http
POST https://api.saspay.me/api/v1/payouts/initialize/
Authorization: Bearer sk_live_...
Content-Type: application/json
Idempotency-Key: 7b8c9d0e-1f2a-4c3d-9e0f-1a2b3c4d5e6f

{
  "amount": "15000.00",
  "currency": "XOF",
  "country": "BJ",
  "method": "mtn_bj",
  "recipient": {
    "name": "Jean Dupont",
    "msisdn": "22997000000"
  },
  "description": "Paiement commission animateur"
}
```

---

## 8. Référentiel des Pays et Réseaux

| Pays | Code ISO2 | Devise | Indicatif | Réseaux disponibles |
|---|---|---|---|---|
| **Bénin** | `BJ` | XOF | +229 | `mtn_bj`, `moov_bj`, `celtiis_bj` |
| **Côte d'Ivoire** | `CI` | XOF | +225 | `orange_ci`, `mtn_ci`, `moov_ci`, `wave_ci` |
| **Sénégal** | `SN` | XOF | +221 | `orange_sn`, `wave_sn`, `freemoney_sn`, `wizall_sn` |
| **Burkina Faso** | `BF` | XOF | +226 | `orange_bf`, `moov_bf` |
| **Cameroun** | `CM` | XAF | +237 | `mtn_cm`, `orange_cm` |
| **Mali** | `ML` | XOF | +223 | `orange_ml`, `moov_ml`, `mobi_cash_ml` |
| **Togo** | `TG` | XOF | +228 | `togocel`, `moov_tg` |
| **RD Congo** | `CD` | CDF | +243 | `vodacom_cd`, `airtel_cd`, `orange_cd` |
| **International** | `XX` | USD / EUR | - | `card` (CB Visa/Mastercard via Stripe), `crypto` |
