<?php

declare(strict_types=1);

namespace App\Request;

class CreateJobRequest
{
    public const KEY_LABEL = 'label';
    public const KEY_CALLBACK_URL = 'callback_url';
    public const KEY_MAXIMUM_DURATION = 'maximum_duration_in_seconds';
    public const KEY_SOURCE = 'source';

    public function __construct(
        public readonly string $label,
        public readonly string $callbackUrl,
        public readonly ?int $maximumDurationInSeconds,
        public readonly string $source,
    ) {
    }
}
