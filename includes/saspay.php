<?php
/**
 * ONE VISION COMMUNITY — MODULE D'INTÉGRATION SASPAY (CARTE BANCAIRE & MOBILE MONEY)
 * Documentation officielle : https://docs.saspay.me
 */

require_once __DIR__ . '/config.php';

/**
 * Récupère le header d'authentification (Clé secrète Bearer ou OAuth Client Credentials)
 */
function saspay_get_auth_header(): string {
    if (!empty(SASPAY_API_KEY)) {
        return 'Bearer ' . SASPAY_API_KEY;
    }

    if (!empty(SASPAY_CLIENT_ID) && !empty(SASPAY_CLIENT_SECRET)) {
        static $cachedToken = null;
        static $tokenExpiry = 0;

        if ($cachedToken !== null && time() < $tokenExpiry) {
            return 'Bearer ' . $cachedToken;
        }

        $tokenUrl = rtrim(SASPAY_API_URL, '/') . '/auth/token/';
        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => SASPAY_CLIENT_ID . ':' . SASPAY_CLIENT_SECRET,
            CURLOPT_POSTFIELDS     => http_build_query(['grant_type' => 'client_credentials']),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http >= 200 && $http < 300 && $raw) {
            $json = json_decode($raw, true);
            if (!empty($json['access_token'])) {
                $cachedToken = $json['access_token'];
                $tokenExpiry = time() + (int)($json['expires_in'] ?? 3600) - 60;
                return 'Bearer ' . $cachedToken;
            }
        }

        return 'Basic ' . base64_encode(SASPAY_CLIENT_ID . ':' . SASPAY_CLIENT_SECRET);
    }

    return 'Bearer none';
}

/**
 * Exécute un appel HTTP cURL vers l'API SasPay (Production ou Sandbox)
 */
function saspay_request(string $endpoint, string $method = 'GET', ?array $data = null, ?string $idempotencyKey = null): array {
    $url = rtrim(SASPAY_API_URL, '/') . '/' . ltrim($endpoint, '/');
    
    $headers = [
        'Authorization: ' . saspay_get_auth_header(),
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if (!empty(SASPAY_MERCHANT_CODE)) {
        $headers[] = 'X-Merchant-Code: ' . SASPAY_MERCHANT_CODE;
    }

    if (!empty($idempotencyKey)) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

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
        'callback_url'   => defined('SASPAY_WEBHOOK_URL') ? SASPAY_WEBHOOK_URL : '',
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
            'session_id'   => $session['id'] ?? $session['session_id'] ?? '',
            'checkout_url' => $session['checkout_url'] ?? '',
            'slug'         => $session['slug'] ?? '',
            'status'       => strtoupper($session['status'] ?? 'PENDING'),
            'raw'          => $session
        ];
    }

    return [
        'success' => false,
        'error'   => $res['error'] ?? 'Impossible d\'initier la session de paiement SasPay.'
    ];
}

/**
 * Résout le code réseau et l'indicatif pays officiel SasPay à partir des entrées
 * Catalogue : https://docs.saspay.me/api-reference/reference/formats
 */
function saspay_resolve_network_and_country(string $countryInput, string $operatorInput): array {
    $c = strtolower(trim($countryInput));
    $op = strtolower(trim($operatorInput));

    // Détection ISO Pays
    $countryIso = 'CI'; // Côte d'Ivoire par défaut
    if (strpos($c, 'cameroun') !== false || $c === 'cm') $countryIso = 'CM';
    elseif (strpos($c, 'ivoire') !== false || $c === 'ci') $countryIso = 'CI';
    elseif (strpos($c, 'bénin') !== false || strpos($c, 'benin') !== false || $c === 'bj') $countryIso = 'BJ';
    elseif (strpos($c, 'sénégal') !== false || strpos($c, 'senegal') !== false || $c === 'sn') $countryIso = 'SN';
    elseif (strpos($c, 'burkina') !== false || $c === 'bf') $countryIso = 'BF';
    elseif (strpos($c, 'togo') !== false || $c === 'tg') $countryIso = 'TG';
    elseif (strpos($c, 'mali') !== false || $c === 'ml') $countryIso = 'ML';
    elseif (strpos($c, 'congo') !== false || strpos($c, 'rdc') !== false || $c === 'cd') $countryIso = 'CD';
    elseif (strpos($c, 'niger') !== false || $c === 'ne') $countryIso = 'NE';
    elseif (strpos($c, 'ghana') !== false || $c === 'gh') $countryIso = 'GH';

    // Détection Code Réseau selon le pays
    $network = 'mtn_' . strtolower($countryIso);

    if ($countryIso === 'CI') {
        if (strpos($op, 'wave') !== false) $network = 'wave_ci';
        elseif (strpos($op, 'orange') !== false) $network = 'orange_ci';
        elseif (strpos($op, 'moov') !== false) $network = 'moov_ci';
        else $network = 'mtn_ci';
    } elseif ($countryIso === 'SN') {
        if (strpos($op, 'wave') !== false) $network = 'wave_sn';
        elseif (strpos($op, 'free') !== false) $network = 'freemoney_sn';
        elseif (strpos($op, 'wizall') !== false) $network = 'wizall_sn';
        else $network = 'orange_sn';
    } elseif ($countryIso === 'CM') {
        if (strpos($op, 'orange') !== false) $network = 'orange_cm';
        else $network = 'mtn_cm';
    } elseif ($countryIso === 'BJ') {
        if (strpos($op, 'celtiis') !== false) $network = 'celtiis_bj';
        elseif (strpos($op, 'moov') !== false) $network = 'moov_bj';
        else $network = 'mtn_bj';
    } elseif ($countryIso === 'BF') {
        if (strpos($op, 'orange') !== false) $network = 'orange_bf';
        else $network = 'moov_bf';
    } elseif ($countryIso === 'TG') {
        if (strpos($op, 'togo') !== false) $network = 'togocel';
        else $network = 'moov_tg';
    } elseif ($countryIso === 'ML') {
        if (strpos($op, 'orange') !== false) $network = 'orange_ml';
        elseif (strpos($op, 'mobi') !== false) $network = 'mobi_cash_ml';
        else $network = 'moov_ml';
    } elseif ($countryIso === 'CD') {
        if (strpos($op, 'orange') !== false) $network = 'orange_cd';
        elseif (strpos($op, 'airtel') !== false) $network = 'airtel_cd';
        else $network = 'vodacom_cd';
    }

    return [
        'country' => $countryIso,
        'network' => $network
    ];
}

/**
 * Initie un paiement SoftPay (Mobile Money Push ou Redirection)
 * Doc officielle : https://docs.saspay.me/api-reference/payments/softpay
 */
function saspay_initiate_softpay(array $params): array {
    $resolved = saspay_resolve_network_and_country(
        $params['country'] ?? 'CI',
        $params['operator'] ?? 'mtn'
    );

    $customerName = trim($params['customer_name'] ?? '');
    $nameParts = explode(' ', $customerName, 2);
    $firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Client';
    $lastName = !empty($nameParts[1]) ? $nameParts[1] : 'OneVision';

    $phone = preg_replace('/[^\d+]/', '', $params['phone_number'] ?? $params['customer_phone'] ?? '');

    $network = $params['network'] ?? $resolved['network'];
    $country = $params['country_iso'] ?? $resolved['country'];

    $payload = [
        'amount'      => number_format((float)($params['amount'] ?? 9.00), 2, '.', ''),
        'currency'    => $params['currency'] ?? 'XOF',
        'country'     => $country,
        'network'     => $network,
        'description' => $params['description'] ?? 'Adhésion One Vision Community',
        'customer'    => [
            'email'      => $params['customer_email'] ?? 'contact@onevision.community',
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'phone'      => $phone
        ],
        'metadata'    => $params['metadata'] ?? []
    ];

    if (!empty($params['return_url'])) {
        $payload['return_url'] = $params['return_url'];
    }

    // Génération UUID idempotence pour sécuriser la transaction
    $idempotencyKey = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $res = saspay_request('/payments/softpay/', 'POST', $payload, $idempotencyKey);

    if ($res['success']) {
        $data = $res['data'] ?? [];
        $paymentId = $data['id'] ?? $data['payment_id'] ?? $data['transaction_id'] ?? '';
        $checkoutUrl = $data['checkout_url'] ?? '';

        return [
            'success'      => true,
            'status'       => 'PENDING',
            'payment_id'   => $paymentId,
            'checkout_url' => $checkoutUrl,
            'message'      => !empty($checkoutUrl) 
                ? 'Redirection vers la page sécurisée de paiement.' 
                : 'Demande de paiement transmise. En attente de validation sur votre téléphone.',
            'raw'          => $data
        ];
    }

    return [
        'success' => false,
        'error'   => $res['error'] ?? 'Échec lors de l\'initiation du paiement SasPay.'
    ];
}

/**
 * Wrapper de compatibilité pour l'appel C2B Mobile Money
 */
function saspay_initiate_c2b(array $params): array {
    $res = saspay_initiate_softpay($params);
    if ($res['success']) {
        return [
            'success'             => true,
            'status'              => 'PENDING',
            'checkout_request_id' => $res['payment_id'] ?? '',
            'checkout_url'        => $res['checkout_url'] ?? '',
            'message'             => $res['message'] ?? 'Demande transmise avec succès.',
            'raw'                 => $res['raw'] ?? []
        ];
    }

    // Si softpay échoue, tentative alternative via session de checkout
    $sessionRes = saspay_create_checkout_session($params);
    if ($sessionRes['success']) {
        return [
            'success'             => true,
            'status'              => 'PENDING',
            'checkout_request_id' => $sessionRes['session_id'],
            'session_id'          => $sessionRes['session_id'],
            'checkout_url'        => $sessionRes['checkout_url'] ?? '',
            'message'             => 'Session de paiement ouverte. En attente de confirmation.',
            'raw'                 => $sessionRes['raw']
        ];
    }

    return [
        'success' => false,
        'error'   => $res['error'] ?? $sessionRes['error'] ?? 'Échec lors de l\'envoi de la demande de paiement.'
    ];
}

/**
 * Récupère le détail et le statut d'une session de Checkout
 */
function saspay_get_checkout_session(string $sessionId): array {
    return saspay_request('/checkout-sessions/' . urlencode($sessionId) . '/', 'GET');
}

/**
 * Vérifie l'état réel d'un paiement auprès de SasPay
 * Endpoint officiel SasPay : GET /payments/{payment_id}/verify/
 * Doc : https://docs.saspay.me/api-reference/payments/verify
 */
function saspay_check_transaction_status(string $paymentId, string $orderNumber = ''): array {
    if (empty($paymentId)) {
        return ['success' => false, 'status' => 'PENDING', 'message' => 'Identifiant manquant'];
    }

    // 1. Appel du endpoint officiel de vérification en direct
    $res = saspay_request('/payments/' . urlencode($paymentId) . '/verify/', 'GET');
    
    // 2. Si non trouvé sous /payments/, vérifier s'il s'agit d'une session de checkout
    if (!$res['success']) {
        $res = saspay_get_checkout_session($paymentId);
    }

    if ($res['success'] && !empty($res['data'])) {
        $d = $res['data'];
        $rawStatus = strtoupper($d['status'] ?? $d['payment_status'] ?? '');

        // Statut confirmé selon la doc SasPay (SUCCESS / PAID)
        if (in_array($rawStatus, ['SUCCESS', 'PAID', 'COMPLETED'])) {
            return [
                'success'        => true,
                'status'         => 'PAID',
                'transaction_id' => $d['id'] ?? $d['reference'] ?? $paymentId,
                'raw'            => $d
            ];
        }

        // Statut échec ou annulé selon la doc SasPay
        if (in_array($rawStatus, ['FAILED', 'CANCELLED', 'REJECTED'])) {
            return [
                'success' => false,
                'status'  => 'FAILED',
                'raw'     => $d
            ];
        }

        // Statut expiré
        if (in_array($rawStatus, ['EXPIRED', 'TIMEOUT'])) {
            return [
                'success' => false,
                'status'  => 'EXPIRED',
                'raw'     => $d
            ];
        }
    }

    return [
        'success' => true,
        'status'  => 'PENDING'
    ];
}
