<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Services\TestDocumentFactory;
use App\Services\TestDocumentMutator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Dumper;
use webignition\YamlDocumentGenerator\YamlGenerator;

class TestDocumentFactoryTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private TestDocumentFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TestDocumentFactory(
            new YamlGenerator(),
            new TestDocumentMutator(new Dumper(), self::COMPILER_SOURCE_DIRECTORY)
        );
    }

    public function testCreate(): void
    {
        $test = Test::create(
            TestConfiguration::create('chrome', 'http://example.com'),
            '/app/source/test.yml',
            '/app/target/GeneratedTest.php',
            2,
            1
        );

        $document = $this->factory->create($test);

        self::assertSame(
            [
                'type' => 'test',
                'payload' => [
                    'path' => 'test.yml',
                    'config' => [
                        'browser' => 'chrome',
                        'url' => 'http://example.com',
                    ],
                ],
            ],
            $document->getData()
        );
    }
}
