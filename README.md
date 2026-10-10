# Nette ARES

[![Latest Stable Version](https://poser.pugx.org/nks-hub/nette-ares/v)](https://packagist.org/packages/nks-hub/nette-ares)
[![Total Downloads](https://poser.pugx.org/nks-hub/nette-ares/downloads)](https://packagist.org/packages/nks-hub/nette-ares)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![Nette Version](https://img.shields.io/badge/nette-%5E3.1%20%7C%7C%20%5E4.0-blue.svg)](https://nette.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Nette DI extension for [ARES](https://ares.gov.cz/) (the Czech Administrative Register of Economic Subjects) — company lookup by IČO and by name with automatic result caching. PHP 8.1+.

## Features

- 🔍 **Lookup by IČO** - Structured result with address, VAT ID (DIČ) and legal form
- 📝 **Full-text search** - Search companies by name with a result limit
- 💾 **Automatic caching** - Configurable TTL (default 1 month)
- ✅ **Activity check** - Verify that a company has not been dissolved
- 🎯 **Nette integration** - DI extension with auto-registration via `composer.json`
- 🛡️ **Type-safe** - PHP 8.1+ with strict types and typed properties

## Requirements

- PHP 8.1+
- Nette 3.1+ / 4.0+

## Installation

```bash
composer require nks-hub/nette-ares
```

## Configuration

The extension registers itself automatically thanks to `extra.nette.extensions` in `composer.json`.

Manual registration in `config.neon`:

```neon
extensions:
    ares: NksHub\NetteAres\DI\AresExtension
```

Optional configuration:

```neon
ares:
    cacheTtl: '1 month'   # How long to cache results (default: 1 month)
```

## Usage

### Look up a company by IČO

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

### Search companies by name

```php
$results = $this->ares->searchByName('Asseco', limit: 5);

foreach ($results as $result) {
    echo "{$result->obchodniJmeno} (IČO: {$result->ico})\n";
}
```

### Check whether a company is active

```php
if ($this->ares->isActive('27074358')) {
    echo 'Company is active';
}
```

### Get the VAT ID (DIČ)

```php
$dic = $this->ares->getDic('27074358'); // "CZ27074358" or null
```

### Use in an AJAX handler (typically for forms)

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

`toArray()` returns:
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

Results are cached automatically (default: 1 month). Manual invalidation:

```php
$this->ares->clearCacheByIco('27074358'); // a specific IČO
$this->ares->clearCache();                 // the whole ARES cache
```

## AresResult

The `AresResult` object contains:

| Property | Type | Description |
|---|---|---|
| `ico` | `string` | IČO (8 digits) |
| `dic` | `?string` | DIČ (format CZ + IČO) |
| `obchodniJmeno` | `string` | Business name |
| `pravniForma` | `?string` | Legal form code |
| `ulice` | `?string` | Street name |
| `cisloDomovni` | `?int` | Building number (číslo popisné) |
| `cisloOrientacni` | `?int` | Orientation number (číslo orientační) |
| `mesto` | `?string` | Municipality |
| `castObce` | `?string` | Municipality part |
| `psc` | `?int` | Postal code (PSČ) |
| `kodStatu` | `?string` | Country code |
| `textovaAdresa` | `?string` | Full address as text |
| `datumVzniku` | `?string` | Date of establishment |
| `datumZaniku` | `?string` | Date of dissolution |

Helper methods: `getStreet()`, `getCity()`, `getFormattedPsc()`, `toArray()`.

## API

The extension uses the official [ARES REST API v3](https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/v3/api-docs):

- `GET /ekonomicke-subjekty/{ico}` — lookup by IČO
- `POST /ekonomicke-subjekty/vyhledat` — full-text search

No authentication and no rate limit on the ARES side (sensible caching is recommended).

## Testing

```bash
./vendor/bin/tester tests
```

## Contributing

Contributions are welcome! For major changes, please open an issue first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: description'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- 📧 **Email:** dev@nks-hub.cz
- 🐛 **Bug reports:** [GitHub Issues](https://github.com/nks-hub/nette-ares/issues)
- 📖 **ARES API docs:** [ares.gov.cz](https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/v3/api-docs)

## License

MIT License — see [LICENSE](LICENSE) for details.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/nks-hub">NKS Hub</a>
</p>
