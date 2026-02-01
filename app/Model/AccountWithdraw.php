<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasOne;

class AccountWithdraw extends Model
{
    protected ?string $table = 'account_withdraw';

    protected string $keyType = 'string';

    public bool $incrementing = false;

    protected array $fillable = [
        'id',
        'account_id',
        'method',
        'amount',
        'scheduled',
        'scheduled_for',
        'done',
        'error',
        'error_reason',
    ];

    protected array $casts = [
        'amount' => 'decimal:2',
        'scheduled' => 'boolean',
        'scheduled_for' => 'datetime',
        'done' => 'boolean',
        'error' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    public function pix(): HasOne
    {
        return $this->hasOne(AccountWithdrawPix::class, 'account_withdraw_id', 'id');
    }

    public function isPending(): bool
    {
        return !$this->done && !$this->error;
    }

    public function isScheduled(): bool
    {
        return $this->scheduled && $this->scheduled_for !== null;
    }

    public function markAsDone(): void
    {
        $this->done = true;
        $this->save();
    }

    public function markAsError(string $reason): void
    {
        $this->error = true;
        $this->error_reason = $reason;
        $this->save();
    }

    /**
     * Mark withdrawal as processed but with failure
     * Used when scheduled withdrawal is processed but fails (e.g., insufficient balance)
     */
    public function markAsProcessedWithError(string $reason): void
    {
        $this->done = true;
        $this->error = true;
        $this->error_reason = $reason;
        $this->save();
    }
}
