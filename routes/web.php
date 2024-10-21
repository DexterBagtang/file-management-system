<?php

use App\Livewire\FolderUpload;
use App\Models\File;
use App\Models\Folder;
use App\Models\SharedItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Http\Request;

// Users will be redirected to this route if not logged in
Volt::route('/login', 'login')->name('login');
Volt::route('/register', 'register');


// Define the logout
Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
});

// Protected routes here
Route::middleware('auth')->group(function () {
    Volt::route('/', 'index');
    Volt::route('/users', 'users.index');
    Volt::route('/users/create', 'users.create');
    Volt::route('/users/{user}/edit', 'users.edit');
    // ... more

    Volt::route('/','folders.index');
    Volt::route('/folders/{id}/show','folders.show');

    Volt::route('/trash','trash.index');
    Volt::route('file/{id}/view','file-viewer');

    Volt::route('/shared','shared.index');
    Volt::route('/shared/{id}/show','shared.show');

    Route::get('/folder/upload', FolderUpload::class);

    // routes/web.php



    Route::get('/test-share',function(){
        $folder = Folder::with('shares')->get();
        dd($folder);
       return view('test-share');
    });

    Route::post('/share-item',function(Illuminate\Http\Request $request){
        $request->validate([
            'item_type' => 'required|in:file,folder',
            'item_id' => 'required|numeric',
            'shared_with_id' => 'required|numeric|different:user_id',
            'permission' => 'required|in:read,write'
        ]);

        $sharedItem = new App\Models\SharedItem([
            'item_type' => $request->item_type,
            'item_id' => $request->item_id,
            'owner_id' => Auth::id(),
            'shared_with_id' => $request->shared_with_id,
            'permission' => $request->permission,
        ]);

        $sharedItem->save();

        return back()->with('success', 'Item shared successfully.');
    })->name('share.item');


});
