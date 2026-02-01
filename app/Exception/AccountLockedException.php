<?php

declare(strict_types=1);

namespace App\Exception;

class AccountLockedException extends BusinessException
{
    public function __construct(string $accountId)
    {
        parent::__construct(
            sprintf('Conta %s está temporariamente bloqueada para operações. Tente novamente em alguns segundos.', $accountId),
            423
        );
    }
}
