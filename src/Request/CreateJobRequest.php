<?php

declare(strict_types=1);

namespace App\Request;

class CreateJobRequest
{
    public const string KEY_LABEL = 'label';
    public const string KEY_EVENT_ADD_URL = 'event_add_url';
    public const string KEY_MAXIMUM_DURATION = 'maximum_duration_in_seconds';
    public const string KEY_SOURCE = 'source';
    public const string STATE_NOTIFY_URL = 'state_notify_url';

    public function __construct(
        public readonly string $label,
        public readonly string $eventAddUrl,
        public readonly ?int $maximumDurationInSeconds,
        public readonly string $source,
        public readonly ?string $stateNotifyUrl,
    ) {}
}
