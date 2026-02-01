<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/health')]
class HealthController extends AbstractController
{
    /**
     * Basic health check - just confirms the service is running
     */
    #[GetMapping(path: '')]
    public function index(): ResponseInterface
    {
        return $this->response->json([
            'status' => 'ok',
            'service' => 'hyperf-api',
            'instance' => getenv('INSTANCE_ID') ?: 'unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Deep health check - verifies database connectivity
     */
    #[GetMapping(path: '/ready')]
    public function ready(): ResponseInterface
    {
        try {
            // Check database connection
            Db::select('SELECT 1');

            return $this->response->json([
                'status' => 'ok',
                'service' => 'hyperf-api',
                'instance' => getenv('INSTANCE_ID') ?: 'unknown',
                'checks' => [
                    'database' => 'ok',
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return $this->response->json([
                'status' => 'error',
                'service' => 'hyperf-api',
                'instance' => getenv('INSTANCE_ID') ?: 'unknown',
                'checks' => [
                    'database' => 'failed: ' . $e->getMessage(),
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ])->withStatus(503);
        }
    }
}
