<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DuplicateMemberException extends Exception
{
    public function __construct(
        public readonly string $userId,
        public readonly string $projectId,
    ) {
        parent::__construct(
            'Bu kullanıcı zaten projenin üyesi.',
            422,
        );
    }
}
