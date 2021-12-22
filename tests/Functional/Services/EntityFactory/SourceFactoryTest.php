<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityFactory;

use App\Entity\Source;
use App\Services\EntityFactory\SourceFactory;
use App\Tests\AbstractBaseFunctionalTest;

class SourceFactoryTest extends AbstractBaseFunctionalTest
{
    private SourceFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(SourceFactory::class);
        \assert($factory instanceof SourceFactory);
        $this->factory = $factory;
    }

    public function testCreate(): void
    {
        $type = Source::TYPE_TEST;
        $path = 'Test/test.yml';
        $source = $this->factory->create($type, $path);

        self::assertNotNull($source->getId());
        self::assertSame($type, $source->getType());
        self::assertSame($path, $source->getPath());
    }
}
