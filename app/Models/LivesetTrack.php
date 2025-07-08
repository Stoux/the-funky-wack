<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LivesetTrack extends Model
{

    protected $fillable = [
        'liveset_id',
        'title',
        'timestamp',
        'order',
    ];

    public function liveset()
    {
        return $this->belongsTo(Liveset::class);
    }
}
