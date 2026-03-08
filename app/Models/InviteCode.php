<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InviteCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'used_by',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /**
     * The user who created this invite code.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who used this invite code.
     */
    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * Check if this invite code has been used.
     */
    public function isUsed(): bool
    {
        return $this->used_by !== null;
    }

    /**
     * Generate a new random invite code (8-char alphanumeric).
     */
    public static function generateCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
