<?php

declare(strict_types=1);

namespace App\Message;

readonly class CompileSourceMessage
{
    /**
     * @param non-empty-string $path
     */
    public function __construct(public string $path) {}
}
