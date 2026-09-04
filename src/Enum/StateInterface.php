<?php

declare(strict_types=1);

namespace App\Enum;

interface StateInterface
{
    public function isEnd(): bool;

    public function isSuccess(): bool;

    public function isFailed(): bool;

    public function isPending(): bool;

    /**
     * @return non-empty-string
     */
    public function getValue(): string;

    /**
     * @return StateInterface[]
     */
    public function getPreviousStates(): array;
}
