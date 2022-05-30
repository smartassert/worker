<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;

class JobSerializer
{
    /**
     * @return array<mixed>
     */
    public function serialize(Job $job): array
    {
        return [
            'label' => $job->getLabel(),
            'event_delivery_url' => $job->getEventDeliveryUrl(),
            'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
            'test_paths' => $job->getTestPaths(),
        ];
    }
}
