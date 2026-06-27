<?php

namespace App\Scraping;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Live ufcstats fetcher.
 *
 * Transparently solves the site's JavaScript proof-of-work challenge
 * ("Checking your browser…") server-side: find n such that
 * sha256(nonce + ":" + n) has the required leading zeros, POST it to /__c to
 * obtain a clearance cookie, then replay the request. The clearance cookie is
 * cached and reused across runs; we only re-solve when it expires.
 */
class UfcStatsClient implements PageFetcher
{
    private Client $http;

    private CookieJar $jar;

    public function __construct(private array $config)
    {
        $this->jar = $this->loadJar();
        $this->http = new Client([
            'cookies' => $this->jar,
            'timeout' => $this->config['timeout'],
            'headers' => ['User-Agent' => $this->config['user_agent']],
            'http_errors' => false,
            'allow_redirects' => true,
        ]);
    }

    public static function fromConfig(): self
    {
        return new self(config('ufc'));
    }

    public function fetch(string $url): string
    {
        $html = $this->request('GET', $url);

        if ($this->isChallenge($html)) {
            $this->solveChallenge($html);
            $html = $this->request('GET', $url);
        }

        if ($this->isChallenge($html)) {
            throw new RuntimeException("Failed to clear ufcstats challenge for: {$url}");
        }

        $this->jitter();

        return $html;
    }

    private function request(string $method, string $url, array $options = []): string
    {
        $attempts = max(1, (int) $this->config['retries']);
        $lastError = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $this->http->request($method, $url, $options);
                $status = $response->getStatusCode();
                if ($status >= 500) {
                    $lastError = "HTTP {$status}";
                    usleep((int) (250_000 * ($i + 1)));

                    continue;
                }

                return (string) $response->getBody();
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                usleep((int) (250_000 * ($i + 1)));
            }
        }

        throw new RuntimeException("Request failed ({$method} {$url}): {$lastError}");
    }

    private function isChallenge(string $html): bool
    {
        return str_contains($html, 'Checking your browser');
    }

    private function solveChallenge(string $html): void
    {
        if (! preg_match('/nonce\s*=\s*"([a-f0-9]+)"/', $html, $m)) {
            throw new RuntimeException('Could not parse challenge nonce.');
        }
        $nonce = $m[1];

        $zeros = 2;
        if (preg_match('/new Array\((\d+)\+1\)\.join\(\'0\'\)/', $html, $z)) {
            $zeros = (int) $z[1];
        }
        $prefix = str_repeat('0', $zeros);

        $n = 0;
        while (substr(hash('sha256', $nonce.':'.$n), 0, $zeros) !== $prefix) {
            $n++;
            if ($n > 50_000_000) {
                throw new RuntimeException('Challenge proof-of-work did not converge.');
            }
        }

        $this->request('POST', rtrim($this->config['base_url'], '/').'/__c', [
            'form_params' => ['nonce' => $nonce, 'n' => $n],
        ]);

        $this->persistJar();
    }

    private function loadJar(): CookieJar
    {
        $cached = Cache::get($this->config['clearance_cache_key']);

        return new CookieJar(false, is_array($cached) ? $cached : []);
    }

    private function persistJar(): void
    {
        // Clearance cookies are short-lived; cache for an hour and re-solve after.
        Cache::put($this->config['clearance_cache_key'], $this->jar->toArray(), now()->addHour());
    }

    private function jitter(): void
    {
        $min = (int) $this->config['delay_min_ms'];
        $max = max($min, (int) $this->config['delay_max_ms']);
        usleep(random_int($min, $max) * 1000);
    }
}
