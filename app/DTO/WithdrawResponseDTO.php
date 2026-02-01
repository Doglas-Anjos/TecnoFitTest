<?php

declare(strict_types=1);

namespace App\DTO;

use App\Model\AccountWithdraw;

class WithdrawResponseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $method,
        public readonly float $amount,
        public readonly bool $scheduled,
        public readonly ?string $scheduledFor,
        public readonly bool $done,
        public readonly array $pix,
        public readonly string $createdAt,
    ) {
    }

    public static function fromModel(AccountWithdraw $withdraw): self
    {
        return new self(
            id: $withdraw->id,
            accountId: $withdraw->account_id,
            method: $withdraw->method,
            amount: (float) $withdraw->amount,
            scheduled: $withdraw->scheduled,
            scheduledFor: $withdraw->scheduled_for?->toDateTimeString(),
            done: $withdraw->done,
            pix: [
                'type' => $withdraw->pix?->type,
                'key' => $withdraw->pix?->key,
            ],
            createdAt: $withdraw->created_at->toDateTimeString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->accountId,
            'method' => $this->method,
            'amount' => $this->amount,
            'scheduled' => $this->scheduled,
            'scheduled_for' => $this->scheduledFor,
            'done' => $this->done,
            'pix' => $this->pix,
            'created_at' => $this->createdAt,
        ];
    }
}
