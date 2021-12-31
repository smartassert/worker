<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\HttpFoundation\Request;
use webignition\EncapsulatingRequestResolverBundle\Model\EncapsulatingRequestInterface;

class JobCreateRequest implements EncapsulatingRequestInterface
{
    public const KEY_LABEL = 'label';
    public const KEY_CALLBACK_URL = 'callback-url';
    public const KEY_MAXIMUM_DURATION = 'maximum-duration-in-seconds';

    public function __construct(
        private string $label,
        private string $callbackUrl,
        private ?int $maximumDurationInSeconds
    ) {
    }

    public static function create(Request $request): JobCreateRequest
    {
        $requestData = $request->request;

        $maximumDurationInSeconds = null;
        if ($requestData->has(self::KEY_MAXIMUM_DURATION)) {
            $maximumDurationInRequest = $requestData->get(self::KEY_MAXIMUM_DURATION);
            if (is_int($maximumDurationInRequest) || ctype_digit($maximumDurationInRequest)) {
                $maximumDurationInSeconds = (int) $maximumDurationInRequest;
            }
        }

        return new JobCreateRequest(
            (string) $requestData->get(self::KEY_LABEL),
            (string) $requestData->get(self::KEY_CALLBACK_URL),
            $maximumDurationInSeconds
        );
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function getMaximumDurationInSeconds(): ?int
    {
        return $this->maximumDurationInSeconds;
    }
}
