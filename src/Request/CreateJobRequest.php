<?php

declare(strict_types=1);

namespace App\Request;

class CreateJobRequest
{
    public const KEY_LABEL = 'label';
    public const KEY_EVENT_DELIVERY_URL = 'event_delivery_url';
    public const KEY_MAXIMUM_DURATION = 'maximum_duration_in_seconds';
    public const KEY_SOURCE = 'source';

    public function __construct(
        public readonly string $label,
        public readonly string $eventDeliveryUrl,
        public readonly ?int $maximumDurationInSeconds,
        public readonly string $source,
    ) {
    }
}
