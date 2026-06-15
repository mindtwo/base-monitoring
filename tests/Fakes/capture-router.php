<?php

declare(strict_types=1);

/**
 * Router for the built-in PHP test server: captures every request (method,
 * URI, headers, raw body) into M2_CAPTURE_FILE and answers per path.
 */
$headers = [];

foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $name = str_replace('_', '-', strtolower(substr($key, 5)));
        $headers[$name] = is_string($value) ? $value : '';
    }
}

if (isset($_SERVER['CONTENT_TYPE'])) {
    $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
}

file_put_contents((string) getenv('M2_CAPTURE_FILE'), json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'body' => (string) file_get_contents('php://input'),
]));

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

header('Content-Type: application/json');

if ($path === '/fail-500') {
    http_response_code(500);
    echo '{"message":"server exploded"}';

    return;
}

if ($path === '/unauthorized') {
    http_response_code(401);
    echo '{"message":"unauthorized"}';

    return;
}

http_response_code(200);
echo '{"status":"received"}';
