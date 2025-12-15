<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RecognitionLog extends Model
{
    use HasFactory, HasUuids; 

    protected $table = 'recognition_logs';
    protected $fillable = ['probe_s3_files', 'score', 'top1_user_id', 'model_name', 'decision'];
    protected $casts = [
        'probe_s3_files' => 'array', 
        'score' => 'float',
    ];

    public function top1User() {
        return $this->belongsTo(User::class, 'top1_user_id');
    }
}