<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status',
        'go',
        'rest',
        'back',
        'sum',
        'apply',
        'applied_at',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
