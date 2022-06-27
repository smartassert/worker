<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Enum\WorkerEventType;
use App\Event\StepEvent;
use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Model\Document\Document;
use App\Services\DocumentFactory\StepFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use webignition\TcpCliProxyClient\Client;
use webignition\TcpCliProxyClient\Exception\ClientCreationException;
use webignition\TcpCliProxyClient\Exception\SocketErrorException;
use webignition\TcpCliProxyClient\Handler;
use webignition\YamlDocument\Document as YamlDocument;
use webignition\YamlDocument\Factory;

class TestExecutor
{
    public function __construct(
        private readonly Client $delegatorClient,
        private readonly Factory $yamlDocumentFactory,
        private EventDispatcherInterface $eventDispatcher,
        private readonly TestPathNormalizer $testPathNormalizer,
        private readonly StepFactory $stepFactory,
    ) {
    }

    /**
     * @throws ClientCreationException
     * @throws SocketErrorException
     * @throws InvalidDocumentException
     * @throws InvalidStepException
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

        $this->yamlDocumentFactory->reset(function (YamlDocument $document) use ($test) {
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

    /**
     * @throws InvalidDocumentException
     * @throws InvalidStepException
     */
    private function dispatchStepProgressEvent(Test $test, YamlDocument $yamlDocument): void
    {
        $documentData = $yamlDocument->parse();
        $documentData = is_array($documentData) ? $documentData : [];
        $document = new Document($documentData);

        if ('step' === $document->getType()) {
            $step = $this->stepFactory->create($documentData);
            $path = $this->testPathNormalizer->normalize((string) $test->getSource());

            $eventType = $step->statusIsPassed() ? WorkerEventType::STEP_PASSED : WorkerEventType::STEP_FAILED;
            $event = new StepEvent($eventType, $step, $path, $test);

            $this->eventDispatcher->dispatch($event);
        }
    }
}
