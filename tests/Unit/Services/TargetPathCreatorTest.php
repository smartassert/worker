<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\TargetPathCreator;
use PHPUnit\Framework\TestCase;

class TargetPathCreatorTest extends TestCase
{
    private const COMPILER_TARGET_DIRECTORY = '/app/target';

    private TargetPathCreator $targetPathCreator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->targetPathCreator = new TargetPathCreator(self::COMPILER_TARGET_DIRECTORY);
    }

    /**
     * @dataProvider createAbsolutePathDataProvider
     *
     * @param non-empty-string $relativePath
     * @param non-empty-string $expected
     */
    public function testCreateAbsolutePath(string $relativePath, string $expected): void
    {
        self::assertSame($expected, $this->targetPathCreator->createAbsolutePath($relativePath));
    }

    /**
     * @return array<string, array{relativePath: non-empty-string, expected: non-empty-string}>
     */
    public function createAbsolutePathDataProvider(): array
    {
        return [
            'relative path has path separator prefix' => [
                'relativePath' => '/Test/test.yml',
                'expected' => self::COMPILER_TARGET_DIRECTORY . '/Test/test.yml',
            ],
            'relative path does not have path separator prefix' => [
                'relativePath' => 'Test/test.yml',
                'expected' => self::COMPILER_TARGET_DIRECTORY . '/Test/test.yml',
            ],
            'relative path has path separator prefix and coincidental absolute path' => [
                'relativePath' => '/' . self::COMPILER_TARGET_DIRECTORY . '/Test/test.yml',
                'expected' => self::COMPILER_TARGET_DIRECTORY . self::COMPILER_TARGET_DIRECTORY . '/Test/test.yml',
            ],
            'relative path does not have path separator prefix and coincidental absolute path' => [
                'relativePath' => self::COMPILER_TARGET_DIRECTORY . '/Test/test.yml',
                'expected' => self::COMPILER_TARGET_DIRECTORY . self::COMPILER_TARGET_DIRECTORY . '/Test/test.yml',
            ],
        ];
    }
}
