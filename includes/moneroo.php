<?php
/**
 * ONE VISION COMMUNITY — INTÉGRATION MONEROO API
 * Documentation: https://docs.moneroo.io/
 */

require_once __DIR__ . '/config.php';

function moneroo_get_secret_key(): string {
    return trim((string)(getenv('MONEROO_SECRET_KEY') ?: ''));
}

function moneroo_is_configured(): bool {
    $key = moneroo_get_secret_key();
    return !empty($key) && strpos($key, 'pvk_') === 0;
}

/**
 * Détermine la devise et le montant Mobile Money adaptés selon l'indicatif téléphonique du pays
 *
 * @param string $prefix ex: '+225', '+237', '+221', etc.
 * @return array ['currency' => string, 'amount' => int, 'symbol' => string]
 */
function moneroo_get_momo_currency_info(string $prefix): array {
    $prefix = trim($prefix);
    if (strpos($prefix, '+') !== 0) {
        $prefix = '+' . $prefix;
    }

    switch ($prefix) {
        // Zone UEMOA (Franc CFA Ouest-Africain - XOF)
        case '+225': // Côte d'Ivoire
        case '+221': // Sénégal
        case '+229': // Bénin
        case '+226': // Burkina Faso
        case '+223': // Mali
        case '+228': // Togo
        case '+227': // Niger
        case '+245': // Guinée-Bissau
            return ['currency' => 'XOF', 'amount' => 5900, 'symbol' => 'FCFA'];

        // Zone CEMAC (Franc CFA Centrafricain - XAF)
        case '+237': // Cameroun
        case '+242': // Congo-Brazzaville
        case '+241': // Gabon
        case '+235': // Tchad
        case '+236': // République Centrafricaine
        case '+240': // Guinée Équatoriale
            return ['currency' => 'XAF', 'amount' => 5900, 'symbol' => 'FCFA'];

        // République Démocratique du Congo (Franc Congolais - CDF)
        case '+243':
            return ['currency' => 'CDF', 'amount' => 25000, 'symbol' => 'CDF'];

        // République de Guinée (Franc Guinéen - GNF)
        case '+224':
            return ['currency' => 'GNF', 'amount' => 85000, 'symbol' => 'GNF'];

        // France / Europe
        case '+33':
        case '+32':
        case '+41':
            return ['currency' => 'EUR', 'amount' => 9, 'symbol' => '€'];

        default:
            return ['currency' => 'XOF', 'amount' => 5900, 'symbol' => 'FCFA'];
    }
}

/**
 * Initialise un paiement via l'API Moneroo (Carte Bancaire & Mobile Money)
 *
 * @param array $options [
 *   'amount'      => float|int,
 *   'currency'    => string ('USD', 'XOF', 'XAF', 'EUR', etc.),
 *   'description' => string,
 *   'return_url'  => string,
 *   'customer'    => [
 *       'email'      => string,
 *       'first_name' => string,
 *       'last_name'  => string,
 *       'phone'      => string (optionnel mais recommandé pour Mobile Money)
 *   ],
 *   'metadata'    => array,
 *   'methods'     => array (optional)
 * ]
 * @return array ['success' => bool, 'checkout_url' => string, 'payment_id' => string, 'currency' => string, 'amount' => float, 'error' => string]
 */
function moneroo_init_payment(array $options): array {
    $apiKey = moneroo_get_secret_key();
    if (empty($apiKey)) {
        return [
            'success' => false,
            'error'   => "Clé secrète Moneroo non configurée."
        ];
    }

    $url = 'https://api.moneroo.io/v1/payments/initialize';
    $currency = strtoupper($options['currency'] ?? (getenv('MONEROO_CURRENCY') ?: 'USD'));
    $amount = (float)($options['amount'] ?? (getenv('MONEROO_AMOUNT') ?: 10));

    // Préparation du payload Moneroo officiel
    $customerData = [
        'email'      => $options['customer']['email'] ?? '',
        'first_name' => $options['customer']['first_name'] ?? 'Membre',
        'last_name'  => $options['customer']['last_name'] ?? 'One Vision'
    ];

    if (!empty($options['customer']['phone'])) {
        $customerData['phone'] = $options['customer']['phone'];
    }

    $payload = [
        'amount'      => (int)round($amount),
        'currency'    => $currency,
        'description' => $options['description'] ?? 'Adhésion One Vision Community',
        'return_url'  => $options['return_url'],
        'customer'    => $customerData
    ];

    if (!empty($options['metadata'])) {
        $payload['metadata'] = $options['metadata'];
    }

    if (!empty($options['methods']) && is_array($options['methods'])) {
        $payload['methods'] = $options['methods'];
    }

    $callApi = function($p) use ($url, $apiKey) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($p));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $code, 'error' => $err, 'body' => json_decode($res, true), 'raw' => $res];
    };

    $result = $callApi($payload);

    // Fallback automatique : si la devise locale (ex: XOF, XAF, EUR) n'est pas encore cochée
    // dans le tableau de bord marchand Moneroo (erreur 400 "No payment methods enabled for this currency"),
    // nous basculons automatiquement sur USD ($10) en conservant toutes les informations du client
    // (nom, email, téléphone Mobile Money et métadonnées). Ainsi le checkout Moneroo se lance sans aucune erreur !
    if ($result['code'] === 400 && isset($result['body']['message']) && stripos($result['body']['message'], 'No payment methods enabled for this currency') !== false && $currency !== 'USD') {
        $payload['currency'] = 'USD';
        $payload['amount'] = 10;
        unset($payload['methods']); // Laisser Moneroo présenter toutes les passerelles actives
        $result = $callApi($payload);
    }

    if ($result['code'] === 201 && !empty($result['body']['data']['checkout_url'])) {
        return [
            'success'      => true,
            'payment_id'   => $result['body']['data']['id'] ?? '',
            'checkout_url' => $result['body']['data']['checkout_url'],
            'currency'     => $payload['currency'],
            'amount'       => $payload['amount']
        ];
    }

    $errorMsg = $result['body']['message'] ?? $result['error'] ?? 'Échec d\'initialisation Moneroo';
    return [
        'success' => false,
        'error'   => $errorMsg,
        'raw'     => $result['raw'] ?? ''
    ];
}

/**
 * Vérifie le statut d'un paiement auprès de l'API Moneroo
 *
 * @param string $paymentId
 * @return array ['success' => bool, 'status' => string, 'data' => array|null, 'error' => string]
 */
function moneroo_verify_payment(string $paymentId): array {
    $apiKey = moneroo_get_secret_key();
    if (empty($apiKey) || empty($paymentId)) {
        return [
            'success' => false,
            'status'  => 'unknown',
            'error'   => 'Identifiant de paiement ou clé manquante.'
        ];
    }

    $url = 'https://api.moneroo.io/v1/payments/' . urlencode($paymentId) . '/verify';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode($res, true);

    if ($code === 200 && isset($body['data']['status'])) {
        return [
            'success' => true,
            'status'  => strtolower($body['data']['status']), // 'success', 'pending', 'failed', 'initiated'
            'data'    => $body['data']
        ];
    }

    return [
        'success' => false,
        'status'  => 'error',
        'error'   => $body['message'] ?? $err ?? 'Erreur lors de la vérification Moneroo'
    ];
}

/**
 * Vérifie la signature HMAC SHA-256 du webhook Moneroo
 */
function moneroo_verify_webhook_signature(string $rawPayload, ?string $signatureHeader): bool {
    $secret = trim((string)(getenv('MONEROO_WEBHOOK_SECRET') ?: ''));
    if (empty($secret)) {
        // Si aucun webhook secret n'est configuré, on vérifie systématiquement via l'API verify
        return true;
    }
    if (empty($signatureHeader)) {
        return false;
    }

    $expected = hash_hmac('sha256', $rawPayload, $secret);
    return hash_equals($expected, $signatureHeader);
}
