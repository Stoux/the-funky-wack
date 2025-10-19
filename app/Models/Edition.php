<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Edition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'tag_line',
        'date',
        'poster_path',
        'poster_srcset',
        'notes',
        'empty_note',
        'timetabler_mode',
    ];

    protected $appends = [
        'poster_url',
        'poster_srcset_urls',
    ];

    protected function casts()
    {
        return [
            'date' => 'date',
            'poster_srcset' => 'array',
            'timetabler_mode' => 'boolean',
        ];
    }

    protected function livesets(): HasMany
    {
        return $this->hasMany(Liveset::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Casts\Attribute<string|null>
     */
    public function posterUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->poster_path) return null;
                // pick edition-level version if present in srcset entries, else default to '0'
                $version = collect($this->poster_srcset ?? [])->first()['version'] ?? '0';
                return route('storage.versioned-images', [
                    'version' => $version,
                    'path' => $this->poster_path,
                ]);
            },
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Casts\Attribute<\Illuminate\Support\Collection<int, array{url: string, width: int}>>
     */
    public function posterSrcsetUrls(): Attribute
    {
        return Attribute::make(
            get: fn() => collect($this->poster_srcset ?? [])->map(function($poster_src) {
                $version = $poster_src['version'] ?? '0';
                return [
                    'url' => route('storage.versioned-images', [
                        'version' => $version,
                        'path' => $poster_src['path'],
                    ]),
                    'width' => $poster_src['width'],
                ];
            }),
        );
    }

}
