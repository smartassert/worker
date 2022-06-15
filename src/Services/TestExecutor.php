<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Enum\WorkerEventType;
use App\Event\StepEvent;
use App\Model\Document\Step;
use Psr\EventDispatcher\EventDispatcherInterface;
use webignition\TcpCliProxyClient\Client;
use webignition\TcpCliProxyClient\Exception\ClientCreationException;
use webignition\TcpCliProxyClient\Exception\SocketErrorException;
use webignition\TcpCliProxyClient\Handler;
use webignition\YamlDocument\Document;
use webignition\YamlDocument\Factory;

class TestExecutor
{
    public function __construct(
        private readonly Client $delegatorClient,
        private readonly Factory $yamlDocumentFactory,
        private EventDispatcherInterface $eventDispatcher,
        private readonly TestPathMutator $testPathMutator,
    ) {
    }

    /**
     * @throws ClientCreationException
     * @throws SocketErrorException
     */
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

        $this->yamlDocumentFactory->reset(function (Document $document) use ($test) {
            $this->dispatchStepProgressEvent($test, $document);
        });

        $this->delegatorClient->request(
            sprintf(
                './bin/delegator --browser %s %s',
                $test->getBrowser(),
                $test->getTarget()
            ),
            $delegatorClientHandler
        );

        $this->yamlDocumentFactory->stop();
    }

    private function dispatchStepProgressEvent(Test $test, Document $document): void
    {
        $step = new Step($document);

        if (false === $step->isStep()) {
            return;
        }

        $path = $this->testPathMutator->removeCompilerSourceDirectoryFromPath((string) $test->getSource());

        $eventType = $step->statusIsPassed() ? WorkerEventType::STEP_PASSED : WorkerEventType::STEP_FAILED;
        $event = new StepEvent($eventType, $step, $path, $test);

        $this->eventDispatcher->dispatch($event);
    }
}
