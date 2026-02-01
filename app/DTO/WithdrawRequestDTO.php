<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\Carbon;

class WithdrawRequestDTO
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $method,
        public readonly PixDataDTO $pix,
        public readonly float $amount,
        public readonly ?Carbon $schedule,
    ) {
    }

    public static function fromArray(string $accountId, array $data): self
    {
        $schedule = null;
        if (!empty($data['schedule'])) {
            // Parse schedule in São Paulo timezone
            $schedule = Carbon::parse($data['schedule'], 'America/Sao_Paulo');
        }

        return new self(
            accountId: $accountId,
            method: strtoupper($data['method'] ?? ''),
            pix: PixDataDTO::fromArray($data['pix'] ?? []),
            amount: (float) ($data['amount'] ?? 0),
            schedule: $schedule,
        );
    }

    public function isScheduled(): bool
    {
        return $this->schedule !== null;
    }

    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'method' => $this->method,
            'pix' => $this->pix->toArray(),
            'amount' => $this->amount,
            'schedule' => $this->schedule?->toDateTimeString(),
        ];
    }
}
