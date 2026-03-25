<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class MaxMembersExceededException extends Exception
{
    public function __construct(
        public readonly string $projectId,
        public readonly int $maxMembers = 5,
    ) {
        parent::__construct(
            "Bu grup en fazla {$maxMembers} üyeye sahip olabilir.",
            422,
        );
    }
}
