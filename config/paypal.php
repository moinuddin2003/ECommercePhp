<?php
/**
 * config/paypal.php
 * ------------------------------------------------
 * PayPal SANDBOX credentials. Get these from
 * https://developer.paypal.com -> Apps & Credentials -> Sandbox
 * Replace the placeholders below with your own sandbox app's
 * Client ID and Secret. Never put LIVE credentials in code you
 * might commit to a public repo.
 */

define('PAYPAL_MODE', 'sandbox');
define('PAYPAL_CLIENT_ID', 'YOUR_SANDBOX_CLIENT_ID');
define('PAYPAL_CLIENT_SECRET', 'YOUR_SANDBOX_CLIENT_SECRET');
define('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com');

/**
 * Gets an OAuth2 access token from PayPal using the client
 * credentials grant. Needed before calling any Orders API endpoint.
 * Returns the token string, or null if the request failed.
 */
function paypalGetAccessToken()
{
    if (!function_exists('curl_init')) {
        // The cURL extension isn't enabled in PHP. In XAMPP/WAMP/Laragon,
        // open php.ini and uncomment the line "extension=curl", then restart.
        error_log('PayPal error: the PHP curl extension is not enabled.');
        return null;
    }

    $ch = curl_init(PAYPAL_BASE_URL . '/v1/oauth2/token');

    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Makes an authenticated JSON request to a PayPal API endpoint.
 * Returns [statusCode, decodedBody].
 */
function paypalApiRequest($method, $path, $accessToken, $body = null)
{
    $ch = curl_init(PAYPAL_BASE_URL . $path);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$httpCode, json_decode($response, true)];
}