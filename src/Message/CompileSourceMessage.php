<?php

declare(strict_types=1);

namespace App\Message;

class CompileSourceMessage
{
    /**
     * @param non-empty-string $path
     */
    public function __construct(public readonly string $path)
    {
    }
}
