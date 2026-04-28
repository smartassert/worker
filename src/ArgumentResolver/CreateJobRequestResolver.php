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

        $label = $request->request->get(CreateJobRequest::KEY_LABEL);
        $label = is_string($label) ? trim($label) : '';

        $eventAddUrl = $request->request->get(CreateJobRequest::KEY_EVENT_ADD_URL);
        $eventAddUrl = is_string($eventAddUrl) ? trim($eventAddUrl) : '';

        $maximumDurationInSeconds = null;
        if ($request->request->has(CreateJobRequest::KEY_MAXIMUM_DURATION)) {
            $maximumDurationInRequest = $request->request->get(CreateJobRequest::KEY_MAXIMUM_DURATION);
            if (is_int($maximumDurationInRequest) || ctype_digit($maximumDurationInRequest)) {
                $maximumDurationInSeconds = (int) $maximumDurationInRequest;
            }
        }

        $sourceContent = $request->request->get(CreateJobRequest::KEY_SOURCE);
        $sourceContent = is_string($sourceContent) ? $sourceContent : '';

        return [new CreateJobRequest($label, $eventAddUrl, $maximumDurationInSeconds, $sourceContent)];
    }
}
