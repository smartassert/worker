<?php

declare(strict_types=1);

namespace App\Tests\Unit\Request;

use App\Request\JobCreateRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class JobCreateRequestTest extends TestCase
{
    public function testCreate(): void
    {
        $label = md5('label source');
        $callbackUrl = 'http://example.com/callback';

        $request = new Request([], [
            JobCreateRequest::KEY_LABEL => $label,
            JobCreateRequest::KEY_CALLBACK_URL => $callbackUrl,
        ]);

        $jobCreateRequest = JobCreateRequest::create($request);

        self::assertSame($label, $jobCreateRequest->getLabel());
        self::assertSame($callbackUrl, $jobCreateRequest->getCallbackUrl());
    }
}
