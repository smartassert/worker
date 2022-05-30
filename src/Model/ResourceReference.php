<?php

declare(strict_types=1);

namespace App\Model;

class ResourceReference implements \JsonSerializable
{
    public function __construct(
        public readonly string $label,
        public readonly string $reference,
    ) {
    }

    /**
     * @return array{label: string, reference: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'reference' => $this->reference,
        ];
    }
}
