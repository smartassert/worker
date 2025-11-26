<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EmittableEvent\EmittableEventInterface;
use App\Event\EmittableEvent\HasTestInterface;
use App\Services\TestProgressHandler;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventRecorder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use webignition\YamlDocument\Document as YamlDocument;

class TestProgressHandlerTest extends WebTestCase
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
     *
     * @param array<mixed> $expectedEventPayload
     */
    public function testHandle(
        YamlDocument $yamlDocument,
        WorkerEventScope $expectedEventScope,
        WorkerEventOutcome $expectedEventOutcome,
        array $expectedEventPayload,
    ): void {
        $this->handler->handle($this->test, $yamlDocument);

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        self::assertSame(1, $eventRecorder->count());

        $event = $eventRecorder->get(0);
        self::assertTrue($event instanceof EmittableEventInterface);
        self::assertTrue($event instanceof HasTestInterface);

        self::assertSame($this->test, $event->getTest());
        self::assertSame($expectedEventScope, $event->getScope());
        self::assertSame($expectedEventOutcome, $event->getOutcome());
        self::assertSame($expectedEventPayload, $event->getPayload());
    }

    /**
     * @return array<mixed>
     */
    public static function handleDataProvider(): array
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
                'expectedEventScope' => WorkerEventScope::STEP,
                'expectedEventOutcome' => WorkerEventOutcome::PASSED,
                'expectedEventPayload' => [
                    'source' => 'Test/test.yml',
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
                'expectedEventScope' => WorkerEventScope::STEP,
                'expectedEventOutcome' => WorkerEventOutcome::FAILED,
                'expectedEventPayload' => [
                    'source' => 'Test/test.yml',
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
                'expectedEventScope' => WorkerEventScope::TEST,
                'expectedEventOutcome' => WorkerEventOutcome::EXCEPTION,
                'expectedEventPayload' => [
                    'source' => 'Test/test.yml',
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
            ],
        ];
    }
}
