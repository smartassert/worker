<?php

declare(strict_types=1);

namespace App\Tests\Functional\ArgumentResolver;

use App\ArgumentResolver\AddSerializedSourceRequestResolver;
use App\Request\AddSerializedSourceRequest;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockArgumentMetadata;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AddSerializedSourceRequestResolverTest extends AbstractBaseFunctionalTest
{
    private AddSerializedSourceRequestResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $resolver = self::getContainer()->get(AddSerializedSourceRequestResolver::class);
        \assert($resolver instanceof AddSerializedSourceRequestResolver);
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
                    ->withGetTypeCall(AddSerializedSourceRequest::class)
                    ->getMock(),
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider resolveDataProvider
     */
    public function testResolve(Request $request, AddSerializedSourceRequest $expected): void
    {
        $argumentMetadata = (new MockArgumentMetadata())
            ->withGetTypeCall(AddSerializedSourceRequest::class)
            ->getMock()
        ;

        $generator = $this->resolver->resolve($request, $argumentMetadata);
        $actual = iterator_to_array($generator)[0];

        self::assertEquals($expected, $actual);
    }

    /**
     * @return array<mixed>
     */
    public function resolveDataProvider(): array
    {
        return [
            'no request parameters' => [
                'request' => new Request(),
                'expected' => new AddSerializedSourceRequest(
                    new ArrayCollection([])
                ),
            ],
            'empty source parameter' => [
                'request' => new Request(
                    request: [
                        AddSerializedSourceRequest::KEY_SOURCE => '',
                    ]
                ),
                'expected' => new AddSerializedSourceRequest(
                    new ArrayCollection([])
                ),
            ],
            'populated source parameter' => [
                'request' => new Request(
                    request: [
                        AddSerializedSourceRequest::KEY_SOURCE => <<< 'EOT'
                        ---
                        9427cdad1e98865ed992588e1856958d:
                            - manifest.yaml
                        81872df992b687816c56189b401fe61a:
                            - test1.yaml
                        ...
                        ---
                        - test1.yaml
                        ...
                        ---
                        - test1 content
                        ...
                        EOT
                        ,
                    ]
                ),
                'expected' => new AddSerializedSourceRequest(
                    new ArrayCollection([
                        YamlFile::create('manifest.yaml', '- test1.yaml'),
                        YamlFile::create('test1.yaml', '- test1 content'),
                    ])
                ),
            ],
        ];
    }
}
