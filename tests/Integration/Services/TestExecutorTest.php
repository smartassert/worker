<?php

declare(strict_types=1);

namespace App\Tests\Integration\Services;

use App\Event\EventInterface;
use App\Event\StepPassedEvent;
use App\Model\Document\Step;
use App\Services\Compiler;
use App\Services\TestExecutor;
use App\Services\TestFactory;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use webignition\BasilCompilerModels\SuiteManifest;
use webignition\ObjectReflector\ObjectReflector;
use webignition\YamlDocument\Document;

class TestExecutorTest extends AbstractTestCreationTest
{
    private TestExecutor $testExecutor;
    private Compiler $compiler;
    private TestFactory $testFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $testExecutor = self::getContainer()->get(TestExecutor::class);
        \assert($testExecutor instanceof TestExecutor);
        $this->testExecutor = $testExecutor;

        $compiler = self::getContainer()->get(Compiler::class);
        \assert($compiler instanceof Compiler);
        $this->compiler = $compiler;

        $testFactory = self::getContainer()->get(TestFactory::class);
        \assert($testFactory instanceof TestFactory);
        $this->testFactory = $testFactory;
    }

    /**
     * @dataProvider executeSuccessDataProvider
     *
     * @param string[] $sources
     */
    public function testExecute(
        array $sources,
        string $testSource,
        ExpectedDispatchedEventCollection $expectedDispatchedEvents
    ): void {
        foreach ($sources as $source) {
            $this->localSourceStoreHandler->copyFixture($source);
        }

        /** @var SuiteManifest $suiteManifest */
        $suiteManifest = $this->compiler->compile($testSource);

        self::assertInstanceOf(SuiteManifest::class, $suiteManifest);

        $tests = $this->testFactory->createFromManifestCollection($suiteManifest->getTestManifests());

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls($expectedDispatchedEvents)
            ->getMock()
        ;

        ObjectReflector::setProperty(
            $this->testExecutor,
            TestExecutor::class,
            'eventDispatcher',
            $eventDispatcher
        );

        foreach ($tests as $test) {
            $this->testExecutor->execute($test);
        }
    }

    /**
     * @return array<mixed>
     */
    public function executeSuccessDataProvider(): array
    {
        return [
            'Test/chrome-open-index.yml: single-browser test (chrome)' => [
                'sources' => [
                    'Page/index.yml',
                    'Test/chrome-open-index.yml',
                ],
                'testSource' => 'Test/chrome-open-index.yml',
                'expectedDispatchedEventCollection' => new ExpectedDispatchedEventCollection([
                    new ExpectedDispatchedEvent(
                        function (EventInterface $event): bool {
                            self::assertInstanceOf(StepPassedEvent::class, $event);

                            $expectedDocument = new Step(new Document((string) json_encode([
                                'type' => 'step',
                                'payload' => [
                                    'name' => 'verify page is open',
                                    'status' => 'passed',
                                    'statements' => [
                                        [
                                            'type' => 'assertion',
                                            'source' => '$page.url is "http://html-fixtures/index.html"',
                                            'status' => 'passed',
                                            'transformations' => [
                                                [
                                                    'type' => 'resolution',
                                                    'source' => '$page.url is $index.url',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ])));

                            if ($event instanceof StepPassedEvent) {
                                self::assertEquals($expectedDocument, $event->getDocument());
                            }

                            return true;
                        }
                    ),
                ]),
            ],
            'Test/chrome-open-index.yml: single-browser test (firefox)' => [
                'sources' => [
                    'Test/firefox-open-index.yml',
                ],
                'testSource' => 'Test/firefox-open-index.yml',
                'expectedDispatchedEventCollection' => new ExpectedDispatchedEventCollection([
                    new ExpectedDispatchedEvent(
                        function (EventInterface $event): bool {
                            self::assertInstanceOf(StepPassedEvent::class, $event);

                            $expectedDocument = new Step(new Document((string) json_encode([
                                'type' => 'step',
                                'payload' => [
                                    'name' => 'verify page is open',
                                    'status' => 'passed',
                                    'statements' => [
                                        [
                                            'type' => 'assertion',
                                            'source' => '$page.url is "http://html-fixtures/index.html"',
                                            'status' => 'passed',
                                        ],
                                    ],
                                ],
                            ])));

                            if ($event instanceof StepPassedEvent) {
                                self::assertEquals($event->getDocument(), $expectedDocument);
                            }

                            return true;
                        }
                    ),
                ]),
            ],
            'Test/chrome-firefox-open-index.yml: multi-browser test' => [
                'sources' => [
                    'Test/chrome-firefox-open-index.yml',
                ],
                'testSource' => 'Test/chrome-firefox-open-index.yml',
                'expectedDispatchedEventCollection' => new ExpectedDispatchedEventCollection([
                    new ExpectedDispatchedEvent(
                        function (EventInterface $event): bool {
                            self::assertInstanceOf(StepPassedEvent::class, $event);

                            $expectedDocument = new Step(new Document((string) json_encode([
                                'type' => 'step',
                                'payload' => [
                                    'name' => 'verify page is open',
                                    'status' => 'passed',
                                    'statements' => [
                                        [
                                            'type' => 'assertion',
                                            'source' => '$page.url is "http://html-fixtures/index.html"',
                                            'status' => 'passed',
                                        ],
                                    ],
                                ],
                            ])));

                            if ($event instanceof StepPassedEvent) {
                                self::assertEquals($event->getDocument(), $expectedDocument);
                            }

                            return true;
                        }
                    ),
                    new ExpectedDispatchedEvent(
                        function (EventInterface $event): bool {
                            self::assertInstanceOf(StepPassedEvent::class, $event);

                            $expectedDocument = new Step(new Document((string) json_encode([
                                'type' => 'step',
                                'payload' => [
                                    'name' => 'verify page is open',
                                    'status' => 'passed',
                                    'statements' => [
                                        [
                                            'type' => 'assertion',
                                            'source' => '$page.url is "http://html-fixtures/index.html"',
                                            'status' => 'passed',
                                        ],
                                    ],
                                ],
                            ])));

                            if ($event instanceof StepPassedEvent) {
                                self::assertEquals($event->getDocument(), $expectedDocument);
                            }

                            return true;
                        }
                    ),
                ]),
            ],
        ];
    }
}
