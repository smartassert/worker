<?php

declare(strict_types=1);

namespace App\Tests\Integration;

class GetJobBeforeCreatingJobTest extends AbstractIntegrationTestCase
{
    public function testGetJobBeforeCreatingJob(): void
    {
        $response = $this->makeGetJobRequest();

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('[]', $response->getBody()->getContents());
    }
}
