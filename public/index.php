<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ProxmoxClient.php';

$sessionPath = __DIR__ . '/../var/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);
session_start();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/api/')) {
    handleApi($path);
    exit;
}

readfile(__DIR__ . '/app.html');

function handleApi(string $path): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($input)) {
            $input = [];
        }

        if ($path === '/api/session' && $method === 'GET') {
            respond([
                'connected' => isset($_SESSION['proxmox']),
                'readOnly' => (bool) ($_SESSION['readOnly'] ?? false),
                'baseUrl' => $_SESSION['proxmox']['baseUrl'] ?? null,
            ]);
        }

        if ($path === '/api/connect' && $method === 'POST') {
            $_SESSION['proxmox'] = ProxmoxClient::authenticate($input);
            $_SESSION['readOnly'] = (bool) ($input['readOnly'] ?? false);
            respond(['connected' => true, 'readOnly' => $_SESSION['readOnly']]);
        }

        if ($path === '/api/logout' && $method === 'POST') {
            $_SESSION = [];
            session_destroy();
            respond(['connected' => false]);
        }

        $client = requireClient();

        if ($path === '/api/resources' && $method === 'GET') {
            respond(['resources' => $client->listResources()]);
        }

        if ($path === '/api/startup' && $method === 'PUT') {
            if ((bool) ($_SESSION['readOnly'] ?? false)) {
                http_response_code(403);
                respond(['error' => 'Mode lecture seule actif.']);
            }

            $changes = $input['changes'] ?? [];
            if (!is_array($changes)) {
                throw new InvalidArgumentException('Payload de modifications invalide.');
            }

            respond($client->updateStartup($changes));
        }

        http_response_code(404);
        respond(['error' => 'Endpoint inconnu.']);
    } catch (Throwable $exception) {
        http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
        respond(['error' => $exception->getMessage()]);
    }
}

function requireClient(): ProxmoxClient
{
    if (!isset($_SESSION['proxmox']) || !is_array($_SESSION['proxmox'])) {
        http_response_code(401);
        throw new RuntimeException('Session Proxmox non connectée.');
    }

    return new ProxmoxClient($_SESSION['proxmox']);
}

function respond(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
