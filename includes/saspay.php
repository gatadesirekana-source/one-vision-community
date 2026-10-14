<?php
/**
 * ONE VISION COMMUNITY — MODULE CLIENT OFFICIEL SASPAY
 * 
 * Conçu selon les spécifications strictes de l'API SasPay (v1.0.0) :
 * - https://docs.saspay.me/
 * - https://docs.saspay.me/api-reference/openapi.json
 * 
 * RÈGLES DE SÉCURITÉ & ARCHITECTURE :
 * 1. Clé API secrète utilisée UNIQUEMENT côté serveur (backend).
 * 2. Montants obligatoirement formatés en chaîne décimale ("9.00", "89.00").
 * 3. Vérification systématique du statut réel (seul 'SUCCESS' autorise l'activation).
 * 4. Contrôle d'âge (max 300s) et signature HMAC SHA-256 en temps constant sur les webhooks.
 */

require_once __DIR__ . '/config.php';

class SasPayClient {

    private string $apiKey;
    private string $webhookSecret;
    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $webhookSecret = null) {
        $this->apiKey = $apiKey ?: (defined('SASPAY_API_KEY') ? SASPAY_API_KEY : (getenv('SASPAY_API_KEY') ?: ''));
        $this->webhookSecret = $webhookSecret ?: (defined('SASPAY_WEBHOOK_SECRET') ? SASPAY_WEBHOOK_SECRET : (getenv('SASPAY_WEBHOOK_SECRET') ?: ''));
        $this->baseUrl = defined('SASPAY_BASE_URL') ? SASPAY_BASE_URL : 'https://api.saspay.me/api/v1';
    }

    /**
     * Effectue une requête HTTP vers l'API SasPay
     */
    private function request(string $method, string $endpoint, ?array $data = null, array $headers = []): array {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'code'    => 401,
                'error'   => "La clé API SasPay n'est pas configurée dans le fichier d'environnement (.env)."
            ];
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);

        $defaultHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

        // Prise en charge du faisceau SSL local si présent
        $caFile = __DIR__ . '/cacert.pem';
        if (file_exists($caFile)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caFile);
        }

        $method = strtoupper($method);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'success' => false,
                'code'    => 0,
                'error'   => "Erreur réseau lors de la communication avec SasPay : " . $curlError
            ];
        }

        $json = json_decode($rawResponse, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'code'    => $httpCode,
                'data'    => $json['data'] ?? $json
            ];
        }

        // Gestion des erreurs structurées SasPay
        $errorMessage = "Erreur SasPay (HTTP {$httpCode})";
        if (is_array($json)) {
            if (isset($json['error']['message'])) {
                $errorMessage = $json['error']['message'];
            } elseif (isset($json['message'])) {
                $errorMessage = $json['message'];
            }
        }

        return [
            'success' => false,
            'code'    => $httpCode,
            'error'   => $errorMessage,
            'details' => $json
        ];
    }

    /**
     * Création d'une session de Checkout hébergé SasPay
     * 
     * @param array $params [amount, currency, description, customer_email, customer_name, return_url, metadata]
     */
    public function createCheckoutSession(array $params): array {
        // Validation et formatage strict du montant en chaîne décimale
        $amount = is_numeric($params['amount']) ? sprintf('%.2f', (float)$params['amount']) : (string)$params['amount'];
        $currency = strtoupper(trim($params['currency'] ?? 'EUR'));

        $body = [
            'amount'         => $amount,
            'currency'       => $currency,
            'description'    => $params['description'] ?? 'Abonnement One Vision Community',
            'customer_email' => $params['customer_email'] ?? '',
            'customer_name'  => $params['customer_name'] ?? 'Client',
            'customer_phone' => $params['customer_phone'] ?? '',
            'return_url'     => $params['return_url'] ?? (rtrim(APP_URL, '/') . '/paiement-retour.php'),
            'metadata'       => $params['metadata'] ?? []
        ];

        if (!empty($params['country'])) {
            $body['country'] = strtoupper(trim($params['country']));
        }

        $response = $this->request('POST', '/checkout-sessions/', $body);

        if ($response['success']) {
            $session = $response['data'];
            return [
                'success'      => true,
                'session_id'   => $session['id'] ?? null,
                'slug'         => $session['slug'] ?? null,
                'checkout_url' => $session['checkout_url'] ?? null,
                'status'       => $session['status'] ?? 'PENDING',
                'data'         => $session
            ];
        }

        return $response;
    }

    /**
     * Récupère le statut d'une session de checkout
     */
    public function getCheckoutSessionStatus(string $sessionId): array {
        return $this->request('GET', '/checkout-sessions/' . urlencode($sessionId) . '/status/');
    }

    /**
     * Vérification directe et active d'une transaction de paiement auprès de SasPay
     * Re-vérifie toujours le statut réel côté passerelle si PENDING.
     */
    public function verifyPayment(string $paymentId): array {
        return $this->request('GET', '/payments/' . urlencode($paymentId) . '/verify/');
    }

    /**
     * Annule une session de checkout en cours
     */
    public function cancelCheckoutSession(string $sessionId): array {
        return $this->request('POST', '/checkout-sessions/' . urlencode($sessionId) . '/cancel/');
    }

    /**
     * Vérification cryptographique stricte d'un Webhook entrant
     * 
     * 1. Vérification de l'âge de l'événement (tolérance 300 secondes max)
     * 2. Recalcul HMAC SHA-256 sur le corps brut (timestamp . '.' . rawBody)
     * 3. Comparaison en temps constant via hash_equals
     */
    public function verifyWebhook(string $rawBody, string $signature, string $timestamp): array {
        if (empty($this->webhookSecret)) {
            return [
                'valid' => false,
                'error' => "Le secret de signature du webhook (SASPAY_WEBHOOK_SECRET) n'est pas configuré."
            ];
        }

        if (empty($signature) || empty($timestamp)) {
            return [
                'valid' => false,
                'error' => "En-têtes de signature de webhook manquants."
            ];
        }

        // 1. Contrôle d'âge (max 300 secondes d'écart pour prévenir le rejeu)
        $tolerance = 300;
        $now = time();
        if (abs($now - (int)$timestamp) > $tolerance) {
            return [
                'valid' => false,
                'error' => "Horodatage du webhook hors tolérance (" . abs($now - (int)$timestamp) . "s > {$tolerance}s)."
            ];
        }

        // 2. Calcul du HMAC SHA-256 sur le message 'timestamp.body'
        $message = $timestamp . '.' . $rawBody;
        $expectedSignature = hash_hmac('sha256', $message, $this->webhookSecret);

        // 3. Comparaison en temps constant (contre les attaques par analyse temporelle)
        if (!hash_equals($expectedSignature, strtolower(trim($signature)))) {
            return [
                'valid' => false,
                'error' => "Signature HMAC SHA-256 invalide."
            ];
        }

        return [
            'valid' => true,
            'error' => null
        ];
    }

    /**
     * Journalise les événements de webhook dans un fichier sécurisé pour audit
     */
    public static function logWebhookEvent(string $message, array $context = []): void {
        $logDir = BASE_DIR . DIRECTORY_SEPARATOR . 'database';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . DIRECTORY_SEPARATOR . 'webhooks.log';
        $time = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $jsonContext = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$time}] [{$ip}] {$message}{$jsonContext}\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
