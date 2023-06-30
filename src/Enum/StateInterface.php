<?php

declare(strict_types=1);

namespace App\Enum;

interface StateInterface
{
    public static function isEndState(StateInterface $state): bool;

    /**
     * @return non-empty-string
     */
    public function getValue(): string;
}
