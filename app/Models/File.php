<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

class File extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Searchable;

    #[SearchUsingFullText(['contents'])]
    public function toSearchableArray()
    {
        return [
            'name' => $this->name,
            'contents' => $this->contents,
            'metadata' => $this->metadata,
            // Include other fields you want to be searchable
        ];
    }

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function shares()
    {
        return $this->morphMany(SharedItem::class, 'item');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($file) {
            // Delete related shared items
            $file->shares()->delete();
        });
    }



}
