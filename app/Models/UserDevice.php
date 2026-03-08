<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDevice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_id',
        'device_type',
        'device_name',
        'device_nickname',
        'user_agent',
        'is_hidden',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the display name for this device.
     * Returns nickname if set, otherwise the auto-detected device_name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->device_nickname ?? $this->device_name;
    }

    /**
     * Detect device type from user agent string.
     */
    public static function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'other';
        }

        $userAgent = strtolower($userAgent);

        // Check for car first (Android Auto, CarPlay)
        if (str_contains($userAgent, 'carplay') || str_contains($userAgent, 'android auto')) {
            return 'car';
        }

        // Check for mobile devices
        if (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) {
            // Tablets often have 'mobile' but also have specific identifiers
            if (preg_match('/ipad|tablet|playbook/i', $userAgent)) {
                return 'tablet';
            }
            // Android tablets often don't have 'mobile' in UA
            if (preg_match('/android/i', $userAgent) && ! preg_match('/mobile/i', $userAgent)) {
                return 'tablet';
            }

            return 'mobile';
        }

        // Check for tablets
        if (preg_match('/ipad|tablet|playbook/i', $userAgent)) {
            return 'tablet';
        }

        // Default to desktop
        return 'desktop';
    }

    /**
     * Detect a human-readable device name from user agent string.
     */
    public static function detectDeviceName(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown Device';
        }

        // Try to extract OS and browser info
        $os = self::detectOS($userAgent);
        $browser = self::detectBrowser($userAgent);

        if ($os && $browser) {
            return "$browser on $os";
        }

        if ($os) {
            return $os;
        }

        if ($browser) {
            return $browser;
        }

        return 'Unknown Device';
    }

    protected static function detectOS(string $userAgent): ?string
    {
        $patterns = [
            '/iPhone/i' => 'iPhone',
            '/iPad/i' => 'iPad',
            '/Mac OS X ([0-9_]+)/i' => function ($matches) {
                $version = str_replace('_', '.', $matches[1]);

                return "macOS $version";
            },
            '/Windows NT 10/i' => 'Windows',
            '/Windows NT 6\.3/i' => 'Windows 8.1',
            '/Windows NT 6\.2/i' => 'Windows 8',
            '/Windows/i' => 'Windows',
            '/Android ([0-9.]+)/i' => function ($matches) {
                return "Android {$matches[1]}";
            },
            '/Linux/i' => 'Linux',
            '/CrOS/i' => 'Chrome OS',
        ];

        foreach ($patterns as $pattern => $result) {
            if (preg_match($pattern, $userAgent, $matches)) {
                if (is_callable($result)) {
                    return $result($matches);
                }

                return $result;
            }
        }

        return null;
    }

    protected static function detectBrowser(string $userAgent): ?string
    {
        $patterns = [
            '/Edg\/([0-9.]+)/i' => 'Edge',
            '/Chrome\/([0-9.]+)/i' => 'Chrome',
            '/Firefox\/([0-9.]+)/i' => 'Firefox',
            '/Safari\/([0-9.]+)/i' => 'Safari',
            '/Opera\/([0-9.]+)/i' => 'Opera',
        ];

        foreach ($patterns as $pattern => $result) {
            if (preg_match($pattern, $userAgent)) {
                return $result;
            }
        }

        return null;
    }
}
