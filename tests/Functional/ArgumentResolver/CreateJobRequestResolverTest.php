<?php

declare(strict_types=1);

namespace App\Tests\Functional\ArgumentResolver;

use App\ArgumentResolver\CreateJobRequestResolver;
use App\Request\CreateJobRequest;
use App\Tests\Mock\MockArgumentMetadata;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class CreateJobRequestResolverTest extends WebTestCase
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
     * @dataProvider resolveDataProvider
     */
    public function testResolve(Request $request, CreateJobRequest $expected): void
    {
        $argumentMetadata = (new MockArgumentMetadata())
            ->withGetTypeCall(CreateJobRequest::class)
            ->getMock()
        ;

        $requests = $this->resolver->resolve($request, $argumentMetadata);
        \assert(is_array($requests));
        $request = $requests[0];

        self::assertEquals($expected, $request);
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
                        CreateJobRequest::KEY_RESULTS_TOKEN => '',
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
                        CreateJobRequest::KEY_RESULTS_TOKEN => '',
                        CreateJobRequest::KEY_MAXIMUM_DURATION => 300,
                        CreateJobRequest::KEY_SOURCE => '',
                    ],
                ),
                'expected' => new CreateJobRequest(
                    'label value',
                    '',
                    300,
                    ''
                ),
            ],
            'all request parameters populated' => [
                'request' => new Request(
                    request: [
                        CreateJobRequest::KEY_LABEL => 'label value',
                        CreateJobRequest::KEY_RESULTS_TOKEN => 'results-token-value',
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
                    'results-token-value',
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
