<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\StepEvent;
use App\Event\TestEvent;
use App\Services\TestProgressHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockEventDispatcher;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\ExpectedDispatchedEvent;
use App\Tests\Model\ExpectedDispatchedEventCollection;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use webignition\ObjectReflector\ObjectReflector;
use webignition\YamlDocument\Document as YamlDocument;

class TestProgressHandlerTest extends AbstractBaseFunctionalTest
{
    private TestProgressHandler $handler;
    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $testProgressHandler = self::getContainer()->get(TestProgressHandler::class);
        \assert($testProgressHandler instanceof TestProgressHandler);
        $this->handler = $testProgressHandler;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withTestSetups([
                new TestSetup(),
            ])
        ;

        $environment = $environmentFactory->create($environmentSetup);

        $tests = $environment->getTests();
        $test = $tests[0];
        \assert($test instanceof Test);
        $this->test = $test;
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(YamlDocument $yamlDocument, callable $expectedDispatchedEventCollectionCreator): void
    {
        $eventExpectationCount = 0;

        $expectedDispatchedEvents = $expectedDispatchedEventCollectionCreator($this->test, $eventExpectationCount);

        $eventDispatcher = (new MockEventDispatcher())
            ->withDispatchCalls($expectedDispatchedEvents)
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, $this->handler::class, 'eventDispatcher', $eventDispatcher);

        $this->handler->handle($this->test, $yamlDocument);

        self::assertGreaterThan(0, $eventExpectationCount, 'Mock event dispatcher expectations did not run');
    }

    /**
     * @return array<mixed>
     */
    public function handleDataProvider(): array
    {
        return [
            'step, passed' => [
                'yamlDocument' => new YamlDocument(
                    <<< 'EOF'
                    type: step
                    payload:
                      name: 'verify page is open'
                      status: passed
                      statements:
                        -
                          type: assertion
                          source: '$page.url is "http://example.com/"'
                          status: passed
                    EOF
                ),
                'expectedDispatchedEventCollectionCreator' => function (
                    Test $test,
                    int &$eventExpectationCount
                ): ExpectedDispatchedEventCollection {
                    return new ExpectedDispatchedEventCollection([
                        new ExpectedDispatchedEvent(
                            function (StepEvent $actualEvent) use ($test, &$eventExpectationCount) {
                                self::assertSame($test, $actualEvent->getTest());

                                self::assertSame(WorkerEventScope::STEP, $actualEvent->getScope());
                                self::assertSame(WorkerEventOutcome::PASSED, $actualEvent->getOutcome());
                                self::assertSame(
                                    [
                                        'source' => '/app/source/Test/test.yml',
                                        'document' => [
                                            'type' => 'step',
                                            'payload' => [
                                                'name' => 'verify page is open',
                                                'status' => 'passed',
                                                'statements' => [
                                                    [
                                                        'type' => 'assertion',
                                                        'source' => '$page.url is "http://example.com/"',
                                                        'status' => 'passed',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'name' => 'verify page is open',
                                    ],
                                    $actualEvent->getPayload()
                                );

                                ++$eventExpectationCount;

                                return true;
                            },
                        ),
                    ]);
                },
            ],
            'step, failed' => [
                'yamlDocument' => new YamlDocument(
                    <<< 'EOF'
                    type: step
                    payload:
                      name: 'failing step name'
                      status: failed
                      statements:
                        -
                          type: assertion
                          source: '$".selector" is "expected value"'
                          status: failed
                          summary:
                            operator: is
                            expected:
                              value: 'expected value'
                              source:
                                type: scalar
                                body:
                                  type: literal
                                  value: '"expected value"'
                            actual:
                              value: 'actual value'
                              source:
                                type: node
                                body:
                                  type: element
                                  identifier:
                                    source: '$".selector"'
                                    properties:
                                      type: css
                                      locator: .selector
                                      position: 1                        
                    EOF
                ),
                'expectedDispatchedEventCollectionCreator' => function (
                    Test $test,
                    int &$eventExpectationCount
                ): ExpectedDispatchedEventCollection {
                    return new ExpectedDispatchedEventCollection([
                        new ExpectedDispatchedEvent(
                            function (StepEvent $actualEvent) use ($test, &$eventExpectationCount) {
                                self::assertSame($test, $actualEvent->getTest());

                                self::assertSame(WorkerEventScope::STEP, $actualEvent->getScope());
                                self::assertSame(WorkerEventOutcome::FAILED, $actualEvent->getOutcome());
                                self::assertSame(
                                    [
                                        'source' => '/app/source/Test/test.yml',
                                        'document' => [
                                            'type' => 'step',
                                            'payload' => [
                                                'name' => 'failing step name',
                                                'status' => 'failed',
                                                'statements' => [
                                                    [
                                                        'type' => 'assertion',
                                                        'source' => '$".selector" is "expected value"',
                                                        'status' => 'failed',
                                                        'summary' => [
                                                            'operator' => 'is',
                                                            'expected' => [
                                                                'value' => 'expected value',
                                                                'source' => [
                                                                    'type' => 'scalar',
                                                                    'body' => [
                                                                        'type' => 'literal',
                                                                        'value' => '"expected value"',
                                                                    ],
                                                                ],
                                                            ],
                                                            'actual' => [
                                                                'value' => 'actual value',
                                                                'source' => [
                                                                    'type' => 'node',
                                                                    'body' => [
                                                                        'type' => 'element',
                                                                        'identifier' => [
                                                                            'source' => '$".selector"',
                                                                            'properties' => [
                                                                                'type' => 'css',
                                                                                'locator' => '.selector',
                                                                                'position' => 1,
                                                                            ],
                                                                        ],
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'name' => 'failing step name',
                                    ],
                                    $actualEvent->getPayload()
                                );

                                ++$eventExpectationCount;

                                return true;
                            },
                        ),
                    ]);
                },
            ],
            'test-scoped exception document' => [
                'yamlDocument' => new YamlDocument(
                    <<< 'EOF'
                    type: exception
                    payload:
                      step: null
                      class: RuntimeException
                      message: 'Exception thrown in setUpBeforeClass'
                      code: 123                       
                    EOF
                ),
                'expectedDispatchedEventCollectionCreator' => function (
                    Test $test,
                    int &$eventExpectationCount
                ): ExpectedDispatchedEventCollection {
                    return new ExpectedDispatchedEventCollection([
                        new ExpectedDispatchedEvent(
                            function (TestEvent $actualEvent) use ($test, &$eventExpectationCount) {
                                self::assertSame($test, $actualEvent->getTest());

                                self::assertSame(WorkerEventScope::TEST, $actualEvent->getScope());
                                self::assertSame(WorkerEventOutcome::EXCEPTION, $actualEvent->getOutcome());
                                self::assertSame(
                                    [
                                        'source' => '/app/source/Test/test.yml',
                                        'document' => [
                                            'type' => 'exception',
                                            'payload' => [
                                                'step' => null,
                                                'class' => 'RuntimeException',
                                                'message' => 'Exception thrown in setUpBeforeClass',
                                                'code' => 123,
                                            ],
                                        ],
                                        'step_names' => [
                                            'step 1',
                                        ],
                                    ],
                                    $actualEvent->getPayload()
                                );

                                ++$eventExpectationCount;

                                return true;
                            },
                        ),
                    ]);
                },
            ],
        ];
    }
}
