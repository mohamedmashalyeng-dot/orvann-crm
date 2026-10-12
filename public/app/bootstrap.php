<?php
// Shared setup for every API request: configuration, JSON answers, login session, CORS.
// Plain PHP 7 syntax throughout: the host's PHP version may vary.

ini_set('display_errors', '0');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

const CRM_SESSION_COOKIE = 'crm_session';
const CRM_SESSION_SECONDS = 604800; // 7 days
const CRM_MAX_BODY_BYTES = 65536; // room for the longest enquiry the website accepts, in any script

function crm_config()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function crm_settings()
{
    static $settings = null;
    if ($settings === null) {
        $file = __DIR__ . '/settings.php';
        if (!is_file($file)) {
            throw new RuntimeException('app/settings.php is missing: copy settings.example.php and fill it in');
        }
        $settings = require $file;
    }
    return $settings;
}

require __DIR__ . '/leads.php';
require __DIR__ . '/db.php';

// ---------- answers ----------

function crm_json($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function crm_error($message, $status)
{
    crm_json(['error' => $message], $status);
}

set_exception_handler(function ($e) {
    error_log('CRM: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    crm_error('حصل خطأ في السيرفر', 500);
});

// ---------- request ----------

function crm_is_json_request()
{
    $type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    return stripos($type, 'application/json') === 0;
}

/** The JSON object in the request body, or an empty array. */
function crm_body()
{
    $raw = file_get_contents('php://input', false, null, 0, CRM_MAX_BODY_BYTES + 1);
    if (strlen($raw) > CRM_MAX_BODY_BYTES) {
        crm_error('البيانات كبيرة جداً', 413);
    }
    if (trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        crm_error('بيانات غير صالحة', 400);
    }
    return $data;
}

function crm_header($name)
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
}

function crm_is_https()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || crm_header('X-Forwarded-Proto') === 'https';
}

function crm_client_ip()
{
    if (!empty(crm_settings()['trust_proxy']) && crm_header('X-Forwarded-For') !== '') {
        $parts = explode(',', crm_header('X-Forwarded-For'));
        return substr(trim($parts[0]), 0, 60);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
}

/** Compares two secrets without leaking, through timing, how much of them matched. */
function crm_same($a, $b)
{
    return hash_equals(hash('sha256', (string) $a, true), hash('sha256', (string) $b, true));
}

// ---------- CORS (public endpoints only) ----------

/** Lets the customer form on another domain call the public endpoints. */
function crm_public_cors()
{
    $allowed = crm_settings()['allowed_origins'];
    $origin = crm_header('Origin');
    if (in_array('*', $allowed, true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ---------- login session: a signed cookie, nothing stored on the server ----------

function crm_base64url($bytes)
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function crm_sign($data)
{
    return crm_base64url(hash_hmac('sha256', $data, crm_settings()['session_secret'], true));
}

function crm_send_session_cookie($value, $maxAge)
{
    header(
        'Set-Cookie: ' . CRM_SESSION_COOKIE . '=' . $value . '; Max-Age=' . $maxAge . '; Path=/; HttpOnly; SameSite=Lax'
        . (crm_is_https() ? '; Secure' : ''),
        false
    );
}

function crm_start_session($user)
{
    $payload = crm_base64url(json_encode(['u' => $user, 'exp' => time() + CRM_SESSION_SECONDS]));
    crm_send_session_cookie($payload . '.' . crm_sign($payload), CRM_SESSION_SECONDS);
}

/** The logged-in user name, or null. */
function crm_session_user()
{
    $token = isset($_COOKIE[CRM_SESSION_COOKIE]) && is_string($_COOKIE[CRM_SESSION_COOKIE]) ? $_COOKIE[CRM_SESSION_COOKIE] : '';
    $parts = explode('.', $token);
    if (count($parts) !== 2 || $parts[0] === '' || !crm_same($parts[1], crm_sign($parts[0]))) {
        return null;
    }
    $data = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/')), true);
    if (!is_array($data) || !isset($data['exp'], $data['u']) || $data['exp'] < time()) {
        return null;
    }
    return (string) $data['u'];
}

/**
 * Stops unless logged in. Changes must also arrive as JSON: a page on another site cannot
 * send that content type with the visitor's cookie, which rules out forged requests.
 */
function crm_require_login()
{
    $user = crm_session_user();
    if ($user === null) {
        crm_error('لازم تسجل دخول', 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !crm_is_json_request()) {
        crm_error('بيانات غير صالحة', 400);
    }
    return $user;
}
