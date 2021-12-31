<?php

declare(strict_types=1);

namespace App\Exception\Manifest;

interface ManifestFactoryExceptionInterface extends \Throwable
{
    public function __toString(): string;
}
