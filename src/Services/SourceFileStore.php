<?php

declare(strict_types=1);

namespace App\Services;

class SourceFileStore
{
    public function __construct(
        private string $path
    ) {}

    public function storeContent(string $content, string $relativePath): void
    {
        $directory = $this->path . '/' . dirname($relativePath);
        if (false == file_exists($directory)) {
            mkdir($directory);
        }

        $filename = basename($relativePath);

        $path = $directory . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }

        file_put_contents($path, $content);
    }
}
