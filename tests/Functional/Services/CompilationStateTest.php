<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Services\CompilationState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\CallbackSetup;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class CompilationStateTest extends AbstractBaseFunctionalTest
{
    private CompilationState $compilationState;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $compilationState = self::getContainer()->get(CompilationState::class);
        if ($compilationState instanceof CompilationState) {
            $this->compilationState = $compilationState;
        }

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

        self::assertSame($expectedState, (string) $this->compilationState);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'awaiting: no job' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => CompilationState::STATE_AWAITING,
            ],
            'awaiting: has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedState' => CompilationState::STATE_AWAITING,
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
                'expectedState' => CompilationState::STATE_RUNNING,
            ],
            'failed: has job, has sources, has more than zero compile-failure callbacks' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                        (new SourceSetup())
                            ->withPath('Test/test2.yml'),
                    ])
                    ->withCallbackSetups([
                        (new CallbackSetup())
                            ->withType(WorkerEvent::TYPE_COMPILATION_FAILED),
                    ]),
                'expectedState' => CompilationState::STATE_FAILED,
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
                'expectedState' => CompilationState::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<CompilationState::STATE_*> $expectedIsStates
     * @param array<CompilationState::STATE_*> $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->compilationState->is(...$expectedIsStates));
        self::assertFalse($this->compilationState->is(...$expectedIsNotStates));
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
                    CompilationState::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    CompilationState::STATE_RUNNING,
                    CompilationState::STATE_FAILED,
                    CompilationState::STATE_COMPLETE,
                    CompilationState::STATE_UNKNOWN,
                ],
            ],
            'awaiting: has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedIsStates' => [
                    CompilationState::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    CompilationState::STATE_RUNNING,
                    CompilationState::STATE_FAILED,
                    CompilationState::STATE_COMPLETE,
                    CompilationState::STATE_UNKNOWN,
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
                    CompilationState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    CompilationState::STATE_AWAITING,
                    CompilationState::STATE_FAILED,
                    CompilationState::STATE_COMPLETE,
                    CompilationState::STATE_UNKNOWN,
                ],
            ],
            'failed: has job, has sources, has more than zero compile-failure callbacks' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/test1.yml'),
                        (new SourceSetup())
                            ->withPath('Test/test2.yml'),
                    ])
                    ->withCallbackSetups([
                        (new CallbackSetup())
                            ->withType(WorkerEvent::TYPE_COMPILATION_FAILED),
                    ]),
                'expectedIsStates' => [
                    CompilationState::STATE_FAILED,
                ],
                'expectedIsNotStates' => [
                    CompilationState::STATE_AWAITING,
                    CompilationState::STATE_RUNNING,
                    CompilationState::STATE_COMPLETE,
                    CompilationState::STATE_UNKNOWN,
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
                    CompilationState::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    CompilationState::STATE_AWAITING,
                    CompilationState::STATE_RUNNING,
                    CompilationState::STATE_FAILED,
                    CompilationState::STATE_UNKNOWN,
                ],
            ],
        ];
    }
}
