<?php
/**
 * Simple PHP mock server for the Petstore API.
 * Used for demonstrating alama/arazzo-runner with a real API.
 *
 * Run with: php -S localhost:8080 petstore-mock.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// In-memory pet store
$pets = [
    ['id' => 1, 'name' => 'Buddy', 'status' => 'available', 'category' => 'dog'],
    ['id' => 2, 'name' => 'Whiskers', 'status' => 'available', 'category' => 'cat'],
    ['id' => 3, 'name' => 'Max', 'status' => 'sold', 'category' => 'dog'],
];

// Login endpoint - returns plain string (matches OpenAPI spec)
if ($uri === '/v3/user/login' && $method === 'GET') {
    $username = $_GET['username'] ?? '';
    $password = $_GET['password'] ?? '';

    if ($username && $password) {
        $token = base64_encode($username . ":" . $password);
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $token;
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid username/password supplied']);
    }
    exit;
}

// Find pets by status
if ($uri === '/v3/pet/findByStatus' && $method === 'GET') {
    $status = $_GET['status'] ?? 'available';
    $filtered = array_values(array_filter($pets, fn($p) => $p['status'] === $status));
    http_response_code(200);
    echo json_encode($filtered);
    exit;
}

// Get pet by ID
if (preg_match('#^/v3/pet/(\d+)$#', $uri) && $method === 'GET') {
    $petId = (int)$matches[1];
    if (isset($pets[$petId])) {
        http_response_code(200);
        echo json_encode($pets[$petId]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Pet not found']);
    }
    exit;
}

// Default 404
http_response_code(404);
echo json_encode(['error' => 'Not found']);
