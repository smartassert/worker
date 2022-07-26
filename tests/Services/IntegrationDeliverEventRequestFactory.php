<?php

declare(strict_types=1);

namespace App\Tests\Services;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class IntegrationDeliverEventRequestFactory
{
    /**
     * @param non-empty-string $reference
     * @param array<mixed>     $payload
     */
    public function create(
        string $jobLabel,
        string $eventDeliveryUrl,
        int $sequenceNumber,
        string $type,
        string $label,
        string $reference,
        array $payload
    ): RequestInterface {
        return new Request(
            'POST',
            $eventDeliveryUrl,
            [
                'content-type' => 'application/json',
            ],
            (string) json_encode([
                'job' => $jobLabel,
                'sequence_number' => $sequenceNumber,
                'type' => $type,
                'label' => $label,
                'reference' => $reference,
                'payload' => $payload,
            ])
        );
    }
}
