<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Exception\BusinessException;
use App\Middleware\RequestTracingMiddleware;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class BusinessExceptionHandler extends ExceptionHandler
{
    public function __construct(
        protected LoggerInterface $logger
    ) {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->stopPropagation();

        $correlationId = RequestTracingMiddleware::getCorrelationId();

        $this->logger->warning('Business exception', [
            'correlation_id' => $correlationId,
            'exception' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
        ]);

        $data = [
            'success' => false,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
        ];

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Correlation-ID', $correlationId ?? '')
            ->withStatus($throwable->getCode())
            ->withBody(new SwooleStream(json_encode($data, JSON_UNESCAPED_UNICODE)));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}
