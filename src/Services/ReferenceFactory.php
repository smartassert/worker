<?php

declare(strict_types=1);

namespace App\Services;

class ReferenceFactory
{
    /**
     * @param non-empty-string $jobLabel
     * @param string[]         $components
     *
     * @return non-empty-string
     */
    public function create(string $jobLabel, array $components = []): string
    {
        array_unshift($components, $jobLabel);

        return md5(implode('', $components));
    }
}
