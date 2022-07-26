<?php

declare(strict_types=1);

namespace App\Tests\Image;

use GuzzleHttp\Exception\ClientException;

class GetJobBeforeCreatingJobTest extends AbstractImageTest
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
