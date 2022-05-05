<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use webignition\YamlDocument\Document;

trait CreateFromTestEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromTestEventEventDataProvider(): array
    {
        $testSource = 'Test/' . md5((string) rand()) . '.yml';

        $documentData = [
            'type' => 'test',
            'payload' => [
                'path' => $testSource,
            ],
        ];

        $document = new Document((string) json_encode($documentData));

        $test = Test::create(\Mockery::mock(TestConfiguration::class), $testSource, '', 1, 1);

        return [
            TestStartedEvent::class => [
                'event' => new TestStartedEvent($test, $document),
                'expectedReferenceSource' => '{{ job_label }}' . $testSource,
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_TEST_STARTED, '', $documentData),
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent($test, $document),
                'expectedReferenceSource' => '{{ job_label }}' . $testSource,
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_TEST_PASSED, '', $documentData),
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent($test, $document),
                'expectedReferenceSource' => '{{ job_label }}' . $testSource,
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_TEST_FAILED, '', $documentData),
            ],
        ];
    }
}
