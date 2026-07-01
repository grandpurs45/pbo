<?php

declare(strict_types=1);

require_once __DIR__ . '/StartupConfig.php';

final class ProxmoxClient
{
    private string $baseUrl;
    private bool $verifySsl;
    private ?string $ticket;
    private ?string $csrfToken;
    private ?string $apiTokenHeader;

    /**
     * @param array{baseUrl:string,verifySsl?:bool,ticket?:string|null,csrfToken?:string|null,apiTokenHeader?:string|null} $session
     */
    public function __construct(array $session)
    {
        $this->baseUrl = rtrim($session['baseUrl'], '/');
        $this->verifySsl = (bool) ($session['verifySsl'] ?? true);
        $this->ticket = $session['ticket'] ?? null;
        $this->csrfToken = $session['csrfToken'] ?? null;
        $this->apiTokenHeader = $session['apiTokenHeader'] ?? null;
    }

    /**
     * @return array{baseUrl:string,verifySsl:bool,ticket?:string,csrfToken?:string,apiTokenHeader?:string}
     */
    public static function authenticate(array $input): array
    {
        $host = trim((string) ($input['host'] ?? ''));
        if ($host === '') {
            throw new InvalidArgumentException('Host Proxmox manquant.');
        }

        $baseUrl = self::normalizeBaseUrl($host);
        $verifySsl = (bool) ($input['verifySsl'] ?? true);
        $authMode = (string) ($input['authMode'] ?? 'password');

        if ($authMode === 'token') {
            $user = trim((string) ($input['tokenUser'] ?? ''));
            $tokenId = trim((string) ($input['tokenId'] ?? ''));
            $tokenSecret = trim((string) ($input['tokenSecret'] ?? ''));

            if ($user === '' || $tokenId === '' || $tokenSecret === '') {
                throw new InvalidArgumentException('Utilisateur, Token ID et Secret sont requis.');
            }
            self::assertProxmoxUserHasRealm($user);

            $session = [
                'baseUrl' => $baseUrl,
                'verifySsl' => $verifySsl,
                'apiTokenHeader' => sprintf('PVEAPIToken=%s!%s=%s', $user, $tokenId, $tokenSecret),
            ];

            $client = new self($session);
            $client->request('GET', '/version');

            return $session;
        }

        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Identifiant et mot de passe sont requis.');
        }
        self::assertProxmoxUserHasRealm($username);

        $response = self::rawRequest($baseUrl, $verifySsl, 'POST', '/access/ticket', [
            'username' => $username,
            'password' => $password,
        ]);

        $data = $response['data'] ?? [];
        if (!is_array($data) || empty($data['ticket']) || empty($data['CSRFPreventionToken'])) {
            throw new RuntimeException('Réponse Proxmox invalide pendant l’authentification.');
        }

        return [
            'baseUrl' => $baseUrl,
            'verifySsl' => $verifySsl,
            'ticket' => (string) $data['ticket'],
            'csrfToken' => (string) $data['CSRFPreventionToken'],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listResources(): array
    {
        $resources = $this->request('GET', '/cluster/resources', ['type' => 'vm'])['data'] ?? [];
        if (!is_array($resources)) {
            return [];
        }

        $items = [];
        foreach ($resources as $resource) {
            if (!is_array($resource) || !in_array($resource['type'] ?? '', ['qemu', 'lxc'], true)) {
                continue;
            }

            $type = (string) $resource['type'];
            $node = (string) ($resource['node'] ?? '');
            $vmid = (int) ($resource['vmid'] ?? 0);
            if ($node === '' || $vmid === 0) {
                continue;
            }

            $config = $this->request('GET', sprintf('/nodes/%s/%s/%d/config', rawurlencode($node), $type, $vmid))['data'] ?? [];
            $startup = is_array($config) ? StartupConfig::parse($config['startup'] ?? null) : StartupConfig::parse(null);
            $onboot = is_array($config) ? (int) ($config['onboot'] ?? 0) === 1 : false;

            $items[] = [
                'id' => $type . '-' . $vmid,
                'vmid' => $vmid,
                'type' => $type,
                'node' => $node,
                'name' => (string) ($resource['name'] ?? $resource['id'] ?? ('VM ' . $vmid)),
                'status' => (string) ($resource['status'] ?? 'unknown'),
                'onboot' => $onboot,
                'startup' => $startup,
                'pending' => is_array($config) ? ($config['pending'] ?? null) : null,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $orderA = $a['startup']['order'] ?? PHP_INT_MAX;
            $orderB = $b['startup']['order'] ?? PHP_INT_MAX;

            return [$orderA, $a['node'], $a['vmid']] <=> [$orderB, $b['node'], $b['vmid']];
        });

        return $items;
    }

    /**
     * @param array<int,array{type:string,node:string,vmid:int,order?:int,onboot?:bool,up?:int|null,down?:int|null}> $changes
     * @return array{results:array<int,array<string,mixed>>,success:int,failed:int}
     */
    public function updateStartup(array $changes): array
    {
        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($changes as $change) {
            $type = (string) ($change['type'] ?? '');
            $node = (string) ($change['node'] ?? '');
            $vmid = (int) ($change['vmid'] ?? 0);

            $result = [
                'type' => $type,
                'node' => $node,
                'vmid' => $vmid,
                'status' => 'failed',
                'payload' => [],
                'error' => null,
            ];

            try {
                if (!in_array($type, ['qemu', 'lxc'], true) || $node === '' || $vmid === 0) {
                    throw new InvalidArgumentException('Modification invalide détectée.');
                }

                $payload = [];

                if (array_key_exists('order', $change)) {
                    $payload['startup'] = StartupConfig::build(
                        (int) $change['order'],
                        array_key_exists('up', $change) && $change['up'] !== null ? (int) $change['up'] : null,
                        array_key_exists('down', $change) && $change['down'] !== null ? (int) $change['down'] : null,
                    );
                }

                if (array_key_exists('onboot', $change)) {
                    $payload['onboot'] = (bool) $change['onboot'] ? 1 : 0;
                }

                if ($payload === []) {
                    throw new InvalidArgumentException('Aucun champ modifié à appliquer.');
                }

                $this->request('PUT', sprintf('/nodes/%s/%s/%d/config', rawurlencode($node), $type, $vmid), $payload);

                $result['status'] = 'success';
                $result['payload'] = $payload;
                $success++;
            } catch (Throwable $exception) {
                $result['error'] = $exception->getMessage();
                $failed++;
            }

            $results[] = $result;
        }

        return [
            'results' => $results,
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function request(string $method, string $path, array $params = []): array
    {
        $headers = [];
        if ($this->apiTokenHeader !== null) {
            $headers[] = 'Authorization: ' . $this->apiTokenHeader;
        }
        if ($this->ticket !== null) {
            $headers[] = 'Cookie: PVEAuthCookie=' . $this->ticket;
        }
        if ($this->csrfToken !== null && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            $headers[] = 'CSRFPreventionToken: ' . $this->csrfToken;
        }

        return self::rawRequest($this->baseUrl, $this->verifySsl, $method, $path, $params, $headers);
    }

    private static function normalizeBaseUrl(string $host): string
    {
        $host = preg_replace('!/api2/json/?$!', '', trim($host)) ?? trim($host);

        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://' . $host;
        }

        if (!preg_match('/:\d+($|\/)/', $host)) {
            $host = rtrim($host, '/') . ':8006';
        }

        return rtrim($host, '/') . '/api2/json';
    }

    private static function assertProxmoxUserHasRealm(string $username): void
    {
        if (!str_contains($username, '@')) {
            throw new InvalidArgumentException('Identifiant Proxmox incomplet. Utiliser le format user@realm, par exemple root@pam.');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function rawRequest(
        string $baseUrl,
        bool $verifySsl,
        string $method,
        string $path,
        array $params = [],
        array $headers = [],
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('L’extension PHP cURL est requise.');
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $method = strtoupper($method);

        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('Initialisation cURL impossible.');
        }

        if ($method === 'GET' && $params !== []) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($method !== 'GET' && $params !== []) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('Erreur de connexion Proxmox: ' . $error);
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Réponse Proxmox non JSON HTTP ' . $status . '.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['errors'] ?? $decoded['message'] ?? ('HTTP ' . $status);
            throw new RuntimeException('Erreur API Proxmox: ' . (is_string($message) ? $message : json_encode($message)));
        }

        return $decoded;
    }
}
