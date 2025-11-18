<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset_id',
        'scheduled_at',
        'google_calendar_event_id',
        'status',
        'gmv',
        'screenshot_path',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'gmv' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}