<?php

declare(strict_types=1);

namespace App\Message;

readonly class ExecuteTestMessage
{
    public function __construct(
        public int $testId,
    ) {}
}
