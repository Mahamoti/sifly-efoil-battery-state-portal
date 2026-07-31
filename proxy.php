<?php
/**
 * CORS proxy for my.sifly.global (auth + api).
 * Same-origin browser → this script → SiFly API.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Sifly-Authorization');
    http_response_code(204);
    exit;
}

$endpoint = $_GET['endpoint'] ?? 'api';
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if (!in_array($endpoint, ['auth', 'api', 'ping'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid endpoint. Use auth, api, or ping.']);
    exit;
}

/**
 * Apache/Plesk/FastCGI often does not expose Authorization to PHP.
 * Read from every common source, plus X-Sifly-Authorization fallback.
 */
function readAuthorizationHeader(): array
{
    $candidates = [
        'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
        'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
        'Authorization' => $_SERVER['Authorization'] ?? null,
        'HTTP_X_SIFLY_AUTHORIZATION' => $_SERVER['HTTP_X_SIFLY_AUTHORIZATION'] ?? null,
    ];

    foreach ($candidates as $source => $value) {
        if (is_string($value) && trim($value) !== '') {
            return ['value' => trim($value), 'source' => $source];
        }
    }

    $headerFns = [];
    if (function_exists('getallheaders')) {
        $headerFns[] = 'getallheaders';
    }
    if (function_exists('apache_request_headers')) {
        $headerFns[] = 'apache_request_headers';
    }

    foreach ($headerFns as $fn) {
        $headers = $fn();
        if (!is_array($headers)) {
            continue;
        }
        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);
            if (in_array($lower, ['authorization', 'x-sifly-authorization'], true) && trim((string) $value) !== '') {
                return ['value' => trim((string) $value), 'source' => $fn . ':' . $lower];
            }
        }
    }

    return ['value' => '', 'source' => 'none'];
}

function maskAuth(string $auth): string
{
    if ($auth === '') {
        return '(none)';
    }
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        $token = $m[1];
        if (strlen($token) <= 24) {
            return 'Bearer ***';
        }
        return 'Bearer ' . substr($token, 0, 16) . '…' . substr($token, -12);
    }
    return '***';
}

// Debug/ping endpoint — check if Authorization reaches PHP
if ($endpoint === 'ping') {
    $auth = readAuthorizationHeader();
    echo json_encode([
        'ok' => true,
        'php' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'authReceived' => $auth['value'] !== '',
        'authSource' => $auth['source'],
        'authMasked' => maskAuth($auth['value']),
        'hint' => $auth['value'] === ''
            ? 'Authorization header not visible to PHP. Upload .htaccess or use X-Sifly-Authorization header.'
            : 'Authorization header is reaching PHP correctly.',
    ], JSON_PRETTY_PRINT);
    exit;
}

$url = 'https://my.sifly.global/' . $endpoint;
$body = file_get_contents('php://input') ?: '';
$headers = [
    'Accept: application/json, text/plain, */*',
    'Content-Type: application/json',
    'Origin: https://my.sifly.global',
    'Referer: ' . ($endpoint === 'auth'
        ? 'https://my.sifly.global/en/login'
        : 'https://my.sifly.global/en/'),
    'User-Agent: Mozilla/5.0 (compatible; SiFlyBatteryMonitor/1.0)',
];

// Login must never include a Bearer token — expired tokens cause INVALID_TOKEN errors.
$authHeader = '';
$authSource = 'none';
if ($endpoint === 'api') {
    $auth = readAuthorizationHeader();
    $authHeader = $auth['value'];
    $authSource = $auth['source'];
}

header('X-Proxy-Auth-Received: ' . ($authHeader !== '' ? 'yes' : 'no'));
header('X-Proxy-Auth-Source: ' . $authSource);

if ($authHeader !== '') {
    $headers[] = 'Authorization: ' . $authHeader;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream request failed', 'detail' => $error]);
    exit;
}

if ($debug) {
    $decoded = json_decode((string) $response, true);
    echo json_encode([
        'proxy' => [
            'authReceived' => $authHeader !== '',
            'authSource' => $authSource,
            'authMasked' => maskAuth($authHeader),
            'upstreamStatus' => $status,
            'upstreamUrl' => $url,
        ],
        'upstreamRaw' => $decoded ?? $response,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code($status ?: 500);
echo $response !== false ? $response : json_encode(['error' => 'Empty upstream response']);
