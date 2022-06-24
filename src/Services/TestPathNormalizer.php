<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\String\UnicodeString;

class TestPathNormalizer
{
    public function __construct(
        private readonly string $compilerSourceDirectory,
    ) {
    }

    public function normalize(string $path): string
    {
        $path = trim($path);

        return (string) (new UnicodeString($path))->trimPrefix($this->compilerSourceDirectory . '/');
    }
}
