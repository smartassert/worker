<?php

declare(strict_types=1);

namespace App\Tests\Integration\Services;

use App\Services\Compiler;
use webignition\BasilCompilerModels\ErrorOutput;
use webignition\BasilCompilerModels\SuiteManifest;

class CompilerTest extends AbstractTestCreationTest
{
    private Compiler $compiler;
    private string $compilerSourceDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $compiler = self::$container->get(Compiler::class);
        \assert($compiler instanceof Compiler);
        $this->compiler = $compiler;

        $compilerSourceDirectory = self::$container->getParameter('compiler_source_directory');
        if (is_string($compilerSourceDirectory)) {
            $this->compilerSourceDirectory = $compilerSourceDirectory;
        }
    }

    /**
     * @dataProvider compileSuccessDataProvider
     *
     * @param string[]     $sources
     * @param array<mixed> $expectedSuiteManifestData
     */
    public function testCompileSuccess(array $sources, string $test, array $expectedSuiteManifestData): void
    {
        foreach ($sources as $source) {
            $this->localSourceStoreHandler->copyFixture($source);
        }

        /** @var SuiteManifest $suiteManifest */
        $suiteManifest = $this->compiler->compile($test);

        self::assertInstanceOf(SuiteManifest::class, $suiteManifest);

        $expectedSuiteManifestData = $this->replaceCompilerDirectories($expectedSuiteManifestData);
        $expectedSuiteManifest = SuiteManifest::fromArray($expectedSuiteManifestData);

        self::assertEquals($expectedSuiteManifest, $suiteManifest);
    }

    /**
     * @return array[]
     */
    public function compileSuccessDataProvider(): array
    {
        return [
            'Test/chrome-open-index.yml: single-browser test' => [
                'sources' => [
                    'Page/index.yml',
                    'Test/chrome-open-index.yml',
                ],
                'test' => 'Test/chrome-open-index.yml',
                'expectedSuiteManifestData' => [
                    'config' => [
                        'source' => '{{ source_directory }}/Test/chrome-open-index.yml',
                        'target' => '{{ target_directory }}',
                        'base-class' => 'webignition\BaseBasilTestCase\AbstractBaseTest',
                    ],
                    'manifests' => [
                        [
                            'config' => [
                                'browser' => 'chrome',
                                'url' => 'http://html-fixtures/index.html'
                            ],
                            'source' => '{{ source_directory }}/Test/chrome-open-index.yml',
                            'target' => '{{ target_directory }}/Generated2380721d052389cf928f39ac198a41baTest.php',
                            'step_count' => 1,
                        ],
                    ],
                ],
            ],
            'Test/chrome-firefox-open-index.yml: multiple-browser test' => [
                'sources' => [
                    'Test/chrome-firefox-open-index.yml',
                ],
                'test' => 'Test/chrome-firefox-open-index.yml',
                'expectedSuiteManifestData' => [
                    'config' => [
                        'source' => '{{ source_directory }}/Test/chrome-firefox-open-index.yml',
                        'target' => '{{ target_directory }}',
                        'base-class' => 'webignition\BaseBasilTestCase\AbstractBaseTest',
                    ],
                    'manifests' => [
                        [
                            'config' => [
                                'browser' => 'chrome',
                                'url' => 'http://html-fixtures/index.html'
                            ],
                            'source' => '{{ source_directory }}/Test/chrome-firefox-open-index.yml',
                            'target' => '{{ target_directory }}/Generated45ead8003cb8ba3fa966dc1ad5a91372Test.php',
                            'step_count' => 1,
                        ],
                        [
                            'config' => [
                                'browser' => 'firefox',
                                'url' => 'http://html-fixtures/index.html'
                            ],
                            'source' => '{{ source_directory }}/Test/chrome-firefox-open-index.yml',
                            'target' => '{{ target_directory }}/Generated88b4291e887760b0fe2eec8891356665Test.php',
                            'step_count' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider compileFailureDataProvider
     *
     * @param string[]     $sources
     * @param array<mixed> $expectedErrorOutputData
     */
    public function testCompileFailure(array $sources, string $test, array $expectedErrorOutputData): void
    {
        foreach ($sources as $source) {
            $this->localSourceStoreHandler->copyFixture($source);
        }

        /** @var ErrorOutput $errorOutput */
        $errorOutput = $this->compiler->compile($test);

        self::assertInstanceOf(ErrorOutput::class, $errorOutput);

        $expectedErrorOutputData = $this->replaceCompilerDirectories($expectedErrorOutputData);
        $expectedErrorOutput = ErrorOutput::fromArray($expectedErrorOutputData);

        self::assertEquals($expectedErrorOutput, $errorOutput);
    }

    /**
     * @return array[]
     */
    public function compileFailureDataProvider(): array
    {
        return [
            'unparseable assertion' => [
                'sources' => [
                    'InvalidTest/invalid-unparseable-assertion.yml',
                ],
                'test' => 'InvalidTest/invalid-unparseable-assertion.yml',
                'expectedErrorOutputData' => [
                    'config' => [
                        'source' => '{{ source_directory }}/InvalidTest/invalid-unparseable-assertion.yml',
                        'target' => '{{ target_directory }}',
                        'base-class' => 'webignition\BaseBasilTestCase\AbstractBaseTest',
                    ],
                    'error' => [
                        'code' => 206,
                        'message' => 'Unparseable test',
                        'context' => [
                            'type' => 'test',
                            'test_path' => '{{ source_directory }}/InvalidTest/invalid-unparseable-assertion.yml',
                            'step_name' => 'verify page is open',
                            'reason' => 'empty-value',
                            'statement_type' => 'assertion',
                            'statement' => '$page.url is',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $compilerOutputData
     *
     * @return array<mixed>
     */
    private function replaceCompilerDirectories(array $compilerOutputData): array
    {
        $encodedData = (string) json_encode($compilerOutputData);

        $encodedData = str_replace(
            [
                '{{ source_directory }}',
                '{{ target_directory }}',
            ],
            [
                $this->compilerSourceDirectory,
                $this->compilerTargetDirectory,
            ],
            $encodedData
        );

        return json_decode($encodedData, true);
    }
}
