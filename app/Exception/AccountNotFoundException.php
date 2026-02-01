<?php

declare(strict_types=1);

namespace App\Exception;

class AccountNotFoundException extends BusinessException
{
    public function __construct(
        string $accountId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf('Conta não encontrada: %s', $accountId);

        parent::__construct($message, 404, $previous);
    }
}
