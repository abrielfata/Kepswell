<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'platform',
        'credentials',
    ];

    public function liveSessions()
    {
        return $this->hasMany(LiveSession::class);
    }
}