<?php

declare(strict_types=1);

use Nette\Caching\Storages\MemoryStorage;
use NksHub\NetteAres\AresClient;
use NksHub\NetteAres\AresException;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

/** Client with a canned HTTP response, no network. */
final class FakeAresClient extends AresClient
{
    public int $calls = 0;

    /** @param string|false $body */
    public function __construct(private string|false $body, private string $status = 'HTTP/1.1 200 OK')
    {
        parent::__construct(new MemoryStorage());
    }

    protected function send(string $method, string $url, ?string $content): array
    {
        $this->calls++;
        return [$this->body, [$this->status]];
    }
}

$active = '{"ico":"00006947","obchodniJmeno":"Test a.s.","sidlo":{"nazevUlice":"Letenská","cisloDomovni":15,"psc":11800}}';
$dissolved = '{"ico":"00006947","obchodniJmeno":"Test a.s.","datumZaniku":"2020-01-01"}';

// isActive: active, dissolved
Assert::true((new FakeAresClient($active))->isActive('6947'));
Assert::false((new FakeAresClient($dissolved))->isActive('6947'));

// isActive: unknown company and malformed IČO give false
Assert::false((new FakeAresClient('{"kod":"NENALEZENO"}', 'HTTP/1.1 404 Not Found'))->isActive('00006947'));
Assert::false((new FakeAresClient($active))->isActive('abc'));

// isActive: connection failure and server errors are not reported as "dissolved"
Assert::exception(fn() => (new FakeAresClient(false))->isActive('00006947'), AresException::class, '~nedostupn~');
Assert::exception(fn() => (new FakeAresClient('<html>oops</html>', 'HTTP/1.1 503 Service Unavailable'))->isActive('00006947'), AresException::class, '~503~');

// notFound message carries the IČO, not the request path
try {
    (new FakeAresClient('{"kod":"NENALEZENO"}', 'HTTP/1.1 404 Not Found'))->findByIco('6947');
    Assert::fail('exception expected');
} catch (AresException $e) {
    Assert::same("Subjekt s IČO '00006947' nebyl nalezen v ARES.", $e->getMessage());
    Assert::same(404, $e->getCode());
}

// checksum
Assert::true(AresClient::isValidIco('00006947'));
Assert::true(AresClient::isValidIco('27074358'));
Assert::true(AresClient::isValidIco('6947'));
Assert::false(AresClient::isValidIco('00006948'));
Assert::false(AresClient::isValidIco('1234'));
Assert::false(AresClient::isValidIco('abcdefgh'));
