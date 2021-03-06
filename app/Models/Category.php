<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function genres()
    {
        return $this->hasMany(Genre::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
