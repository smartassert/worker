<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\String\UnicodeString;

class TestPathMutator
{
    public function __construct(
        private readonly string $compilerSourceDirectory,
    ) {
    }

    public function removeCompilerSourceDirectoryFromPath(string $path): string
    {
        return (string) (new UnicodeString($path))->trimPrefix($this->compilerSourceDirectory . '/');
    }
}
