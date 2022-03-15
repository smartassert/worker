<?php

declare(strict_types=1);

namespace App\Tests\Functional\ArgumentResolver;

use App\ArgumentResolver\YamlFileProviderResolver;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockArgumentMetadata;
use SmartAssert\YamlFile\Collection\ProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class YamlFileProviderResolverTest extends AbstractBaseFunctionalTest
{
    private YamlFileProviderResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $resolver = self::getContainer()->get(YamlFileProviderResolver::class);
        \assert($resolver instanceof YamlFileProviderResolver);
        $this->resolver = $resolver;
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(ArgumentMetadata $argumentMetadata, bool $expected): void
    {
        self::assertSame($expected, $this->resolver->supports(\Mockery::mock(Request::class), $argumentMetadata));
    }

    /**
     * @return array<mixed>
     */
    public function supportsDataProvider(): array
    {
        return [
            'does not support' => [
                'argumentMetadata' => (new MockArgumentMetadata())->withGetTypeCall('string')->getMock(),
                'expected' => false,
            ],
            'does support' => [
                'argumentMetadata' => (new MockArgumentMetadata())
                    ->withGetTypeCall(ProviderInterface::class)
                    ->getMock(),
                'expected' => true,
            ],
        ];
    }
}
