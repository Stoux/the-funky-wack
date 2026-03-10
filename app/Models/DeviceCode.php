<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceCode extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'client_id',
        'device_name',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_token' => 'encrypted',
            'expires_at' => 'datetime',
            'authorized_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAuthorized(): bool
    {
        return $this->authorized_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isExpired() && ! $this->isAuthorized();
    }

    /**
     * Generate a unique device code in TFW-XXXX format.
     */
    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $random = '';
            for ($i = 0; $i < 6; $i++) {
                $random .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = 'TFW-'.$random;
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
