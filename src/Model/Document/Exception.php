<?php

declare(strict_types=1);

namespace App\Model\Document;

use App\Enum\ExecutionExceptionScope;

class Exception extends Document
{
    public function __construct(
        public readonly ExecutionExceptionScope $scope,
        array $data
    ) {
        parent::__construct($data);
    }
}
