<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Request\CreateJobRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CreateJobRequestResolver implements ValueResolverInterface
{
    /**
     * @return CreateJobRequest[]
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (CreateJobRequest::class !== $argument->getType()) {
            return [];
        }

        $label = $this->getNonEmptyStringFromRequest($request, CreateJobRequest::KEY_LABEL);
        $eventNotifyUrl = $this->getNonEmptyStringFromRequest($request, CreateJobRequest::KEY_EVENT_NOTIFY_URL);
        $eventNotifyToken = $this->getNonEmptyStringFromRequest($request, CreateJobRequest::KEY_EVENT_NOTIFY_TOKEN);

        $maximumDurationInSeconds = null;
        if ($request->request->has(CreateJobRequest::KEY_MAXIMUM_DURATION)) {
            $maximumDurationInRequest = $request->request->get(CreateJobRequest::KEY_MAXIMUM_DURATION);
            if (is_int($maximumDurationInRequest) || ctype_digit($maximumDurationInRequest)) {
                $maximumDurationInSeconds = (int) $maximumDurationInRequest;
            }
        }
        $maximumDurationInSeconds = is_int($maximumDurationInSeconds) && $maximumDurationInSeconds > 0
            ? $maximumDurationInSeconds
            : null;

        $sourceContent = $this->getNonEmptyStringFromRequest($request, CreateJobRequest::KEY_SOURCE);
        $stateNotifyUrl = $this->getNonEmptyStringFromRequest($request, CreateJobRequest::KEY_STATE_NOTIFY_URL);
        $stateNotifyToken = $this->getNonEmptyStringFromRequest($request, CreateJobRequest::KEY_STATE_NOTIFY_TOKEN);

        return [new CreateJobRequest(
            $label,
            $eventNotifyUrl,
            $eventNotifyToken,
            $maximumDurationInSeconds,
            $sourceContent,
            $stateNotifyUrl,
            $stateNotifyToken,
        )];
    }

    /**
     * @return ?non-empty-string
     */
    private function getNonEmptyStringFromRequest(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);
        $value = is_string($value) ? trim($value) : '';

        return '' === $value ? null : $value;
    }
}
