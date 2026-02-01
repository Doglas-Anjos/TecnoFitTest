<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\WithdrawRequestDTO;
use App\DTO\WithdrawResponseDTO;
use App\Middleware\RequestTracingMiddleware;
use App\Request\WithdrawRequest;
use App\Service\Withdraw\WithdrawService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

#[Controller(prefix: '/account')]
class AccountController extends AbstractController
{
    public function __construct(
        private WithdrawService $withdrawService,
        private LoggerInterface $logger
    ) {
    }

    #[PostMapping(path: '/{accountId}/balance/withdraw')]
    public function withdraw(string $accountId, WithdrawRequest $request): ResponseInterface
    {
        $correlationId = RequestTracingMiddleware::getCorrelationId();

        // Validation is automatically handled by WithdrawRequest
        $validated = $request->validated();

        $this->logger->info('Withdraw request validated', [
            'correlation_id' => $correlationId,
            'account_id' => $accountId,
            'method' => $validated['method'],
            'amount' => $validated['amount'],
            'scheduled' => $validated['schedule'] ?? null,
            'pix_type' => $validated['pix']['type'] ?? null,
        ]);

        // Create DTO from validated data
        $withdrawDTO = WithdrawRequestDTO::fromArray($accountId, $validated);

        // Process withdrawal
        $withdraw = $this->withdrawService->withdraw($withdrawDTO);

        // Build response
        $response = WithdrawResponseDTO::fromModel($withdraw);

        $this->logger->info('Withdraw request completed', [
            'correlation_id' => $correlationId,
            'account_id' => $accountId,
            'withdraw_id' => $withdraw->id,
            'amount' => (float) $withdraw->amount,
            'scheduled' => $withdraw->scheduled,
            'done' => $withdraw->done,
        ]);

        return $this->response->json([
            'success' => true,
            'message' => $withdraw->scheduled
                ? 'Saque agendado com sucesso'
                : 'Saque realizado com sucesso',
            'data' => $response->toArray(),
        ]);
    }
}
