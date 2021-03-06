<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Request\CreateJobRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CreateJobRequestResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return CreateJobRequest::class === $argument->getType();
    }

    /**
     * @return \Traversable<CreateJobRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Traversable
    {
        if ($this->supports($request, $argument)) {
            $label = $request->request->get(CreateJobRequest::KEY_LABEL);
            $label = is_string($label) ? trim($label) : '';

            $eventDeliveryUrl = $request->request->get(CreateJobRequest::KEY_EVENT_DELIVERY_URL);
            $eventDeliveryUrl = is_string($eventDeliveryUrl) ? trim($eventDeliveryUrl) : '';

            $maximumDurationInSeconds = null;
            if ($request->request->has(CreateJobRequest::KEY_MAXIMUM_DURATION)) {
                $maximumDurationInRequest = $request->request->get(CreateJobRequest::KEY_MAXIMUM_DURATION);
                if (is_int($maximumDurationInRequest) || ctype_digit($maximumDurationInRequest)) {
                    $maximumDurationInSeconds = (int) $maximumDurationInRequest;
                }
            }

            $sourceContent = $request->request->get(CreateJobRequest::KEY_SOURCE);
            $sourceContent = is_string($sourceContent) ? $sourceContent : '';

            yield new CreateJobRequest($label, $eventDeliveryUrl, $maximumDurationInSeconds, $sourceContent);
        }
    }
}
