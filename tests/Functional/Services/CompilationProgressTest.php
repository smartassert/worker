<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\CompilationState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
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
    public function testGet(EnvironmentSetup $setup, CompilationState $expectedState): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedState->value, $this->compilationProgress->get()->value);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'awaiting: no job' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => CompilationState::AWAITING,
            ],
            'awaiting: has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedState' => CompilationState::AWAITING,
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
                'expectedState' => CompilationState::RUNNING,
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
                            ->withScope(WorkerEventScope::COMPILATION)
                            ->withOutcome(WorkerEventOutcome::FAILED),
                    ]),
                'expectedState' => CompilationState::FAILED,
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
                            ->withSource('Test/test1.yml'),
                    ]),
                'expectedState' => CompilationState::COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param CompilationState[] $expectedIsStates
     * @param CompilationState[] $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->compilationProgress->is($expectedIsStates));
        self::assertFalse($this->compilationProgress->is($expectedIsNotStates));
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
                    CompilationState::AWAITING,
                ],
                'expectedIsNotStates' => [
                    CompilationState::RUNNING,
                    CompilationState::FAILED,
                    CompilationState::COMPLETE,
                    CompilationState::UNKNOWN,
                ],
            ],
            'awaiting: has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedIsStates' => [
                    CompilationState::AWAITING,
                ],
                'expectedIsNotStates' => [
                    CompilationState::RUNNING,
                    CompilationState::FAILED,
                    CompilationState::COMPLETE,
                    CompilationState::UNKNOWN,
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
                    CompilationState::RUNNING,
                ],
                'expectedIsNotStates' => [
                    CompilationState::AWAITING,
                    CompilationState::FAILED,
                    CompilationState::COMPLETE,
                    CompilationState::UNKNOWN,
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
                            ->withScope(WorkerEventScope::COMPILATION)
                            ->withOutcome(WorkerEventOutcome::FAILED),
                    ]),
                'expectedIsStates' => [
                    CompilationState::FAILED,
                ],
                'expectedIsNotStates' => [
                    CompilationState::AWAITING,
                    CompilationState::RUNNING,
                    CompilationState::COMPLETE,
                    CompilationState::UNKNOWN,
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
                            ->withSource('Test/test1.yml'),
                    ]),
                'expectedIsStates' => [
                    CompilationState::COMPLETE,
                ],
                'expectedIsNotStates' => [
                    CompilationState::AWAITING,
                    CompilationState::RUNNING,
                    CompilationState::FAILED,
                    CompilationState::UNKNOWN,
                ],
            ],
        ];
    }
}
