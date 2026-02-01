<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Middleware\RequestTracingMiddleware;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ValidationExceptionHandler extends ExceptionHandler
{
    public function __construct(
        protected LoggerInterface $logger
    ) {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->stopPropagation();

        $correlationId = RequestTracingMiddleware::getCorrelationId();

        /** @var ValidationException $throwable */
        $errors = $throwable->validator->errors()->toArray();

        $this->logger->info('Validation error', [
            'correlation_id' => $correlationId,
            'errors' => $errors,
        ]);

        $data = [
            'success' => false,
            'message' => 'Erro de validação',
            'errors' => $errors,
        ];

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Correlation-ID', $correlationId ?? '')
            ->withStatus(422)
            ->withBody(new SwooleStream(json_encode($data, JSON_UNESCAPED_UNICODE)));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ValidationException;
    }
}
