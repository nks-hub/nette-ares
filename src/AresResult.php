<?php

declare(strict_types=1);

namespace NksHub\NetteAres;

/**
 * Structured result from ARES API lookup.
 */
final class AresResult
{
    public function __construct(
        public readonly string $ico,
        public readonly ?string $dic,
        public readonly string $obchodniJmeno,
        public readonly ?string $pravniForma,
        public readonly ?string $ulice,
        public readonly ?int $cisloDomovni,
        public readonly ?int $cisloOrientacni,
        public readonly ?string $cisloOrientacniPismeno,
        public readonly ?string $mesto,
        public readonly ?string $castObce,
        public readonly ?int $psc,
        public readonly ?string $kodStatu,
        public readonly ?string $textovaAdresa,
        public readonly ?string $datumVzniku,
        public readonly ?string $datumZaniku,
    ) {
    }

    /**
     * Create from ARES API response array.
     */
    public static function fromApi(array $data): self
    {
        $sidlo = $data['sidlo'] ?? [];

        return new self(
            ico: $data['ico'],
            dic: $data['dic'] ?? null,
            obchodniJmeno: $data['obchodniJmeno'] ?? '',
            pravniForma: $data['pravniForma'] ?? null,
            ulice: $sidlo['nazevUlice'] ?? null,
            cisloDomovni: $sidlo['cisloDomovni'] ?? null,
            cisloOrientacni: $sidlo['cisloOrientacni'] ?? null,
            cisloOrientacniPismeno: $sidlo['cisloOrientacniPismeno'] ?? null,
            mesto: $sidlo['nazevObce'] ?? null,
            castObce: $sidlo['nazevCastiObce'] ?? null,
            psc: $sidlo['psc'] ?? null,
            kodStatu: $sidlo['kodStatu'] ?? null,
            textovaAdresa: $sidlo['textovaAdresa'] ?? null,
            datumVzniku: $data['datumVzniku'] ?? null,
            datumZaniku: $data['datumZaniku'] ?? null,
        );
    }

    /**
     * Formatted street with house number (e.g. "Budějovická 778/3a").
     */
    public function getStreet(): string
    {
        if ($this->ulice === null) {
            return '';
        }

        $street = $this->ulice;

        if ($this->cisloDomovni !== null) {
            $street .= ' ' . $this->cisloDomovni;
            if ($this->cisloOrientacni !== null) {
                $street .= '/' . $this->cisloOrientacni;
                if ($this->cisloOrientacniPismeno !== null) {
                    $street .= $this->cisloOrientacniPismeno;
                }
            }
        }

        return $street;
    }

    /**
     * Formatted PSČ (e.g. "140 00").
     */
    public function getFormattedPsc(): string
    {
        if ($this->psc === null) {
            return '';
        }

        $psc = (string) $this->psc;
        return substr($psc, 0, 3) . ' ' . substr($psc, 3);
    }

    /**
     * City name with district if different (e.g. "Praha 4 - Michle").
     */
    public function getCity(): string
    {
        $city = $this->mesto ?? '';
        if ($this->castObce !== null && $this->castObce !== $this->mesto) {
            $city .= ' - ' . $this->castObce;
        }
        return $city;
    }

    /**
     * Whether the subject is a natural person (FO) based on legal form code.
     * Codes 100-109 = physical persons, everything else = legal entity.
     */
    public function isPhysicalPerson(): bool
    {
        if ($this->pravniForma === null) {
            return false;
        }
        $code = (int) $this->pravniForma;
        return $code >= 100 && $code <= 109;
    }

    /**
     * Return as array (useful for form filling, API responses).
     */
    public function toArray(): array
    {
        return [
            'ico' => $this->ico,
            'dic' => $this->dic,
            'company' => $this->obchodniJmeno,
            'street' => $this->getStreet(),
            'city' => $this->getCity(),
            'zip' => $this->getFormattedPsc(),
            'country' => $this->kodStatu ?? 'CZ',
            'textovaAdresa' => $this->textovaAdresa,
            'pravniForma' => $this->pravniForma,
            'isPhysicalPerson' => $this->isPhysicalPerson(),
        ];
    }
}
