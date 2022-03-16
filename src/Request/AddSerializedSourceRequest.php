<?php

declare(strict_types=1);

namespace App\Request;

use SmartAssert\YamlFile\Collection\ProviderInterface;

class AddSerializedSourceRequest
{
    public const KEY_SOURCE = 'source';

    public function __construct(
        public readonly ProviderInterface $provider,
    ) {
    }
}
