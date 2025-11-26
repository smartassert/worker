<?php

declare(strict_types=1);

namespace App\Message;

class ExecuteTestMessage
{
    public const TYPE = 'execute-test';

    public function __construct(private int $testId) {}

    public function getTestId(): int
    {
        return $this->testId;
    }
}
