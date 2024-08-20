<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SharedItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function owner() {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sharedWith() {
        return $this->belongsTo(User::class, 'shared_with_id');
    }

    public function item() {
//        return $this->morphTo(__FUNCTION__, 'item_type', 'item_id');
        return $this->morphTo();
    }


}
