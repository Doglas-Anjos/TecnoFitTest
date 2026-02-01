<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Method;

use App\DTO\WithdrawRequestDTO;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Service\Pix\PixKeyValidator;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class PixWithdrawMethod extends AbstractWithdrawMethod
{
    public const METHOD_NAME = 'PIX';

    public function __construct(
        protected LoggerInterface $logger,
        protected PixKeyValidator $pixKeyValidator
    ) {
        parent::__construct($logger);
    }

    public function getMethodName(): string
    {
        return self::METHOD_NAME;
    }

    public function validate(WithdrawRequestDTO $request): void
    {
        $this->pixKeyValidator->validate($request->pix->type, $request->pix->key);
    }

    public function createMethodDetails(AccountWithdraw $withdraw, WithdrawRequestDTO $request): void
    {
        AccountWithdrawPix::create([
            'id' => Uuid::uuid7()->toString(),
            'account_withdraw_id' => $withdraw->id,
            'type' => $request->pix->type,
            'key' => $request->pix->key,
        ]);

        $this->logger->info(sprintf(
            '[PIX] Created PIX details for withdrawal #%s: type=%s, key=%s',
            $withdraw->id,
            $request->pix->type,
            $request->pix->key
        ));
    }

    public function process(AccountWithdraw $withdraw, Account $account): bool
    {
        parent::process($withdraw, $account);

        // Here we would integrate with the bank's PIX API
        // For now, we simulate a successful transfer

        $pix = $withdraw->pix;

        $this->logger->info(sprintf(
            '[PIX] Transfer executed: R$ %.2f to %s (%s)',
            $withdraw->amount,
            $pix->key,
            $pix->type
        ));

        return true;
    }

    public function getNotificationRecipient(AccountWithdraw $withdraw): ?string
    {
        $pix = $withdraw->pix;

        if ($pix && $pix->type === 'email') {
            return $pix->key;
        }

        return null;
    }
}
