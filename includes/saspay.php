<?php
/**
 * ONE VISION COMMUNITY — INTÉGRATION API SASPAY
 * Documentation officielle : https://docs.saspay.me/
 */

function saspay_get_secret_key(): string {
    $envKey = trim((string)(getenv('SASPAY_SECRET_KEY') ?: ''));
    if (!empty($envKey)) {
        return $envKey;
    }
    // Clé de production fournie
    return 'sk_live_tjB-dfgP-8yKTv0Buv0ffLXRccege90M1Oaq0QqkghY';
}

function saspay_is_configured(): bool {
    $key = saspay_get_secret_key();
    return !empty($key) && (str_starts_with($key, 'sk_live_') || str_starts_with($key, 'sk_test_'));
}

/**
 * Retourne le pays ISO, la devise et le montant adapté selon l'indicatif téléphonique
 */
function saspay_get_currency_info(string $prefix): array {
    $prefixMap = [
        '+225' => ['country' => 'CI', 'currency' => 'XOF', 'amount' => '5900.00'], // Côte d'Ivoire
        '+237' => ['country' => 'CM', 'currency' => 'XAF', 'amount' => '5900.00'], // Cameroun
        '+221' => ['country' => 'SN', 'currency' => 'XOF', 'amount' => '5900.00'], // Sénégal
        '+229' => ['country' => 'BJ', 'currency' => 'XOF', 'amount' => '5900.00'], // Bénin
        '+226' => ['country' => 'BF', 'currency' => 'XOF', 'amount' => '5900.00'], // Burkina Faso
        '+243' => ['country' => 'CD', 'currency' => 'CDF', 'amount' => '25000.00'], // RDC
        '+242' => ['country' => 'CG', 'currency' => 'XAF', 'amount' => '5900.00'], // Congo
        '+223' => ['country' => 'ML', 'currency' => 'XOF', 'amount' => '5900.00'], // Mali
        '+228' => ['country' => 'TG', 'currency' => 'XOF', 'amount' => '5900.00'], // Togo
        '+224' => ['country' => 'GN', 'currency' => 'GNF', 'amount' => '85000.00'], // Guinée
        '+241' => ['country' => 'GA', 'currency' => 'XAF', 'amount' => '5900.00'], // Gabon
        '+227' => ['country' => 'NE', 'currency' => 'XOF', 'amount' => '5900.00'], // Niger
        '+33'  => ['country' => 'FR', 'currency' => 'EUR', 'amount' => '9.00'],     // France
        '+32'  => ['country' => 'BE', 'currency' => 'EUR', 'amount' => '9.00'],     // Belgique
        '+41'  => ['country' => 'CH', 'currency' => 'EUR', 'amount' => '9.00'],     // Suisse
    ];

    return $prefixMap[$prefix] ?? ['country' => 'CI', 'currency' => 'XOF', 'amount' => '5900.00'];
}

/**
 * Création d'une session de paiement hébergée SasPay (Checkout Session)
 * POST https://api.saspay.me/api/v1/checkout-sessions/
 */
function saspay_create_checkout_session(array $options): array {
    $apiKey = saspay_get_secret_key();
    if (empty($apiKey)) {
        return [
            'success' => false,
            'error'   => "Clé secrète SasPay non configurée."
        ];
    }

    $url = 'https://api.saspay.me/api/v1/checkout-sessions/';

    $payload = [
        'amount'         => (string)($options['amount'] ?? '9.00'),
        'currency'       => strtoupper((string)($options['currency'] ?? 'EUR')),
        'description'    => (string)($options['description'] ?? 'Adhésion One Vision Community (9€/mois)'),
        'customer_email' => (string)($options['customer_email'] ?? ''),
        'customer_name'  => (string)($options['customer_name'] ?? 'Membre One Vision'),
        'return_url'     => (string)($options['return_url'] ?? ''),
        'metadata'       => $options['metadata'] ?? []
    ];

    if (!empty($options['country'])) {
        $payload['country'] = strtoupper((string)$options['country']);
    }

    if (!empty($options['customer_phone'])) {
        $payload['customer_phone'] = (string)$options['customer_phone'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curlErr)) {
        return [
            'success' => false,
            'error'   => "Erreur de connexion SasPay : " . ($curlErr ?: 'inconnue')
        ];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['data']['checkout_url'])) {
        return [
            'success'      => true,
            'id'           => $data['data']['id'] ?? '',
            'slug'         => $data['data']['slug'] ?? '',
            'checkout_url' => $data['data']['checkout_url'],
            'amount'       => (float)($data['data']['amount'] ?? $payload['amount']),
            'currency'     => $data['data']['currency'] ?? $payload['currency'],
            'status'       => $data['data']['status'] ?? 'PENDING',
            'raw'          => $data['data']
        ];
    }

    // Gestion des messages d'erreurs SasPay
    $errMsg = 'Échec de création de la session SasPay';
    if (!empty($data['error'])) {
        if (is_array($data['error'])) {
            $parts = [];
            foreach ($data['error'] as $field => $msg) {
                $val = is_array($msg) ? implode(', ', $msg) : $msg;
                $parts[] = "$field: $val";
            }
            $errMsg = implode(' | ', $parts);
        } else {
            $errMsg = (string)$data['error'];
        }
    } elseif (!empty($data['message'])) {
        $errMsg = (string)$data['message'];
    }

    return [
        'success'   => false,
        'http_code' => $httpCode,
        'error'     => $errMsg,
        'raw'       => $data
    ];
}

/**
 * Vérification du statut d'une session de checkout auprès de SasPay
 * GET https://api.saspay.me/api/v1/checkout-sessions/{id}/status/
 */
function saspay_verify_checkout_session(string $sessionId): array {
    $apiKey = saspay_get_secret_key();
    if (empty($apiKey) || empty($sessionId)) {
        return [
            'success' => false,
            'error'   => "Identifiant ou clé manquante"
        ];
    }

    $url = 'https://api.saspay.me/api/v1/checkout-sessions/' . urlencode($sessionId) . '/status/';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'error'   => "Erreur vérification SasPay : " . ($curlErr ?: 'inconnue')
        ];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['data'])) {
        $status = strtoupper($data['data']['status'] ?? 'PENDING');
        $txStatus = strtoupper($data['data']['transaction_status'] ?? '');
        $isPaid = ($status === 'PAID' || $txStatus === 'SUCCESS');

        return [
            'success'            => true,
            'is_paid'            => $isPaid,
            'status'             => $status,
            'transaction_status' => $txStatus,
            'transaction_id'     => $data['data']['transaction_id'] ?? null,
            'data'               => $data['data']
        ];
    }

    return [
        'success'   => false,
        'http_code' => $httpCode,
        'error'     => $data['error']['message'] ?? 'Session introuvable',
        'raw'       => $data
    ];
}

/**
 * Vérification de la signature HMAC SHA-256 du Webhook SasPay
 * Header: X-Webhook-Signature, X-Webhook-Timestamp
 */
function saspay_verify_webhook_signature(string $rawPayload, ?string $signature, ?string $timestamp): bool {
    $secret = trim((string)(getenv('SASPAY_WEBHOOK_SECRET') ?: ''));
    if (empty($secret)) {
        // Si aucun secret de webhook n'est configuré, autoriser par défaut
        return true;
    }

    if (empty($signature) || empty($timestamp)) {
        return false;
    }

    // Tolérance d'âge max de 5 minutes (300 secondes)
    $currentTime = time();
    if (abs($currentTime - (int)$timestamp) > 300) {
        return false;
    }

    $signed = "{$timestamp}.{$rawPayload}";
    $expected = hash_hmac('sha256', $signed, $secret);

    return hash_equals($expected, strtolower($signature));
}
