<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\Folder;
use App\Models\SharedItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule as ValidationRule;
use Mary\Traits\Toast;

trait FolderManager
{
    use Toast;
    public array $breadcrumbs = [];

    public $tree;
    public $selectedFolder;

    public function saveFolder()
    {
        $this->validate([
            'name' => ['required',
                ValidationRule::unique('folders')->where(function ($query) {
                    return $query->where('parent_id', $this->currentFolder->id ?? null)
                        ->where('user_id',Auth::id())
                        ->whereNull('deleted_at');
                })
            ]
        ], [
            'name.unique' => 'A folder with this name already exists in this location. Please choose a different name.',
        ]);
        Folder::create([
            'name' => $this->name,
            'parent_id' => $this->currentFolder->id ?? null,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['name']);

        $this->addFolderModal = false;

        $this->success('Folder created successfully.');
    }

    public function updateFolder(Folder $folder)
    {
        $data = $this->validate([
            'name' => ['required',
                ValidationRule::unique('folders')->where(function ($query) {
                    return $query->where('parent_id', $this->currentFolder->id ?? null)
                        ->whereNull('deleted_at');
                })->ignore($folder->id)
            ]
        ], [
            'name.unique' => 'A folder with this name already exists in this location. Please choose a different name.',
        ]);

        $folder->update($data);

        $this->reset(['name', 'editFolderModal']);

        $this->success('Folder updated successfully.');

    }

    public function buildBreadcrumbs($checkShared = false)
    {
        $this->breadcrumbs = [];
        $folder = $this->currentFolder;

        while ($folder) {
            // If we need to check if the folder is shared
            if ($checkShared) {
                // Check if the folder is shared or if it's a parent folder that's shared
                $isShared = SharedItem::where('item_type', 'folder')
                    ->where('item_id', $folder->id)
                    ->where('shared_with_id', Auth::id())
                    ->exists();

                if (!$isShared && !$this->areParentsShared ($folder->id)) {
                    break; // Stop if the folder and its parents are not shared
                }
            }

            array_unshift($this->breadcrumbs, $folder);
            $folder = $folder->parent;
        }
    }

    public function areParentsShared (int $folderId): bool
    {
        $folder = Folder::find($folderId);

        while ($folder) {
            // Check if the current folder is shared
            if ($folder->shares()->where('shared_with_id', Auth::id())->exists()) {
                return true;
            }

            // Move to the parent folder
            $folder = $folder->parent_id ? Folder::find($folder->parent_id) : null;
        }

        return false;
    }



    public function getFolderTree()
    {
        // Fetch all folders
        $folders = Folder::with('children')
            ->where('user_id', Auth::id())
            ->orderBy('updated_at','desc')
            ->get();

        // Recursively build the tree
        return $this->buildTree($folders);
    }

    private function buildTree($folders, $parentId = null)
    {
        $tree = [];
        foreach ($folders as $folder) {
            if ($folder->parent_id == $parentId) {
                $children = $this->buildTree($folders, $folder->id);
                if ($children) {
                    $folder->children_tree = $children;
                }
                $tree[] = $folder;
            }
        }
        return $tree;
    }

    public function getFolders()
    {
        $this->tree = $this->getFolderTree();
    }

    public function moveItems()
    {
        if ($this->selectedFolder == null) {
            $this->error('Please select a target folder.');
            $this->getFolders();
            return;
        }

        $targetFolder = Folder::find($this->selectedFolder);

        if (!$targetFolder) {
            $this->error('Target folder not found.');
            $this->getFolders();
            return;
        }

        foreach ($this->selected as $item) {
            // Determine if item is a folder or file
            if (is_numeric($item)) {
                // Handle folder (index component case)
                if ($this->moveFolder($item, $targetFolder) === false) {
                    // Stop processing further if an error occurred in moveFolder
                    return;
                }
            } else {
                // Handle both file and folder (show component case)
                list($type, $id) = explode('-', $item);

                if ($type === 'folder') {
                    if ($this->moveFolder($id, $targetFolder) === false) {
                        // Stop processing further if an error occurred in moveFolder
                        return;
                    }
                } elseif ($type === 'file') {
                    $this->moveFile($id, $targetFolder); // Assumes moveFile properly handles its own errors
                }
            }
        }

        $this->reset(['selectedFolder', 'selected', 'moveItemModal']);
        $this->success('Items moved successfully.');
        $this->getFolders(); // Refresh folder structure
    }


    public function moveFolder($folderId, $targetFolder)
    {
        $folder = Folder::find($folderId);
        if (!$folder) return false;

        if ($folder->parent_id == $targetFolder->id) {
            $this->error($folder->name . ' already in this target folder.', timeout: 0);
            $this->getFolders();
            return false;
        }

        // Check if the target folder is a descendant of the folder being moved
        if ($this->isDescendantOf($folder->id, $targetFolder->id)) {
            $this->error('Cannot move a folder into one of its own subfolders.');
            $this->getFolders();
            return false;
        }

        // Move folder
        $folder->parent_id = $targetFolder->id;
        $folder->save();
    }

    public function moveFile($fileId, $targetFolder)
    {
        $file = File::find($fileId);
        if (!$file) return false;

        // Construct old and new paths for Storage facade
        $oldPath = $file->path;
        $newPath = $targetFolder->id . '/' . $file->name;

        // Move the file in storage using the Storage facade
        if (Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->move($oldPath, $newPath);
        }

        // Update the path to reflect the new folder structure
        $file->path = $newPath;
        $file->folder_id = $targetFolder->id;
        $file->save();
    }

//    public function isDescendantOf($folderId, $targetFolderId)
//    {
//        // Base case: if the current folder is the target folder
//        if ($folderId === $targetFolderId) {
//            return true;
//        }
//
//        // Get all children of the current folder
//        $children = DB::table('folders')
//            ->where('parent_id', $folderId)
//            ->whereNull('deleted_at')
//            ->pluck('id');
//
//        // Check if the target folder is among the children
//        if ($children->contains($targetFolderId)) {
//            return true;
//        }
//
//        // Iterate over each child folder
//        foreach ($children as $childId) {
//            // Recursively check if any child is the target folder or has the target as a descendant
//            if ($this->isDescendantOf($childId, $targetFolderId)) {
//                return true;
//            }
//        }
//
//        return false;
//    }
    public function isDescendantOf($folderId, $targetFolderId)
    {
        // Base case: if the current folder is the target folder
        if ($folderId === $targetFolderId) {
            return true;
        }

        // Start from the target folder and move up the tree
        $currentFolderId = $targetFolderId;

        while ($currentFolderId !== null) {
            // Fetch the parent ID of the current folder
            $currentFolderId = DB::table('folders')
                ->where('id', $currentFolderId)
                ->whereNull('deleted_at')
                ->value('parent_id');

            // If the folder being moved is encountered, it means the target is a descendant
            if ($currentFolderId === $folderId) {
                return true;
            }
        }

        return false;
    }


//    public function isDescendantOf($folder, $targetFolderId)
//    {
//        // Base case: check if current folder is the target folder
//        if ($folder->id === $targetFolderId) {
//            return true;
//        }
//
//        // Recursively check if any children are descendants of the target folder
//        foreach ($folder->children as $child) {
//            if ($this->isDescendantOf($child, $targetFolderId)) {
//                return true;
//            }
//        }
//
//        return false;
//    }

    public function bulkDeleteItems($selected,$folderOnly = true)
    {
        if ($folderOnly){
            $folders = Folder::query()->whereIn('id',$selected)->get();
            $folders->each->delete();
        }else{
            foreach ($selected as $item) {
                list($type, $id) = explode('-', $item);

                if ($type === 'folder') {
                    Folder::destroy($id);
                } elseif ($type === 'file') {
                    File::destroy($id);
                }

            }
        }

        $this->reset('selected');

        $this->warning("Selected items deleted", 'Items moved to trash', position: 'toast-bottom');
    }




}
