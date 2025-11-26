<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Services\TestManifestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use webignition\BasilCompilerModels\Factory\TestManifestFactoryInterface;
use webignition\BasilCompilerModels\Model\TestManifest;

class TestManifestFactoryTest extends WebTestCase
{
    private TestManifestFactory $testManifestFactory;
    private string $compilerSourceDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $testManifestFactory = self::getContainer()->get(TestManifestFactoryInterface::class);
        \assert($testManifestFactory instanceof TestManifestFactory);
        $this->testManifestFactory = $testManifestFactory;

        $compilerSourceDirectory = self::getContainer()->getParameter('compiler_source_directory');
        \assert(is_string($compilerSourceDirectory));
        $this->compilerSourceDirectory = $compilerSourceDirectory;
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('createDataProvider')]
    public function testCreate(array $data, TestManifest $expected): void
    {
        $source = $data['source'] ?? null;
        \assert(is_string($source));
        $data['source'] = str_replace('{{ compiler_source_directory }}', $this->compilerSourceDirectory, $source);

        self::assertEquals($expected, $this->testManifestFactory->create($data));
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): array
    {
        return [
            'source path is relative' => [
                'data' => [
                    'config' => [
                        'browser' => 'chrome',
                        'url' => 'http://example.com',
                    ],
                    'source' => 'Test/test.yml',
                    'target' => 'target',
                    'step_names' => ['step one name'],
                ],
                'expected' => new TestManifest(
                    'chrome',
                    'http://example.com',
                    'Test/test.yml',
                    'target',
                    ['step one name']
                ),
            ],
            'source path is absolute' => [
                'data' => [
                    'config' => [
                        'browser' => 'chrome',
                        'url' => 'http://example.com',
                    ],
                    'source' => '{{ compiler_source_directory }}/Test/test.yml',
                    'target' => 'target',
                    'step_names' => ['step one name'],
                ],
                'expected' => new TestManifest(
                    'chrome',
                    'http://example.com',
                    'Test/test.yml',
                    'target',
                    ['step one name']
                ),
            ],
        ];
    }
}
