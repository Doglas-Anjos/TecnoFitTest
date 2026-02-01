<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidPixKeyException extends BusinessException
{
    public function __construct(
        string $type,
        string $key,
        string $accountId,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Chave PIX não pertence à conta. Tipo: %s, Chave: %s, Conta: %s',
            $type,
            $key,
            $accountId
        );

        parent::__construct($message, 422, $previous);
    }
}
