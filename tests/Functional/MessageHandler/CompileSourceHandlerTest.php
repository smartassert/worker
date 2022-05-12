<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Source;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Message\CompileSourceMessage;
use App\MessageHandler\CompileSourceHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Mock\MockSuiteManifest;
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
use webignition\BasilCompilerModels\ErrorOutputInterface;
use webignition\BasilCompilerModels\TestManifest;
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
            ->withJobSetup(new JobSetup())
            ->withSourceSetups([
                (new SourceSetup())->withPath($sourcePath),
            ])
        ;

        $this->environmentFactory->create($environmentSetup);

        $compileSourceMessage = new CompileSourceMessage($sourcePath);

        $testManifests = [
            \Mockery::mock(TestManifest::class),
            \Mockery::mock(TestManifest::class),
        ];

        $suiteManifest = (new MockSuiteManifest())
            ->withGetTestManifestsCall($testManifests)
            ->getMock()
        ;

        $compiler = (new MockCompiler())
            ->withCompileCall(
                $compileSourceMessage->getPath(),
                $suiteManifest
            )
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->handler,
            CompileSourceHandler::class,
            'compiler',
            $compiler
        );

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (SourceCompilationStartedEvent $actualEvent) use ($sourcePath) {
                        self::assertSame($sourcePath, $actualEvent->getSource());

                        return true;
                    },
                ),
                new ExpectedDispatchedEvent(
                    function (SourceCompilationPassedEvent $actualEvent) use ($sourcePath, $suiteManifest) {
                        self::assertSame($sourcePath, $actualEvent->getSource());
                        self::assertSame($suiteManifest, $actualEvent->getOutput());

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        $this->setCompileSourceHandlerEventDispatcher($eventDispatcher);

        $handler = $this->handler;
        $handler($compileSourceMessage);
    }

    public function testInvokeCompileFailure(): void
    {
        $sourcePath = 'Test/test1.yml';
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withSourceSetups([
                (new SourceSetup())->withPath($sourcePath),
            ])
        ;

        $this->environmentFactory->create($environmentSetup);

        $compileSourceMessage = new CompileSourceMessage($sourcePath);
        $errorOutput = \Mockery::mock(ErrorOutputInterface::class);

        $compiler = (new MockCompiler())
            ->withCompileCall(
                $compileSourceMessage->getPath(),
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

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls(new ExpectedDispatchedEventCollection([
                new ExpectedDispatchedEvent(
                    function (SourceCompilationStartedEvent $actualEvent) use ($sourcePath) {
                        self::assertSame($sourcePath, $actualEvent->getSource());

                        return true;
                    },
                ),
                new ExpectedDispatchedEvent(
                    function (SourceCompilationFailedEvent $actualEvent) use ($sourcePath, $errorOutput) {
                        self::assertSame($sourcePath, $actualEvent->getSource());
                        self::assertSame($errorOutput, $actualEvent->getOutput());

                        return true;
                    },
                ),
            ]))
            ->getMock()
        ;

        $this->setCompileSourceHandlerEventDispatcher($eventDispatcher);

        $handler = $this->handler;
        $handler($compileSourceMessage);
    }

    private function setCompileSourceHandlerEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        ObjectReflector::setProperty($this->handler, CompileSourceHandler::class, 'eventDispatcher', $eventDispatcher);
    }
}
