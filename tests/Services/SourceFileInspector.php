<?php

declare(strict_types=1);

namespace App\Tests\Services;

class SourceFileInspector
{
    public function __construct(
        private string $path
    ) {}

    public function read(string $path): string
    {
        return (string) file_get_contents($this->path . '/' . $path);
    }

    public function has(string $path): bool
    {
        return file_exists($this->path . '/' . $path);
    }
}
