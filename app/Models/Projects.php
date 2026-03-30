<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Projects extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'icon',
        'type',
        'archived_at',
        'status',
        'due_at',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
