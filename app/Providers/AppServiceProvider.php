<?php

namespace App\Providers;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'file' => File::class,
            'folder' => Folder::class,
        ]);
    }
}
