<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Provider extends Model
{
    use HasFactory, HasUuids; 

    protected $fillable = ['name', 'version'];

    public function faceModels() {
        return $this->hasMany(FaceModel::class);
    }
}