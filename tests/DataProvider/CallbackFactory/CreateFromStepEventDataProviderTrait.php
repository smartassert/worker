<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Entity\Test;
use App\Entity\TestConfiguration;
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

        $passingStepSource = 'Test/passing-step.yml';
        $passingStepName = 'passing step';

        $failingStepSource = 'Test/failing-step.yml';
        $failingStepName = 'failing step';

        $passingStepData = ['type' => 'step', 'payload' => ['name' => $passingStepName]];
        $failingStepData = ['type' => 'step', 'payload' => ['name' => $failingStepName]];

        $passingStepDocument = new Document((string) json_encode($passingStepData));
        $failingStepDocument = new Document((string) json_encode($failingStepData));

        return [
            StepPassedEvent::class => [
                'event' => new StepPassedEvent(
                    Test::create($testConfiguration, $passingStepSource, '', 1, 1),
                    new Step($passingStepDocument)
                ),
                'expectedCallback' => CallbackEntity::create(
                    CallbackInterface::TYPE_STEP_PASSED,
                    '{{ job_label }}' . $passingStepSource . $passingStepName,
                    $passingStepData
                ),
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    Test::create($testConfiguration, $failingStepSource, '', 1, 1),
                    new Step($failingStepDocument)
                ),
                'expectedCallback' => CallbackEntity::create(
                    CallbackInterface::TYPE_STEP_FAILED,
                    '{{ job_label }}' . $failingStepSource . $failingStepName,
                    $failingStepData
                ),
            ],
        ];
    }
}
