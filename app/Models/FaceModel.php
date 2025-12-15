<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Photo extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'score', 's3_files', 'captured_at'];
    protected $casts = [
        's3_files' => 'array', 
        'captured_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}