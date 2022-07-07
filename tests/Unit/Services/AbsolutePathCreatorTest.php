<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\AbsolutePathCreator;
use PHPUnit\Framework\TestCase;

class AbsolutePathCreatorTest extends TestCase
{
    private const PREFIX = '/absolute-path-prefix';

    private AbsolutePathCreator $absolutePathCreator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->absolutePathCreator = new AbsolutePathCreator(self::PREFIX);
    }

    /**
     * @dataProvider createDataProvider
     *
     * @param non-empty-string $relativePath
     * @param non-empty-string $expected
     */
    public function testCreate(string $relativePath, string $expected): void
    {
        self::assertSame($expected, $this->absolutePathCreator->create($relativePath));
    }

    /**
     * @return array<string, array{relativePath: non-empty-string, expected: non-empty-string}>
     */
    public function createDataProvider(): array
    {
        return [
            'relative path has path separator prefix' => [
                'relativePath' => '/Test/test.yml',
                'expected' => self::PREFIX . '/Test/test.yml',
            ],
            'relative path does not have path separator prefix' => [
                'relativePath' => 'Test/test.yml',
                'expected' => self::PREFIX . '/Test/test.yml',
            ],
            'relative path has path separator prefix and coincidental absolute path' => [
                'relativePath' => '/' . self::PREFIX . '/Test/test.yml',
                'expected' => self::PREFIX . self::PREFIX . '/Test/test.yml',
            ],
            'relative path does not have path separator prefix and coincidental absolute path' => [
                'relativePath' => self::PREFIX . '/Test/test.yml',
                'expected' => self::PREFIX . self::PREFIX . '/Test/test.yml',
            ],
        ];
    }
}
