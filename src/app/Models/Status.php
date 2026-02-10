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
        'rest_add',
        'note',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rests()
    {
        return $this->hasMany(Rest::class, 'statuses_id');
    }
}
