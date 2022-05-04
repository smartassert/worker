<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Entity\Test;
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
        $documentData = [
            'document-key' => 'document-value',
        ];

        $document = new Document((string) json_encode($documentData));

        return [
            TestStartedEvent::class => [
                'event' => new TestStartedEvent(new Test(), $document),
                'expectedReferenceSource' => '',
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_TEST_STARTED, '', $documentData),
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent(new Test(), $document),
                'expectedReferenceSource' => '',
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_TEST_PASSED, '', $documentData),
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent(new Test(), $document),
                'expectedReferenceSource' => '',
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_TEST_FAILED, '', $documentData),
            ],
        ];
    }
}
