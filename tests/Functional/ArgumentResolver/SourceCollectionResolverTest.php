<?php

declare(strict_types=1);

namespace App\Tests\Functional\ArgumentResolver;

use App\ArgumentResolver\SourceCollectionResolver;
use App\Model\SourceCollection;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockArgumentMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SourceCollectionResolverTest extends AbstractBaseFunctionalTest
{
    private SourceCollectionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $resolver = self::getContainer()->get(SourceCollectionResolver::class);
        \assert($resolver instanceof SourceCollectionResolver);
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
                'argumentMetadata' => (new MockArgumentMetadata())->withGetTypeCall(SourceCollection::class)->getMock(),
                'expected' => true,
            ],
        ];
    }
}
