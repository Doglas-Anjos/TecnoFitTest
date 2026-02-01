<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Middleware for request tracing and logging.
 * Generates a correlation ID for each request and logs request/response details.
 */
class RequestTracingMiddleware implements MiddlewareInterface
{
    public const CORRELATION_ID_HEADER = 'X-Correlation-ID';
    public const CONTEXT_CORRELATION_ID = 'correlation_id';
    public const CONTEXT_REQUEST_START = 'request_start_time';

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Generate or use existing correlation ID
        $correlationId = $request->getHeaderLine(self::CORRELATION_ID_HEADER) ?: Uuid::uuid4()->toString();

        // Store in context for use throughout the request
        Context::set(self::CONTEXT_CORRELATION_ID, $correlationId);
        Context::set(self::CONTEXT_REQUEST_START, microtime(true));

        // Log incoming request
        $this->logRequest($request, $correlationId);

        try {
            // Process the request
            $response = $handler->handle($request);

            // Log response
            $this->logResponse($request, $response, $correlationId);

            // Add tracing headers to response
            $instanceId = getenv('INSTANCE_ID') ?: 'unknown';
            return $response
                ->withHeader(self::CORRELATION_ID_HEADER, $correlationId)
                ->withHeader('X-Instance-ID', $instanceId);
        } catch (\Throwable $e) {
            // Log error
            $this->logError($request, $e, $correlationId);
            throw $e;
        }
    }

    private function logRequest(ServerRequestInterface $request, string $correlationId): void
    {
        // Use debug level to avoid overhead in production
        $this->logger->debug('Request received', [
            'cid' => $correlationId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ]);
    }

    private function logResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $correlationId
    ): void {
        $startTime = Context::get(self::CONTEXT_REQUEST_START, microtime(true));
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Only log slow requests (>500ms) at info level, others at debug
        $level = $duration > 500 ? 'info' : 'debug';

        $this->logger->{$level}('Response', [
            'cid' => $correlationId,
            'status' => $response->getStatusCode(),
            'ms' => $duration,
        ]);
    }

    private function logError(
        ServerRequestInterface $request,
        \Throwable $e,
        string $correlationId
    ): void {
        $startTime = Context::get(self::CONTEXT_REQUEST_START, microtime(true));
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->logger->error('Request failed', [
            'correlation_id' => $correlationId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'duration_ms' => $duration,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        // Check for forwarded IP (proxy/load balancer)
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded) {
            $ips = explode(',', $forwarded);
            return trim($ips[0]);
        }

        $realIp = $request->getHeaderLine('X-Real-IP');
        if ($realIp) {
            return $realIp;
        }

        $serverParams = $request->getServerParams();
        return $serverParams['remote_addr'] ?? 'unknown';
    }

    /**
     * Get the current correlation ID from context
     */
    public static function getCorrelationId(): ?string
    {
        return Context::get(self::CONTEXT_CORRELATION_ID);
    }
}
