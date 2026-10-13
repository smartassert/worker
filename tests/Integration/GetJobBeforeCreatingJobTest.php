<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use GuzzleHttp\Exception\ClientException;

class GetJobBeforeCreatingJobTest extends AbstractIntegrationTestCase
{
    public function testInitialStatus(): void
    {
        try {
            $response = $this->makeGetJobRequest();
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
        }

        self::assertSame(400, $response->getStatusCode());
    }
}
