<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class FolderUpload extends Component
{
    use WithFileUploads;

    public $directory = [];

    public function folderUpload()
    {
        foreach ($this->uploadedFiles as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace(storage_path('app/livewire-tmp/'), '', $filePath);

            // Handle nested folder creation
            $folderPath = dirname($relativePath);

            if (!Storage::exists($folderPath)) {
                Storage::makeDirectory($folderPath);
            }

            // Save the file
            Storage::putFileAs($folderPath, $file, $file->getClientOriginalName());
        }

        // Emit an event to notify the front-end of completion
        $this->dispatch('uploadComplete');
    }

    public function handleUploadComplete()
    {
        // Logic to handle after the upload completes, e.g., refresh the file list, show a success message, etc.
    }

    public function render()
    {
        return view('livewire.folder-upload');
    }


}

