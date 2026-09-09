<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\CompilationState;
use App\Event\EmittableEvent\EventTypeInterface;
use App\Services\CompilationProgress;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CompilationProgressTest extends WebTestCase
{
    private CompilationProgress $compilationProgress;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $compilationProgress = self::getContainer()->get(CompilationProgress::class);
        \assert($compilationProgress instanceof CompilationProgress);
        $this->compilationProgress = $compilationProgress;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    #[DataProvider('getDataProvider')]
    public function testGet(EnvironmentSetup $setup, CompilationState $expected): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expected->value, $this->compilationProgress->get()->value);
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'awaiting: no job' => [
                'setup' => new EnvironmentSetup(),
                'expected' => CompilationState::AWAITING,
            ],
            'awaiting: has job, no sources' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup()),
                'expected' => CompilationState::AWAITING,
            ],
            'running: has job, has sources, no sources compiled' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()
                            ->withPath('Test/test1.yml'),
                        new SourceSetup()
                            ->withPath('Test/test2.yml'),
                    ]),
                'expected' => CompilationState::RUNNING,
            ],
            'failed: has job, has sources, has more than zero compile-failure event deliveries' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()
                            ->withPath('Test/test1.yml'),
                        new SourceSetup()
                            ->withPath('Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withType(EventTypeInterface::COMPILATION_FAILED),
                    ]),
                'expected' => CompilationState::FAILED,
            ],
            'complete: has job, has sources, no next source' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()
                            ->withPath('Test/test1.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml'),
                    ]),
                'expected' => CompilationState::COMPLETE,
            ],
        ];
    }
}
