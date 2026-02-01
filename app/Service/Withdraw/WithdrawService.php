<?php

declare(strict_types=1);

namespace App\Service\Withdraw;

use App\DTO\WithdrawRequestDTO;
use App\Exception\AccountLockedException;
use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientBalanceException;
use App\Exception\InvalidScheduleException;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Service\Notification\NotificationService;
use App\Service\Withdraw\Method\WithdrawMethodFactory;
use App\Service\Withdraw\Method\WithdrawMethodInterface;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class WithdrawService
{
    public function __construct(
        private WithdrawMethodFactory $methodFactory,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Process a withdrawal request
     *
     * @throws AccountNotFoundException
     * @throws AccountLockedException
     * @throws InsufficientBalanceException
     * @throws InvalidScheduleException
     */
    public function withdraw(WithdrawRequestDTO $request): AccountWithdraw
    {
        // Use transaction with row-level locking to prevent race conditions
        return Db::transaction(function () use ($request) {
            // Get the account with row lock (SELECT FOR UPDATE)
            $account = $this->getAccountWithLock($request->accountId);

            // Check if account is locked by another process
            if ($account->isLocked()) {
                throw new AccountLockedException($request->accountId);
            }

            // Validate schedule if provided
            if ($request->isScheduled()) {
                $this->validateSchedule($request->schedule);
            } else {
                // Only validate balance for immediate withdrawals
                // Scheduled withdrawals will have balance checked at execution time by cron
                $this->validateBalance($account, $request->amount);
            }

            // Get the withdrawal method strategy
            $method = $this->methodFactory->create($request->method);
            $method->validate($request);

            // Lock the account for processing
            $this->lockAccount($account);

            try {
                // Execute withdrawal transaction
                $withdraw = $this->executeWithdrawTransaction($request, $account, $method);

                // Send email notification for immediate withdrawals
                if (!$request->isScheduled() && $withdraw->done) {
                    $this->sendNotification($withdraw, $method);
                }

                return $withdraw;
            } finally {
                // Always unlock the account
                $this->unlockAccount($account);
            }
        });
    }

    private function executeWithdrawTransaction(
        WithdrawRequestDTO $request,
        Account $account,
        WithdrawMethodInterface $method
    ): AccountWithdraw {
        // Create the withdrawal record
        $withdraw = $this->createWithdrawRecord($request);

        // Create method-specific details (e.g., PIX data)
        $method->createMethodDetails($withdraw, $request);

        // If not scheduled, process immediately
        if (!$request->isScheduled()) {
            $this->processImmediateWithdraw($withdraw, $account, $method);
        }

        // Reload with relationships (include account for email notification)
        return $withdraw->fresh(['pix', 'account']);
    }

    /**
     * Process a scheduled withdrawal (called by cron)
     */
    public function processScheduledWithdraw(AccountWithdraw $withdraw): bool
    {
        $this->logger->info(sprintf(
            'Processing scheduled withdrawal #%s',
            $withdraw->id
        ));

        $account = $withdraw->account;

        // Check if account is locked
        if ($account->isLocked()) {
            $this->logger->warning(sprintf(
                'Scheduled withdrawal #%s skipped: account is locked',
                $withdraw->id
            ));
            return false;
        }

        // Lock the account before processing
        $this->lockAccount($account);

        try {
            // Check balance at execution time
            $balance = (float) $account->balance;
            $amount = (float) $withdraw->amount;

            // If insufficient balance, mark as processed with error
            if ($amount > $balance) {
                $errorMessage = sprintf(
                    'Saldo insuficiente no momento do processamento. Valor solicitado: R$ %.2f, Saldo disponível: R$ %.2f',
                    $amount,
                    $balance
                );

                $this->logger->warning(sprintf(
                    'Scheduled withdrawal #%s processed with failure: %s',
                    $withdraw->id,
                    $errorMessage
                ));

                $withdraw->markAsProcessedWithError($errorMessage);

                return false;
            }

            // Get the method strategy
            $method = $this->methodFactory->create($withdraw->method);

            $success = Db::transaction(function () use ($withdraw, $account, $method) {
                // Deduct balance
                $this->deductBalance($account, (float) $withdraw->amount);

                // Process the transfer
                $success = $method->process($withdraw, $account);

                if ($success) {
                    $withdraw->markAsDone();
                    $this->logger->info(sprintf(
                        'Scheduled withdrawal #%s completed successfully',
                        $withdraw->id
                    ));
                }

                return $success;
            });

            // Send email notification after successful scheduled withdrawal
            if ($success) {
                $this->sendNotification($withdraw->fresh(['pix', 'account']), $method);
            }

            return $success;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Error processing scheduled withdrawal #%s: %s',
                $withdraw->id,
                $e->getMessage()
            ));

            // For other errors, mark as processed with error
            $withdraw->markAsProcessedWithError($e->getMessage());

            return false;
        } finally {
            // Always unlock the account
            $this->unlockAccount($account);
        }
    }

    /**
     * Get pending scheduled withdrawals ready for processing
     */
    public function getPendingScheduledWithdrawals(): array
    {
        return AccountWithdraw::query()
            ->where('scheduled', true)
            ->where('done', false)
            ->where('error', false)
            ->where('scheduled_for', '<=', Carbon::now('America/Sao_Paulo'))
            ->with(['account', 'pix'])
            ->get()
            ->all();
    }

    private function getAccount(string $accountId): Account
    {
        $account = Account::find($accountId);

        if (!$account) {
            throw new AccountNotFoundException($accountId);
        }

        return $account;
    }

    /**
     * Get account with database row lock to prevent race conditions
     */
    private function getAccountWithLock(string $accountId): Account
    {
        $account = Account::query()
            ->where('id', $accountId)
            ->lockForUpdate() // SELECT FOR UPDATE
            ->first();

        if (!$account) {
            throw new AccountNotFoundException($accountId);
        }

        return $account;
    }

    private function validateBalance(Account $account, float $amount): void
    {
        $balance = (float) $account->balance;

        if ($amount > $balance) {
            throw new InsufficientBalanceException($amount, $balance);
        }
    }

    private function validateSchedule(Carbon $scheduledFor): void
    {
        // Compare using São Paulo timezone
        $now = Carbon::now('America/Sao_Paulo');
        if ($scheduledFor->lessThanOrEqualTo($now)) {
            throw new InvalidScheduleException($scheduledFor->toDateTimeString());
        }
    }

    private function createWithdrawRecord(WithdrawRequestDTO $request): AccountWithdraw
    {
        $withdraw = AccountWithdraw::create([
            'id' => Uuid::uuid7()->toString(),
            'account_id' => $request->accountId,
            'method' => $request->method,
            'amount' => $request->amount,
            'scheduled' => $request->isScheduled(),
            'scheduled_for' => $request->schedule,
            'done' => false,
            'error' => false,
            'error_reason' => null,
        ]);

        $this->logger->info(sprintf(
            'Created withdrawal record #%s for account #%s, amount: %.2f, scheduled: %s',
            $withdraw->id,
            $request->accountId,
            $request->amount,
            $request->isScheduled() ? $request->schedule->toDateTimeString() : 'immediate'
        ));

        return $withdraw;
    }

    private function processImmediateWithdraw(
        AccountWithdraw $withdraw,
        Account $account,
        WithdrawMethodInterface $method
    ): void {
        try {
            // Deduct balance first
            $this->deductBalance($account, (float) $withdraw->amount);

            // Process the transfer
            $success = $method->process($withdraw, $account);

            if ($success) {
                $withdraw->markAsDone();
                $this->logger->info(sprintf(
                    'Immediate withdrawal #%s completed successfully',
                    $withdraw->id
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Error processing immediate withdrawal #%s: %s',
                $withdraw->id,
                $e->getMessage()
            ));

            $withdraw->markAsError($e->getMessage());

            throw $e;
        }
    }

    private function deductBalance(Account $account, float $amount): void
    {
        $newBalance = (float) $account->balance - $amount;

        $account->balance = $newBalance;
        $account->save();

        $this->logger->info(sprintf(
            'Deducted %.2f from account #%s, new balance: %.2f',
            $amount,
            $account->id,
            $newBalance
        ));
    }

    private function sendNotification(
        AccountWithdraw $withdraw,
        WithdrawMethodInterface $method
    ): void {
        $recipient = $method->getNotificationRecipient($withdraw);

        if ($recipient) {
            // Send email asynchronously (non-blocking)
            $this->notificationService->sendWithdrawConfirmationAsync($withdraw, $recipient);
        }
    }

    private function lockAccount(Account $account): void
    {
        $account->lock();

        $this->logger->info(sprintf(
            'Account #%s locked for balance operation',
            $account->id
        ));
    }

    private function unlockAccount(Account $account): void
    {
        $account->unlock();

        $this->logger->info(sprintf(
            'Account #%s unlocked after balance operation',
            $account->id
        ));
    }
}
