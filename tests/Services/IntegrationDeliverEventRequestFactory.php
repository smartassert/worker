<?php

declare(strict_types=1);

namespace App\Tests\Services;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class IntegrationDeliverEventRequestFactory
{
    /**
     * @param array<mixed> $payload
     */
    public function create(string $eventDeliveryUrl, array $payload): RequestInterface
    {
        return new Request(
            'POST',
            $eventDeliveryUrl,
            [
                'content-type' => 'application/json',
            ],
            (string) json_encode($payload)
        );
    }
}
