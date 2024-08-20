<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Folder extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Searchable;

    public function toSearchableArray()
    {
        return [
            'name' => $this->name,
            // Include other fields you want to be searchable
        ];
    }


    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function shares()
    {
        return $this->morphMany(SharedItem::class, 'item');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($folder) {
            // Delete related shared items
            $folder->shares()->delete();

            // Optionally, handle nested folders
            foreach ($folder->children as $childFolder) {
                $childFolder->delete(); // This will trigger the deleting event for the child folders
            }
        });
    }

}
