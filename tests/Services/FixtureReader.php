<?php

declare(strict_types=1);

namespace App\Tests\Services;

class FixtureReader
{
    public function __construct(
        private string $path,
    ) {
    }

    public function read(string $path): string
    {
        $path = (string) realpath($this->path . '/' . $path);

        return (string) file_get_contents($path);
    }
}
