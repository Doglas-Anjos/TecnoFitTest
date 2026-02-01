<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Method;

use App\DTO\WithdrawRequestDTO;
use App\Model\Account;
use App\Model\AccountWithdraw;

interface WithdrawMethodInterface
{
    /**
     * Get the method name/identifier
     */
    public function getMethodName(): string;

    /**
     * Validate method-specific data
     *
     * @throws \App\Exception\BusinessException
     */
    public function validate(WithdrawRequestDTO $request): void;

    /**
     * Create method-specific records (e.g., PIX details)
     */
    public function createMethodDetails(AccountWithdraw $withdraw, WithdrawRequestDTO $request): void;

    /**
     * Process the withdrawal (execute the actual transfer)
     *
     * @throws \App\Exception\BusinessException
     */
    public function process(AccountWithdraw $withdraw, Account $account): bool;

    /**
     * Get the notification recipient (email, phone, etc.)
     */
    public function getNotificationRecipient(AccountWithdraw $withdraw): ?string;
}
