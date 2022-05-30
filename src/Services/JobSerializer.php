<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;

class JobSerializer
{
    public function __construct(
        private readonly ReferenceFactory $referenceFactory,
    ) {
    }

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
            'references' => $this->createTestReferences($job),
        ];
    }

    /**
     * @return array<int, array{label: string, reference: string}>
     */
    private function createTestReferences(Job $job): array
    {
        $data = [];

        foreach ($job->getTestPaths() as $testPath) {
            $data[] = [
                'label' => $testPath,
                'reference' => $this->referenceFactory->create($job->getLabel(), [$testPath]),
            ];
        }

        return $data;
    }
}
