<?php

declare(strict_types=1);

namespace App\Exception;

class InsufficientBalanceException extends BusinessException
{
    public function __construct(
        float $requested,
        float $available,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Saldo insuficiente. Valor solicitado: R$ %.2f, Saldo disponível: R$ %.2f',
            $requested,
            $available
        );

        parent::__construct($message, 422, $previous);
    }
}
