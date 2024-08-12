<?php
namespace App\Support;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Spotlight
{
    public function search(Request $request)
    {
        // Check if the user is authenticated
        if (!auth()->check()) {
            return [];
        }


        $search = $request->get('search', '');
        $withFolders = filter_var($request->get('withFolders', 'true'), FILTER_VALIDATE_BOOLEAN);
        $withFiles = filter_var($request->get('withFiles', 'true'), FILTER_VALIDATE_BOOLEAN);


        // Initialize results collection
        $results = collect()->merge($this->actions($search));

        // Add results based on the filters
        if ($withFolders) {
            $results = $results->merge($this->folders($search));
        }

        if ($withFiles) {
            $results = $results->merge($this->files($search));
        }

        return $results;
    }

    // Search folders
    public function folders(string $search = '')
    {

        return Folder::search($search)
//            ->where('name', 'like', "%$search%")
            ->where('user_id', auth()->id()) // Ensure the search is limited to the authenticated user's folders
            ->take(5)
            ->get()
            ->map(function (Folder $folder) {
                return [
                    'name' => $folder->name,
                    'description' => 'Folder',
                    'link' => "/folders/{$folder->id}/show", // Adjust based on your route
                    'icon' => Blade::render('<x-icon name="o-folder" />') // Use a pre-rendered blade icon if needed
                ];
            });
    }

    // Search files
    public function files(string $search = '')
    {
//        return File::query()
//            ->where('name', 'like', "%$search%")
//            ->where('user_id', auth()->id()) // Ensure the search is limited to the authenticated user's files
//            ->take(5)
//            ->get()
//            ->map(function (File $file) {
//                return [
//                    'name' => $file->name,
//                    'description' => 'File',
//                    'link' => "/file/$file->id/view", // Adjust based on your route
//                    'icon' => Blade::render('<x-icon name="o-document-text" />') // Use a pre-rendered blade icon if needed
//                ];
//            });

        // Perform a Scout search first
        $filesQuery = File::search($search)
            ->query(function (Builder $query) {
                $query->select('files.*', 'users.name as owner', DB::raw("'file' as type"))
                    ->join('users', 'files.user_id', '=', 'users.id');
            })->get();
        return $filesQuery
            ->map(function (File $file) {
                return [
                    'name' => $file->name,
                    'description' => 'File',
                    'link' => "/file/$file->id/view", // Adjust based on your route
                    'icon' => Blade::render('<x-icon name="o-document-text" />') // Use a pre-rendered blade icon if needed
                ];
            });
    }

    // Static search, but it could come from a database
    public function actions(string $search = '')
    {
        $icon = Blade::render("<x-icon name='o-bolt' class='w-11 h-11 p-2 bg-yellow-50 rounded-full' />");

        return collect([
            [
                'name' => 'Create user',
                'description' => 'Create a new user',
                'icon' => $icon,
                'link' => '/users/create'
            ],

        ])->filter(fn(array $item) => str($item['name'] . $item['description'])->contains($search, true));
    }
}

