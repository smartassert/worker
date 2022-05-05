<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Model\Document\Step;
use App\Tests\Mock\Entity\MockCallback;
use App\Tests\Mock\Entity\MockTest;
use webignition\YamlDocument\Document;

trait CreateFromStepEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromStepEventDataProvider(): array
    {
        $documentData = [
            'document-key' => 'document-value',
        ];

        $document = new Document((string) json_encode($documentData));

        return [
            StepPassedEvent::class => [
                'event' => new StepPassedEvent(
                    (new MockTest())->getMock(),
                    $document,
                    new Step(
                        new Document('type: step' . "\n" . 'payload: { name: "passing step" }')
                    )
                ),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_STEP_PASSED)
                    ->withGetPayloadCall($documentData)
                    ->getMock(),
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    (new MockTest())->getMock(),
                    $document,
                    new Step(
                        new Document('type: step' . "\n" . 'payload: { name: "failing step" }')
                    )
                ),
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_STEP_FAILED)
                    ->withGetPayloadCall($documentData)
                    ->getMock(),
            ],
        ];
    }
}
