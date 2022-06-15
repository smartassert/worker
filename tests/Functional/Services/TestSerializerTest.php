<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Services\TestSerializer;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestTestFactory;

class TestSerializerTest extends AbstractBaseFunctionalTest
{
    private TestSerializer $testSerializer;
    private TestTestFactory $testTestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $testSerializer = self::getContainer()->get(TestSerializer::class);
        \assert($testSerializer instanceof TestSerializer);
        $this->testSerializer = $testSerializer;

        $testTestFactory = self::getContainer()->get(TestTestFactory::class);
        \assert($testTestFactory instanceof TestTestFactory);
        $this->testTestFactory = $testTestFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider serializeDataProvider
     *
     * @param array<mixed> $expectedSerializedTest
     */
    public function testSerialize(TestSetup $setup, array $expectedSerializedTest): void
    {
        $test = $this->testTestFactory->create($setup);

        self::assertSame(
            $expectedSerializedTest,
            $this->testSerializer->serialize($test)
        );
    }

    /**
     * @return array<mixed>
     */
    public function serializeDataProvider(): array
    {
        return [
            'with compiler source path, with compiler target path' => [
                'setup' => (new TestSetup())
                    ->withSource('{{ compiler_source_directory }}/Test/test.yml')
                    ->withTarget('{{ compiler_target_directory }}/GeneratedTest.php'),
                'expectedSerializedTest' => [
                    'browser' => 'chrome',
                    'url' => 'http://example.com',
                    'source' => 'Test/test.yml',
                    'target' => 'GeneratedTest.php',
                    'step_names' => ['step 1'],
                    'state' => 'awaiting',
                    'position' => 1,
                ],
            ],
            'without compiler source path, without compiler target path' => [
                'setup' => (new TestSetup())
                    ->withSource('Test/test.yml')
                    ->withTarget('GeneratedTest.php'),
                'expectedSerializedTest' => [
                    'browser' => 'chrome',
                    'url' => 'http://example.com',
                    'source' => 'Test/test.yml',
                    'target' => 'GeneratedTest.php',
                    'step_names' => ['step 1'],
                    'state' => 'awaiting',
                    'position' => 1,
                ],
            ],
        ];
    }
}
