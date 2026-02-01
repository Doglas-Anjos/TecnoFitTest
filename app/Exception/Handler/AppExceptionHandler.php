<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Exception\Handler;

use App\Middleware\RequestTracingMiddleware;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $correlationId = RequestTracingMiddleware::getCorrelationId();

        $this->logger->error('Unhandled exception', [
            'correlation_id' => $correlationId,
            'exception' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
        ]);

        $body = json_encode([
            'success' => false,
            'message' => 'Internal Server Error',
            'correlation_id' => $correlationId,
        ]);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Correlation-ID', $correlationId ?? '')
            ->withStatus(500)
            ->withBody(new SwooleStream($body));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
