<?php

declare(strict_types=1);

namespace App\Task;

use App\Service\Notification\NotificationService;
use App\Service\Withdraw\Method\WithdrawMethodFactory;
use App\Service\Withdraw\WithdrawService;
use Hyperf\Crontab\Annotation\Crontab;
use Psr\Log\LoggerInterface;

#[Crontab(
    name: 'ProcessScheduledWithdraws',
    rule: '* * * * *',
    callback: 'execute',
    memo: 'Process scheduled withdrawals every minute'
)]
class ProcessScheduledWithdrawsTask
{
    public function __construct(
        private WithdrawService $withdrawService,
        private WithdrawMethodFactory $methodFactory,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('[CRON] Starting scheduled withdrawals processing');

        $pendingWithdrawals = $this->withdrawService->getPendingScheduledWithdrawals();

        if (empty($pendingWithdrawals)) {
            $this->logger->info('[CRON] No pending scheduled withdrawals found');
            return;
        }

        $this->logger->info(sprintf(
            '[CRON] Found %d pending scheduled withdrawals',
            count($pendingWithdrawals)
        ));

        $processed = 0;
        $failed = 0;

        foreach ($pendingWithdrawals as $withdraw) {
            try {
                $this->logger->info(sprintf(
                    '[CRON] Processing withdrawal #%s for account #%s, amount: %.2f',
                    $withdraw->id,
                    $withdraw->account_id,
                    $withdraw->amount
                ));

                $success = $this->withdrawService->processScheduledWithdraw($withdraw);

                if ($success) {
                    $processed++;
                    $this->logger->info(sprintf(
                        '[CRON] Withdrawal #%s processed successfully',
                        $withdraw->id
                    ));
                } else {
                    $failed++;
                    $this->logger->warning(sprintf(
                        '[CRON] Withdrawal #%s failed to process',
                        $withdraw->id
                    ));
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->error(sprintf(
                    '[CRON] Error processing withdrawal #%s: %s',
                    $withdraw->id,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf(
            '[CRON] Finished processing. Success: %d, Failed: %d',
            $processed,
            $failed
        ));
    }
}
