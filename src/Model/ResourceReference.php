<?php

declare(strict_types=1);

namespace App\Model;

class ResourceReference
{
    public function __construct(
        public readonly string $label,
        public readonly string $reference,
    ) {
    }

    /**
     * @return array{label: string, reference: string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'reference' => $this->reference,
        ];
    }
}
