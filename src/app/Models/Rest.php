<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rest extends Model
{
    use HasFactory;
    protected $fillable = [
        'statuses_id',
        'start',
        'end',
    ];
    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];
    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
