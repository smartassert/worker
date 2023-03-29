<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Services\SourcePathFinder;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class SourcePathFinderTest extends AbstractBaseFunctionalTestCase
{
    private SourcePathFinder $sourcePathFinder;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $sourcePathFinder = self::getContainer()->get(SourcePathFinder::class);
        if ($sourcePathFinder instanceof SourcePathFinder) {
            $this->sourcePathFinder = $sourcePathFinder;
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider findNextNonCompiledPathDataProvider
     */
    public function testFindNextNonCompiledPath(
        EnvironmentSetup $setup,
        ?string $expectedNextNonCompiledSource
    ): void {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedNextNonCompiledSource, $this->sourcePathFinder->findNextNonCompiledPath());
    }

    /**
     * @return array<mixed>
     */
    public function findNextNonCompiledPathDataProvider(): array
    {
        $sourceSetups = [
            (new SourceSetup())
                ->withType(Source::TYPE_RESOURCE)
                ->withPath('Page/page1.yml'),
            (new SourceSetup())
                ->withType(Source::TYPE_TEST)
                ->withPath('Test/test1.yml'),
            (new SourceSetup())
                ->withType(Source::TYPE_TEST)
                ->withPath('Test/test2.yml'),
            (new SourceSetup())
                ->withType(Source::TYPE_RESOURCE)
                ->withPath('Page/page2.yml'),
        ];

        return [
            'no job' => [
                'setup' => new EnvironmentSetup(),
                'expectedNextNonCompiledSource' => null,
            ],
            'has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedNextNonCompiledSource' => null,
            ],
            'has job, has resource-only sources, no tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        $sourceSetups[0],
                        $sourceSetups[3],
                    ]),
                'expectedNextNonCompiledSource' => null,
            ],
            'has job, has sources, no tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups($sourceSetups),
                'expectedNextNonCompiledSource' => 'Test/test1.yml',
            ],
            'test exists for first test source' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups($sourceSetups)
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml'),
                    ]),
                'expectedNextNonCompiledSource' => 'Test/test2.yml',
            ],
            'test exists for all sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups($sourceSetups)
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml'),
                        (new TestSetup())
                            ->withSource('Test/test2.yml'),
                    ]),
                'expectedNextNonCompiledSource' => null,
            ],
        ];
    }
}
