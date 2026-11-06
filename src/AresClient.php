<?php

declare(strict_types=1);

namespace NksHub\NetteAres;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class AresClient
{
    private const API_BASE = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest';

    private Cache $cache;
    private string $cacheTtl;

    public function __construct(
        Storage $cacheStorage,
        string $cacheTtl = '1 month',
    ) {
        $this->cache = new Cache($cacheStorage, 'nks-hub.ares');
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * Lookup a company by IČO (8-digit identification number).
     *
     * @throws AresException on validation error or API failure
     */
    public function findByIco(string $ico): AresResult
    {
        $ico = self::normalizeIco($ico);

        return $this->cache->load("ico.$ico", function () use ($ico): AresResult {
            $data = $this->request('GET', "/ekonomicke-subjekty/$ico", null, $ico);
            return AresResult::fromApi($data);
        }, [Cache::Expire => $this->cacheTtl]);
    }

    /**
     * Search companies by name.
     *
     * @return AresResult[]
     * @throws AresException
     */
    public function searchByName(string $name, int $limit = 10): array
    {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            throw new AresException('Název firmy musí mít alespoň 3 znaky.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new AresException("Limit musí být mezi 1 a 100, zadáno: $limit.");
        }

        $cacheKey = 'search.' . md5("$name|$limit");

        return $this->cache->load($cacheKey, function () use ($name, $limit): array {
            $data = $this->request('POST', '/ekonomicke-subjekty/vyhledat', [
                'obchodniJmeno' => $name,
                'start' => 0,
                'pocet' => $limit,
            ]);

            $results = [];
            foreach ($data['ekonomickeSubjekty'] ?? [] as $subjekt) {
                $results[] = AresResult::fromApi($subjekt);
            }
            return $results;
        }, [Cache::Expire => $this->cacheTtl]);
    }

    /**
     * Check if a company with given IČO exists and is active.
     * Returns false for a malformed, unknown (404) or dissolved IČO.
     *
     * @throws AresException when ARES cannot be reached or answers with an error other than "not found"
     */
    public function isActive(string $ico): bool
    {
        try {
            $ico = self::normalizeIco($ico);
        } catch (AresException) {
            return false;
        }

        try {
            return $this->findByIco($ico)->datumZaniku === null;
        } catch (AresException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Verify the IČO checksum (modulo 11 over the first seven digits).
     * Accepts the same input forms as normalizeIco(); returns false instead of throwing.
     */
    public static function isValidIco(string $ico): bool
    {
        try {
            $ico = self::normalizeIco($ico);
        } catch (AresException) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $ico[$i] * (8 - $i);
        }

        return (11 - $sum % 11) % 10 === (int) $ico[7];
    }

    /**
     * Get VAT ID (DIČ) for given IČO. Returns null if not a VAT payer.
     */
    public function getDic(string $ico): ?string
    {
        return $this->findByIco($ico)->dic;
    }

    /**
     * Clear entire ARES cache.
     */
    public function clearCache(): void
    {
        $this->cache->clean([Cache::All => true]);
    }

    /**
     * Clear cache for a specific IČO.
     */
    public function clearCacheByIco(string $ico): void
    {
        $ico = self::normalizeIco($ico);
        $this->cache->remove("ico.$ico");
    }

    /**
     * Normalize and validate IČO.
     */
    public static function normalizeIco(string $ico): string
    {
        $ico = preg_replace('/\s+/', '', $ico);
        $ico = ltrim($ico, '0');
        $ico = str_pad($ico, 8, '0', STR_PAD_LEFT);

        if (!preg_match('/^\d{8}$/', $ico)) {
            throw new AresException("Neplatné IČO: '$ico'. IČO musí být 8 číslic.");
        }

        return $ico;
    }

    /**
     * @param string|null $subject IČO shown in the "not found" message
     * @throws AresException
     */
    private function request(string $method, string $path, ?array $body = null, ?string $subject = null): array
    {
        $url = self::API_BASE . $path;
        [$response, $headers] = $this->send($method, $url, $body !== null ? Json::encode($body) : null);

        if ($response === false) {
            throw new AresException("ARES API je nedostupné ($url).");
        }

        $statusCode = 0;
        if (isset($headers[0]) && preg_match('/\d{3}/', $headers[0], $m)) {
            $statusCode = (int) $m[0];
        }

        try {
            $data = Json::decode($response, forceArrays: true);
        } catch (JsonException) {
            $data = null;
        }

        if ($statusCode === 404 || (is_array($data) && ($data['kod'] ?? null) === 'NENALEZENO')) {
            throw AresException::notFound($subject ?? $path);
        }

        if ($statusCode >= 400) {
            $message = is_array($data) ? ($data['popis'] ?? $data['kod'] ?? "HTTP $statusCode") : "HTTP $statusCode";
            throw new AresException("ARES API chyba: $message", $statusCode);
        }

        if (!is_array($data)) {
            throw new AresException('ARES API vrátilo neplatnou odpověď.');
        }

        return $data;
    }

    /**
     * Perform the HTTP call. Overridable so tests can run without network.
     *
     * @return array{0: string|false, 1: list<string>} response body (false on connection failure) and response headers
     */
    protected function send(string $method, string $url, ?string $content): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Accept: application/json
Content-Type: application/json
",
                'content' => $content,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        return [$response, $http_response_header ?? []];
    }
}
