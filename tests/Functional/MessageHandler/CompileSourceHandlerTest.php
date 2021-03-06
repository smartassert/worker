<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\WorkerEvent;
use App\Event\AbstractSourceEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Message\CompileSourceMessage;
use App\MessageHandler\CompileSourceHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockErrorOutput;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\MockTestManifest;
use App\Tests\Mock\Services\MockCompiler;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\BasilCompilerModels\Model\TestManifestCollection;
use webignition\ObjectReflector\ObjectReflector;

class CompileSourceHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private CompileSourceHandler $handler;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $compileSourceHandler = self::getContainer()->get(CompileSourceHandler::class);
        \assert($compileSourceHandler instanceof CompileSourceHandler);
        $this->handler = $compileSourceHandler;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    public function testInvokeNoJob(): void
    {
        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, CompileSourceHandler::class, 'eventDispatcher', $eventDispatcher);

        $handler = $this->handler;
        $handler(\Mockery::mock(CompileSourceMessage::class));
    }

    public function testInvokeJobInWrongState(): void
    {
        $this->environmentFactory->create(
            (new EnvironmentSetup())
                ->withJobSetup(new JobSetup()),
        );

        $eventDispatcher = (new MockEventDispatcher())
            ->withoutDispatchCall()
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, CompileSourceHandler::class, 'eventDispatcher', $eventDispatcher);

        $handler = $this->handler;
        $handler(\Mockery::mock(CompileSourceMessage::class));
    }

    public function testInvokeCompileSuccess(): void
    {
        $sourcePath = 'Test/test1.yml';
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(
                (new JobSetup())->withTestPaths([$sourcePath])
            )
            ->withSourceSetups([
                (new SourceSetup())->withPath($sourcePath),
            ])
        ;

        $this->environmentFactory->create($environmentSetup);

        $compileSourceMessage = new CompileSourceMessage($sourcePath);

        $testManifestCollection = new TestManifestCollection([
            (new MockTestManifest())
                ->withGetStepNamesCall([])
                ->getMock(),
            (new MockTestManifest())
                ->withGetStepNamesCall([])
                ->getMock(),
        ]);

        $compiler = (new MockCompiler())
            ->withCompileCall(
                $compileSourceMessage->path,
                $testManifestCollection
            )
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            CompileSourceHandler::class,
            'compiler',
            $compiler
        );

        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (SourceCompilationStartedEvent $actualEvent) use ($sourcePath, &$eventExpectationCount) {
                        self::assertSame(
                            $sourcePath,
                            ObjectReflector::getProperty($actualEvent, 'source', AbstractSourceEvent::class)
                        );
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
                new ExpectedDispatchedEvent(
                    function (
                        SourceCompilationPassedEvent $actualEvent
                    ) use (
                        $sourcePath,
                        $testManifestCollection,
                        &$eventExpectationCount
                    ) {
                        self::assertSame(
                            $sourcePath,
                            ObjectReflector::getProperty($actualEvent, 'source', AbstractSourceEvent::class)
                        );
                        self::assertSame($testManifestCollection, $actualEvent->getTestManifestCollection());
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        $this->setCompileSourceHandlerEventDispatcher($eventDispatcher);

        ($this->handler)($compileSourceMessage);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }

    public function testInvokeCompileFailure(): void
    {
        $sourcePath = 'Test/test1.yml';
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(
                (new JobSetup())->withTestPaths([$sourcePath])
            )
            ->withSourceSetups([
                (new SourceSetup())->withPath($sourcePath),
            ])
        ;

        $this->environmentFactory->create($environmentSetup);

        $compileSourceMessage = new CompileSourceMessage($sourcePath);

        $errorOutputData = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        $errorOutput = (new MockErrorOutput())
            ->withToArrayCall($errorOutputData)
            ->getMock()
        ;

        $compiler = (new MockCompiler())
            ->withCompileCall(
                $compileSourceMessage->path,
                $errorOutput
            )
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            CompileSourceHandler::class,
            'compiler',
            $compiler
        );

        $eventExpectationCount = 0;

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (SourceCompilationStartedEvent $actualEvent) use ($sourcePath, &$eventExpectationCount) {
                        self::assertSame(
                            $sourcePath,
                            ObjectReflector::getProperty($actualEvent, 'source', AbstractSourceEvent::class)
                        );
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
                new ExpectedDispatchedEvent(
                    function (
                        SourceCompilationFailedEvent $actualEvent
                    ) use (
                        $sourcePath,
                        $errorOutputData,
                        &$eventExpectationCount
                    ) {
                        $payload = $actualEvent->getPayload();
                        self::assertArrayHasKey('source', $payload);
                        self::assertSame($sourcePath, $payload['source']);

                        self::assertArrayHasKey('output', $payload);
                        self::assertSame($errorOutputData, $payload['output']);
                        ++$eventExpectationCount;

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        $this->setCompileSourceHandlerEventDispatcher($eventDispatcher);

        $handler = $this->handler;
        $handler($compileSourceMessage);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }

    private function setCompileSourceHandlerEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        ObjectReflector::setProperty($this->handler, CompileSourceHandler::class, 'eventDispatcher', $eventDispatcher);
    }
}
