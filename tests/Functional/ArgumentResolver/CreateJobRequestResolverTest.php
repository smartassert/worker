<?php

declare(strict_types=1);

namespace App\Tests\Functional\ArgumentResolver;

use App\ArgumentResolver\CreateJobRequestResolver;
use App\Request\CreateJobRequest;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockArgumentMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CreateJobRequestResolverTest extends AbstractBaseFunctionalTest
{
    private CreateJobRequestResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $resolver = self::getContainer()->get(CreateJobRequestResolver::class);
        \assert($resolver instanceof CreateJobRequestResolver);
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
                    ->withGetTypeCall(CreateJobRequest::class)
                    ->getMock(),
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider resolveDataProvider
     */
    public function testResolve(Request $request, CreateJobRequest $expected): void
    {
        $argumentMetadata = (new MockArgumentMetadata())
            ->withGetTypeCall(CreateJobRequest::class)
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
                'expected' => new CreateJobRequest(
                    '',
                    '',
                    null,
                    ''
                ),
            ],
            'all request parameters empty' => [
                'request' => new Request(
                    request: [
                        CreateJobRequest::KEY_LABEL => '',
                        CreateJobRequest::KEY_EVENT_DELIVERY_URL => '',
                        CreateJobRequest::KEY_MAXIMUM_DURATION => '',
                        CreateJobRequest::KEY_SOURCE => '',
                    ],
                ),
                'expected' => new CreateJobRequest(
                    '',
                    '',
                    null,
                    ''
                ),
            ],
            'label, event_delivery_url, maximum_duration_in_seconds populated' => [
                'request' => new Request(
                    request: [
                        CreateJobRequest::KEY_LABEL => 'label value',
                        CreateJobRequest::KEY_EVENT_DELIVERY_URL => 'https://example.com/events',
                        CreateJobRequest::KEY_MAXIMUM_DURATION => 300,
                        CreateJobRequest::KEY_SOURCE => '',
                    ],
                ),
                'expected' => new CreateJobRequest(
                    'label value',
                    'https://example.com/events',
                    300,
                    ''
                ),
            ],
            'all request parameters populated' => [
                'request' => new Request(
                    request: [
                        CreateJobRequest::KEY_LABEL => 'label value',
                        CreateJobRequest::KEY_EVENT_DELIVERY_URL => 'https://example.com/events',
                        CreateJobRequest::KEY_MAXIMUM_DURATION => 300,
                        CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                        ---
                        ...
                        EOT
                        ,
                    ],
                ),
                'expected' => new CreateJobRequest(
                    'label value',
                    'https://example.com/events',
                    300,
                    <<< 'EOT'
                        ---
                        ...
                        EOT
                    ,
                ),
            ],
        ];
    }
}
