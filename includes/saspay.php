<?php
/**
 * ONE VISION COMMUNITY — MODULE D'INTÉGRATION SASPAY (CARTE BANCAIRE & MOBILE MONEY)
 * Documentation officielle : https://docs.saspay.me
 */

require_once __DIR__ . '/config.php';

/**
 * Exécute un appel HTTP cURL vers l'API SasPay
 */
function saspay_request(string $endpoint, string $method = 'GET', ?array $data = null): array {
    $url = rtrim(SASPAY_API_URL, '/') . '/' . ltrim($endpoint, '/');
    
    $headers = [
        'Authorization: Bearer ' . SASPAY_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ];

    $methodUpper = strtoupper($method);
    if ($methodUpper === 'POST') {
        $curlOptions[CURLOPT_POST] = true;
        if ($data !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    } elseif ($methodUpper !== 'GET') {
        $curlOptions[CURLOPT_CUSTOMREQUEST] = $methodUpper;
        if ($data !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    }

    curl_setopt_array($ch, $curlOptions);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($rawResponse === false || !empty($curlError)) {
        return [
            'success'   => false,
            'http_code' => $httpCode ?: 500,
            'error'     => 'Erreur de connexion avec la passerelle SasPay : ' . $curlError,
            'data'      => null
        ];
    }

    $json = json_decode($rawResponse, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success'   => true,
            'http_code' => $httpCode,
            'data'      => $json['data'] ?? $json,
            'raw'       => $json
        ];
    }

    $errorMsg = 'Erreur SasPay (' . $httpCode . ')';
    if (is_array($json)) {
        if (!empty($json['error']['detail'])) {
            $errorMsg = $json['error']['detail'];
        } elseif (!empty($json['message'])) {
            $errorMsg = $json['message'];
        } elseif (!empty($json['detail'])) {
            $errorMsg = $json['detail'];
        }
    }

    return [
        'success'   => false,
        'http_code' => $httpCode,
        'error'     => $errorMsg,
        'data'      => $json
    ];
}

/**
 * Crée une session de Checkout hébergé SasPay
 * Permet au client de payer soit par Carte Bancaire soit par Mobile Money
 */
function saspay_create_checkout_session(array $params): array {
    $payload = [
        'amount'         => number_format((float)($params['amount'] ?? 9.00), 2, '.', ''),
        'currency'       => $params['currency'] ?? SASPAY_CURRENCY,
        'description'    => $params['description'] ?? 'Adhésion One Vision Community (9€/mois)',
        'customer_email' => $params['customer_email'] ?? '',
        'customer_name'  => $params['customer_name'] ?? '',
        'customer_phone' => $params['customer_phone'] ?? '',
        'return_url'     => $params['return_url'] ?? '',
        'metadata'       => $params['metadata'] ?? []
    ];

    if (!empty($params['country'])) {
        $payload['country'] = $params['country'];
    }

    $res = saspay_request('/checkout-sessions/', 'POST', $payload);

    if ($res['success']) {
        $session = $res['data'];
        return [
            'success'      => true,
            'session_id'   => $session['id'] ?? '',
            'checkout_url' => $session['checkout_url'] ?? '',
            'slug'         => $session['slug'] ?? '',
            'status'       => $session['status'] ?? 'PENDING',
            'raw'          => $session
        ];
    }

    return [
        'success' => false,
        'error'   => $res['error'] ?? 'Impossible d\'initier la session de paiement SasPay.'
    ];
}

/**
 * Récupère le détail et le statut d'une session de Checkout
 */
function saspay_get_checkout_session(string $sessionId): array {
    return saspay_request('/checkout-sessions/' . urlencode($sessionId) . '/', 'GET');
}

/**
 * Vérifie l'état réel d'une transaction de paiement
 */
function saspay_verify_payment(string $paymentId): array {
    return saspay_request('/payments/' . urlencode($paymentId) . '/verify/', 'GET');
}
