<?php

declare(strict_types=1);

// Loopback-only development fixture. Every run has isolated, bounded state.
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$respond = static function (array $body, int $status = 200): never {
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

if ($path === '/health' && $method === 'GET') {
    $respond(['service' => 'live-activities-mock', 'version' => 1]);
}
if ($path === '/scenario' && $method === 'GET') {
    $respond(['steps' => [
        ['operation' => 'start', 'content' => ['title' => 'Preparing your order', 'subtitle' => 'The kitchen has started', 'progress' => 0.0]],
        ['operation' => 'update', 'content' => ['title' => 'On the way', 'subtitle' => 'Your courier is nearby', 'progress' => 0.5]],
        ['operation' => 'update', 'content' => ['title' => 'Delivered', 'subtitle' => 'Enjoy your meal', 'progress' => 1.0]],
        ['operation' => 'end'],
    ]]);
}
$directory = getenv('MOCK_STATE_DIR') ?: __DIR__.'/.state';
if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    $respond(['error' => 'Cannot create mock state directory'], 500);
}
if ($path === '/pending' && $method === 'GET') {
    $pending = is_file($directory.'/pending') ? trim(file_get_contents($directory.'/pending')) : null;
    $respond(['id' => $pending]);
}
if ($path === '/runs' && $method === 'POST') {
    $id = bin2hex(random_bytes(16));
    file_put_contents($directory.'/'.$id.'.json', json_encode(['id' => $id, 'events' => [], 'preview' => ($_GET['preview'] ?? '') === '1'], JSON_THROW_ON_ERROR), LOCK_EX);
    if (($_GET['automated'] ?? '') === '1') {
        file_put_contents($directory.'/pending', $id, LOCK_EX);
    }
    $respond(['id' => $id], 201);
}
if (! preg_match('#^/runs/([a-f0-9]{32})(/events)?$#', (string) $path, $matches)) {
    $respond(['error' => 'Unknown endpoint'], 404);
}
$file = $directory.'/'.$matches[1].'.json';
if (! is_file($file)) {
    $respond(['error' => 'Unknown run'], 404);
}
if ($method === 'GET' && ! isset($matches[2])) {
    $respond(json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR));
}
if ($method !== 'POST' || ! isset($matches[2])) {
    $respond(['error' => 'Method not allowed'], 405);
}
$raw = file_get_contents('php://input', length: 32769);
if (strlen($raw) > 32768) {
    $respond(['error' => 'Event too large'], 413);
}
try {
    $event = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
} catch (JsonException) {
    $respond(['error' => 'Invalid JSON'], 422);
}
if (! is_array($event) || ! in_array($event['operation'] ?? null, ['start', 'update', 'end', 'error'], true)) {
    $respond(['error' => 'Invalid event'], 422);
}
$handle = fopen($file, 'r+');
flock($handle, LOCK_EX);
$run = json_decode(stream_get_contents($handle), true, flags: JSON_THROW_ON_ERROR);
if (count($run['events']) >= 20) {
    flock($handle, LOCK_UN);
    fclose($handle);
    $respond(['error' => 'Run event limit reached'], 409);
}
$run['events'][] = $event;
if (in_array($event['operation'], ['end', 'error'], true) && is_file($directory.'/pending') && trim(file_get_contents($directory.'/pending')) === $matches[1]) {
    unlink($directory.'/pending');
}
rewind($handle);
ftruncate($handle, 0);
fwrite($handle, json_encode($run, JSON_THROW_ON_ERROR));
flock($handle, LOCK_UN);
fclose($handle);
$respond(['accepted' => true], 201);
