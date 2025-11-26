<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Event\EmittableEvent\SourceCompilationPassedEvent;
use App\Repository\TestRepository;
use App\Services\TestFactory;
use App\Tests\Services\EntityRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\BasilCompilerModels\Model\TestManifest;
use webignition\BasilCompilerModels\Model\TestManifestCollection;
use webignition\ObjectReflector\ObjectReflector;

class TestFactoryTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    private TestFactory $factory;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var array<string, TestManifest>
     */
    private array $testManifests;

    /**
     * @var array<string, Test>
     */
    private array $tests;

    protected function setUp(): void
    {
        parent::setUp();

        $testFactory = self::getContainer()->get(TestFactory::class);
        \assert($testFactory instanceof TestFactory);
        $this->factory = $testFactory;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $this->testManifests = [
            'chrome' => new TestManifest(
                'chrome',
                'http://example.com',
                'Tests/chrome_test.yml',
                'GeneratedChromeTest.php',
                ['step 1', 'step 2']
            ),
            'firefox' => new TestManifest(
                'firefox',
                'http://example.com',
                'Tests/firefox_test.yml',
                'GeneratedFirefoxTest.php',
                ['step 1', 'step 2', 'step 3']
            ),
        ];

        $this->tests = [
            'chrome' => new Test(
                'chrome',
                'http://example.com',
                'Tests/chrome_test.yml',
                'GeneratedChromeTest.php',
                ['step 1', 'step 2'],
                1
            ),
            'firefox' => new Test(
                'firefox',
                'http://example.com',
                'Tests/firefox_test.yml',
                'GeneratedFirefoxTest.php',
                ['step 1', 'step 2', 'step 3'],
                2
            ),
        ];

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider createFromTestManifestCollectionDataProvider
     *
     * @param string[] $manifestKeys
     * @param string[] $expectedTestKeys
     */
    public function testCreateFromTestManifestCollection(array $manifestKeys, array $expectedTestKeys): void
    {
        $manifests = $this->createTestManifestCollection($manifestKeys);
        $expectedTests = $this->createExpectedTestCollection($expectedTestKeys);

        $tests = $this->factory->createFromManifestCollection($manifests);
        self::assertCount(count($expectedTests), $tests);

        foreach ($tests as $testIndex => $test) {
            $this->assertCreatedTest($test, $expectedTests[$testIndex] ?? null);
        }
    }

    /**
     * @return array<mixed>
     */
    public static function createFromTestManifestCollectionDataProvider(): array
    {
        return [
            'empty' => [
                'manifestKeys' => [],
                'expectedTestKeys' => [],
            ],
            'single manifest' => [
                'manifestKeys' => [
                    'chrome',
                ],
                'expectedTestKeys' => [
                    'chrome',
                ],
            ],
            'two manifests' => [
                'manifestKeys' => [
                    'chrome',
                    'firefox',
                ],
                'expectedTestKeys' => [
                    'chrome',
                    'firefox',
                ],
            ],
        ];
    }

    public function testCreateFromSourceCompileSuccessEvent(): void
    {
        $this->doSourceCompileSuccessEventDrivenTest(function (TestManifestCollection $collection) {
            $event = new SourceCompilationPassedEvent('Test/test.yml', $collection);

            return $this->factory->createFromSourceCompileSuccessEvent($event);
        });
    }

    public function testSubscribesToSourceCompileSuccessEvent(): void
    {
        $this->doSourceCompileSuccessEventDrivenTest(function (TestManifestCollection $collection) {
            $event = new SourceCompilationPassedEvent('Test/test.yml', $collection);
            $this->eventDispatcher->dispatch($event);

            $testRepository = self::getContainer()->get(TestRepository::class);
            \assert($testRepository instanceof TestRepository);

            return $testRepository->findAll();
        });
    }

    private function doSourceCompileSuccessEventDrivenTest(callable $callable): void
    {
        $testManifestCollection = new TestManifestCollection(
            $this->createTestManifestCollection(['chrome', 'firefox'])
        );

        $tests = $callable($testManifestCollection);

        $expectedTests = $this->createExpectedTestCollection(['chrome', 'firefox']);
        self::assertCount(count($expectedTests), $tests);

        foreach ($tests as $testIndex => $test) {
            $this->assertCreatedTest($test, $expectedTests[$testIndex] ?? null);
        }
    }

    private function assertTestEquals(Test $expected, Test $actual): void
    {
        self::assertSame($expected->browser, $actual->browser);
        self::assertSame($expected->url, $actual->url);
        self::assertSame($expected->getSource(), $actual->getSource());
        self::assertSame($expected->getTarget(), $actual->getTarget());
        self::assertSame($expected->getState(), $actual->getState());
        self::assertSame($expected->getStepNames(), $actual->getStepNames());
        self::assertSame($expected->position, $actual->position);
    }

    private function assertCreatedTest(Test $test, ?Test $expectedTest): void
    {
        $testId = ObjectReflector::getProperty($test, 'id');
        self::assertIsInt($testId);
        self::assertGreaterThan(0, $testId);
        self::assertInstanceOf(Test::class, $expectedTest);

        $this->assertTestEquals($expectedTest, $test);
    }

    /**
     * @param string[] $manifestKeys
     *
     * @return TestManifest[]
     */
    private function createTestManifestCollection(array $manifestKeys): array
    {
        $manifests = [];
        foreach ($manifestKeys as $manifestKey) {
            $manifest = $this->testManifests[$manifestKey] ?? null;
            if ($manifest instanceof TestManifest) {
                $manifests[] = $manifest;
            }
        }

        return $manifests;
    }

    /**
     * @param string[] $expectedTestKeys
     *
     * @return Test[]
     */
    private function createExpectedTestCollection(array $expectedTestKeys): array
    {
        $expectedTests = [];
        foreach ($expectedTestKeys as $expectedTestKey) {
            $expectedTest = $this->tests[$expectedTestKey] ?? null;
            if ($expectedTest instanceof Test) {
                $expectedTests[] = $expectedTest;
            }
        }

        return $expectedTests;
    }
}
