<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class JsonResponseAsserter
{
    /**
     * @param array<mixed> $expectedData
     */
    public function assertJsonResponse(
        int $expectedStatusCode,
        array | \stdClass $expectedData,
        Response $response
    ): void {
        TestCase::assertSame($expectedStatusCode, $response->getStatusCode());
        TestCase::assertSame('application/json', $response->headers->get('content-type'));

        $body = $response->getContent();
        TestCase::assertIsString($body);

        $encodedExpectedData = (string) json_encode($expectedData);
        TestCase::assertJsonStringEqualsJsonString($encodedExpectedData, $body);
    }
}
