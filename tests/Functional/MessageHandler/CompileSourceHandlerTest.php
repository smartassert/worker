<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Event\EmittableEvent\SourceCompilationFailedEvent;
use App\Event\EmittableEvent\SourceCompilationPassedEvent;
use App\Event\EmittableEvent\SourceCompilationStartedEvent;
use App\Message\CompileSourceMessage;
use App\MessageHandler\CompileSourceHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockErrorOutput;
use App\Tests\Mock\MockTestManifest;
use App\Tests\Mock\Services\MockCompiler;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventRecorder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\BasilCompilerModels\Model\TestManifestCollection;
use webignition\ObjectReflector\ObjectReflector;

class CompileSourceHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private CompileSourceHandler $handler;
    private EnvironmentFactory $environmentFactory;
    private EventRecorder $eventRecorder;

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
            $entityRemover->removeForEntity(Test::class);
            $entityRemover->removeForEntity(WorkerEvent::class);
        }

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;
    }

    public function testInvokeNoJob(): void
    {
        $handler = $this->handler;
        $handler(\Mockery::mock(CompileSourceMessage::class));

        self::assertSame(0, $this->eventRecorder->count());
    }

    public function testInvokeJobInWrongState(): void
    {
        $this->environmentFactory->create(
            (new EnvironmentSetup())
                ->withJobSetup(new JobSetup()),
        );

        $handler = $this->handler;
        $handler(\Mockery::mock(CompileSourceMessage::class));

        self::assertSame(0, $this->eventRecorder->count());
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
                ->withGetStepNamesCall(['step one', 'step two'])
                ->withGetBrowserCall('chrome')
                ->withGetUrlCall('https://example.com')
                ->withGetSourceCall($sourcePath)
                ->withGetTargetCall('Target.php')
                ->getMock(),
            (new MockTestManifest())
                ->withGetStepNamesCall(['step one', 'step two'])
                ->withGetBrowserCall('chrome')
                ->withGetUrlCall('https://example.com')
                ->withGetSourceCall($sourcePath)
                ->withGetTargetCall('Target.php')
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

        ($this->handler)($compileSourceMessage);

        self::assertSame(2, $this->eventRecorder->count());
        self::assertEquals(new SourceCompilationStartedEvent($sourcePath), $this->eventRecorder->get(0));
        self::assertEquals(
            new SourceCompilationPassedEvent($sourcePath, $testManifestCollection),
            $this->eventRecorder->get(1)
        );
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

        $handler = $this->handler;
        $handler($compileSourceMessage);

        self::assertSame(2, $this->eventRecorder->count());
        self::assertEquals(new SourceCompilationStartedEvent($sourcePath), $this->eventRecorder->get(0));

        $foo = $this->eventRecorder->get(1);
        self::assertInstanceOf(SourceCompilationFailedEvent::class, $foo);
        self::assertEquals(
            new SourceCompilationFailedEvent($sourcePath, $errorOutputData),
            $this->eventRecorder->get(1)
        );
    }
}
