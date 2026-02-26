# nette-ares

Nette DI extension pro [ARES](https://ares.gov.cz/) (Administrativní registr ekonomických subjektů) — vyhledávání firem podle IČO i názvu s automatickým cachováním výsledků. PHP 8.1+.

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

## Licence

MIT
