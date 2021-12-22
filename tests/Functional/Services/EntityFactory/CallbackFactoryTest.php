<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityFactory;

use App\Entity\Callback\CallbackInterface;
use App\Services\EntityFactory\CallbackFactory;
use App\Tests\AbstractBaseFunctionalTest;

class CallbackFactoryTest extends AbstractBaseFunctionalTest
{
    private CallbackFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(CallbackFactory::class);
        \assert($factory instanceof CallbackFactory);
        $this->factory = $factory;
    }

    public function testCreate(): void
    {
        $type = CallbackInterface::TYPE_COMPILATION_FAILED;
        $payload = [
            'key1' => 'value1',
            'key2' => [
                'key2key1' => 'value2',
                'key2key2' => 'value3',
            ],
        ];

        $callback = $this->factory->create($type, $payload);

        self::assertNotNull($callback->getId());
        self::assertSame($type, $callback->getType());
        self::assertSame($payload, $callback->getPayload());
    }
}
