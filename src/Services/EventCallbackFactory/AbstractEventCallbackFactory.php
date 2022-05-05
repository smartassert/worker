<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Repository\CallbackRepository;
use App\Services\CallbackReferenceFactory;

abstract class AbstractEventCallbackFactory implements EventCallbackFactoryInterface
{
    public function __construct(
        private readonly CallbackRepository $callbackRepository,
        protected readonly CallbackReferenceFactory $callbackReferenceFactory,
    ) {
    }

    /**
     * @param CallbackInterface::TYPE_* $type
     * @param non-empty-string          $reference
     * @param array<mixed>              $data
     */
    protected function create(string $type, string $reference, array $data): CallbackInterface
    {
        return $this->callbackRepository->create($type, $reference, $data);
    }
}
