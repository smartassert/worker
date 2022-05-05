<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Test;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Tests\Mock\Entity\MockCallback;
use webignition\YamlDocument\Document;

trait CreateFromTestEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromTestEventEventDataProvider(): array
    {
        $documentData = [
            'document-key' => 'document-value',
        ];

        $document = new Document((string) json_encode($documentData));

        return [
            TestStartedEvent::class => [
                'event' => new TestStartedEvent(new Test(), $document),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_TEST_STARTED)
                    ->withGetPayloadCall($documentData)
                    ->getMock(),
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent(new Test(), $document),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_TEST_PASSED)
                    ->withGetPayloadCall($documentData)
                    ->getMock(),
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent(new Test(), $document),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_TEST_FAILED)
                    ->withGetPayloadCall($documentData)
                    ->getMock(),
            ],
        ];
    }
}
