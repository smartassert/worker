<?php

declare(strict_types=1);

namespace App\Model\Document;

use App\Enum\ExecutionExceptionScope;

class StepException extends Exception
{
    /**
     * @param non-empty-string $stepName
     */
    public function __construct(
        public readonly string $stepName,
        array $data
    ) {
        parent::__construct(ExecutionExceptionScope::STEP, $data);
    }
}
