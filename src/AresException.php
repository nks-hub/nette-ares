<?php

declare(strict_types=1);

namespace NksHub\NetteAres;

class AresException extends \RuntimeException
{
    public static function notFound(string $ico): self
    {
        return new self("Subjekt s IČO '$ico' nebyl nalezen v ARES.", 404);
    }
}
