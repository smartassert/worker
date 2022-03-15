<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\UploadedSource;
use Symfony\Component\HttpFoundation\File\File;

class SourceFileStore
{
    public function __construct(
        private string $path
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function store(UploadedSource $uploadedSource, string $relativePath): File
    {
        $directory = $this->path . '/' . dirname($relativePath);
        $filename = basename($relativePath);

        $path = $directory . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }

        $uploadedFile = $uploadedSource->getUploadedFile();

        return $uploadedFile->move($directory, $filename);
    }

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

    public function has(string $relativePath): bool
    {
        return file_exists($this->path . '/' . $relativePath);
    }
}
