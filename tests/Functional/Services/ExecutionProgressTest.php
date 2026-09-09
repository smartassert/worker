<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Services\ExecutionProgress;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExecutionProgressTest extends WebTestCase
{
    private ExecutionProgress $executionProgress;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $executionProgress = self::getContainer()->get(ExecutionProgress::class);
        \assert($executionProgress instanceof ExecutionProgress);
        $this->executionProgress = $executionProgress;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    #[DataProvider('getDataProvider')]
    public function testGet(EnvironmentSetup $setup, ExecutionState $expected): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expected, $this->executionProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'awaiting: not has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup(),
                'expected' => ExecutionState::AWAITING,
            ],
            'running: not has finished tests and has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup()
                    ->withTestSetups([
                        new TestSetup()
                            ->withState(TestState::RUNNING),
                    ]),
                'expected' => ExecutionState::RUNNING,
            ],
            'awaiting: not has finished tests and not has running tests and has awaiting tests' => [
                'setup' => new EnvironmentSetup()
                    ->withTestSetups([
                        new TestSetup()
                            ->withState(TestState::AWAITING),
                    ]),
                'expected' => ExecutionState::AWAITING,
            ],
            'running: has complete tests and has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup()
                    ->withTestSetups([
                        new TestSetup()->withState(TestState::COMPLETE),
                        new TestSetup()->withState(TestState::RUNNING),
                    ]),
                'expected' => ExecutionState::RUNNING,
            ],
            'running: has complete tests and not has running tests and has awaiting tests' => [
                'setup' => new EnvironmentSetup()
                    ->withTestSetups([
                        new TestSetup()->withState(TestState::COMPLETE),
                        new TestSetup()->withState(TestState::AWAITING),
                    ]),
                'expected' => ExecutionState::RUNNING,
            ],
            'complete: has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup()
                    ->withTestSetups([
                        new TestSetup()->withState(TestState::COMPLETE),
                    ]),
                'expected' => ExecutionState::COMPLETE,
            ],
            'cancelled: has failed tests' => [
                'setup' => new EnvironmentSetup()
                    ->withTestSetups([
                        new TestSetup()->withState(TestState::FAILED),
                    ]),
                'expected' => ExecutionState::CANCELLED,
            ],
            'cancelled: has cancelled tests' => [
                'setup' => new EnvironmentSetup()
                    ->withTestSetups([
                        new TestSetup()->withState(TestState::CANCELLED),
                    ]),
                'expected' => ExecutionState::CANCELLED,
            ],
        ];
    }
}
