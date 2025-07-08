<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Edition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'tag_line',
        'date',
        'poster_path',
        'notes',
    ];

    protected function casts()
    {
        return [
            'date' => 'date',
        ];
    }

    protected function livesets(): HasMany
    {
        return $this->hasMany(Liveset::class);
    }

}
