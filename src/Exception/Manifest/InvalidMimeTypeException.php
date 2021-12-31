<?php

declare(strict_types=1);

namespace App\Exception\Manifest;

class InvalidMimeTypeException extends \Exception implements ManifestFactoryExceptionInterface
{
    public function __construct(
        private string $mimeType
    ) {
        parent::__construct(sprintf(
            'Invalid mime type:  %s',
            $mimeType
        ));
    }

    public function __toString(): string
    {
        return $this->message;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
