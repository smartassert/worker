<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Services\CompilationProgress;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class CompilationProgressTest extends AbstractBaseFunctionalTest
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

    /**
     * @dataProvider getDataProvider
     */
    public function testGet(EnvironmentSetup $setup, string $expectedState): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedState, $this->compilationProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'awaiting: no job' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => CompilationProgress::STATE_AWAITING,
            ],
            'awaiting: has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedState' => CompilationProgress::STATE_AWAITING,
            ],
            'running: has job, has sources, no sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                        (new SourceSetup())
                            ->withPath('Test/test2.yml'),
                    ]),
                'expectedState' => CompilationProgress::STATE_RUNNING,
            ],
            'failed: has job, has sources, has more than zero compile-failure event deliveries' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                        (new SourceSetup())
                            ->withPath('Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withType(WorkerEventType::COMPILATION_FAILED),
                    ]),
                'expectedState' => CompilationProgress::STATE_FAILED,
            ],
            'complete: has job, has sources, no next source' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml'),
                    ]),
                'expectedState' => CompilationProgress::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<CompilationProgress::STATE_*> $expectedIsStates
     * @param array<CompilationProgress::STATE_*> $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->compilationProgress->is(...$expectedIsStates));
        self::assertFalse($this->compilationProgress->is(...$expectedIsNotStates));
    }

    /**
     * @return array<mixed>
     */
    public function isDataProvider(): array
    {
        return [
            'awaiting: no job' => [
                'setup' => new EnvironmentSetup(),
                'expectedIsStates' => [
                    CompilationProgress::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    CompilationProgress::STATE_RUNNING,
                    CompilationProgress::STATE_FAILED,
                    CompilationProgress::STATE_COMPLETE,
                    CompilationProgress::STATE_UNKNOWN,
                ],
            ],
            'awaiting: has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedIsStates' => [
                    CompilationProgress::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    CompilationProgress::STATE_RUNNING,
                    CompilationProgress::STATE_FAILED,
                    CompilationProgress::STATE_COMPLETE,
                    CompilationProgress::STATE_UNKNOWN,
                ],
            ],
            'running: has job, has sources, no sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                        (new SourceSetup())
                            ->withPath('Test/test2.yml'),
                    ]),
                'expectedIsStates' => [
                    CompilationProgress::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    CompilationProgress::STATE_AWAITING,
                    CompilationProgress::STATE_FAILED,
                    CompilationProgress::STATE_COMPLETE,
                    CompilationProgress::STATE_UNKNOWN,
                ],
            ],
            'failed: has job, has sources, has more than zero compile-failure event deliveries' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                        (new SourceSetup())
                            ->withPath('Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withType(WorkerEventType::COMPILATION_FAILED),
                    ]),
                'expectedIsStates' => [
                    CompilationProgress::STATE_FAILED,
                ],
                'expectedIsNotStates' => [
                    CompilationProgress::STATE_AWAITING,
                    CompilationProgress::STATE_RUNNING,
                    CompilationProgress::STATE_COMPLETE,
                    CompilationProgress::STATE_UNKNOWN,
                ],
            ],
            'complete: has job, has sources, no next source' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml'),
                    ]),
                'expectedIsStates' => [
                    CompilationProgress::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    CompilationProgress::STATE_AWAITING,
                    CompilationProgress::STATE_RUNNING,
                    CompilationProgress::STATE_FAILED,
                    CompilationProgress::STATE_UNKNOWN,
                ],
            ],
        ];
    }
}
