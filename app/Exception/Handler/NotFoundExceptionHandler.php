<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Middleware\RequestTracingMiddleware;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class NotFoundExceptionHandler extends ExceptionHandler
{
    public function __construct(
        protected LoggerInterface $logger
    ) {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->stopPropagation();

        $correlationId = RequestTracingMiddleware::getCorrelationId();

        $this->logger->debug('Endpoint not found', [
            'correlation_id' => $correlationId,
        ]);

        $data = [
            'success' => false,
            'message' => 'Endpoint não encontrado',
            'code' => 404,
        ];

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Correlation-ID', $correlationId ?? '')
            ->withStatus(404)
            ->withBody(new SwooleStream(json_encode($data, JSON_UNESCAPED_UNICODE)));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof NotFoundHttpException;
    }
}
