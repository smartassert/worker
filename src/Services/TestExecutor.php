<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Model\Document\Step;
use Psr\EventDispatcher\EventDispatcherInterface;
use webignition\TcpCliProxyClient\Client;
use webignition\TcpCliProxyClient\Handler;
use webignition\YamlDocument\Document;

class TestExecutor
{
    public function __construct(
        private readonly Client $delegatorClient,
        private readonly YamlDocumentFactory $yamlDocumentFactory,
        private EventDispatcherInterface $eventDispatcher,
        private readonly TestPathMutator $testPathMutator,
    ) {
    }

    public function execute(Test $test): void
    {
        $delegatorClientHandler = new Handler();
        $delegatorClientHandler
            ->addCallback(function (string $buffer) {
                if (false === ctype_digit($buffer) && '' !== trim($buffer)) {
                    $this->yamlDocumentFactory->process($buffer);
                }
            })
        ;

        $this->yamlDocumentFactory->setOnDocumentCreated(function (Document $document) use ($test) {
            $this->dispatchStepProgressEvent($test, $document);
        });

        $this->yamlDocumentFactory->start();

        $this->delegatorClient->request(
            sprintf(
                './bin/delegator --browser %s %s',
                $test->getConfiguration()->getBrowser(),
                $test->getTarget()
            ),
            $delegatorClientHandler
        );

        $this->yamlDocumentFactory->stop();
    }

    private function dispatchStepProgressEvent(Test $test, Document $document): void
    {
        $step = new Step($document);

        if ($step->isStep()) {
            $path = $this->testPathMutator->removeCompilerSourceDirectoryFromPath((string) $test->getSource());

            if ($step->statusIsPassed()) {
                $this->eventDispatcher->dispatch(new StepPassedEvent($test, $step, $path));
            }

            if ($step->statusIsFailed()) {
                $this->eventDispatcher->dispatch(new StepFailedEvent($test, $step, $path));
            }
        }
    }
}
