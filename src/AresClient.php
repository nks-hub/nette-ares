<?php

declare(strict_types=1);

namespace NksHub\NetteAres;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\Json;

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
            $data = $this->request('GET', "/ekonomicke-subjekty/$ico");
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
     */
    public function isActive(string $ico): bool
    {
        try {
            $result = $this->findByIco($ico);
            return $result->datumZaniku === null;
        } catch (AresException) {
            return false;
        }
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
     * @throws AresException
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = self::API_BASE . $path;

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
                'content' => $body !== null ? Json::encode($body) : null,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new AresException("ARES API je nedostupné ($url).");
        }

        // Parse HTTP status from $http_response_header
        $statusCode = 0;
        if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
            $statusCode = (int) $m[0];
        }

        $data = Json::decode($response, forceArrays: true);

        if ($statusCode === 404 || ($data['kod'] ?? null) === 'NENALEZENO') {
            throw AresException::notFound($body['ico'] ?? $path);
        }

        if ($statusCode >= 400) {
            $message = $data['popis'] ?? $data['kod'] ?? "HTTP $statusCode";
            throw new AresException("ARES API chyba: $message", $statusCode);
        }

        return $data;
    }
}
