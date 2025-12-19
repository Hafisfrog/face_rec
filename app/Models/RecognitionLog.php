<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class RecognitionLog extends Model
{
    use HasFactory;

    protected $table = 'recognition_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'probe_s3_files',
        'score',
        'top1_user_id',
        'model_name',
        'decision',
    ];

    protected $casts = [
        'probe_s3_files' => 'array',
        'score' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
