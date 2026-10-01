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
 * Documentation officielle : https://docs.saspay.me/api-reference/payments/checkout-create
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
        'amount'         => (string)($options['amount'] ?? '5900.00'),
        'currency'       => strtoupper((string)($options['currency'] ?? 'XOF')),
        'description'    => (string)($options['description'] ?? 'Adhésion One Vision Community'),
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
    if (!is_array($data)) {
        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => "Réponse SasPay invalide."
        ];
    }

    // SasPay peut retourner les données à la racine ou sous la clé 'data'
    $resData = (!empty($data['data']) && is_array($data['data'])) ? $data['data'] : $data;
    $checkoutUrl = $resData['checkout_url'] ?? '';

    if ($httpCode >= 200 && $httpCode < 300 && !empty($checkoutUrl)) {
        return [
            'success'      => true,
            'id'           => $resData['id'] ?? '',
            'slug'         => $resData['slug'] ?? '',
            'checkout_url' => $checkoutUrl,
            'amount'       => (float)($resData['amount'] ?? $payload['amount']),
            'currency'     => $resData['currency'] ?? $payload['currency'],
            'status'       => $resData['status'] ?? 'PENDING',
            'raw'          => $resData
        ];
    }

    // Gestion détaillée des messages d'erreurs SasPay
    $errMsg = 'Échec de création de la session SasPay';
    if (!empty($data['error'])) {
        if (is_array($data['error'])) {
            $parts = [];
            foreach ($data['error'] as $field => $msg) {
                $val = is_array($msg) ? implode(', ', $msg) : (is_string($msg) ? $msg : json_encode($msg));
                $parts[] = "$field: $val";
            }
            $errMsg = implode(' | ', $parts);
        } else {
            $errMsg = (string)$data['error'];
        }
    } elseif (!empty($data['message'])) {
        $errMsg = (string)$data['message'];
    } elseif (!empty($data['detail'])) {
        $errMsg = (string)$data['detail'];
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
 * Documentation officielle : https://docs.saspay.me/api-reference/payments/checkout-status
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
    if (!is_array($data)) {
        return [
            'success' => false,
            'error'   => "Format de réponse SasPay invalide"
        ];
    }

    $resData = (!empty($data['data']) && is_array($data['data'])) ? $data['data'] : $data;

    if ($httpCode >= 200 && $httpCode < 300 && is_array($resData)) {
        $status = strtoupper($resData['status'] ?? 'PENDING');
        $txStatus = strtoupper($resData['transaction_status'] ?? '');
        $isPaid = ($status === 'PAID' || $status === 'SUCCESS' || $txStatus === 'SUCCESS');

        return [
            'success'            => true,
            'is_paid'            => $isPaid,
            'status'             => $status,
            'transaction_status' => $txStatus,
            'transaction_id'     => $resData['transaction_id'] ?? null,
            'transaction_ref'    => $resData['transaction_reference'] ?? null,
            'data'               => $resData
        ];
    }

    $errMsg = 'Session introuvable';
    if (!empty($data['error'])) {
        $errMsg = is_array($data['error']) ? json_encode($data['error']) : (string)$data['error'];
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
 * Vérification directe d'une transaction de paiement auprès de SasPay
 * Documentation officielle : https://docs.saspay.me/quickstart#4-verifiez-le-resultat
 * GET https://api.saspay.me/api/v1/payments/{id}/verify/
 */
function saspay_verify_payment(string $paymentId): array {
    $apiKey = saspay_get_secret_key();
    if (empty($apiKey) || empty($paymentId)) {
        return [
            'success' => false,
            'error'   => "Identifiant de paiement ou clé manquante"
        ];
    }

    $url = 'https://api.saspay.me/api/v1/payments/' . urlencode($paymentId) . '/verify/';

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
    if (!is_array($data)) {
        return [
            'success' => false,
            'error'   => "Format de réponse SasPay invalide"
        ];
    }

    $resData = (!empty($data['data']) && is_array($data['data'])) ? $data['data'] : $data;

    if ($httpCode >= 200 && $httpCode < 300 && is_array($resData)) {
        $status = strtoupper($resData['status'] ?? 'PENDING');
        $isPaid = ($status === 'SUCCESS' || $status === 'PAID');

        return [
            'success'    => true,
            'is_paid'    => $isPaid,
            'status'     => $status,
            'net_amount' => $resData['net_amount'] ?? '',
            'currency'   => $resData['currency'] ?? '',
            'data'       => $resData
        ];
    }

    return [
        'success'   => false,
        'http_code' => $httpCode,
        'error'     => $data['message'] ?? 'Paiement introuvable',
        'raw'       => $data
    ];
}

/**
 * Vérification de la signature HMAC SHA-256 du Webhook SasPay
 * Header: X-Webhook-Signature, X-Webhook-Timestamp
 * Documentation : https://docs.saspay.me/api-reference/webhooks#securite-verifier-la-signature
 */
function saspay_verify_webhook_signature(string $rawPayload, ?string $signature, ?string $timestamp): bool {
    $secret = trim((string)(getenv('SASPAY_WEBHOOK_SECRET') ?: ''));
    if (empty($secret)) {
        // Si aucun secret de webhook n'est configuré dans .env, accepter pour ne pas bloquer les transactions
        return true;
    }

    if (empty($signature) || empty($timestamp)) {
        return false;
    }

    // Tolérance d'âge max de 5 minutes (300 secondes) comme spécifié par la doc SasPay
    $currentTime = time();
    if (abs($currentTime - (int)$timestamp) > 300) {
        return false;
    }

    $signed = "{$timestamp}.{$rawPayload}";
    $expected = hash_hmac('sha256', $signed, $secret);

    return hash_equals($expected, strtolower($signature));
}

