<?php

declare(strict_types=1);

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorResponse extends JsonResponse
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(string $errorState, array $payload = [])
    {
        $data = ['error_state' => $errorState];
        if ([] !== $payload) {
            $data['payload'] = $payload;
        }

        parent::__construct($data, 400);
    }
}
