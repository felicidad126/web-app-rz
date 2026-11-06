<?php

namespace App\Services\SatuSehat;

use App\Enums\SatuSehatIssue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client FHIR SATUSEHAT: OAuth2 client credentials, token di-cache sampai mendekati kedaluwarsa.
 *
 * Sengaja tidak mencatat URL/isi request ke log: query pencarian memuat NIK (UU PDP).
 */
class SatuSehatClient
{
    public function __construct(
        private readonly string $authUrl,
        private readonly string $fhirUrl,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $organizationId,
        private readonly int $timeout = 30,
    ) {}

    public static function fromConfig(): self
    {
        $env = config('satusehat.environment') === 'production' ? 'production' : 'sandbox';

        return new self(
            authUrl: rtrim((string) (config('satusehat.auth_url') ?: config("satusehat.environments.$env.auth_url")), '/'),
            fhirUrl: rtrim((string) (config('satusehat.fhir_url') ?: config("satusehat.environments.$env.fhir_url")), '/'),
            clientId: config('satusehat.client_id'),
            clientSecret: config('satusehat.client_secret'),
            organizationId: config('satusehat.organization_id'),
            timeout: (int) config('satusehat.timeout', 30),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->clientSecret) && filled($this->organizationId);
    }

    public function organizationId(): string
    {
        if (blank($this->organizationId)) {
            throw new SatuSehatException('Organization ID SATUSEHAT belum diisi.', SatuSehatIssue::NotConfigured);
        }

        return (string) $this->organizationId;
    }

    /** Token akses (di-cache). */
    public function token(bool $fresh = false): string
    {
        if (! $this->isConfigured()) {
            throw new SatuSehatException('Kredensial SATUSEHAT belum diisi.', SatuSehatIssue::NotConfigured);
        }

        $key = 'satusehat:token:'.sha1($this->authUrl.'|'.$this->clientId);

        if ($fresh) {
            Cache::forget($key);
        }

        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->authUrl.'/accesstoken?grant_type=client_credentials', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);
        } catch (ConnectionException) {
            throw new SatuSehatException('Server otentikasi SATUSEHAT tidak bisa dihubungi.', SatuSehatIssue::Unreachable);
        }

        if ($response->serverError()) {
            throw new SatuSehatException('Server otentikasi SATUSEHAT sedang bermasalah (HTTP '.$response->status().').', SatuSehatIssue::Unreachable, $response->status());
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new SatuSehatException('Kredensial ditolak SATUSEHAT (HTTP '.$response->status().').', SatuSehatIssue::AuthFailed, $response->status());
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($key, $token, $ttl);

        return $token;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body): array
    {
        return $this->send('POST', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function put(string $path, array $body): array
    {
        return $this->send('PUT', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $payload  query (GET) atau body JSON
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $payload, bool $retried = false): array
    {
        // Path kosong = endpoint dasar FHIR (dipakai untuk Bundle transaksi).
        $url = $path === '' ? $this->fhirUrl : $this->fhirUrl.'/'.ltrim($path, '/');

        try {
            $request = Http::withToken($this->token())->acceptJson()->timeout($this->timeout);
            $response = $method === 'GET' ? $request->get($url, $payload) : $request->asJson()->send($method, $url, ['json' => $payload]);
        } catch (ConnectionException) {
            throw new SatuSehatException('SATUSEHAT tidak bisa dihubungi (timeout atau jaringan).', SatuSehatIssue::Unreachable);
        }

        // Token kedaluwarsa lebih cepat dari perkiraan: ambil token baru sekali.
        if ($response->status() === 401 && ! $retried) {
            $this->token(fresh: true);

            return $this->send($method, $path, $payload, true);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->failure($response, $path);
    }

    private function failure(Response $response, string $path): SatuSehatException
    {
        $resource = strtok(ltrim($path, '/'), '/?') ?: 'Bundle';
        $status = $response->status();

        if ($response->serverError() || $status === 429) {
            return new SatuSehatException("SATUSEHAT sedang bermasalah saat mengirim {$resource} (HTTP {$status}).", SatuSehatIssue::Unreachable, $status);
        }

        if ($status === 401 || $status === 403) {
            return new SatuSehatException("Akses ke {$resource} ditolak (HTTP {$status}).", SatuSehatIssue::AuthFailed, $status);
        }

        $message = self::outcomeMessage($response);

        // Katalog validasi 20002: identifier yang sama sudah pernah diterima (mis. kiriman sebelumnya sempat terputus).
        $issue = str_contains(mb_strtolower($message), 'duplicate') ? SatuSehatIssue::Duplicate : SatuSehatIssue::Rejected;

        return new SatuSehatException("{$resource} ditolak SATUSEHAT (HTTP {$status}): ".$message, $issue, $status);
    }

    /** Ringkas pesan OperationOutcome (issue[].details.text / diagnostics), maksimal 600 karakter. */
    public static function outcomeMessage(Response $response): string
    {
        $issues = collect((array) $response->json('issue', []))
            ->map(fn ($issue): string => is_array($issue)
                ? trim((string) (data_get($issue, 'details.text') ?? data_get($issue, 'diagnostics') ?? data_get($issue, 'code', ''))
                    .(is_array($issue['expression'] ?? null) ? ' ['.implode(', ', $issue['expression']).']' : ''))
                : '')
            ->filter()
            ->unique()
            ->implode('; ');

        $message = $issues !== '' ? $issues : (string) ($response->json('message') ?? $response->json('error_description') ?? 'tanpa keterangan');

        // Jaga-jaga: buang deretan 16 digit (NIK) bila server memantulkannya di pesan.
        return mb_strimwidth((string) preg_replace('/\b\d{16}\b/', '[NIK]', $message), 0, 600, '…');
    }
}
