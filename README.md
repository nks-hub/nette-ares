# Nette ARES

[![Latest Stable Version](https://poser.pugx.org/nks-hub/nette-ares/v)](https://packagist.org/packages/nks-hub/nette-ares)
[![Total Downloads](https://poser.pugx.org/nks-hub/nette-ares/downloads)](https://packagist.org/packages/nks-hub/nette-ares)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![Nette Version](https://img.shields.io/badge/nette-%5E3.1%20%7C%7C%20%5E4.0-blue.svg)](https://nette.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Nette DI extension pro [ARES](https://ares.gov.cz/) (Administrativní registr ekonomických subjektů) — vyhledávání firem podle IČO i názvu s automatickým cachováním výsledků. PHP 8.1+.

## Features

- 🔍 **Vyhledání podle IČO** — strukturovaný výsledek s adresou, DIČ, právní formou
- 📝 **Fulltextové vyhledávání** — hledání firem podle názvu s limitem výsledků
- 💾 **Automatické cachování** — konfigurovatelný TTL (výchozí 1 měsíc)
- ✅ **Kontrola aktivity** — ověření, zda firma není zaniklá
- 🎯 **Nette integrace** — DI extension s auto-registrací přes `composer.json`
- 🛡️ **Type-safe** — PHP 8.1+ s strict types a typed properties

## Requirements

- PHP 8.1+
- Nette 3.1+ / 4.0+

## Instalace

```bash
composer require nks-hub/nette-ares
```

## Registrace

Extension se registruje automaticky díky `extra.nette.extensions` v `composer.json`.

Ruční registrace v `config.neon`:

```neon
extensions:
    ares: NksHub\NetteAres\DI\AresExtension
```

### Konfigurace (volitelná)

```neon
ares:
    cacheTtl: '1 month'   # Jak dlouho cachovat výsledky (výchozí: 1 month)
```

## Použití

### Vyhledání firmy podle IČO

```php
use NksHub\NetteAres\AresClient;
use NksHub\NetteAres\AresException;

class MyPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private AresClient $ares,
    ) {}

    public function actionDetail(string $ico): void
    {
        try {
            $result = $this->ares->findByIco($ico);

            echo $result->obchodniJmeno;      // "Asseco Central Europe, a.s."
            echo $result->ico;                 // "27074358"
            echo $result->dic;                 // "CZ27074358"
            echo $result->getStreet();         // "Budějovická 778/3a"
            echo $result->getCity();           // "Praha - Michle"
            echo $result->getFormattedPsc();   // "140 00"
            echo $result->kodStatu;            // "CZ"

        } catch (AresException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
        }
    }
}
```

### Vyhledání firem podle názvu

```php
$results = $this->ares->searchByName('Asseco', limit: 5);

foreach ($results as $result) {
    echo "{$result->obchodniJmeno} (IČO: {$result->ico})\n";
}
```

### Kontrola aktivity firmy

```php
if ($this->ares->isActive('27074358')) {
    echo 'Firma je aktivní';
}
```

### Získání DIČ

```php
$dic = $this->ares->getDic('27074358'); // "CZ27074358" nebo null
```

### Použití v AJAX handleru (typicky pro formuláře)

```php
public function handleAresLookup(string $ico): void
{
    try {
        $result = $this->ares->findByIco($ico);
        $this->sendJson($result->toArray());
    } catch (AresException $e) {
        $this->sendJson(['error' => $e->getMessage()]);
    }
}
```

`toArray()` vrací:
```php
[
    'ico'            => '27074358',
    'dic'            => 'CZ27074358',
    'company'        => 'Asseco Central Europe, a.s.',
    'street'         => 'Budějovická 778/3a',
    'city'           => 'Praha - Michle',
    'zip'            => '140 00',
    'country'        => 'CZ',
    'textovaAdresa'  => 'Budějovická 778/3a, Michle, 14000 Praha 4',
]
```

### Cache

Výsledky se automaticky cachují (výchozí: 1 měsíc). Manuální invalidace:

```php
$this->ares->clearCacheByIco('27074358'); // konkrétní IČO
$this->ares->clearCache();                 // celý ARES cache
```

## AresResult

Objekt `AresResult` obsahuje:

| Property | Typ | Popis |
|---|---|---|
| `ico` | `string` | IČO (8 číslic) |
| `dic` | `?string` | DIČ (formát CZ + IČO) |
| `obchodniJmeno` | `string` | Obchodní jméno |
| `pravniForma` | `?string` | Kód právní formy |
| `ulice` | `?string` | Název ulice |
| `cisloDomovni` | `?int` | Číslo popisné |
| `cisloOrientacni` | `?int` | Číslo orientační |
| `mesto` | `?string` | Obec |
| `castObce` | `?string` | Část obce |
| `psc` | `?int` | PSČ |
| `kodStatu` | `?string` | Kód státu |
| `textovaAdresa` | `?string` | Celá adresa textem |
| `datumVzniku` | `?string` | Datum vzniku |
| `datumZaniku` | `?string` | Datum zániku |

Helper metody: `getStreet()`, `getCity()`, `getFormattedPsc()`, `toArray()`.

## API

Extension využívá oficiální [ARES REST API v3](https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/v3/api-docs):

- `GET /ekonomicke-subjekty/{ico}` — vyhledání podle IČO
- `POST /ekonomicke-subjekty/vyhledat` — fulltextové vyhledávání

Bez autentizace, bez rate-limitu ze strany ARES (doporučujeme rozumné cachování).

## Testing

```bash
./vendor/bin/tester tests
```

## Contributing

Pull requesty jsou vítány! Pro větší změny prosím nejprve otevřete issue.

1. Fork repozitáře
2. Vytvořte feature branch (`git checkout -b feature/nova-funkce`)
3. Commit změn (`git commit -m 'feat: popis'`)
4. Push branch (`git push origin feature/nova-funkce`)
5. Otevřete Pull Request

## Podpora

- 📧 **Email:** dev@nks-hub.cz
- 🐛 **Bug reports:** [GitHub Issues](https://github.com/nks-hub/nette-ares/issues)
- 📖 **ARES API docs:** [ares.gov.cz](https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/v3/api-docs)

## Licence

MIT License — viz [LICENSE](LICENSE)

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/nks-hub">NKS Hub</a>
</p>
