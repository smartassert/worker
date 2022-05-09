<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\Test as TestEntity;
use App\Entity\TestConfiguration;
use App\Entity\WorkerEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Model\Document\Test as TestDocument;
use webignition\YamlDocument\Document;

trait CreateFromTestEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromTestEventEventDataProvider(): array
    {
        $testRelativeSource = 'Test/' . md5((string) rand()) . '.yml';
        $testAbsoluteSource = '/app/source/' . $testRelativeSource;

        $documentData = [
            'type' => 'test',
            'payload' => [
                'path' => $testRelativeSource,
            ],
        ];

        $testDocument = new TestDocument(
            new Document((string) json_encode($documentData))
        );

        $test = TestEntity::create(\Mockery::mock(TestConfiguration::class), $testAbsoluteSource, '', 1, 1);

        return [
            TestStartedEvent::class => [
                'event' => new TestStartedEvent($test, $testDocument),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEvent::TYPE_TEST_STARTED,
                    '{{ job_label }}' . $testRelativeSource,
                    $documentData
                ),
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent($test, $testDocument),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEvent::TYPE_TEST_PASSED,
                    '{{ job_label }}' . $testRelativeSource,
                    $documentData
                ),
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent($test, $testDocument),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEvent::TYPE_TEST_FAILED,
                    '{{ job_label }}' . $testRelativeSource,
                    $documentData
                ),
            ],
        ];
    }
}
