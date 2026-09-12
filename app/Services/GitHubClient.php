<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/**
 * Thin GitHub REST client.
 *
 * The token is passed only in the Authorization header, never in a URL,
 * never logged and never returned to the browser.
 */
final class GitHubClient
{
    public function __construct(
        private string $owner,
        private string $repo,
        private string $branch = 'main',
        private string $token = ''
    ) {
    }

    public static function fromSettings(): self
    {
        return new self(
            SettingsService::string('github_owner'),
            SettingsService::string('github_repo'),
            SettingsService::string('github_branch', 'main') ?: 'main',
            SettingsService::string('github_token')
        );
    }

    public function isConfigured(): bool
    {
        return $this->owner !== '' && $this->repo !== '';
    }

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    public function repository(): string
    {
        return $this->owner . '/' . $this->repo;
    }

    public function branch(): string
    {
        return $this->branch;
    }

    /** @return array<string,mixed> The newest commit on the configured branch. */
    public function latestCommit(): array
    {
        $data = $this->get('/repos/' . rawurlencode($this->owner) . '/' . rawurlencode($this->repo) . '/commits/' . rawurlencode($this->branch));

        return [
            'sha'     => (string) ($data['sha'] ?? ''),
            'message' => (string) ($data['commit']['message'] ?? ''),
            'author'  => (string) ($data['commit']['author']['name'] ?? ($data['author']['login'] ?? 'unknown')),
            'date'    => (string) ($data['commit']['author']['date'] ?? ''),
            'url'     => (string) ($data['html_url'] ?? ''),
            'files'   => array_map(
                static fn ($f) => ['filename' => (string) ($f['filename'] ?? ''), 'status' => (string) ($f['status'] ?? '')],
                is_array($data['files'] ?? null) ? $data['files'] : []
            ),
        ];
    }

    /** @return array<string,mixed>|null The newest published release, if any. */
    public function latestRelease(): ?array
    {
        try {
            $data = $this->get('/repos/' . rawurlencode($this->owner) . '/' . rawurlencode($this->repo) . '/releases/latest');
        } catch (RuntimeException) {
            return null;
        }
        if (!isset($data['tag_name'])) {
            return null;
        }
        return [
            'tag'          => (string) $data['tag_name'],
            'name'         => (string) ($data['name'] ?? $data['tag_name']),
            'body'         => (string) ($data['body'] ?? ''),
            'published_at' => (string) ($data['published_at'] ?? ''),
            'url'          => (string) ($data['html_url'] ?? ''),
        ];
    }

    /** Verify the repository is reachable with the configured credentials. */
    public function verifyRepository(): bool
    {
        $data = $this->get('/repos/' . rawurlencode($this->owner) . '/' . rawurlencode($this->repo));
        return isset($data['full_name']);
    }

    /** Verify the branch exists. */
    public function verifyBranch(): bool
    {
        $data = $this->get('/repos/' . rawurlencode($this->owner) . '/' . rawurlencode($this->repo) . '/branches/' . rawurlencode($this->branch));
        return isset($data['name']);
    }

    /**
     * Download the branch as a zipball to the given path.
     * Returns the number of bytes written.
     */
    public function downloadZipball(string $targetPath, ?string $ref = null): int
    {
        $ref = $ref ?? $this->branch;
        $url = Config::get('updates.api_base', 'https://api.github.com')
            . '/repos/' . rawurlencode($this->owner) . '/' . rawurlencode($this->repo)
            . '/zipball/' . rawurlencode($ref);

        $handle = @fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not open the download target for writing.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => (int) Config::get('updates.timeout', 120),
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_USERAGENT      => $this->userAgent(),
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($ok === false || $status >= 400) {
            @unlink($targetPath);
            throw new RuntimeException(
                'Download failed (HTTP ' . $status . ')' . ($error !== '' ? ': ' . $error : '.')
            );
        }

        $size = (int) (filesize($targetPath) ?: 0);
        if ($size <= 0) {
            @unlink($targetPath);
            throw new RuntimeException('The downloaded update package is empty.');
        }

        return $size;
    }

    /**
     * @return array<string,mixed>
     */
    private function get(string $path): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('The GitHub repository owner and name have not been configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required to talk to GitHub.');
        }

        $url = rtrim((string) Config::get('updates.api_base', 'https://api.github.com'), '/') . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_USERAGENT      => $this->userAgent(),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Could not reach GitHub' . ($error !== '' ? ': ' . $error : '.'));
        }

        $decoded = json_decode((string) $body, true);

        if ($status === 401 || $status === 403) {
            $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
            throw new RuntimeException(
                'GitHub refused the request (HTTP ' . $status . '). Check the token and its permissions.'
                . ($message !== '' ? ' GitHub said: ' . $message : '')
            );
        }
        if ($status === 404) {
            throw new RuntimeException('The repository, branch or resource was not found on GitHub (HTTP 404). Check the owner, repository name and branch.');
        }
        if ($status >= 400) {
            throw new RuntimeException('GitHub returned HTTP ' . $status . '.');
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('GitHub returned an unexpected response.');
        }

        return $decoded;
    }

    /** @return array<int,string> */
    private function headers(): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        return $headers;
    }

    private function userAgent(): string
    {
        return 'GanpatiQuizShow/' . (string) Config::get('app.version', '1.0.0');
    }
}
