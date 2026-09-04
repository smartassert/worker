<?php

declare(strict_types=1);

namespace App\Enum;

interface StateInterface
{
    public function isEndState(): bool;

    public function isSuccessState(): bool;

    public function isFailedState(): bool;

    public function isPendingState(): bool;

    /**
     * @return non-empty-string
     */
    public function getValue(): string;
}
