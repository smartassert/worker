<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Model\Document\Step;
use webignition\YamlDocument\Document;

trait CreateFromStepEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromStepEventDataProvider(): array
    {
        $testConfiguration = \Mockery::mock(TestConfiguration::class);

        $passingStepSource = '/app/source/Test/passing-step.yml';
        $passingStepPath = 'Test/passing-step.yml';
        $passingStepName = 'passing step';

        $failingStepSource = '/app/source/Test/failing-step.yml';
        $failingStepPath = 'Test/failing-step.yml';
        $failingStepName = 'failing step';

        $passingStepData = ['type' => 'step', 'payload' => ['name' => $passingStepName]];
        $failingStepData = ['type' => 'step', 'payload' => ['name' => $failingStepName]];

        $passingStepDocument = new Document((string) json_encode($passingStepData));
        $failingStepDocument = new Document((string) json_encode($failingStepData));

        return [
            StepPassedEvent::class => [
                'event' => new StepPassedEvent(
                    Test::create($testConfiguration, $passingStepSource, '', 1, 1),
                    new Step($passingStepDocument),
                    $passingStepPath
                ),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::STEP_PASSED,
                    '{{ job_label }}' . $passingStepPath . $passingStepName,
                    $passingStepData
                ),
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    Test::create($testConfiguration, $failingStepSource, '', 1, 1),
                    new Step($failingStepDocument),
                    $failingStepPath
                ),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::STEP_FAILED,
                    '{{ job_label }}' . $failingStepPath . $failingStepName,
                    $failingStepData
                ),
            ],
        ];
    }
}
