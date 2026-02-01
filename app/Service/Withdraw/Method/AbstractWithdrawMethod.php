<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Method;

use App\DTO\WithdrawRequestDTO;
use App\Model\Account;
use App\Model\AccountWithdraw;
use Psr\Log\LoggerInterface;

abstract class AbstractWithdrawMethod implements WithdrawMethodInterface
{
    public function __construct(
        protected LoggerInterface $logger
    ) {
    }

    public function validate(WithdrawRequestDTO $request): void
    {
        // Default implementation - override in subclasses if needed
    }

    public function process(AccountWithdraw $withdraw, Account $account): bool
    {
        $this->logger->info(sprintf(
            '[%s] Processing withdrawal #%s for account #%s, amount: %.2f',
            $this->getMethodName(),
            $withdraw->id,
            $account->id,
            $withdraw->amount
        ));

        // Simulate processing - in production, this would call external APIs
        // For PIX, this would integrate with the bank's PIX API
        return true;
    }
}
