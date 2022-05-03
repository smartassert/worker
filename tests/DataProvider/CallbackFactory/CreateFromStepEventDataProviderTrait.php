<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Entity\Test;
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
        $documentData = [
            'document-key' => 'document-value',
        ];

        $document = new Document((string) json_encode($documentData));

        return [
            StepPassedEvent::class => [
                'event' => new StepPassedEvent(
                    new Test(),
                    $document,
                    new Step(
                        new Document('type: step' . "\n" . 'payload: { name: "passing step" }')
                    )
                ),
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_STEP_PASSED, '', $documentData),
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    new Test(),
                    $document,
                    new Step(
                        new Document('type: step' . "\n" . 'payload: { name: "failing step" }')
                    )
                ),
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_STEP_FAILED, '', $documentData),
            ],
        ];
    }
}
