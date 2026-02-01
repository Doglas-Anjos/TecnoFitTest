<?php

declare(strict_types=1);

namespace App\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Relations\HasMany;

class Account extends Model
{
    protected ?string $table = 'account';

    protected string $keyType = 'string';

    public bool $incrementing = false;

    protected array $fillable = [
        'id',
        'cpf',
        'name',
        'balance',
        'locked',
        'locked_at',
    ];

    protected array $casts = [
        'balance' => 'decimal:2',
        'locked' => 'boolean',
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function withdrawals(): HasMany
    {
        return $this->hasMany(AccountWithdraw::class, 'account_id', 'id');
    }

    public function pixKeys(): HasMany
    {
        return $this->hasMany(AccountPix::class, 'account_id', 'id');
    }

    public function isLocked(): bool
    {
        return (bool) $this->locked;
    }

    public function lock(): bool
    {
        $this->locked = true;
        $this->locked_at = Carbon::now();
        return $this->save();
    }

    public function unlock(): bool
    {
        $this->locked = false;
        $this->locked_at = null;
        return $this->save();
    }
}
