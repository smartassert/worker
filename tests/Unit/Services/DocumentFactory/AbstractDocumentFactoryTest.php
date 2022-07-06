<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Document;
use App\Services\DocumentFactory\DocumentFactoryInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractDocumentFactoryTest extends TestCase
{
    protected DocumentFactoryInterface $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFactory();
    }

    /**
     * @return array<mixed>
     */
    abstract public function createDataProvider(): array;

    /**
     * @dataProvider createDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreate(array $data, Document $expected): void
    {
        self::assertEquals($expected, $this->factory->create($data));
    }

    /**
     * @dataProvider createEmptyTypeDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateEmptyType(array $data): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('Type empty');

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    public function createEmptyTypeDataProvider(): array
    {
        return [
            'no data' => [
                'data' => [],
            ],
            'type not present' => [
                'data' => ['key1' => 'value1', 'key2' => 'value2'],
            ],
            'type is empty' => [
                'data' => ['type' => ''],
            ],
            'type is whitespace-only' => [
                'data' => ['type' => '  '],
            ],
        ];
    }

    /**
     * @dataProvider createInvalidTypeDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateInvalidType(array $data, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    abstract public function createInvalidTypeDataProvider(): array;

    abstract protected function createFactory(): DocumentFactoryInterface;
}
