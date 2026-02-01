<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidScheduleException extends BusinessException
{
    public function __construct(
        string $scheduledFor,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Data de agendamento inválida. A data %s está no passado.',
            $scheduledFor
        );

        parent::__construct($message, 422, $previous);
    }
}
