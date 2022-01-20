<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Tests\AbstractBaseFunctionalTest;
use webignition\StringPrefixRemover\DefinedStringPrefixRemover;

class StringPrefixRemoverTest extends AbstractBaseFunctionalTest
{
    private DefinedStringPrefixRemover $compilerSourceRemover;
    private DefinedStringPrefixRemover $compilerTargetRemover;
    private string $compilerSourceDirectory;
    private string $compilerTargetDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $compilerSourceRemover = self::getContainer()->get('app.services.path-prefix-remover.compiler-source');
        \assert($compilerSourceRemover instanceof DefinedStringPrefixRemover);
        $this->compilerSourceRemover = $compilerSourceRemover;

        $compilerTargetRemover = self::getContainer()->get('app.services.path-prefix-remover.compiler-target');
        \assert($compilerTargetRemover instanceof DefinedStringPrefixRemover);
        $this->compilerTargetRemover = $compilerTargetRemover;

        $compilerSourceDirectory = self::getContainer()->getParameter('compiler_source_directory');
        $this->compilerSourceDirectory = is_string($compilerSourceDirectory) ? $compilerSourceDirectory : '';

        $compilerTargetDirectory = self::getContainer()->getParameter('compiler_target_directory');
        $this->compilerTargetDirectory = is_string($compilerTargetDirectory) ? $compilerTargetDirectory : '';
    }

    /**
     * @dataProvider compilerSourceRemoverDataProvider
     */
    public function testCompilerSourceRemover(string $path, string $expectedPath): void
    {
        $path = str_replace('{{ prefix }}', $this->compilerSourceDirectory, $path);

        self::assertSame($expectedPath, $this->compilerSourceRemover->remove($path));
    }

    /**
     * @return array<mixed>
     */
    public function compilerSourceRemoverDataProvider(): array
    {
        return [
            'path shorter than prefix' => [
                'path' => 'short/path',
                'expectedPath' => 'short/path',
            ],
            'prefix not present' => [
                'path' => '/path/that/does/not/contain/prefix/test.yml',
                'expectedPath' => '/path/that/does/not/contain/prefix/test.yml',
            ],
            'prefix present' => [
                'path' => '{{ prefix }}/Test/test.yml',
                'expectedPath' => 'Test/test.yml',
            ],
        ];
    }

    /**
     * @dataProvider compilerTargetRemoverDataProvider
     */
    public function testCompilerTargetRemover(string $path, string $expectedPath): void
    {
        $path = str_replace('{{ prefix }}', $this->compilerTargetDirectory, $path);

        self::assertSame($expectedPath, $this->compilerTargetRemover->remove($path));
    }

    /**
     * @return array<mixed>
     */
    public function compilerTargetRemoverDataProvider(): array
    {
        return [
            'path shorter than prefix' => [
                'path' => 'short/path',
                'expectedPath' => 'short/path',
            ],
            'prefix not present' => [
                'path' => '/path/that/does/not/contain/prefix/GeneratedTest.php',
                'expectedPath' => '/path/that/does/not/contain/prefix/GeneratedTest.php',
            ],
            'prefix present' => [
                'path' => '{{ prefix }}/GeneratedTest.php',
                'expectedPath' => 'GeneratedTest.php',
            ],
        ];
    }
}
