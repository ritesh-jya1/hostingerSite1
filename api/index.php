<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 102400) {
        respond(400, ['error' => 'Invalid request body.']);
    }

    try {
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        respond(400, ['error' => 'Invalid JSON request body.']);
    }

    if (!is_array($body)) {
        respond(400, ['error' => 'Invalid JSON request body.']);
    }

    return $body;
}

function value(array $body, string $key): string
{
    return isset($body[$key]) && is_string($body[$key]) ? trim($body[$key]) : '';
}

function admin_token(string $password): string
{
    return hash_hmac('sha256', 'knox-gable-admin', $password);
}

function configured_token(): string
{
    return (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
}

function authenticate_admin(string $password): void
{
    $token = configured_token();
    if ($token === '' || !hash_equals(admin_token($password), $token)) {
        respond(401, ['error' => 'Unauthorized.']);
    }
}

function registration_payload(array $body): array
{
    $fullName = value($body, 'fullName');
    $email = value($body, 'email');
    $phone = value($body, 'phone');
    $amazonOrderId = value($body, 'amazonOrderId');

    if ($fullName === '' || $email === '' || $phone === '' || $amazonOrderId === '') {
        respond(400, ['error' => 'All fields are required.']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(400, ['error' => 'Please enter a valid email address.']);
    }

    if (strlen(preg_replace('/\D/', '', $phone)) < 10) {
        respond(400, ['error' => 'Please enter a valid phone number.']);
    }

    return [$fullName, $email, $phone, $amazonOrderId];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = require __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_password'],
        $config['db_name'],
        (int) $config['db_port']
    );
    $db->set_charset('utf8mb4');
    $db->query(
        'CREATE TABLE IF NOT EXISTS registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            amazon_order_id TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX registrations_created_at_idx (created_at)
        )'
    );

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST' && $path === '/api/registrations') {
        [$fullName, $email, $phone, $amazonOrderId] = registration_payload(request_body());
        $statement = $db->prepare(
            'INSERT INTO registrations (full_name, email, phone, amazon_order_id) VALUES (?, ?, ?, ?)'
        );
        $statement->bind_param('ssss', $fullName, $email, $phone, $amazonOrderId);
        $statement->execute();
        $id = $db->insert_id;
        $statement->close();

        $statement = $db->prepare(
            'SELECT id, full_name AS fullName, email, phone, amazon_order_id AS amazonOrderId,
                    created_at AS createdAt FROM registrations WHERE id = ?'
        );
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        respond(201, $row);
    }

    if ($method === 'POST' && $path === '/api/admin/login') {
        $password = value(request_body(), 'password');
        if ($password === '' || !hash_equals($config['admin_password'], $password)) {
            respond(401, ['error' => 'Invalid password.']);
        }
        respond(200, ['token' => admin_token($config['admin_password'])]);
    }

    if ($method === 'GET' && $path === '/api/admin/registrations') {
        authenticate_admin($config['admin_password']);
        $result = $db->query(
            'SELECT id, full_name AS fullName, email, phone, amazon_order_id AS amazonOrderId,
                    created_at AS createdAt FROM registrations ORDER BY created_at DESC'
        );
        respond(200, $result->fetch_all(MYSQLI_ASSOC));
    }

    respond(404, ['error' => 'Not found.']);
} catch (Throwable $error) {
    error_log('Knox & Gable API error: ' . $error->getMessage());
    respond(500, ['error' => 'Something went wrong. Please try again.']);
}
